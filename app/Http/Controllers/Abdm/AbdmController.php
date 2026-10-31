<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Http\Requests\HiuConsentRequest;
use App\Http\Requests\RequestLinkTokenRequest;
use App\Http\Requests\RetryHealthInfoPushRequest;
use App\Http\Requests\StoreHiuConsentRequest;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\AbdmCareContext;
use App\Models\AbdmConsent;
use App\Models\AbdmHiuConsentRequest;
use App\Models\AbdmLinkToken;
use App\Models\Patient;
use App\Models\Opd;
use App\Models\Ipd;
use App\Models\Dcd;
use App\Services\AbdmTokenService;
use Illuminate\Support\Facades\DB;
use App\Services\Abdm\LinkTokenService;
use App\Services\Abdm\CareContextService;
use App\Services\Abdm\HiuConsentService;
use App\Services\Abdm\HiuHealthInfoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\CurrentUser;

class AbdmController extends Controller
{
    public function __construct(
        private AbdmTokenService     $tokenService,
        private LinkTokenService  $linkTokenSvc,
        private CareContextService $careCtxSvc,
        private HiuConsentService  $hiuConsentSvc,
        private HiuHealthInfoService $hiuHealthInfoSvc,
    ) {}

    // -------------------------------------------------------------------------
    // ABHA Account Services
    // -------------------------------------------------------------------------

    public function abhaAccountServices(Request $request, \App\Services\PermissionService $ps)
    {
        $canAccess = $ps->hasPermission('im_opd_history') || $ps->hasPermission('m_roles_and_permissions');
        return view('abdm.abha-account-services', compact('canAccess'));
    }

    // -------------------------------------------------------------------------
    // Consent Requests (HIU - Health Information User)
    // -------------------------------------------------------------------------

    public function consentRequests(Request $request, \App\Services\PermissionService $ps)
    {
        // JSON branch: used by the HIU offcanvas. Each entry is a consent REQUEST carrying its
        // consent ARTEFACTS in `consents[]` (one request can be granted as several artefacts);
        // GRANTED actions in the panel operate per artefact consent_id.
        if ($request->input('format') === 'json' || $request->expectsJson()) {
            $abha = trim((string) $request->query('abha_address', ''));
            return response()->json([
                'success' => true,
                'data'    => $this->hiuConsentSvc->listRequests($abha !== '' ? $abha : null, 50),
            ]);
        }

        $canAccess = $ps->hasPermission('im_opd_history') || $ps->hasPermission('m_roles_and_permissions');
        $requests = AbdmHiuConsentRequest::orderByDesc('id')->paginate(25);
        return view('abdm.consent-requests', compact('requests', 'canAccess'));
    }

