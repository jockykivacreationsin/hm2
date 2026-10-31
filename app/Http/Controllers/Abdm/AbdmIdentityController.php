<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\Abdm\AbdmIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\CurrentUser;
use App\Support\Uploads;

/**
 * ABDM M1 identity AJAX endpoints - port of bmhabdm/abdm/identity_ajax.php:
 *   - Aadhaar OTP enrolment (ABHA creation) + ABHA address
 *   - ABHA login OTP (ABHA Number / ABHA Address), Aadhaar login OTP, mobile login OTP
 *   - ABHA card download, ABDM profile photo save
 *   - "new vs returning patient" identity check
 *
 * Routes: abdm.identity.* in routes/web.php under the auth group (CSRF via the web middleware).
 * OTP transaction state (txnId/scope) is kept ONLY in the server-side session - never taken
 * from the request - exactly like the legacy $_SESSION. The raw Aadhaar number is never stored
 * or logged. X-tokens returned to the browser (as legacy does, for the card download) are also
 * remembered in the session, and the card endpoint only spends a token issued to this session.
 */
class AbdmIdentityController extends Controller
{
    private const MAX_PHOTO_BASE64_BYTES = 2 * 1024 * 1024;
    private const MAX_SESSION_TOKENS     = 10;

    public function __construct(private AbdmIdentityService $identitySvc) {}

