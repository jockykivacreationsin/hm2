<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Abdm\AbdmWebhookController;

/*
|--------------------------------------------------------------------------
| ABDM Webhook Callbacks
|--------------------------------------------------------------------------
| All ABDM gateway callbacks land here. These routes are CSRF-exempt
| (standard Laravel API middleware). ABDM sends POST requests to these
| URLs (configured as your bridge callback base URL + path).
|
| Bridge base URL (set in .env as ABDM_CALLBACK_BASE_URL):
|   http(s)://your-host/bmh/hospital-laravel/public
|
| Example full URL:
|   https://your-ngrok-url/bmh/hospital-laravel/public/api/v3/hip/token/on-generate-token
|
| Authenticity: a callback URL is reachable by everyone, so every route below runs through the
| `abdm.callback` guard (App\Http\Middleware\VerifyAbdmCallback) BEFORE the controller reads the body:
|   - answers=<kind>   the callback answers a call WE made: its requestId must match a request we sent
|                      (and one that is still waiting) - a forger cannot know a REQUEST-ID we generated.
|   - facility=hip|hiu the callback names no request of ours (consent notify, data request, profile share,
|                      ...): the X-HIP-ID / X-HIU-ID header must name a facility this bridge serves.
| Every rule also requires a well-formed, unexpired bearer token. See the middleware for what is (and, per
| ABDM's own guidance, is deliberately not) verified. Mode: config('abdm.callback_auth') = enforce|log|off.
*/

// ── HIP-side callbacks (ABDM → us, acting as HIP) ─────────────────────

// M2 Step 1: link token received (answers our generate-token call)
Route::post('v3/hip/token/on-generate-token', [AbdmWebhookController::class, 'onGenerateToken'])
    ->middleware('abdm.callback:answers=link_token')
    ->name('abdm.webhook.hip.on-generate-token');

// M2 Step 2: care context link confirmed/rejected (answers our add-contexts call)
// ABDM calls the path it received during bridge registration: v3/link/on_carecontext
// (NOT v3/hip/link/on-carecontext — that was an incorrect assumption; keep both for safety)
Route::post('v3/link/on_carecontext',    [AbdmWebhookController::class, 'onCareContext'])
    ->middleware('abdm.callback:answers=care_context')
    ->name('abdm.webhook.link.on-carecontext');
Route::post('v3/hip/link/on-carecontext', [AbdmWebhookController::class, 'onCareContext'])
    ->middleware('abdm.callback:answers=care_context')
    ->name('abdm.webhook.hip.on-carecontext');

// M3 HIP: consent notification (patient granted/revoked) - unsolicited
Route::post('v3/consent/request/hip/notify', [AbdmWebhookController::class, 'hipConsentNotify'])
    ->middleware('abdm.callback:facility=hip')
    ->name('abdm.webhook.hip.consent-notify');

// M4 HIP: HIU requesting health data - unsolicited
Route::post('v3/hip/health-information/request', [AbdmWebhookController::class, 'healthInfoRequest'])
    ->middleware('abdm.callback:facility=hip')
    ->name('abdm.webhook.hip.health-info-request');

// Profile Share / Scan-and-Share - unsolicited
Route::post('v3/hip/patient/share', [AbdmWebhookController::class, 'patientShare'])
    ->middleware('abdm.callback:facility=hip')
    ->name('abdm.webhook.hip.patient-share');

// Running token status - unsolicited
Route::post('v3/hip/patient/running-token/status', [AbdmWebhookController::class, 'runningTokenStatus'])
    ->middleware('abdm.callback:facility=hip')
    ->name('abdm.webhook.hip.running-token-status');

// ── HIU-side callbacks (ABDM → us, acting as HIU) ─────────────────────

// Consent request on-init (ABDM assigned consentRequestId) - answers our consent/request/init call
Route::post('v3/hiu/consent/request/on-init', [AbdmWebhookController::class, 'hiuConsentOnInit'])
    ->middleware('abdm.callback:answers=hiu_consent')
    ->name('abdm.webhook.hiu.consent-on-init');

// Consent request on-status: legacy bodies carry no requestId (matched by consentRequestId in the handler)
Route::post('v3/hiu/consent/request/on-status', [AbdmWebhookController::class, 'hiuConsentOnStatus'])
    ->middleware('abdm.callback:facility=hiu')
    ->name('abdm.webhook.hiu.consent-on-status');

// Patient's consent decision (GRANTED / DENIED): matched by consentRequestId, REQUEST-ID is a header
Route::post('v3/hiu/consent/request/notify', [AbdmWebhookController::class, 'hiuConsentNotify'])
    ->middleware('abdm.callback:facility=hiu')
    ->name('abdm.webhook.hiu.consent-notify');

// Full consent artefact fetched: matched by the artefact's own consent id in the handler
Route::post('v3/hiu/consent/on-fetch', [AbdmWebhookController::class, 'hiuConsentOnFetch'])
    ->middleware('abdm.callback:facility=hiu')
    ->name('abdm.webhook.hiu.consent-on-fetch');

// HIU health-information/on-request (ABDM acks our data request with transactionId): no request id in
// the body per legacy; the handler matches the pending key-material row
Route::post('v3/hiu/health-information/on-request', [AbdmWebhookController::class, 'hiuHealthInfoOnRequest'])
    ->middleware('abdm.callback:facility=hiu')
    ->name('abdm.webhook.hiu.health-info-on-request');

// Encrypted FHIR data pushed by the HIP: only a transactionId, and it only decrypts with a key pair we
// generated for that request, so a forged push yields nothing readable.
Route::post('v3/hiu/health-information/push', [AbdmWebhookController::class, 'hiuHealthInfoPush'])
    ->middleware('abdm.callback:facility=hiu')
    ->name('abdm.webhook.hiu.health-info-push');

// ── Staff QR-scanner kiosk (legacy api/attendance.php + api/staff_members.php) ─────────────────
// The kiosk has no user login, so it authenticates with a shared key (KIOSK_API_KEY, header X-Kiosk-Key); throttled per IP.
// The key check runs first so unauthenticated callers cannot probe staff ids or burn the throttle budget of the real kiosk.
Route::post('staff/attendance', [\App\Http\Controllers\Api\StaffApiController::class, 'attendance'])
    ->middleware(['kiosk.key', 'throttle:60,1'])
    ->name('api.staff.attendance');
Route::get('staff-members', [\App\Http\Controllers\Api\StaffApiController::class, 'members'])
    ->middleware(['kiosk.key', 'throttle:60,1'])
    ->name('api.staff.members');