    /**
     * Consent-requests page form (names: requested_date_from/to, requested_expiry_at).
     * Delegates to HiuConsentService, the same path as the HIU panel's hiuRequestConsent().
     */
    public function initiateConsentRequest(Request $request): JsonResponse
    {
        $v = $request->validate([
            'abha_address'        => 'required|string|max:255',
            'hi_types'            => 'required|array|min:1',
            'hi_types.*'          => 'string',
            'purpose_code'        => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(HiuConsentService::PURPOSES))],
            'requested_date_from' => 'nullable|date',
            'requested_date_to'   => 'nullable|date',
            'requested_expiry_at' => 'nullable|date',
        ]);

        return $this->submitConsentRequest([
            'abha_address' => $v['abha_address'],
            'hi_types'     => $v['hi_types'],
            'purpose_code' => $v['purpose_code'],
            'date_from'    => $v['requested_date_from'] ?? null,
            'date_to'      => $v['requested_date_to'] ?? null,
            'expiry_at'    => $v['requested_expiry_at'] ?? null,
        ]);
    }

    /** Shared by initiateConsentRequest() and hiuRequestConsent(): one place talks to ABDM. */
    private function submitConsentRequest(array $data): JsonResponse
    {
        $userId = CurrentUser::id();
        $result = $this->hiuConsentSvc->initiateRequest($data, $userId !== null ? (int) $userId : null);

        if (!$result['success']) {
            $result['message'] = $result['error'] ?? 'Consent request failed.';
        } else {
            $result['requestId'] = $result['request_id'] ?? null; // key used by the older consent-requests page
        }
        return response()->json($result);
    }


    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function purposeText(string $code): string
    {
        return match($code) {
            'CAREMGT' => 'Care Management',
            'PATRQT'  => 'Self Requested',
            'PUBHLTH' => 'Public Health',
            'HPROM'   => 'Health Promotion',
            'CARESUPT'=> 'Care Support',
            default   => $code,
        };
    }

    // =========================================================================
    // LINK TOKEN AJAX (M2 Step 1)
    // =========================================================================

    /** Check link token status for a patient */
    public function checkLinkToken(Request $request): JsonResponse
    {
        $patientId = $request->integer('patient_id');
        if ($patientId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid patient']);
        }
        $patient = Patient::find($patientId);
        if (!$patient || empty($patient->abha_address)) {
            return response()->json(['success' => false, 'error' => 'This patient has no ABHA Address on file']);
        }

        $existing = $this->linkTokenSvc->getValidToken($patientId, $patient->abha_address);
        $cooldown = $this->linkTokenSvc->getCooldown($patientId, $patient->abha_address);
        $pending  = $existing ? null : $this->linkTokenSvc->getPendingRequest($patientId, $patient->abha_address);

        $resp = ['success' => true, 'abha_address' => $patient->abha_address];
        if ($existing) {
            $resp['existing'] = true;
            $resp['issued_at']  = $existing->issued_at;
            $resp['expires_at'] = $existing->expires_at;
            $resp['seconds_remaining'] = (int) \Carbon\Carbon::parse($existing->expires_at, 'UTC')->diffInSeconds(now(), false) * -1;
        } else {
            $resp['existing'] = false;
        }
        if ($pending)   $resp['pending'] = true;
        if ($cooldown)  $resp = array_merge($resp, ['cooldown_seconds_remaining' => $cooldown['seconds_remaining'], 'cooldown_is_duplicate' => $cooldown['is_duplicate'], 'cooldown_error_message' => $cooldown['error_message']]);

        return response()->json($resp);
    }

    /** Initiate link token generation (async) */
    public function requestLinkToken(RequestLinkTokenRequest $request): JsonResponse
    {
        $patient = Patient::findOrFail($request->validated('patient_id'));
        if (empty($patient->abha_address) || empty($patient->abha_number)) return response()->json(['success' => false, 'error' => 'This patient has no ABHA Number/Address on file']);

        $result = $this->linkTokenSvc->requestToken(
            $patient->id, $patient->abha_address, $patient->abha_number ?? '', $patient->toArray(), CurrentUser::id()
        );
        return response()->json($result);
    }

    // =========================================================================
    // CARE CONTEXT AJAX (M2 Step 2)
    // =========================================================================

    /**
     * Shared care-context status for the Link-to-ABDM modal (OPD / IPD / DCD).
     *
     * "existing" means every HI type that currently has data is linked, so the modal
     * moves on to consent. A partially linked encounter keeps offering the link action
     * for the remaining types (the legacy IPD/DCD checks treated any linked type as done).
     */
    private function careContextStatus(string $module, int $id): JsonResponse
    {
        $model = ['opd' => Opd::class, 'ipd' => Ipd::class, 'dcd' => Dcd::class][$module];
        // The modal reads {success,error}, so a missing record is a JSON error (as in legacy), not an HTML 404.
        if ($id <= 0 || !($visit = $model::with('patient')->find($id))) {
            return response()->json(['success' => false, 'error' => $this->encounterNotFound($module, $id)]);
        }
        $p = $visit->patient;
        if (!$p || empty($p->abha_address)) {
            return response()->json(['success' => false, 'error' => 'This patient has no ABHA Address on file']);
        }

        $field     = $module . '_id';
        $available = $this->careCtxSvc->availableHiTypes($module, $id);
        $linkedRows = DB::table('abdm_care_contexts')
            ->where($field, $id)->where('status', 'linked')->orderBy('id')
            ->get(['hi_type', 'status', 'created_on']);
        $linkedTypes = $linkedRows->pluck('hi_type')->unique()->values()->all();
        $unlinked    = array_values(array_diff($available, $linkedTypes));

        $linkToken = $this->linkTokenSvc->getValidToken($p->id, $p->abha_address);
        $pending   = $this->careCtxSvc->getPending($field, $id);
        $cooldown  = $this->careCtxSvc->getCooldown($field, $id);

        // Legacy {care_context,ipd_care_context,dcd_care_context}_ajax.php: the module's exact data check
        // (the "Data to be shared" lines and has_* flags) and the doctor/date display name.
        $dataCheck = $this->careCtxSvc->dataCheck($module, $id);

        $resp = [
            'success'            => true,
            'has_link_token'     => (bool) $linkToken,
            'abha_address'       => $p->abha_address,
            'existing'           => $linkedRows->isNotEmpty() && empty($unlinked),
            'linked_count'       => count($linkedTypes),
            'linked_hi_types'    => $linkedRows->map(fn($r) => [
                'hi_type' => $r->hi_type, 'status' => $r->status, 'linked_at' => $r->created_on,
            ])->values()->all(),
            'available_hi_types' => $available,
            'unlinked_hi_types'  => $unlinked,
            'has_data'           => $dataCheck['has_any'],
            'data_check'         => $dataCheck,
        ];
        $resp['care_context_reference_number'] = $linkedRows->isNotEmpty()
            ? (DB::table('abdm_care_contexts')->where($field, $id)->where('status', 'linked')->orderByDesc('id')->value('care_context_reference_number') ?: match ($module) {
                'opd' => $this->careCtxSvc->opdCareContextRef($id),
                'ipd' => 'IPD' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
                'dcd' => 'DCD' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
            })
            : match ($module) {
                'opd' => $this->careCtxSvc->opdCareContextRef($id),
                'ipd' => 'IPD' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
                'dcd' => 'DCD' . str_pad((string) $id, 7, '0', STR_PAD_LEFT),
            };
        $resp['care_context_display'] = $this->careCtxSvc->careContextDisplayName($module, $visit);
        if ($linkedRows->isNotEmpty()) {
            $resp['linked_at'] = $linkedRows->first()->created_on;
        }
        // Only report "pending" while there is still something outstanding to wait for.
        if ($pending && !$resp['existing']) {
            $resp['pending'] = true;
        }
        if ($cooldown) {
            $resp = array_merge($resp, [
                'cooldown_seconds_remaining' => $cooldown['seconds_remaining'],
                'cooldown_is_duplicate'      => $cooldown['is_duplicate'],
                'cooldown_error_message'     => $cooldown['error_message'],
            ]);
        }

        return response()->json($resp);
    }

    /** Submit the bulk care-context link request (single ABDM call, legacy-compatible). */
    private function linkModuleCareContext(string $module, int $id): JsonResponse
    {
        $model = ['opd' => Opd::class, 'ipd' => Ipd::class, 'dcd' => Dcd::class][$module];
        if ($id <= 0 || !($visit = $model::with('patient')->find($id))) {
            return response()->json(['success' => false, 'error' => $this->encounterNotFound($module, $id)]);
        }
        $p = $visit->patient;
        if (!$p || empty($p->abha_address) || empty($p->abha_number)) {
            return response()->json(['success' => false, 'error' => 'This patient has no ABHA Number/Address on file']);
        }

        $method = 'link' . ucfirst($module);
        $result = $this->careCtxSvc->{$method}($id, $p->id, $p->abha_address, $p->patient_name, (int) CurrentUser::id());
        if (!empty($result['success']) && !empty($result['pending']) && !empty($result['request_id'])) {
            $result = $this->awaitCareContextCallback($module, $id, $result);
        }
        return response()->json($result);
    }

    /**
     * Like legacy care_context.php, hold the request open (up to abdm.care_context_wait_seconds) for ABDM's
     * async on_carecontext callback, so the modal gets "linked" straight away instead of polling.
     * Legacy waited for a flag file the callback wrote; the callback here settles the pending rows themselves,
     * so they are watched directly. A rejection is reported at once (legacy only timed out); no answer in time
     * returns the legacy pending/timeout shape, which the modal turns into polling + a refresh button.
     */
    private function awaitCareContextCallback(string $module, int $id, array $result): array
    {
        $wait = (int) config('abdm.care_context_wait_seconds', 30);
        if ($wait <= 0) {
            return $result;
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit($wait + 90);
        }

        $field    = $module . '_id';
        $deadline = microtime(true) + $wait;
        do {
            $rows = DB::table('abdm_care_contexts')->where('request_id', $result['request_id'])->get(['id', 'status']);

            if ($rows->isEmpty()) {
                // The callback carried an error: it recorded the attempt and removed the pending rows.
                $attempt  = DB::table('abdm_care_context_attempts')->where($field, $id)->where('success', 0)->orderByDesc('id')->first();
                $cooldown = $this->careCtxSvc->getCooldown($field, $id);

                return ['success' => false, 'error' => $attempt->error_message ?? 'ABDM rejected the care context link request.']
                    + ($cooldown ? ['cooldown_seconds_remaining' => $cooldown['seconds_remaining']] : []);
            }

            if (!$rows->contains('status', 'pending')) {
                $linkedAt = DB::table('abdm_care_context_hi_types')->whereIn('care_context_id', $rows->pluck('id'))->max('linked_at') ?: gmdate('Y-m-d H:i:s');

                return [
                    'success'  => true,
                    'linked'   => true,
                    'message'  => 'Care context linked successfully! ABDM confirmed at ' . $linkedAt . ' UTC',
                    'hi_types' => $result['hi_types'] ?? [],
                ];
            }

            usleep(500000);
        } while (microtime(true) < $deadline);

        // array_merge, not +: $result already carries the generic "submitted" message that the timeout must replace.
        return array_merge($result, [
            'timeout' => true,
            'message' => 'Request accepted by ABDM - confirmation is taking longer than expected. The link may still succeed in the background.',
        ]);
    }

    /** Legacy wording: "Invalid ..." for a bad id, "... not found" for a missing record. */
    private function encounterNotFound(string $module, int $id): string
    {
        $invalid = ['opd' => 'Invalid OPD visit', 'ipd' => 'Invalid IPD admission', 'dcd' => 'Invalid DCD record'];
        $missing = ['opd' => 'OPD visit not found', 'ipd' => 'IPD admission not found', 'dcd' => 'DCD record not found'];

        return $id <= 0 ? $invalid[$module] : $missing[$module];
    }

    public function checkOpdCareContext(int $opdId): JsonResponse
    {
        return $this->careContextStatus('opd', $opdId);
    }

    public function linkOpdCareContext(int $opdId): JsonResponse
    {
        return $this->linkModuleCareContext('opd', $opdId);
    }

    public function checkIpdCareContext(int $ipdId): JsonResponse
    {
        return $this->careContextStatus('ipd', $ipdId);
    }

    public function linkIpdCareContext(int $ipdId): JsonResponse
    {
        return $this->linkModuleCareContext('ipd', $ipdId);
    }

    public function checkDcdCareContext(int $dcdId): JsonResponse
    {
        return $this->careContextStatus('dcd', $dcdId);
    }

    public function linkDcdCareContext(int $dcdId): JsonResponse
    {
        return $this->linkModuleCareContext('dcd', $dcdId);
    }

    // =========================================================================
    // HEALTH INFO STATUS (consent + push polling)
    // =========================================================================

    /** The encounter a status poll is about, as [column, id] for opd_id / ipd_id / dcd_id, or null. */
    private function encounterFromRequest(Request $request): ?array
    {
        foreach (['opd_id', 'ipd_id', 'dcd_id'] as $field) {
            if ($request->integer($field)) {
                return [$field, $request->integer($field)];
            }
        }
        return null;
    }

    private function missingEncounter(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'Provide opd_id, ipd_id, or dcd_id.']);
    }

    /**
     * Consent notified for an OPD / IPD / DCD encounter (query: opd_id OR ipd_id OR dcd_id).
     *
     * Consent is matched against the care-context references actually linked for this
     * encounter (legacy IPD/DCD share one reference across HI types; earlier typed
     * Laravel links keep their own), never against a reconstructed reference.
     */
    public function healthInfoConsent(Request $request): JsonResponse
    {
        if (!$encounter = $this->encounterFromRequest($request)) {
            return $this->missingEncounter();
        }
        [$field, $id] = $encounter;

        $refs = DB::table('abdm_care_contexts')
            ->where($field, $id)->where('status', 'linked')
            ->pluck('care_context_reference_number')->unique()->values()->all();

        if (!$refs) return response()->json(['success' => true, 'found' => false]);

        $match = AbdmConsent::where(function ($q) use ($refs) {
                foreach ($refs as $ref) {
                    // care_contexts is stored as JSON, so the reference appears as "REF"
                    $q->orWhere('care_contexts', 'like', '%"' . addcslashes($ref, '%_\\') . '"%');
                }
            })
            ->orderByDesc('id')
            ->first(['consent_id', 'status', 'acknowledged', 'acknowledgement_error', 'created_on']);

        if (!$match) return response()->json(['success' => true, 'found' => false]);
        return response()->json([
            'success'               => true, 'found' => true,
            'consent_id'            => $match->consent_id,
            'status'                => $match->status,
            'acknowledged'          => (bool) $match->acknowledged,
            'acknowledgement_error' => $match->acknowledgement_error,
            'notified_at'           => $match->created_on,
        ]);
    }

    /** Latest health-information push for an OPD / IPD / DCD encounter (query: opd_id OR ipd_id OR dcd_id). */
    public function healthInfoPushStatus(Request $request): JsonResponse
    {
        if (!$encounter = $this->encounterFromRequest($request)) {
            return $this->missingEncounter();
        }
        [$field, $id] = $encounter;

        $rows = DB::table('abdm_health_info_pushes')->where($field, $id)
            ->orderByDesc('id')->limit(20)->get();
        if ($rows->isEmpty()) return response()->json(['success' => true, 'found' => false]);

        $latest = $rows->first();
        $latestTxRows = $rows->where('transaction_id', $latest->transaction_id);
        $pushSucceeded = $latestTxRows->every(fn($r) => (bool) $r->push_succeeded);
        $failed = $latestTxRows->first(fn($r) => !$r->push_succeeded);

        return response()->json([
            'success'              => true,
            'found'                => true,
            // The newest transfer (one transaction can have several HI-type rows)
            'push_succeeded'       => $pushSucceeded,
            'notify_succeeded'     => (bool) $latest->notify_succeeded,
            'notify_error_message' => $latest->notify_error_message ?? null,
            'error_message'        => $failed->error_message ?? ($latest->notify_error_message ?? null),
            'attempted_at'         => $latest->created_on,
        ]);
    }

    /**
     * Manual push retry. A push needs the HIU's key material from a fresh health-information/request,
     * so a retry can only ask for that request again (same as legacy health_info_ajax.php).
     */
    public function retryHealthInfoPush(RetryHealthInfoPushRequest $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => 'Manual retry requires a new data request from the HIU side. Please request the data again from your ABHA app or HIU portal.',
            'hint'    => 'The original health-information/request has expired. ABDM needs to send a new request with fresh encryption keys.',
        ]);
    }

    // =========================================================================
    // HIU (health information user): consent requests and received records
    // =========================================================================

    public function hiuRequestConsent(Request $request): JsonResponse
    {
        $v = $request->validate([
            'abha_address'  => 'required|string|max:255',
            'hi_types'      => 'required|array|min:1',
            'hi_types.*'    => 'string',
            'purpose_code'  => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(HiuConsentService::PURPOSES))],
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date',
            'expiry_at'     => 'nullable|date',
        ]);

        // Legacy hiu_ajax request_consent: don't fire a duplicate while a request is still in flight.
        if ($this->hiuConsentSvc->hasPendingRequest($v['abha_address'])) {
            return response()->json([
                'success' => false,
                'error'   => 'A consent request is already pending for this patient',
                'message' => 'A consent request is already pending for this patient',
            ]);
        }

        return $this->submitConsentRequest($v);
    }

    public function hiuFetchArtefact(Request $request): JsonResponse
    {
        // consent_id is the consent ARTEFACT id (one of a request's consent_ids), not the request id.
        $consentId = trim((string) $request->input('consent_id', ''));
        if ($consentId === '') return response()->json(['success' => false, 'error' => 'Consent ID is required']);
        $result = $this->hiuConsentSvc->fetchArtefact($consentId);
        return response()->json($result);
    }

    public function hiuListRecords(Request $request): JsonResponse
    {
        $consentId = trim((string) $request->input('consent_id', ''));
        if ($consentId === '') return response()->json(['success' => false, 'error' => 'consent_id required']);

        // Headline fields (title / facility / date) come from the decrypted bundle, like the legacy
        // "Received Records" list; the bundle itself is only sent when a record is opened.
        $records = DB::table('abdm_hiu_received_records')
            ->where('consent_id', $consentId)->orderByDesc('id')->limit(100)
            ->get(['id','transaction_id','care_context_reference','media','checksum_ok','decryption_error','received_at','decrypted_bundle'])
            ->map(function ($r) {
                $summary = $this->hiuHealthInfoSvc->extractFhirSummary($r->decrypted_bundle);
                unset($r->decrypted_bundle);
                $r->summary = $summary;
                return $r;
            });
        return response()->json(['success' => true, 'records' => $records]);
    }

    /**
     * HIU — request actual health data transfer after consent is GRANTED.
     * Generates ECDH key pair, stores it, calls ABDM health-information/request.
     */
    public function hiuRequestHealthData(Request $request): JsonResponse
    {
        $v = $request->validate([
            'consent_id' => 'required|string',
            'from_date'  => 'nullable|date',
            'to_date'    => 'nullable|date',
        ]);
        $result = $this->hiuHealthInfoSvc->requestHealthData(
            $v['consent_id'], $v['from_date'] ?? null, $v['to_date'] ?? null, (int) CurrentUser::id()
        );
        return response()->json($result);
    }

    /** HIU — actively poll ABDM for consent request status */
    public function hiuCheckConsentStatus(Request $request): JsonResponse
    {
        $consentRequestId = trim((string) $request->input('consent_request_id', ''));

        // Legacy looked the request up by the patient's ABHA address; accept that too.
        if ($consentRequestId === '' && $request->filled('abha_address')) {
            $consentRequestId = (string) AbdmHiuConsentRequest::where('abha_address', (string) $request->input('abha_address'))
                ->orderByDesc('id')->value('consent_request_id');
        }
        if ($consentRequestId === '') {
            return response()->json(['success' => false, 'error' => 'consent_request_id required']);
        }

        // Async: ABDM answers 202 and the real status arrives at the on-status webhook.
        // The service applies the legacy rules (only poll while REQUESTED, 60s throttle) and sends
        // /consent/v3/request/status with X-CM-ID, X-HIU-ID, REQUEST-ID and TIMESTAMP headers.
        return response()->json($this->hiuConsentSvc->checkRequestStatus($consentRequestId));
    }

    /**
     * HIU — get decrypted FHIR record detail by record ID.
     */
    public function hiuGetRecordDetail(Request $request): JsonResponse
    {
        $recordId = $request->integer('record_id');
        if (!$recordId) return response()->json(['success' => false, 'error' => 'record_id required']);
        $record = DB::table('abdm_hiu_received_records')->where('id', $recordId)->first();
        if (!$record) return response()->json(['success' => false, 'error' => 'Record not found']);
        return response()->json([
            'success'          => true,
            'decrypted_bundle' => $record->decrypted_bundle,
            'decryption_error' => $record->decryption_error,
            'checksum_ok'      => $record->checksum_ok,
            'care_context'     => $record->care_context_reference,
        ]);
    }

    // -------------------------------------------------------------------------
    // HIU JSON endpoints behind the "ABDM Records" / "Request Records From Abha" buttons, the consent
    // request modal, the offcanvas records viewer and the Consent Requests page.
    // -------------------------------------------------------------------------

    /**
     * Runs an HIU endpoint; like the legacy hiu_ajax.php, an unexpected failure is answered
     * as {success:false, error} (HTTP 200) so the panels can show it inline.
     */
    private function hiuGuarded(Request $request, \Closure $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('ABDM HIU request failed: ' . $e->getMessage(), [
                'route'      => $request->route()?->getName(),
                'patient_id' => $request->route('patientId') ?? $request->input('patient_id'),
                'exception'  => $e,
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'An unexpected error occurred while processing the ABDM request.',
            ]);
        }
    }

    /** Select2 source for the Consent Requests page patient pickers (query: q). */
    public function consentPatientsDropdown(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $query = Patient::query();
        if ($search !== '') {
            $uhidId = \App\Support\Uhid::parse($search);   // a typed UHID finds that patient
            $query->where(function ($q) use ($search, $uhidId) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('patient_name', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%")
                  ->when($uhidId !== null, fn ($u) => $u->orWhere('id', $uhidId));
            });
        }
        $patients = $query->orderBy('patient_name')->limit(100)->get(['id', 'patient_name', 'mobile_number', 'abha_address']);

        return response()->json($patients->map(function ($p) {
            $badge = !empty($p->abha_address) ? ' ✓ ABHA' : '';
            return [
                'id'   => $p->id,
                'text' => "{$p->patient_name} - {$p->mobile_number}{$badge}",
            ];
        }));
    }

    public function hiuSearchPatientByAbha(Request $request): JsonResponse
    {
        return $this->hiuGuarded($request, function () use ($request) {
            $abha = trim((string) $request->input('abha_address', ''));
            if ($abha === '') {
                return response()->json(['success' => false, 'error' => 'ABHA Address is required']);
            }
            $p = Patient::where('abha_address', $abha)->first(['id', 'patient_name', 'abha_number', 'abha_address']);
            return response()->json(['success' => true, 'found' => (bool) $p, 'patient' => $p]);
        });
    }

    /** One patient with the identity fields the Consent Requests form pre-fills from (ABHA Address / Number). */
    public function hiuPatientShow(int $patientId): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($patientId) {
            $p = Patient::find($patientId);
            if (!$p) {
                return response()->json(['id' => 0, 'patient_name' => '', 'abha_address' => '']);
            }
            return response()->json($p->only(['id', 'patient_name', 'gender', 'mobile_number', 'address', 'aadhaar_number', 'abha_number', 'abha_address', 'date_of_birth', 'photo']));
        });
    }

    // ── Consent requests (Consent Requests page) ────────────────────────────

    /** DataTables source for the Consent Requests page (draw/start/length/search[value] + filters). */
    public function hiuConsentsIndex(Request $request): JsonResponse
    {
        return $this->hiuGuarded($request, function () use ($request) {
            $draw   = (int) $request->input('draw', 0);
            $start  = max(0, (int) $request->input('start', 0));
            $length = max(1, (int) $request->input('length', 10));
            $search = trim((string) data_get($request->all(), 'search.value', ''));

            $query = DB::table('abdm_hiu_consent_requests as r')
                ->leftJoin('patients as p', 'p.abha_address', '=', 'r.abha_address');

            $recordsTotal = DB::table('abdm_hiu_consent_requests')->count();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('r.abha_address', 'like', "%{$search}%")
                      ->orWhere('p.patient_name', 'like', "%{$search}%")
                      ->orWhere('r.latest_status', 'like', "%{$search}%");
                });
            }

            if ($request->filled('patient_id')) {
                $query->where('p.id', (int) $request->input('patient_id'));
            }
            if ($request->filled('status')) {
                $query->where('r.latest_status', (string) $request->input('status'));
            }
            if ($request->filled('purpose_code')) {
                $query->where('r.purpose_code', (string) $request->input('purpose_code'));
            }
            if ($request->filled('created_on_range')) {
                $parts = array_map('trim', explode(' to ', (string) $request->input('created_on_range')));
                $fromDt = \DateTime::createFromFormat('d-m-Y', $parts[0] ?? '');
                $toDt   = \DateTime::createFromFormat('d-m-Y', $parts[1] ?? ($parts[0] ?? ''));
                if ($fromDt) {
                    $query->where('r.created_on', '>=', $fromDt->format('Y-m-d 00:00:00'));
                }
                if ($toDt) {
                    $query->where('r.created_on', '<=', $toDt->format('Y-m-d 23:59:59'));
                }
            }

            $recordsFiltered = (clone $query)->count();
            $rows = $query->select('r.*', 'p.id as patient_id', 'p.patient_name')
                ->orderBy('r.id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            $statusColorMap = [
                'GRANTED' => '#2e7d32', 'REQUESTED' => '#ef6c00', 'DENIED' => '#c62828',
                'EXPIRED' => '#757575', 'REVOKED' => '#c62828', 'UNKNOWN' => '#757575',
            ];

            $data = [];
            foreach ($rows as $row) {
                $cids = json_decode($row->consent_ids ?? '[]', true) ?: [];
                $hiTypesDecoded = json_decode($row->hi_types ?? '[]', true) ?: [];
                $firstConsentId = $cids[0] ?? null;

                $artefact = null;
                if ($firstConsentId) {
                    $artefact = DB::table('abdm_hiu_consent_artefacts')->where('consent_id', $firstConsentId)->first();
                }

                $rangeFrom = $artefact?->date_range_from ?? $row->requested_date_from ?? null;
                $rangeTo   = $artefact?->date_range_to ?? $row->requested_date_to ?? null;
                $expiry    = $artefact?->data_erase_at ?? $row->requested_expiry_at ?? null;
                $status    = $row->latest_status;
                $color     = $statusColorMap[$status] ?? '#757575';

                $viewHtml = '--';
                if ($status === 'GRANTED' && !empty($row->patient_id)) {
                    $viewHtml = '<button type="button" class="btn btn-xs btn-primary" onclick="abdmHiuOpenViewOffcanvas(' . (int) $row->patient_id . ')">VIEW</button>';
                } elseif (!empty($row->rejection_error)) {
                    $viewHtml = '<span title="' . e($row->rejection_error) . '">--</span>';
                }

                $data[] = [
                    'id'                 => (int) $row->id,
                    'name'               => $row->patient_name ?: '(Unknown)',
                    'patient_identifier' => $row->abha_address,
                    'status'             => '<span style="color:' . $color . '; font-weight:600;">' . e($status) . '</span>',
                    'purpose'            => e($this->purposeText($row->purpose_code)),
                    'hi_type'            => e(implode(', ', $hiTypesDecoded)),
                    'created_on'         => $this->fmtDt($row->created_on),
                    'granted_on'         => $this->fmtDt($row->granted_on),
                    'expiry_on'          => $this->fmtDt($expiry),
                    'consent_range'      => ($rangeFrom && $rangeTo) ? ($this->fmtDt($rangeFrom) . ' - ' . $this->fmtDt($rangeTo)) : '--',
                    'view'               => $viewHtml,
                    'first_consent_id'   => $firstConsentId,
                    'consent_count'      => count($cids),
                    'rejection_error'    => $row->rejection_error,
                    'retryable'          => in_array($status, ['DENIED', 'EXPIRED', 'REVOKED', 'UNKNOWN'], true),
                ];
            }

            return response()->json([
                'draw'            => $draw,
                'recordsTotal'    => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data'            => $data,
            ]);
        });
    }

    /** New consent request from the Consent Requests page (patient picked by ABHA address). */
    public function hiuConsentsStore(StoreHiuConsentRequest $request): JsonResponse
    {
        return $this->hiuGuarded($request, fn () => $this->initiateHiuConsent($request, (string) $request->input('abha_address')));
    }

    public function hiuConsentsShow(int $id): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($id) {
            if ($id <= 0) {
                return response()->json(['success' => false, 'error' => 'Invalid consent request']);
            }
            $row = DB::table('abdm_hiu_consent_requests as r')
                ->leftJoin('patients as p', 'p.abha_address', '=', 'r.abha_address')
                ->where('r.id', $id)
                ->select('r.*', 'p.patient_name')
                ->first();
            if (!$row) {
                return response()->json(['success' => false, 'error' => 'Consent request not found']);
            }

            $consentIds = json_decode($row->consent_ids ?? '[]', true) ?: [];
            $artefacts  = [];
            if ($consentIds) {
                $artRows = DB::table('abdm_hiu_consent_artefacts')->whereIn('consent_id', $consentIds)->get();
                foreach ($artRows as $art) {
                    $artArr = (array) $art;
                    $artArr['hi_types_decoded'] = json_decode($art->hi_types ?? '[]', true) ?: [];
                    $artArr['care_contexts_decoded'] = json_decode($art->care_contexts ?? '[]', true) ?: [];
                    $artefacts[] = $artArr;
                }
            }

            $rowArr = (array) $row;
            $rowArr['consent_ids_decoded'] = $consentIds;
            $rowArr['hi_types_decoded']    = json_decode($row->hi_types ?? '[]', true) ?: [];
            unset($rowArr['raw_init_response'], $rowArr['raw_last_callback']);

            return response()->json(['success' => true, 'request' => $rowArr, 'artefacts' => $artefacts]);
        });
    }

    /** Ask ABDM for the current status of one consent request (skipped unless REQUESTED, 60s throttle). */
    public function hiuConsentsRefreshStatus(int $id): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($id) {
            if ($id <= 0) {
                return response()->json(['success' => false, 'error' => 'Invalid consent request']);
            }
            $existing = DB::table('abdm_hiu_consent_requests')->where('id', $id)->first();
            if (!$existing || empty($existing->consent_request_id)) {
                return response()->json(['success' => false, 'error' => 'No consent request id to check yet']);
            }
            if ($existing->latest_status !== 'REQUESTED') {
                return response()->json(['success' => true, 'skipped' => true]);
            }
            $lastChecked = $existing->status_checked_at;
            if ($lastChecked && (time() - strtotime($lastChecked . ' UTC')) < 60) {
                return response()->json(['success' => true, 'skipped' => true]);
            }
            $res = $this->hiuConsentSvc->checkRequestStatus($existing->consent_request_id);
            if (!empty($res['success'])) {
                DB::table('abdm_hiu_consent_requests')->where('id', $id)->update(['status_checked_at' => now()]);
            }
            return response()->json($res);
        });
    }

    // ── Patient-scoped HIU panel (OPD / IPD / DCD visits, offcanvas) ────────

    /** The patient when an ABHA is on file; otherwise the JSON answer the panels expect. */
    private function hiuPatient(int $patientId): Patient|JsonResponse
    {
        if ($patientId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid patient']);
        }

        $patient = Patient::find($patientId, ['id', 'patient_name', 'abha_number', 'abha_address']);
        if (!$patient) {
            return response()->json(['success' => false, 'error' => 'Patient not found']);
        }

        if (empty($patient->abha_number) || empty($patient->abha_address)) {
            return response()->json(['success' => true, 'has_abha' => false]);
        }

        return $patient;
    }

    /** Latest consent request of a patient, its artefacts and received-record counts. */
    public function hiuPatientStatus(int $patientId): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($patientId) {
            $patient = $this->hiuPatient($patientId);
            if ($patient instanceof JsonResponse) {
                return $patient;
            }
            $abhaAddress = $patient->abha_address;

            $latestReq = AbdmHiuConsentRequest::where('abha_address', $abhaAddress)->orderByDesc('id')->first();
            $response = [
                'success'      => true,
                'has_abha'     => true,
                'abha_address' => $abhaAddress,
                'patient_name' => $patient->patient_name,
                'request'      => null,
            ];

            if ($latestReq) {
                $consentIds = is_array($latestReq->consent_ids)
                    ? $latestReq->consent_ids
                    : (json_decode($latestReq->consent_ids ?? '[]', true) ?: []);
                $consents = [];
                foreach ($consentIds as $cid) {
                    $artefactRow = DB::table('abdm_hiu_consent_artefacts')->where('consent_id', $cid)->first(['hip_id']);
                    $keyRow = DB::table('abdm_hiu_key_material')->where('consent_id', $cid)->first(['id', 'created_on']);
                    $recCount = DB::table('abdm_hiu_received_records')->where('consent_id', $cid)->count();

                    $consents[] = [
                        'consent_id'               => $cid,
                        'hip_id'                   => $artefactRow?->hip_id,
                        'artefact_fetched'         => (bool) $artefactRow,
                        'health_info_requested'    => (bool) $keyRow,
                        'health_info_requested_at' => $keyRow?->created_on,
                        'records_count'            => (int) $recCount,
                    ];
                }

                $retryable = in_array($latestReq->latest_status, ['DENIED', 'EXPIRED', 'REVOKED', 'UNKNOWN'], true);
                $hiTypesDecoded = is_array($latestReq->hi_types)
                    ? $latestReq->hi_types
                    : (json_decode($latestReq->hi_types ?? '[]', true) ?: []);
                $response['request'] = [
                    'request_id'            => $latestReq->request_id,
                    'consent_request_id'    => $latestReq->consent_request_id,
                    'latest_status'         => $latestReq->latest_status,
                    'rejection_error'       => $latestReq->rejection_error,
                    'created_on'            => $latestReq->created_on,
                    'updated_on'            => $latestReq->updated_on,
                    'retryable'             => $retryable,
                    'consents'              => $consents,
                    'purpose_text'          => $this->purposeText($latestReq->purpose_code ?? 'CAREMGT'),
                    'hi_types'              => $hiTypesDecoded,
                    'date_range_from'       => $this->fmtDt($latestReq->requested_date_from),
                    'date_range_to'         => $this->fmtDt($latestReq->requested_date_to),
                    'expiry_on'             => $this->fmtDt($latestReq->requested_expiry_at),
                    'granted_on'            => $this->fmtDt($latestReq->granted_on),
                    'consent_id'            => $consents[0]['consent_id'] ?? null,
                    'artefact_fetched'      => $consents[0]['artefact_fetched'] ?? false,
                    'health_info_requested' => $consents[0]['health_info_requested'] ?? false,
                    'records_count'         => array_sum(array_column($consents, 'records_count')),
                ];
            }

            return response()->json($response);
        });
    }

    /** "Request Records From Abha": new consent request for the patient's ABHA address. */
    public function hiuPatientConsentRequest(HiuConsentRequest $request, int $patientId): JsonResponse
    {
        return $this->hiuGuarded($request, function () use ($request, $patientId) {
            $patient = $this->hiuPatient($patientId);
            if ($patient instanceof JsonResponse) {
                return $patient;
            }

            $existing = AbdmHiuConsentRequest::where('abha_address', $patient->abha_address)->orderByDesc('id')->first();
            if ($existing && $existing->latest_status === 'REQUESTED') {
                return response()->json(['success' => false, 'error' => 'A consent request is already pending for this patient']);
            }
            return $this->initiateHiuConsent($request, $patient->abha_address);
        });
    }

    /** Ask ABDM for the status of the patient's latest consent request (skipped unless REQUESTED, 60s throttle). */
    public function hiuPatientCheckConsentStatus(int $patientId): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($patientId) {
            $patient = $this->hiuPatient($patientId);
            if ($patient instanceof JsonResponse) {
                return $patient;
            }

            $existing = AbdmHiuConsentRequest::where('abha_address', $patient->abha_address)->orderByDesc('id')->first();
            if (!$existing || empty($existing->consent_request_id)) {
                return response()->json(['success' => false, 'error' => 'No consent request to check yet']);
            }
            if ($existing->latest_status !== 'REQUESTED') {
                return response()->json(['success' => true, 'skipped' => true]);
            }
            $lastChecked = $existing->status_checked_at;
            if ($lastChecked && (time() - strtotime($lastChecked . ' UTC')) < 60) {
                return response()->json(['success' => true, 'skipped' => true]);
            }
            $result = $this->hiuConsentSvc->checkRequestStatus($existing->consent_request_id);
            if (!empty($result['success'])) {
                $existing->update(['status_checked_at' => now()]);
            }
            return response()->json($result);
        });
    }

    // ── Received records ────────────────────────────────────────────────────

    /** Records received under one consent artefact, with the headline fields from the decrypted bundle. */
    public function hiuConsentRecords(string $consentId): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($consentId) {
            $consentId = trim($consentId);
            if ($consentId === '') {
                return response()->json(['success' => false, 'error' => 'Consent ID is required']);
            }
            $rows = DB::table('abdm_hiu_received_records')
                ->where('consent_id', $consentId)
                ->orderBy('id', 'asc')
                ->get(['id', 'transaction_id', 'care_context_reference', 'media', 'checksum_ok', 'decryption_error', 'decrypted_bundle', 'received_at']);

            $records = [];
            foreach ($rows as $row) {
                $summary = $this->hiuHealthInfoSvc->extractFhirSummary($row->decrypted_bundle);
                $records[] = [
                    'id'                     => (int) $row->id,
                    'transaction_id'         => $row->transaction_id,
                    'care_context_reference' => $row->care_context_reference,
                    'media'                  => $row->media,
                    'checksum_ok'            => $row->checksum_ok,
                    'decryption_error'       => $row->decryption_error,
                    'received_at'            => $row->received_at,
                    'title'                  => $summary['title'] ?? null,
                    'facility'               => $summary['facility'] ?? null,
                    'visit_date'             => $summary['visit_date'] ?? null,
                ];
            }
            return response()->json(['success' => true, 'records' => $records]);
        });
    }

    /** One received record including its decrypted FHIR bundle. */
    public function hiuRecordShow(int $recordId): JsonResponse
    {
        return $this->hiuGuarded(request(), function () use ($recordId) {
            if ($recordId <= 0) {
                return response()->json(['success' => false, 'error' => 'Invalid record']);
            }
            $record = DB::table('abdm_hiu_received_records')->where('id', $recordId)->first();
            if (!$record) {
                return response()->json(['success' => false, 'error' => 'Record not found']);
            }
            return response()->json(['success' => true, 'record' => (array) $record]);
        });
    }

    /** Sends a validated consent request to ABDM (shared by the Consent Requests page and the patient panel). */
    private function initiateHiuConsent(HiuConsentRequest $request, string $abhaAddress): JsonResponse
    {
        $userId = (int) (CurrentUser::id() ?? 1);

        return response()->json($this->hiuConsentSvc->initiateRequest($request->consentPayload($abhaAddress), $userId));
    }

    public function fmtDt(?string $dt): string
    {
        if (empty($dt)) {
            return 'N/A';
        }
        $ts = strtotime($dt . ' UTC');
        return $ts !== false ? date('d M, Y h:i A', $ts) : 'N/A';
    }
}