    private function input(Request $request, string $key): string
    {
        $v = $request->input($key, '');
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private function error(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $message]);
    }

    private function validOtp(string $otp): bool
    {
        return (bool) preg_match('/^\d{6}$/', $otp);
    }

    /** Remember X-tokens we hand to the browser so only those can be spent on the card endpoint. */
    private function rememberXTokens(array $result): void
    {
        $tokens = [];
        if (!empty($result['x_token']) && is_string($result['x_token'])) $tokens[] = $result['x_token'];
        foreach ($result['accounts'] ?? [] as $account) {
            if (!empty($account['x_token']) && is_string($account['x_token'])) $tokens[] = $account['x_token'];
        }
        if (!$tokens) return;

        $known = array_merge((array) session('abdm_identity_x_tokens', []), $tokens);
        session(['abdm_identity_x_tokens' => array_slice(array_values(array_unique($known)), -self::MAX_SESSION_TOKENS)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Aadhaar enrolment flow
    // ─────────────────────────────────────────────────────────────────────────

    public function aadhaarSendOtp(Request $request): JsonResponse
    {
        $aadhaar = $this->input($request, 'aadhaar_number');
        if ($aadhaar === '') return $this->error('Please enter the Aadhaar number');

        $result = $this->identitySvc->aadhaarSendOtp($aadhaar);
        if ($result['success']) {
            session(['abdm_aadhaar_txn' => $result['txn_id']]);
        }
        return response()->json($result);
    }

    public function aadhaarVerifyOtp(Request $request): JsonResponse
    {
        $otp    = $this->input($request, 'otp');
        $mobile = $this->input($request, 'mobile');
        $txnId  = session('abdm_aadhaar_txn');

        if ($otp === '' || !$txnId) return $this->error('Please request an OTP first');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');
        if (!preg_match('/^\d{10}$/', $mobile)) {
            return $this->error('Please enter a valid 10-digit mobile number - ABDM requires this to create the ABHA');
        }

        $result = $this->identitySvc->aadhaarVerifyOtp($txnId, $otp, $mobile);
        if ($result['success']) {
            session()->forget('abdm_aadhaar_txn');
            $this->rememberXTokens($result);

            // A brand-new ABHA has no preferred ABHA Address yet: fetch ABDM's suggestions and let
            // staff pick/type one. The enrolment txnId stays server-side for that next call.
            if (empty($result['abha_address'])) {
                $addressTxnId = $result['txn_id'] ?? $txnId;
                session(['abdm_aadhaar_address_txn' => $addressTxnId]);

                $suggestions = $this->identitySvc->getAbhaAddressSuggestions($addressTxnId);
                $result['needs_abha_address'] = true;
                $result['suggestions']        = $suggestions['success'] ? $suggestions['suggestions'] : [];
            }
        }
        return response()->json($result);
    }

    public function aadhaarCreateAbhaAddress(Request $request): JsonResponse
    {
        $abhaAddress = $this->input($request, 'abha_address');
        $txnId       = session('abdm_aadhaar_address_txn');

        if (!$txnId) return $this->error('Please verify the Aadhaar OTP first');
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._]{3,}$/', $abhaAddress)) {
            return $this->error('Please enter a valid ABHA Address (letters/numbers, at least 4 characters, starting with a letter)');
        }

        $result = $this->identitySvc->createAbhaAddress($txnId, $abhaAddress);
        if ($result['success']) session()->forget('abdm_aadhaar_address_txn');
        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA login OTP flow (ABHA Number / ABHA Address)
    // ─────────────────────────────────────────────────────────────────────────

    public function abhaLoginSendOtp(Request $request): JsonResponse
    {
        $loginId = $this->input($request, 'login_id');
        if ($loginId === '') return $this->error('Please enter the ABHA Number or ABHA Address');

        $result = $this->identitySvc->abhaLoginSendOtp($loginId);
        if ($result['success']) {
            session(['abdm_abha_login_txn' => ['txn_id' => $result['txn_id'], 'scope' => $result['scope']]]);
        }
        return response()->json($result);
    }

    public function abhaLoginVerifyOtp(Request $request): JsonResponse
    {
        $otp   = $this->input($request, 'otp');
        $state = session('abdm_abha_login_txn');

        if ($otp === '' || !$state) return $this->error('Please request an OTP first');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');

        $result = $this->identitySvc->abhaLoginVerifyOtp($state['txn_id'], $otp, $state['scope']);
        if ($result['success']) {
            session()->forget('abdm_abha_login_txn');
            $this->rememberXTokens($result);
        }
        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Aadhaar login OTP flow (verify an ABHA already linked to an Aadhaar - no enrolment)
    // ─────────────────────────────────────────────────────────────────────────

    public function aadhaarLoginSendOtp(Request $request): JsonResponse
    {
        $aadhaar = $this->input($request, 'aadhaar_number') ?: $this->input($request, 'aadhaar');
        if ($aadhaar === '') return $this->error('Please enter the Aadhaar number');

        $result = $this->identitySvc->aadhaarLoginSendOtp($aadhaar);
        if ($result['success']) {
            session(['abdm_aadhaar_login_txn' => $result['txn_id']]);
        }
        return response()->json($result);
    }

    public function aadhaarLoginVerifyOtp(Request $request): JsonResponse
    {
        $otp   = $this->input($request, 'otp');
        $txnId = session('abdm_aadhaar_login_txn');

        if ($otp === '' || !$txnId) return $this->error('Please request an OTP first');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');

        $result = $this->identitySvc->aadhaarLoginVerifyOtp($txnId, $otp);
        if ($result['success']) {
            session()->forget('abdm_aadhaar_login_txn');
            $this->rememberXTokens($result);
        }
        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mobile login OTP flow
    // ─────────────────────────────────────────────────────────────────────────

    public function mobileSendOtp(Request $request): JsonResponse
    {
        $mobile = $this->input($request, 'mobile_number');
        if ($mobile === '') return $this->error('Please enter the mobile number');

        $result = $this->identitySvc->mobileSendOtp($mobile);
        if ($result['success']) {
            session(['abdm_mobile_login_txn' => ['txn_id' => $result['txn_id'], 'scope' => $result['scope']]]);
        }
        return response()->json($result);
    }

    public function mobileVerifyOtp(Request $request): JsonResponse
    {
        $otp   = $this->input($request, 'otp');
        $state = session('abdm_mobile_login_txn');

        if ($otp === '' || !$state) return $this->error('Please request an OTP first');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');

        $result = $this->identitySvc->mobileVerifyOtp($state['txn_id'], $otp, $state['scope']);
        if ($result['success']) {
            session()->forget('abdm_mobile_login_txn');
            $this->rememberXTokens($result);
        }
        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA account services: update mobile / update email
    // (abdm.identity.update-mobile.* / abdm.identity.update-email.* routes)
    //
    // Per flow the server keeps, in the session only, three time-limited stages:
    //   login -> {txn_id, scope}   (login OTP requested)
    //   token -> {x_token}         (login OTP verified; never sent to the browser)
    //   otp   -> {txn_id}          (mobile flow only: OTP sent to the new number)
    // Mobile and email use separate session roots so a login in one flow can never be
    // spent in the other. Nothing here is read from the request body except the user's
    // own inputs (ABHA id, OTP, new mobile/email).
    // ─────────────────────────────────────────────────────────────────────────

    private const UPDATE_FLOWS = ['mobile' => 'abdm_update_mobile', 'email' => 'abdm_update_email'];

    private function flowPut(string $flow, string $stage, array $data): void
    {
        session([self::UPDATE_FLOWS[$flow] . '.' . $stage => $data + ['at' => now()->getTimestamp()]]);
    }

    /** The stage's state, or null when absent/expired (an expired stage is discarded). */
    private function flowGet(string $flow, string $stage): ?array
    {
        $key   = self::UPDATE_FLOWS[$flow] . '.' . $stage;
        $state = session($key);
        if (!is_array($state) || !isset($state['at'])) return null;

        $ttl = max(60, (int) config('abdm.identity_update_ttl_seconds', 900));
        if (now()->getTimestamp() - (int) $state['at'] > $ttl) {
            session()->forget($key);
            return null;
        }
        return $state;
    }

    private function flowClear(string $flow): void
    {
        session()->forget(self::UPDATE_FLOWS[$flow]);
    }

    private function accountLoginOtp(Request $request, string $flow): JsonResponse
    {
        $abha = $this->input($request, 'abha');
        if ($abha === '') return $this->error('ABHA Number/Address required');
        if (strlen($abha) > 100) return $this->error('Invalid ABHA Number/Address');
        if (str_contains($abha, '@') && !preg_match('/^[^\s@]+@[^\s@]+$/', $abha)) {
            return $this->error('Invalid ABHA Address');
        }

        $result = $this->identitySvc->abhaLoginSendOtp($abha);
        if (!$result['success']) return $this->error($result['error'] ?? 'Failed to send OTP');

        // A fresh login discards anything left over from an earlier attempt in this flow.
        $this->flowClear($flow);
        $this->flowPut($flow, 'login', ['txn_id' => $result['txn_id'], 'scope' => $result['scope']]);
        return response()->json(['success' => true, 'message' => 'Login OTP sent']);
    }

    private function accountVerifyLogin(Request $request, string $flow): JsonResponse
    {
        $otp   = $this->input($request, 'otp');
        $state = $this->flowGet($flow, 'login');

        if ($otp === '' || !$state) return $this->error('Please request the login OTP first - it may have expired');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');

        $result = $this->identitySvc->accountLoginVerifyOtp($state['txn_id'], $otp, $state['scope']);
        if (!$result['success']) return $this->error($result['error'] ?? 'Verification failed');

        // The X-token stays server-side; the browser only learns that login succeeded.
        $this->flowClear($flow);
        $this->flowPut($flow, 'token', ['x_token' => $result['x_token']]);
        return response()->json(['success' => true, 'message' => 'Login successful']);
    }

    public function updateMobileLoginOtp(Request $request): JsonResponse
    {
        return $this->accountLoginOtp($request, 'mobile');
    }

    public function updateMobileVerifyLogin(Request $request): JsonResponse
    {
        return $this->accountVerifyLogin($request, 'mobile');
    }

    public function updateMobileSendOtp(Request $request): JsonResponse
    {
        $mobile = $this->input($request, 'mobile');
        if (!preg_match('/^\d{10}$/', $mobile)) return $this->error('Invalid mobile number');

        $token = $this->flowGet('mobile', 'token');
        if (!$token) return $this->error('Please login first');

        $result = $this->identitySvc->updateMobileSendOtp($token['x_token'], $mobile);
        if (!$result['success']) return $this->error($result['error'] ?? 'Failed to send OTP');

        $this->flowPut('mobile', 'otp', ['txn_id' => $result['txn_id']]);
        return response()->json(['success' => true, 'message' => 'OTP sent to new mobile']);
    }

    public function updateMobileVerifyOtp(Request $request): JsonResponse
    {
        $otp   = $this->input($request, 'otp');
        $token = $this->flowGet('mobile', 'token');
        $state = $this->flowGet('mobile', 'otp');

        if (!$token) return $this->error('Please login first');
        if ($otp === '' || !$state) return $this->error('Please request an OTP for the new mobile first - it may have expired');
        if (!$this->validOtp($otp)) return $this->error('Please enter the 6-digit OTP');

        $result = $this->identitySvc->updateMobileVerifyOtp($token['x_token'], $state['txn_id'], $otp);
        if (!$result['success']) return $this->error($result['error'] ?? 'Verification failed');

        $this->flowClear('mobile');
        Log::info('ABHA mobile number updated', ['user_id' => CurrentUser::id()]);
        return response()->json(['success' => true, 'message' => 'Mobile number updated successfully']);
    }

    public function updateEmailLoginOtp(Request $request): JsonResponse
    {
        return $this->accountLoginOtp($request, 'email');
    }

    public function updateEmailVerifyLogin(Request $request): JsonResponse
    {
        return $this->accountVerifyLogin($request, 'email');
    }

    public function updateEmailSendLink(Request $request): JsonResponse
    {
        $email = $this->input($request, 'email');
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->error('Invalid email address');

        $token = $this->flowGet('email', 'token');
        if (!$token) return $this->error('Please login first');

        $result = $this->identitySvc->updateEmailSendLink($token['x_token'], $email);
        if (!$result['success']) return $this->error($result['error'] ?? 'Failed to send verification link');

        $this->flowClear('email');
        Log::info('ABHA email verification link requested', ['user_id' => CurrentUser::id()]);
        return response()->json(['success' => true, 'message' => 'Verification link sent to email']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA card download
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadAbhaCard(Request $request): JsonResponse
    {
        $xToken = $this->input($request, 'x_token');
        if ($xToken === '') {
            return $this->error('No verified identity to download a card for - please verify an ABHA first');
        }

        $issued = array_filter((array) session('abdm_identity_x_tokens', []), 'is_string');
        $known  = false;
        foreach ($issued as $t) {
            if (hash_equals($t, $xToken)) { $known = true; break; }
        }
        if (!$known) {
            // Not ABDM's doing: this browser session never received (or has lost) the token. Say so, so it can be told
            // apart from ABDM's own 401 (same wording in the service). The token itself is never logged.
            Log::warning('ABDM ABHA card download refused: X-token not issued to this session', [
                'issued_to_session' => count($issued),
            ]);
            return $this->error('This verification is no longer held by this login session (expired) - please verify the ABHA again, then download the card.');
        }

        return response()->json($this->identitySvc->downloadAbhaCard($xToken));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Save the ABDM KYC profile photo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Stores the base64 photo ABDM returned as a server-named file under
     * the patients folder of the `uploads` disk (see App\Support\Uploads) and returns the bare
     * filename for patients.photo. If `patient_id` is supplied (edit mode) the column is set directly.
     */
    public function saveProfilePhoto(Request $request): JsonResponse
    {
        $photoBase64 = $request->input('photo_base64', '');
        if (!is_string($photoBase64) || $photoBase64 === '') return $this->error('No photo data provided');

        // ABDM KYC photos are a few tens of KB; reject absurd payloads before decoding.
        if (strlen($photoBase64) > self::MAX_PHOTO_BASE64_BYTES) return $this->error('Photo data too large');

        $binary = base64_decode($photoBase64, true);
        if ($binary === false || $binary === '') return $this->error('Invalid photo data');

        // Must be a real raster JPEG/PNG - never write an arbitrary payload (PHP/HTML) to a public dir.
        $imageInfo = @getimagesizefromstring($binary);
        $allowed   = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
        if ($imageInfo === false || !isset($allowed[$imageInfo[2]])) {
            return $this->error('Photo data is not a valid JPEG/PNG image');
        }


        // Entirely server-generated name (never derived from input): no traversal/injection risk.
        $filename = 'abdm_' . bin2hex(random_bytes(7)) . '.' . $allowed[$imageInfo[2]];
        if (!Uploads::put(Uploads::PATIENTS, $filename, $binary)) {
            return $this->error('Failed to save photo');
        }

        $patientId = (int) $request->input('patient_id', 0);
        if ($patientId > 0) {
            Patient::query()->whereKey($patientId)->update(['photo' => $filename]);
        }

        return response()->json(['success' => true, 'file' => $filename]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New vs returning patient / duplicate identity check
    // ─────────────────────────────────────────────────────────────────────────

    public function checkIdentityMatch(Request $request): JsonResponse
    {
        $result = $this->identitySvc->checkIdentityMatch([
            'mobile'         => $this->input($request, 'mobile'),
            'name'           => $this->input($request, 'name'),
            'gender'         => $this->input($request, 'gender'),
            'date_of_birth'  => $this->input($request, 'date_of_birth'),
            'abha_number'    => $this->input($request, 'abha_number'),
            'abha_address'   => $this->input($request, 'abha_address'),
            'aadhaar_number' => $this->input($request, 'aadhaar_number'),
        ], max(0, (int) $request->input('patient_id', 0)));

        return response()->json($result);
    }
}
