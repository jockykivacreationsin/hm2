<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAbdmHealthInfo;
use App\Services\Abdm\CareContextService;
use App\Services\Abdm\HipConsentService;
use App\Services\Abdm\HiuConsentService;
use App\Services\Abdm\LinkTokenService;
use App\Services\Abdm\HiuHealthInfoService;

use App\Services\Abdm\OpdShareService;
use App\Services\AbdmTokenService;
use App\Support\Redact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles all ABDM webhook callbacks from the gateway.
 * All routes here are in routes/api.php (CSRF-exempt).
 * Every endpoint must return HTTP 202 immediately; real processing is synchronous here
 * but ABDM does not wait for a body — just the status code.
 */
class AbdmWebhookController extends Controller
{
    public function __construct(
        private LinkTokenService     $linkTokenSvc,
        private CareContextService   $careCtxSvc,
        private HipConsentService    $hipConsentSvc,
        private HiuConsentService    $hiuConsentSvc,
        private AbdmTokenService     $tokenSvc,
        private HiuHealthInfoService $hiuHealthInfoSvc,
        private OpdShareService      $opdShareSvc,
    ) {}

    // ═══════════════════════════════════════════════════════════════════
    // HIP-SIDE CALLBACKS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * M2 Step 1 — ABDM delivers the link token asynchronously.
     * POST /api/v3/hip/token/on-generate-token
     */
    public function onGenerateToken(Request $request): JsonResponse
    {
        // Never log the link token itself — it is a bearer credential for linking, wherever in the body it sits.
        Log::info('ABDM on-generate-token', Redact::value($request->all()));
        $this->linkTokenSvc->handleOnGenerateToken($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * M2 Step 2 — ABDM confirms (or rejects) care context linking.
     * POST /api/v3/hip/link/on-carecontext
     */
    public function onCareContext(Request $request): JsonResponse
    {
        Log::info('ABDM on-carecontext', Redact::value($request->all()));
        $this->careCtxSvc->handleOnCareContext($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * M3 HIP — Consent notification (GRANTED / REVOKED / EXPIRED).
     * POST /api/v3/consent/request/hip/notify
     * Checks X-HIP-ID to guard against misrouted callbacks in multi-facility setups.
     */
    public function hipConsentNotify(Request $request): JsonResponse
    {
        Log::info('ABDM HIP consent notify', ['status' => $request->input('notification.status'), 'consent_id' => $request->input('notification.consentId')]);

        // Facility guard: ignore callbacks meant for other bridges
        if (!$this->isForThisFacility($request)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $notification    = $request->input('notification', []);
        $originalReqId   = $request->input('requestId', '');
        $this->hipConsentSvc->handleNotify($notification, $originalReqId);
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * M4 HIP — HIU is requesting health data.
     * POST /api/v3/hip/health-information/request
     *
     * IMPORTANT: transactionId is a TOP-LEVEL sibling of hiRequest in ABDM's
     * real callback body — NOT nested inside hiRequest. Must be merged in.
     */
    public function healthInfoRequest(Request $request): JsonResponse
    {
        $body          = $request->all();
        $hiRequest     = $body['hiRequest'] ?? [];
        // transactionId is top-level, not inside hiRequest — merge it in
        $transactionId = $body['transactionId'] ?? ($hiRequest['transactionId'] ?? null);
        $consentId     = $hiRequest['consent']['id'] ?? null;
        $dataPushUrl   = $hiRequest['dataPushUrl'] ?? null;
        $keyMaterial   = $hiRequest['keyMaterial'] ?? null;
        $requestId     = $request->header('REQUEST-ID') ?? ($body['requestId'] ?? (string) Str::uuid());

        Log::info('ABDM health-info request', compact('transactionId', 'consentId'));

        if (!$transactionId) {
            // The body carries the HIU's encryption key material: log it with credentials masked.
            Log::warning('ABDM health-info request: missing transactionId', Redact::value($body));
            return response()->json(['status' => 'ignored'], 200);
        }

        if (!$this->isForThisFacility($request)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Without these the request can never be fulfilled (legacy health_information.php
        // rejects the same cases), so don't acknowledge or store it.
        if (!$consentId || !$dataPushUrl || empty($keyMaterial['dhPublicKey']['keyValue']) || empty($keyMaterial['nonce'])) {
            Log::warning('ABDM health-info request: incomplete payload', compact('transactionId', 'consentId'));
            return response()->json(['status' => 'ignored'], 200);
        }

        // Store first so a duplicate callback (same transaction) is recognised and never processed twice.
        $stored = DB::table('abdm_health_info_requests')->insertOrIgnore([
            'request_id'     => $requestId,
            'transaction_id' => $transactionId,
            'consent_id'     => $consentId,
            'data_push_url'  => $dataPushUrl,
            'key_material'   => json_encode($keyMaterial),
            'raw_payload'    => json_encode($body),
            'status'         => 'pending',
            'created_on'     => now()->toDateTimeString(),
        ]);

        if (!$stored) {
            return response()->json(['status' => 'OK'], 202);
        }

        // Interim ACKNOWLEDGED to ABDM, then build/encrypt/push once the HTTP response has been
        // returned (same sequence as the legacy handler, without making ABDM wait for the FHIR work).
        $this->sendHealthInfoInterimAck($transactionId, $requestId);
        ProcessAbdmHealthInfo::dispatchAfterResponse($transactionId);

        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * Profile Share / Scan-and-Share.
     * POST /api/v3/hip/patient/share
     * Finds/creates patient from ABHA profile, creates OPD visit, sends ack with token number.
     */
    public function patientShare(Request $request): JsonResponse
    {
        // Same order as legacy share.php: facility guard, JSON, REQUEST-ID, intent. Nothing patient-related is logged.
        $facilityId = $this->resolveShareFacility($request);
        if ($facilityId === null) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $body = $this->decodeJsonBody($request);
        if ($body === null) {
            return response()->json(['error' => ['code' => 'INVALID_JSON', 'message' => 'Invalid JSON']], 400);
        }

        $requestId = $this->shareRequestId($request, $body);
        if ($requestId === null) {
            Log::warning('ABDM patient share: missing/invalid REQUEST-ID');
            return response()->json(['error' => ['code' => 'MISSING_REQUEST_ID', 'message' => 'REQUEST-ID header required']], 400);
        }

        if (($body['intent'] ?? null) !== 'PROFILE_SHARE') {
            return response()->json(['error' => ['code' => 'INVALID_INTENT', 'message' => 'Unsupported intent']], 400);
        }

        $result = $this->opdShareSvc->handleShare($body, $requestId, $facilityId);

        if (!$result['success']) {
            $error = ['code' => $result['code'] ?? 'PROCESSING_ERROR', 'message' => $result['error'] ?? 'Failed'];
            if (isset($result['details'])) {
                $error['details'] = $result['details'];
            }
            $payload = ['error' => $error];
            if (isset($result['opd_id'])) {
                $payload['opd_id']      = $result['opd_id'];
                $payload['tokenNumber'] = $result['token_label'];
            }
            return response()->json($payload, $result['status'] ?? 500);
        }

        return response()->json([
            'status'      => 'ACCEPTED',
            'message'     => 'Profile shared successfully',
            'tokenNumber' => $result['token_label'],
            'expiresIn'   => max(1, (int) config('abdm.profile_share_ack_expiry_seconds', 1800)),
            'timestamp'   => gmdate('Y-m-d\TH:i:s.') . '000Z',
        ], 202);
    }

    /**
     * Running token status — patient checks live queue position.
     * POST /api/v3/hip/patient/running-token/status
     *
     * FIXED: 'context' and 'hipId' are TOP-LEVEL fields in ABDM's body,
     * NOT nested under 'token'. REQUEST-ID comes from HTTP header.
     */
    public function runningTokenStatus(Request $request): JsonResponse
    {
        // Facility guard (X-HIP-ID), then JSON, REQUEST-ID, hipId guard, context - legacy status.php order.
        $facilityId = $this->resolveShareFacility($request);
        if ($facilityId === null) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $body = $this->decodeJsonBody($request);
        if ($body === null) {
            return response()->json(['error' => ['code' => 'INVALID_JSON', 'message' => 'Invalid JSON']], 400);
        }

        $requestId = $this->shareRequestId($request, $body);
        if ($requestId === null) {
            return response()->json(['error' => ['code' => 'MISSING_REQUEST_ID', 'message' => 'REQUEST-ID header required']], 400);
        }

        // Top-level 'hipId' / 'context' per ABDM spec (confirmed from legacy api file).
        $bodyHipId = is_scalar($body['hipId'] ?? null) ? trim((string) $body['hipId']) : '';
        $ownId     = trim((string) config('abdm.hip_id', ''));
        $hipId     = $facilityId;
        if ($bodyHipId !== '') {
            $canonical = $this->matchFacilityId($bodyHipId);
            // Legacy ignores a hipId that is not this facility's; here every facility registered on this bridge is accepted.
            if (($canonical === null && $ownId !== '') || strlen($bodyHipId) > 50) {
                Log::warning('ABDM running-token: hipId does not match any known facility - ignoring', ['hip_id' => mb_substr($bodyHipId, 0, 50)]);
                return response()->json(['status' => 'ignored'], 200);
            }
            $hipId = $canonical ?? $bodyHipId;
        }

        $result = $this->opdShareSvc->handleRunningToken($body, $requestId, $hipId);

        if (!$result['success']) {
            $error = ['code' => $result['code'] ?? 'PROCESSING_ERROR', 'message' => $result['error'] ?? 'Failed'];
            if (isset($result['details'])) {
                $error['details'] = $result['details'];
            }
            $payload = ['error' => $error];
            if (isset($result['running_token'])) {
                $payload['runningTokenNumber'] = $result['running_token'];
            }
            return response()->json($payload, $result['status'] ?? 500);
        }

        return response()->json([
            'status'             => 'ACCEPTED',
            'message'            => 'Running token status shared successfully',
            'runningTokenNumber' => $result['running_token'],
            'timestamp'          => gmdate('Y-m-d\TH:i:s.') . '000Z',
        ], 202);
    }

    // ─── Profile-share / running-token helpers ────────────────────

    /**
     * Legacy abdm_request_is_for_this_facility() semantics for the share + running-token callbacks.
     * Returns the request-local facility (HIP) id, or null when the callback is misrouted.
     * An absent header (or an unconfigured own id) lets the call through; a header naming another
     * facility of this bridge switches the request's facility identity to that facility.
     */
    private function resolveShareFacility(Request $request): ?string
    {
        $own      = trim((string) config('abdm.hip_id', ''));
        $incoming = trim((string) $request->header('X-HIP-ID', ''));
        if ($incoming === '') {
            return $own;
        }

        $matched = $this->matchFacilityId($incoming);
        if ($matched !== null) {
            return $matched;
        }

        if ($own === '') {
            return $own;
        }

        Log::warning('ABDM misrouted callback: X-HIP-ID does not match any known facility', ['x_hip_id' => mb_substr($incoming, 0, 50), 'own_hip_id' => $own]);
        return null;
    }

    /** Canonical facility id if $id is this facility or one of abdm.known_facilities, else null. */
    private function matchFacilityId(string $id): ?string
    {
        $own = trim((string) config('abdm.hip_id', ''));
        if ($own !== '' && strcasecmp($id, $own) === 0) {
            return $own;
        }
        foreach ((array) config('abdm.known_facilities', []) as $knownId => $knownName) {
            if (strcasecmp($id, (string) $knownId) === 0) {
                return (string) $knownId;
            }
        }
        return null;
    }

    /** Decode the raw body; null when it is not a valid JSON object/array (legacy: 400 INVALID_JSON). */
    private function decodeJsonBody(Request $request): ?array
    {
        $decoded = json_decode($request->getContent(), true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    /**
     * The REQUEST-ID header (legacy), else a top-level body requestId. Must be a short id-like string
     * because it is echoed back to ABDM in response.requestId.
     */
    private function shareRequestId(Request $request, array $body): ?string
    {
        $candidate = $request->header('REQUEST-ID') ?: $request->header('X-REQUEST-ID') ?: ($body['requestId'] ?? '');
        $candidate = is_string($candidate) ? trim($candidate) : '';

        return preg_match('/^[A-Za-z0-9._:\-]{1,100}$/', $candidate) ? $candidate : null;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HIU-SIDE CALLBACKS
    // ═══════════════════════════════════════════════════════════════════

    /**
     * HIU — consent request on-init (ABDM assigns consentRequestId).
     * POST /api/v3/hiu/consent/request/on-init
     */
    public function hiuConsentOnInit(Request $request): JsonResponse
    {
        Log::info('ABDM HIU consent on-init', [
            'requestId'        => $request->input('requestId', $request->input('resp.requestId', $request->input('response.requestId'))),
            'consentRequestId' => $request->input('consentRequest.id'),
            'has_error'        => $request->filled('error'),
        ]);
        $this->hiuConsentSvc->handleOnInit($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * HIU — consent request status update.
     * POST /api/v3/hiu/consent/request/on-status
     */
    public function hiuConsentOnStatus(Request $request): JsonResponse
    {
        Log::info('ABDM HIU consent on-status', [
            'consentRequestId' => $request->input('consentRequest.id'),
            'status'           => $request->input('consentRequest.status'),
            'has_error'        => $request->filled('error'),
        ]);
        $this->hiuConsentSvc->handleOnStatus($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * HIU — patient's consent decision (GRANTED / DENIED).
     * POST /api/v3/hiu/consent/request/notify
     *
     * REQUEST-ID must come from the HTTP header (it is NOT in the JSON body) and is echoed back
     * in the ack; ABDM expects the ack as an ARRAY: [{status,consentId},...].
     */
    public function hiuConsentNotify(Request $request): JsonResponse
    {
        // REQUEST-ID is sent as an HTTP header by ABDM, not in the JSON body
        $originalRequestId = $request->header('REQUEST-ID') ?? $request->header('X-REQUEST-ID');
        Log::info('ABDM HIU consent notify', [
            'request_id_header'  => $originalRequestId,
            'consentRequestId'   => $request->input('notification.consentRequestId'),
            'notification_status' => $request->input('notification.status'),
        ]);
        $this->hiuConsentSvc->handleNotify($request->all(), $originalRequestId);
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * HIU — full consent artefact detail (on-fetch).
     * POST /api/v3/hiu/consent/on-fetch
     *
     * Stored by HiuConsentService::storeArtefact() in abdm_hiu_consent_artefacts, keyed by the
     * artefact consentId (UTC timestamps, terminal status never downgraded). The table has no
     * updated_on column and the request table has no artefact_id column, so neither is written.
     */
    public function hiuConsentOnFetch(Request $request): JsonResponse
    {
        // Never log the artefact itself (patient + care-context detail) — only its shape.
        Log::info('ABDM HIU consent on-fetch', [
            'consentId' => $request->input('consent.consentDetail.consentId', $request->input('consent.consentId')),
            'status'    => $request->input('consent.status'),
        ]);

        $result = $this->hiuConsentSvc->storeArtefact($request->all());
        if (!$result['stored']) {
            return response()->json(['status' => 'ignored'], 200);
        }

        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * HIU — encrypted FHIR data pushed by HIP/ABDM gateway.
     * POST /api/v3/hiu/health-information/push
     * Delegates to HiuHealthInfoService which decrypts via FideliusCrypto.
     */
    public function hiuHealthInfoPush(Request $request): JsonResponse
    {
        Log::info('ABDM HIU health-info push received', ['transaction_id' => $request->input('transactionId'), 'entry_count' => count($request->input('entries', []))]);
        $this->hiuHealthInfoSvc->handlePush($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    /**
     * HIU — health-information on-request (transactionId from ABDM).
     * POST /api/v3/hiu/health-information/on-request
     */
    public function hiuHealthInfoOnRequest(Request $request): JsonResponse
    {
        Log::info('ABDM HIU health-info on-request', Redact::value($request->all()));
        $this->hiuHealthInfoSvc->handleOnRequest($request->all());
        return response()->json(['status' => 'OK'], 202);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /**
     * Facility guard: checks X-HIP-ID header matches one of this bridge's
     * registered facilities. Returns true (pass) when header is absent.
     * Mirrors abdm_request_is_for_this_facility() from legacy common.php.
     */
    private function isForThisFacility(Request $request): bool
    {
        $incomingHipId = $request->header('X-HIP-ID');
        if (empty($incomingHipId)) return true; // no header → let through

        $ownId = config('abdm.hip_id', '');
        if (strcasecmp($incomingHipId, $ownId) === 0) return true;

        $known = config('abdm.known_facilities', []);
        foreach ($known as $knownId => $knownName) {
            if (strcasecmp($incomingHipId, (string)$knownId) === 0) return true;
        }

        Log::warning('ABDM misrouted callback: X-HIP-ID does not match any known facility', ['x_hip_id' => $incomingHipId, 'own_hip_id' => $ownId]);
        return false;
    }

    /** ISO 8601 datetime → MySQL DATETIME string */
    private function parseIsoDate(?string $iso): ?string
    {
        if (empty($iso)) return null;
        $ts = strtotime($iso);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    private function sendHealthInfoInterimAck(?string $transactionId, string $originalRequestId): void
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return;

        $payload = [
            'requestId' => (string) Str::uuid(),
            'timestamp' => AbdmTokenService::timestamp(),
            'hiRequest' => ['transactionId' => $transactionId, 'sessionStatus' => 'ACKNOWLEDGED'],
            'response'  => ['requestId' => $originalRequestId],
        ];

        try {
            $res = Http::withToken($token)
                ->withHeaders([
                    'REQUEST-ID' => (string) Str::uuid(),
                    'TIMESTAMP'  => now()->utc()->subSeconds(15)->format('Y-m-d\TH:i:s.v\Z'),
                    'X-CM-ID'    => 'sbx',
                    'X-HIP-ID'   => config('abdm.hip_id'),
                ])
                ->post(config('abdm.health_info_hip_on_request_url'), $payload);

            if (!$res->successful()) {
                Log::warning('ABDM health-info interim ACK non-2xx', [
                    'http_code'      => $res->status(),
                    'body'           => $res->body(),
                    'transaction_id' => $transactionId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ABDM health-info interim ACK failed', ['error' => $e->getMessage()]);
        }
    }
}
