<?php

namespace App\Services\Abdm;

use App\Models\AbdmHiuConsentRequest;
use App\Services\AbdmTokenService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ABDM M3 — HIU side of the consent lifecycle (this app REQUESTING a patient's consent).
 * Port of legacy bmhabdm/abdm/hiu_consent.php (+ the HIU actions of abdm/hiu_ajax.php).
 *
 *   1. request/init   (we call)  -> on-init     (ABDM assigns consentRequestId)
 *   2. request/status (we call)  -> on-status   (manual poll fallback)
 *   3. request/notify (ABDM calls us: patient GRANTED / DENIED ...) -> must be acknowledged
 *   4. consent/fetch  (we call, by ARTEFACT consent id) -> on-fetch (artefact stored)
 *
 * All timestamps are written in UTC (legacy used UTC_TIMESTAMP()).
 * Never log the access token or full artefacts: only ids/status/error text are logged.
 */
class HiuConsentService
{
    /** HI types a consent request may ask for (legacy ABDM_HIU_DEFAULT_HI_TYPES). */
    public const HI_TYPES = [
        'OPConsultation', 'DiagnosticReport', 'Prescription', 'DischargeSummary',
        'ImmunizationRecord', 'HealthDocumentRecord', 'WellnessRecord', 'Invoice',
    ];

    /** Purpose-of-use codes (legacy $purposeTextMap). */
    public const PURPOSES = [
        'CAREMGT' => 'Care Management',
        'BTG'     => 'Break the Glass',
        'PUBHLTH' => 'Public Health',
        'HPAYMT'  => 'Healthcare Payment',
        'DSRCH'   => 'Disease Specific Healthcare Research',
        'PATRQT'  => 'Self Requested',
    ];

    /** Minimum gap between real outbound request/status calls (legacy ABDM_HIU_STATUS_CHECK_THROTTLE_SECONDS). */
    public const STATUS_CHECK_THROTTLE_SECONDS = 60;

    private const REQUESTS  = 'abdm_hiu_consent_requests';
    private const ARTEFACTS = 'abdm_hiu_consent_artefacts';

    public function __construct(private AbdmTokenService $tokenSvc) {}

    // ═══════════════════════════════════════════════════════════════════
    // Step 1 — request/init
    // ═══════════════════════════════════════════════════════════════════

    /** True when this ABHA address's most recent request is still awaiting the patient's decision. */
    public function hasPendingRequest(string $abhaAddress): bool
    {
        $status = DB::table(self::REQUESTS)->where('abha_address', $abhaAddress)->orderByDesc('id')->value('latest_status');
        return $status === 'REQUESTED';
    }

    /**
     * POST /consent/v3/request/init. Only ever answers 202 with an empty body; the real
     * consentRequestId arrives later at on-init, so a row is stored up front (REQUESTED).
     *
     * $data: abha_address, hi_types[], purpose_code?, date_from?, date_to?, expiry_at?,
     *        hip_id?, patient_reference?, care_context_reference?
     * Dates/expiry are optional (legacy defaults: last 6 months .. now, expiry +1 year).
     *
     * @return array{success:bool, error?:string, request_id?:string, message?:string}
     */
    public function initiateRequest(array $data, ?int $userId = null): array
    {
        $abha = trim((string) ($data['abha_address'] ?? ''));
        if ($abha === '') {
            return ['success' => false, 'error' => 'Patient ABHA address is required'];
        }

        $purpose = trim((string) ($data['purpose_code'] ?? 'CAREMGT')) ?: 'CAREMGT';
        if (!isset(self::PURPOSES[$purpose])) {
            return ['success' => false, 'error' => 'Invalid purpose of request'];
        }

        // Restrict to the known v3 HI types (also drops anything a direct API call might smuggle in).
        $hiTypes = array_values(array_intersect(array_map('strval', (array) ($data['hi_types'] ?? [])), self::HI_TYPES));
        if (empty($hiTypes)) {
            return ['success' => false, 'error' => 'Select at least one Health Info type'];
        }

        try {
            [$fromIso, $toIso, $expiryIso] = $this->resolveWindow($data);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $hipId   = trim((string) ($data['hip_id'] ?? '')) ?: null;
        $patRef  = trim((string) ($data['patient_reference'] ?? '')) ?: null;
        $ccRef   = trim((string) ($data['care_context_reference'] ?? '')) ?: null;
        $hiuId   = $this->hiuId();

        $consent = [
            'purpose'   => ['text' => self::PURPOSES[$purpose], 'code' => $purpose, 'refUri' => 'https://www.abdm.gov.in'],
            'patient'   => ['id' => $abha],
            'hiu'       => ['id' => $hiuId],
            'requester' => [
                'name'       => $this->facilityName(),
                'identifier' => ['type' => 'REGNO', 'value' => $hiuId, 'system' => 'https://nha.gov.in'],
            ],
            'hiTypes'    => $hiTypes,
            'permission' => [
                'accessMode'  => 'VIEW',
                'dateRange'   => ['from' => $fromIso, 'to' => $toIso],
                'dataEraseAt' => $expiryIso,
                'frequency'   => ['unit' => 'HOUR', 'value' => 1, 'repeats' => 0],
            ],
            // Per ABDM's M3 Postman collection these are explicit nulls, not omitted.
            'hip'          => $hipId ? ['id' => $hipId] : null,
            'careContexts' => ($patRef && $ccRef) ? [['patientReference' => $patRef, 'careContextReference' => $ccRef]] : null,
        ];

        $sent = $this->send(config('abdm.consent_request_init_url'), ['consent' => $consent], false);
        if (!$sent['ok']) {
            Log::error('ABDM HIU request/init failed', ['error' => $sent['error']]);
            return ['success' => false, 'error' => $sent['error']];
        }

        $now = $this->utcNow();
        DB::table(self::REQUESTS)->insert([
            'request_id'             => $sent['request_id'],
            'abha_address'           => $abha,
            'hip_id'                 => $hipId,
            'patient_reference'      => $patRef,
            'care_context_reference' => $ccRef,
            'hi_types'               => json_encode($hiTypes),
            'purpose_code'           => $purpose,
            'requested_date_from'    => $this->parseIso($fromIso),
            'requested_date_to'      => $this->parseIso($toIso),
            'requested_expiry_at'    => $this->parseIso($expiryIso),
            'latest_status'          => 'REQUESTED',
            'acknowledged'           => 0,
            'raw_init_response'      => $sent['body'],
            'created_on'             => $now,
            'updated_on'             => $now,
            'user_id'                => $userId,
        ]);

        return ['success' => true, 'request_id' => $sent['request_id'], 'message' => 'Consent request sent. Waiting for the patient to respond.'];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Callbacks
    // ═══════════════════════════════════════════════════════════════════

    /**
     * on-init: delivers the real consentRequestId, matched by the REQUEST-ID we sent
     * (echoed back as requestId), or an error when ABDM rejected the request itself.
     */
    public function handleOnInit(array $body): void
    {
        $requestId = $body['requestId'] ?? ($body['resp']['requestId'] ?? ($body['response']['requestId'] ?? null));
        if (!$requestId) {
            Log::warning('ABDM HIU on-init: missing requestId in callback body');
            return;
        }

        $consentRequestId = $body['consentRequest']['id'] ?? null;
        $error            = $body['error'] ?? null;
        $raw              = json_encode($body);
        $now              = $this->utcNow();

        if ($consentRequestId) {
            DB::table(self::REQUESTS)->where('request_id', $requestId)->update([
                'consent_request_id' => $consentRequestId,
                'raw_last_callback'  => $raw,
                'updated_on'         => $now,
            ]);
        } elseif ($error) {
            $message = $this->extractError($body);
            Log::warning('ABDM HIU on-init: request rejected', ['request_id' => $requestId, 'error' => $message]);
            // Only a request that is still waiting can be rejected; never overwrite a decided one.
            $applied = DB::table(self::REQUESTS)->where('request_id', $requestId)->where('latest_status', 'REQUESTED')->update([
                'latest_status'     => 'UNKNOWN',
                'rejection_error'   => Str::limit($message, 500, ''),
                'raw_last_callback' => $raw,
                'updated_on'        => $now,
            ]);
            if (!$applied) {
                DB::table(self::REQUESTS)->where('request_id', $requestId)->update(['raw_last_callback' => $raw, 'updated_on' => $now]);
            }
        } else {
            Log::warning('ABDM HIU on-init: neither consentRequest.id nor error present');
        }
    }

    /** on-status: result of a manual status poll, matched by consentRequestId. */
    public function handleOnStatus(array $body): void
    {
        if (!empty($body['error'])) {
            Log::warning('ABDM HIU on-status error', ['error' => $this->extractError($body)]);
            return;
        }

        $consentRequest = $body['consentRequest'] ?? null;
        if (!is_array($consentRequest) || empty($consentRequest['id'])) {
            Log::warning('ABDM HIU on-status: missing consentRequest.id in callback body');
            return;
        }

        $statusRaw = strtoupper(trim((string) ($consentRequest['status'] ?? '')));
        $result = $this->applyDecision(
            (string) $consentRequest['id'],
            $statusRaw === '' ? null : $statusRaw,
            [],
            json_encode($body),
            true
        );

        if (!$result['matched']) {
            Log::warning('ABDM HIU on-status: no matching request', ['consent_request_id' => $consentRequest['id']]);
        }
    }

    /**
     * notify: the patient's decision. Body: {notification:{consentRequestId,status,consentArtefacts:[{id}]}}.
     * There is NO requestId in the body — the REQUEST-ID header of THIS incoming call is echoed back
     * in the acknowledgement's response.requestId ($originalRequestId).
     *
     * @return array{success:bool, acknowledged?:bool, error?:string}
     */
    public function handleNotify(array $body, ?string $originalRequestId = null): array
    {
        if (!empty($body['error'])) {
            $message = $this->extractError($body);
            Log::warning('ABDM HIU notify: request rejected', ['error' => $message]);
            return ['success' => false, 'error' => $message];
        }

        $notification = $body['notification'] ?? null;
        if (!is_array($notification)) {
            Log::warning('ABDM HIU notify: neither notification nor error present');
            return ['success' => false, 'error' => 'Missing notification'];
        }

        $consentRequestId = $notification['consentRequestId'] ?? null;
        if (!$consentRequestId) {
            Log::warning('ABDM HIU notify: missing consentRequestId in notification');
            return ['success' => false, 'error' => 'Missing consentRequestId'];
        }

        $status     = strtoupper(trim((string) ($notification['status'] ?? ''))) ?: null;
        $consentIds = $this->artefactIds($notification['consentArtefacts'] ?? []);

        $result = $this->applyDecision((string) $consentRequestId, $status, $consentIds, json_encode($body), false);
        if (!$result['matched']) {
            Log::warning('ABDM HIU notify: no matching request', ['consent_request_id' => $consentRequestId]);
        }

        // Ack the WHOLE notification in ONE call, whether or not a local row matched.
        $ack = $this->sendNotifyAck($consentIds, $originalRequestId);
        DB::table(self::REQUESTS)->where('consent_request_id', $consentRequestId)->update([
            'acknowledged'          => $ack['success'] ? 1 : 0,
            'acknowledgement_error' => $ack['success'] ? null : Str::limit((string) ($ack['error'] ?? 'unknown error'), 500, ''),
        ]);

        return ['success' => true, 'acknowledged' => $ack['success']];
    }

    /**
     * on-fetch: the full artefact for a consent fetched via fetchArtefact(). Upserts by consent_id.
     * Body: {consent:{status, consentDetail:{consentId, patient, hip, careContexts, hiTypes, permission}}}.
     *
     * @return array{stored:bool, consent_id?:string}
     */
    public function storeArtefact(array $body): array
    {
        $consent = $body['consent'] ?? null;
        if (!is_array($consent)) {
            Log::warning('ABDM HIU on-fetch: missing "consent" in callback body');
            return ['stored' => false];
        }

        $detail    = $consent['consentDetail'] ?? $consent;
        $consentId = is_array($detail) ? ($detail['consentId'] ?? null) : null;
        if (!$consentId) {
            Log::warning('ABDM HIU on-fetch: missing consentDetail.consentId in callback body');
            return ['stored' => false];
        }
        $consentId = (string) $consentId;

        $status = strtoupper(trim((string) ($consent['status'] ?? ''))) ?: 'GRANTED';
        $now    = $this->utcNow();

        $fields = [
            'status'               => Str::limit($status, 20, ''),
            'patient_abha_address' => (string) ($detail['patient']['id'] ?? ''),
            'hip_id'               => $detail['hip']['id'] ?? null,
            'hi_types'             => json_encode($detail['hiTypes'] ?? []),
            'care_contexts'        => json_encode($detail['careContexts'] ?? []),
            'date_range_from'      => $this->parseIso($detail['permission']['dateRange']['from'] ?? null),
            'date_range_to'        => $this->parseIso($detail['permission']['dateRange']['to'] ?? null),
            'data_erase_at'        => $this->parseIso($detail['permission']['dataEraseAt'] ?? null),
            'raw_artefact'         => json_encode($body),
            'fetched_at'           => $now,
        ];

        $requestRef = $this->requestIdForArtefact($consentId);
        if ($requestRef) {
            $fields['consent_request_id'] = $requestRef;
        }

        $existing = DB::table(self::ARTEFACTS)->where('consent_id', $consentId)->first();
        if ($existing) {
            // A revoked/expired/denied artefact must never be flipped back by a late or replayed callback.
            if (in_array($existing->status, AbdmHiuConsentRequest::TERMINAL_STATUSES, true) && $existing->status !== $fields['status']) {
                $fields['status'] = $existing->status;
            }
            DB::table(self::ARTEFACTS)->where('consent_id', $consentId)->update($fields);
        } else {
            DB::table(self::ARTEFACTS)->insert($fields + ['consent_id' => $consentId, 'created_on' => $now]);
        }

        return ['stored' => true, 'consent_id' => $consentId];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Outbound calls
    // ═══════════════════════════════════════════════════════════════════

    /**
     * POST /consent/v3/fetch by consent ARTEFACT id (not the consent request id).
     * Only ever answers 202; the artefact itself arrives at on-fetch.
     *
     * @return array{success:bool, error?:string, message?:string}
     */
    public function fetchArtefact(string $consentId): array
    {
        $consentId = trim($consentId);
        if ($consentId === '') {
            return ['success' => false, 'error' => 'Consent ID is required'];
        }

        $sent = $this->send(config('abdm.consent_fetch_url'), ['consentId' => $consentId], true);
        if (!$sent['ok']) {
            Log::error('ABDM HIU consent/fetch failed', ['consent_id' => $consentId, 'error' => $sent['error']]);
            return ['success' => false, 'error' => $sent['error']];
        }

        return ['success' => true, 'message' => 'Fetch request submitted (async).'];
    }

    /**
     * POST /consent/v3/request/status — bare consentRequestId body. Answers 202; the status
     * arrives at on-status.
     *
     * @return array{success:bool, error?:string}
     */
    public function requestStatus(string $consentRequestId): array
    {
        $consentRequestId = trim($consentRequestId);
        if ($consentRequestId === '') {
            return ['success' => false, 'error' => 'Consent Request ID is required'];
        }

        $sent = $this->send(config('abdm.consent_request_status_url'), ['consentRequestId' => $consentRequestId], true);
        if (!$sent['ok']) {
            Log::error('ABDM HIU request/status failed', ['consent_request_id' => $consentRequestId, 'error' => $sent['error']]);
            return ['success' => false, 'error' => $sent['error']];
        }

        return ['success' => true];
    }

    /**
     * UI-driven status poll (legacy hiu_ajax check_consent_status): only polls while the request is
     * still REQUESTED (ABDM answers ABDM-1039 for an already-resolved id) and at most once per
     * STATUS_CHECK_THROTTLE_SECONDS. The decision itself arrives via on-status/notify.
     *
     * @return array{success:bool, skipped?:bool, error?:string, message?:string}
     */
    public function checkRequestStatus(string $consentRequestId): array
    {
        $row = DB::table(self::REQUESTS)->where('consent_request_id', $consentRequestId)->first();
        if (!$row) {
            return ['success' => false, 'error' => 'No consent request to check yet'];
        }
        if ($row->latest_status !== 'REQUESTED') {
            return ['success' => true, 'skipped' => true];
        }
        if ($row->status_checked_at
            && (Carbon::now('UTC')->getTimestamp() - Carbon::parse($row->status_checked_at, 'UTC')->getTimestamp()) < self::STATUS_CHECK_THROTTLE_SECONDS) {
            return ['success' => true, 'skipped' => true];
        }

        $result = $this->requestStatus($consentRequestId);
        if ($result['success']) {
            DB::table(self::REQUESTS)->where('consent_request_id', $consentRequestId)->update(['status_checked_at' => $this->utcNow()]);
            $result['message'] = 'Status check submitted (async — the result arrives via the on-status callback).';
        }
        return $result;
    }

    /**
     * Acknowledge a notify: "acknowledgement" must be an ARRAY — one {status,consentId} per artefact,
     * or a single {status:'OK'} when there are none (DENIED/REVOKED). A bare object caused
     * "ABDM-1006: invalid request Body" in production.
     *
     * @param string[] $consentIds
     * @return array{success:bool, error?:string}
     */
    public function sendNotifyAck(array $consentIds, ?string $originalRequestId): array
    {
        $acknowledgements = [];
        foreach ($consentIds as $consentId) {
            $acknowledgements[] = ['status' => 'OK', 'consentId' => $consentId];
        }
        if (empty($acknowledgements)) {
            $acknowledgements[] = ['status' => 'OK'];
        }

        $sent = $this->send(config('abdm.consent_hiu_on_notify_url'), [
            'acknowledgement' => $acknowledgements,
            'error'           => null,
            'response'        => ['requestId' => $originalRequestId ?: (string) Str::uuid()],
        ], false);

        if (!$sent['ok']) {
            Log::error('ABDM HIU on-notify ack failed', ['consent_ids' => $consentIds, 'error' => $sent['error']]);
            return ['success' => false, 'error' => $sent['error']];
        }
        return ['success' => true];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Read model for the HIU panel
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Consent requests (newest first) each with its consent ARTEFACTS — one request can be granted
     * as several artefacts (one per HIP). Mirrors legacy hiu_ajax get_status; GRANTED actions
     * (fetch / request health data / list records) are keyed by each artefact's consent_id.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listRequests(?string $abhaAddress = null, int $limit = 50): array
    {
        $query = AbdmHiuConsentRequest::query()->orderByDesc('id')->limit($limit);
        if ($abhaAddress) {
            $query->where('abha_address', $abhaAddress);
        }
        $rows = $query->get();

        $idsOf = fn ($r) => array_values(array_filter(array_map('strval', (array) ($r->consent_ids ?? []))));
        $allIds = $rows->flatMap($idsOf)->unique()->values()->all();

        $artefacts = $keyed = $counts = collect();
        if ($allIds) {
            $artefacts = DB::table(self::ARTEFACTS)->whereIn('consent_id', $allIds)->get(['consent_id', 'hip_id', 'status'])->keyBy('consent_id');
            $keyed     = DB::table('abdm_hiu_key_material')->whereIn('consent_id', $allIds)
                ->selectRaw('consent_id, MAX(created_on) as requested_at')->groupBy('consent_id')->pluck('requested_at', 'consent_id');
            $counts    = DB::table('abdm_hiu_received_records')->whereIn('consent_id', $allIds)
                ->selectRaw('consent_id, COUNT(*) as cnt')->groupBy('consent_id')->pluck('cnt', 'consent_id');
        }

        return $rows->map(function ($r) use ($idsOf, $artefacts, $keyed, $counts) {
            $consents = [];
            foreach ($idsOf($r) as $cid) {
                $artefact   = $artefacts->get($cid);
                $consents[] = [
                    'consent_id'               => $cid,
                    'hip_id'                   => $artefact->hip_id ?? null,
                    'artefact_status'          => $artefact->status ?? null,
                    'artefact_fetched'         => (bool) $artefact,
                    'health_info_requested'    => $keyed->has($cid),
                    'health_info_requested_at' => $keyed->get($cid),
                    'records_count'            => (int) ($counts->get($cid) ?? 0),
                ];
            }

            return [
                'request_id'         => $r->request_id,
                'consent_request_id' => $r->consent_request_id,
                'abha_address'       => $r->abha_address,
                'latest_status'      => $r->latest_status,
                'rejection_error'    => $r->rejection_error,
                'acknowledged'       => (bool) $r->acknowledged,
                'purpose_code'       => $r->purpose_code,
                'purpose_text'       => self::PURPOSES[$r->purpose_code] ?? $r->purpose_code,
                'hi_types'           => (array) ($r->hi_types ?? []),
                'date_range_from'    => $r->requested_date_from,
                'date_range_to'      => $r->requested_date_to,
                'expiry_on'          => $r->requested_expiry_at,
                'granted_on'         => $r->granted_on,
                'created_on'         => $r->created_on,
                'updated_on'         => $r->updated_on,
                'retryable'          => in_array($r->latest_status, AbdmHiuConsentRequest::RETRYABLE_STATUSES, true),
                'consents'           => $consents,
                // Back-compat for callers expecting a single id: the first artefact.
                'consent_id'         => $consents[0]['consent_id'] ?? null,
                'records_count'      => array_sum(array_column($consents, 'records_count')),
            ];
        })->all();
    }

    // ═══════════════════════════════════════════════════════════════════
    // Internals
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Apply a patient decision / status poll result to the request row(s) with this consentRequestId.
     * A decided request is protected: DENIED/REVOKED/EXPIRED never change, GRANTED can only move to
     * REVOKED/EXPIRED. A refused transition still records the raw callback for audit.
     * granted_on is set once, the first time the row becomes GRANTED. consent_ids is only overwritten
     * by a non-empty list so a REVOKED notify without artefacts doesn't erase the artefact ids.
     *
     * @param string[] $consentIds
     * @return array{matched:bool, applied:bool}
     */
    private function applyDecision(string $consentRequestId, ?string $status, array $consentIds, string $raw, bool $touchChecked): array
    {
        $now  = $this->utcNow();
        $base = ['raw_last_callback' => $raw, 'updated_on' => $now];
        if ($touchChecked) {
            $base['status_checked_at'] = $now;
        }
        $rows    = fn () => DB::table(self::REQUESTS)->where('consent_request_id', $consentRequestId);
        $matched = $rows()->exists();

        // No status in the callback: keep the audit trail but never invent a status.
        if ($status === null) {
            $rows()->update($base);
            return ['matched' => $matched, 'applied' => false];
        }

        $update = $base + ['latest_status' => Str::limit($status, 20, '')];
        if ($consentIds) {
            $update['consent_ids'] = json_encode($consentIds);
        }

        $applied = $rows()->whereIn('latest_status', $this->allowedFrom($status))->update($update) > 0;
        if ($applied) {
            if ($status === 'GRANTED') {
                $rows()->where('latest_status', 'GRANTED')->whereNull('granted_on')->update(['granted_on' => $now]);
            }
            return ['matched' => true, 'applied' => true];
        }

        $rows()->update($base);
        return ['matched' => $matched, 'applied' => false];
    }

    /** Statuses a row may currently hold for $status to be applied over it. */
    private function allowedFrom(string $status): array
    {
        $open = ['REQUESTED', 'UNKNOWN'];
        return match ($status) {
            'REQUESTED'          => ['REQUESTED'],
            'GRANTED'            => [...$open, 'GRANTED'],
            'REVOKED', 'EXPIRED' => [...$open, 'GRANTED', $status],
            default              => [...$open, $status], // DENIED and anything unrecognised
        };
    }


    /** @return string[] */
    private function artefactIds($artefacts): array
    {
        $ids = [];
        foreach ((array) $artefacts as $a) {
            $id = is_array($a) ? ($a['id'] ?? null) : null;
            if ($id !== null && $id !== '') {
                $ids[] = (string) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /** consentRequestId of the request whose consent_ids contains this artefact id (best effort). */
    private function requestIdForArtefact(string $consentId): ?string
    {
        $like = '%"' . addcslashes($consentId, '%_\\') . '"%';
        return DB::table(self::REQUESTS)->where('consent_ids', 'like', $like)->orderByDesc('id')->value('consent_request_id') ?: null;
    }

    /**
     * One authenticated POST to the ABDM gateway.
     * Headers (legacy): X-CM-ID, REQUEST-ID (uuid), TIMESTAMP (UTC, ms), and X-HIU-ID on fetch/status.
     *
     * @return array{ok:bool, error:?string, body:string, request_id:string}
     */
    private function send(string $url, array $payload, bool $withHiuId): array
    {
        $requestId = (string) Str::uuid();

        $token = $this->tokenSvc->getAccessToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'Could not get ABDM access token', 'body' => '', 'request_id' => $requestId];
        }

        $headers = [
            'X-CM-ID'    => config('abdm.cm_id', 'sbx'),
            'REQUEST-ID' => $requestId,
            'TIMESTAMP'  => $this->gatewayTimestamp(),
        ];
        if ($withHiuId) {
            $headers['X-HIU-ID'] = $this->hiuId();
        }

        try {
            $response = Http::withToken($token)->withHeaders($headers)->timeout(30)->post($url, $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'body' => '', 'request_id' => $requestId];
        }

        $body = $response->body();
        if (!$response->successful()) {
            $decoded = json_decode($body, true);
            return [
                'ok'         => false,
                'error'      => $this->extractError(is_array($decoded) ? $decoded : null, $response->status(), $body),
                'body'       => $body,
                'request_id' => $requestId,
            ];
        }

        return ['ok' => true, 'error' => null, 'body' => $body, 'request_id' => $requestId];
    }

    /** Port of legacy abdm_extract_error_message(). */
    private function extractError(?array $response, ?int $httpCode = null, string $rawBody = ''): string
    {
        if (is_array($response) && array_is_list($response) && isset($response[0]) && is_array($response[0])) {
            $response = $response[0];
        }

        if (is_array($response)) {
            $errorObj = (isset($response['error']) && is_array($response['error'])) ? $response['error'] : $response;
            $msg  = $errorObj['message'] ?? $response['message'] ?? ($response['details'][0]['message'] ?? null);
            $code = $errorObj['code'] ?? $response['code'] ?? null;

            if ($msg && is_string($msg)) {
                $codeText = is_string($code) ? rtrim(trim($code), ':') : '';
                return $codeText !== '' ? $codeText . ': ' . $msg : $msg;
            }

            // Field-level validation errors have no message/error wrapper: first plain string value.
            foreach ($response as $key => $value) {
                if (in_array($key, ['timestamp', 'txnId', 'requestId'], true)) {
                    continue;
                }
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        // Plain-text bodies, e.g. a literal "Access Denied" with HTTP 403.
        if ($response === null && trim($rawBody) !== '') {
            return trim($rawBody) . ' (HTTP ' . ($httpCode ?? '?') . ')';
        }

        return 'ABDM request failed (HTTP ' . ($httpCode ?? '?') . ') with no response body';
    }

    /**
     * Resolve the requested health-info window to ISO-8601 UTC strings.
     * Legacy defaults: from = 6 months ago, to = now - 10s (ABDM rejects a future "to"), expiry = +1 year.
     *
     * @return array{0:string,1:string,2:string} [from, to, expiry]
     */
    private function resolveWindow(array $data): array
    {
        $fmtMs  = 'Y-m-d\TH:i:s.000\Z';
        $doneAt = Carbon::now('UTC')->subSeconds(10);

        $from = trim((string) ($data['date_from'] ?? ''));
        $to   = trim((string) ($data['date_to'] ?? ''));
        $exp  = trim((string) ($data['expiry_at'] ?? ''));

        try {
            $fromAt = $from !== '' ? Carbon::parse($from, 'UTC')->startOfDay() : Carbon::now('UTC')->subMonths(6)->startOfDay();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid Health Info "From" date');
        }
        try {
            $toAt = $to !== '' ? Carbon::parse($to, 'UTC')->endOfDay()->setMicroseconds(0) : $doneAt->copy();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('Invalid Health Info "To" date');
        }
        if ($toAt->gt($doneAt)) {
            $toAt = $doneAt->copy(); // e.g. "today" as end date: clamp to now
        }
        if ($fromAt->gt($toAt)) {
            throw new \InvalidArgumentException('The Health Info "From" date must not be after the "To" date');
        }

        if ($exp !== '') {
            try {
                $tz = config('app.timezone', 'UTC');
                $expAt = Carbon::parse($exp, $tz);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) {
                    $expAt = $expAt->endOfDay();
                }
                $expAt = $expAt->utc();
            } catch (\Throwable) {
                throw new \InvalidArgumentException('Invalid Consent Expiry date/time');
            }
            if ($expAt->lte(Carbon::now('UTC'))) {
                throw new \InvalidArgumentException('Consent Expiry must be a date/time in the future');
            }
        } else {
            $expAt = Carbon::now('UTC')->addYear();
        }

        return [$fromAt->format($fmtMs), $toAt->format($fmtMs), $expAt->format($fmtMs)];
    }

    /** ISO 8601 -> 'Y-m-d H:i:s' UTC (legacy abdm_hiu_parse_iso_datetime). */
    private function parseIso(?string $iso): ?string
    {
        if (empty($iso)) {
            return null;
        }
        $ts = strtotime($iso);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    private function utcNow(): string
    {
        return Carbon::now('UTC')->format('Y-m-d H:i:s');
    }

    private function gatewayTimestamp(): string
    {
        return Carbon::now('UTC')->format('Y-m-d\TH:i:s.v\Z');
    }

    /** HIU id (X-HIU-ID / hiu.id): ABDM_HIU_ID, falling back to the HIP/facility id only when empty. */
    private function hiuId(): string
    {
        return (string) (config('abdm.hiu_id') ?: config('abdm.hip_id'));
    }

    private function facilityName(): string
    {
        $known = (array) config('abdm.known_facilities', []);
        return $known[config('abdm.hip_id')] ?? $known[$this->hiuId()] ?? 'Hospital';
    }
}
