<?php

namespace App\Services\Abdm;

use App\Models\Patient;
use App\Services\AbdmTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ABDM M1 identity flows: Aadhaar OTP enrolment, ABHA / Aadhaar / mobile login OTP,
 * ABHA card, and the "new vs returning patient" identity match.
 * Ported from bmhabdm/abdm/{aadhaar,abha_login,abha_card,common,identity_duplicate_check,
 * patient_matching}.php.
 *
 * Transaction state (txnId between send/verify OTP calls) is managed by the calling
 * controller in the Laravel session, NOT here. Nothing in this class logs Aadhaar numbers,
 * OTPs, request bodies or tokens - only endpoint path + error text.
 */
class AbdmIdentityService
{
    public function __construct(
        private AbdmTokenService $tokenSvc,
        private AbdmEncryption   $encryption,
    ) {}

    private function url(string $path): string
    {
        return rtrim((string) config('abdm.base_url', 'https://abhasbx.abdm.gov.in/abha/api'), '/') . $path;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP helpers (legacy abdm_m1_post_helper / abdm_m1_get_helper)
    // ─────────────────────────────────────────────────────────────────────────

    private function baseHeaders(): array
    {
        return [
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP'  => AbdmTokenService::timestamp(),
            'X-CM-ID'    => (string) config('abdm.cm_id', 'sbx'),
        ];
    }

    /** @return array{success:bool, http_code:int, response:mixed, error?:string} */
    private function m1Post(string $path, array $payload, string $accessToken, array $extraHeaders = []): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'] + $this->baseHeaders() + $extraHeaders)
                ->timeout(30)
                ->post($this->url($path), $payload);
        } catch (\Exception $e) {
            Log::error('ABDM M1 POST failed', ['endpoint' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'http_code' => 0, 'response' => null, 'error' => 'Could not reach ABDM: ' . $e->getMessage()];
        }

        return $this->wrap($response);
    }

    /** @return array{success:bool, http_code:int, response:mixed, error?:string} */
    private function m1Get(string $path, string $accessToken, array $extraHeaders = []): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders($this->baseHeaders() + $extraHeaders)
                ->timeout(30)
                ->get($this->url($path));
        } catch (\Exception $e) {
            Log::error('ABDM M1 GET failed', ['endpoint' => $path, 'error' => $e->getMessage()]);
            return ['success' => false, 'http_code' => 0, 'response' => null, 'error' => 'Could not reach ABDM: ' . $e->getMessage()];
        }

        return $this->wrap($response);
    }

    private function wrap($response): array
    {
        $decoded = $response->json();
        $result  = [
            'success'   => $response->status() >= 200 && $response->status() < 300,
            'http_code' => $response->status(),
            'response'  => $decoded ?? $response->body(),
        ];
        if (!$result['success']) {
            $result['error'] = $this->extractError($result);
        }
        return $result;
    }

    /**
     * Legacy abdm_extract_error_message(): ABDM's actual error code + message, verbatim.
     * @param array{response?:mixed, http_code?:int, error?:string} $result
     */
    private function extractError(array $result): string
    {
        $response = $result['response'] ?? null;

        // Some ABDM errors are a top-level array of error objects.
        if (is_array($response) && array_is_list($response) && isset($response[0]) && is_array($response[0])) {
            $response = $response[0];
        }

        if (is_array($response)) {
            $errorObj = (isset($response['error']) && is_array($response['error'])) ? $response['error'] : $response;
            $msg  = $errorObj['message'] ?? $response['message'] ?? ($response['details'][0]['message'] ?? null);
            $code = $errorObj['code'] ?? $response['code'] ?? null;

            if (is_string($msg) && $msg !== '') {
                $codeText = is_string($code) ? rtrim(trim($code), ':') : '';
                return $codeText !== '' ? ($codeText . ': ' . $msg) : $msg;
            }

            // Field-level validation errors, e.g. {"otpValue":"Invalid OTP Value","timestamp":"..."}.
            foreach ($response as $key => $value) {
                if (in_array($key, ['timestamp', 'txnId', 'requestId'], true)) continue;
                if (is_string($value) && $value !== '') return $value;
            }
        }

        // Plain-text body (e.g. "Access Denied" for bad headers).
        if (is_string($response) && trim($response) !== '') {
            return trim($response) . ' (HTTP ' . ($result['http_code'] ?? '?') . ')';
        }

        return 'ABDM request failed (HTTP ' . ($result['http_code'] ?? '?') . ') with no response body';
    }

    private function fail(array $result): array
    {
        return ['success' => false, 'error' => $result['error'] ?? $this->extractError($result)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Aadhaar OTP enrolment (ABHA creation) - legacy aadhaar.php
    // ─────────────────────────────────────────────────────────────────────────

    /** Step 1: validate Aadhaar, ask ABDM/UIDAI to send an OTP to the linked mobile. */
    public function aadhaarSendOtp(string $aadhaarNumber): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber);
        if (!$this->isValidAadhaarChecksum($aadhaar)) {
            return ['success' => false, 'error' => 'Invalid Aadhaar number'];
        }

        $enc = $this->encryption->encrypt($aadhaar);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/enrollment/request/otp', [
            'txnId'     => '',
            'scope'     => ['abha-enrol'],
            'loginHint' => 'aadhaar',
            'loginId'   => $enc['encrypted'],
            'otpSystem' => 'aadhaar',
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $txnId = is_array($result['response']) ? ($result['response']['txnId'] ?? null) : null;
        if (!$txnId) return ['success' => false, 'error' => 'ABDM did not return a transaction ID'];

        return [
            'success' => true,
            'txn_id'  => $txnId,
            'message' => $result['response']['message'] ?? 'OTP sent to the mobile number linked with this Aadhaar',
        ];
    }

    /** Step 2: verify the OTP and create (or fetch) the ABHA - POST /v3/enrollment/enrol/byAadhaar. */
    public function aadhaarVerifyOtp(string $txnId, string $otp, string $mobile): array
    {
        $mobileDigits = preg_replace('/\D/', '', $mobile);
        if (strlen($mobileDigits) !== 10) {
            return ['success' => false, 'error' => 'A valid 10-digit mobile number is required by ABDM to create the ABHA'];
        }

        $enc = $this->encryption->encrypt($otp);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/enrollment/enrol/byAadhaar', [
            'authData' => [
                'authMethods' => ['otp'],
                'otp'         => ['txnId' => $txnId, 'otpValue' => $enc['encrypted'], 'mobile' => $mobileDigits],
            ],
            'consent' => ['code' => 'abha-enrollment', 'version' => '1.4'],
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $response = is_array($result['response']) ? $result['response'] : [];
        // "isNew" only comes back after the OTP is verified; passed through so staff can tell
        // a freshly created ABHA from an already-existing one.
        $isNew = array_key_exists('isNew', $response) ? (bool) $response['isNew'] : null;

        return array_merge(
            ['success' => true, 'is_new' => $isNew, 'message' => $response['message'] ?? null],
            $this->parseAbhaProfile($response)
        );
    }

    /** Step 3: ABDM's suggested ABHA addresses - GET /v3/enrollment/enrol/suggestion (Transaction_Id header). */
    public function getAbhaAddressSuggestions(string $txnId): array
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to get ABDM access token'];

        $result = $this->m1Get('/v3/enrollment/enrol/suggestion', $token, ['Transaction_Id' => $txnId]);
        if (!$result['success']) return $this->fail($result);

        $response    = is_array($result['response']) ? $result['response'] : [];
        $suggestions = $response['abhaAddressList'] ?? [];

        return [
            'success'     => true,
            'suggestions' => is_array($suggestions) ? array_values($suggestions) : [],
            'txn_id'      => $response['txnId'] ?? $txnId,
        ];
    }

    /** Step 4: create + mark preferred a chosen ABHA address - POST /v3/enrollment/enrol/abha-address. */
    public function createAbhaAddress(string $txnId, string $abhaAddress): array
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to get ABDM access token'];

        $result = $this->m1Post('/v3/enrollment/enrol/abha-address', [
            'txnId' => $txnId, 'abhaAddress' => $abhaAddress, 'preferred' => 1,
        ], $token);

        if (!$result['success']) return $this->fail($result);

        $response = is_array($result['response']) ? $result['response'] : [];
        return ['success' => true, 'abha_address' => $response['preferredAbhaAddress'] ?? $abhaAddress];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA login OTP (ABHA Number / ABHA Address) - legacy abha_login.php
    // ─────────────────────────────────────────────────────────────────────────

    public function abhaLoginSendOtp(string $loginId): array
    {
        $loginId   = trim($loginId);
        $isAddress = str_contains($loginId, '@');
        $loginHint = $isAddress ? 'abha-address' : 'abha-number';
        $scope     = $isAddress ? ['abha-address-login', 'mobile-verify'] : ['abha-login', 'mobile-verify'];
        $path      = $isAddress ? '/v3/phr/web/login/abha/request/otp' : '/v3/profile/login/request/otp';

        if (!$isAddress) {
            // ABDM rejects a digits-only loginId ("LoginId is invalid"): send 91-XXXX-XXXX-XXXX.
            $digits = preg_replace('/\D/', '', $loginId);
            if (strlen($digits) !== 14) return ['success' => false, 'error' => 'ABHA Number must be 14 digits'];
            $loginId = substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4);
        }

        $enc = $this->encryption->encrypt($loginId);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post($path, [
            'scope' => $scope, 'loginHint' => $loginHint, 'loginId' => $enc['encrypted'], 'otpSystem' => 'abdm',
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $txnId = is_array($result['response']) ? ($result['response']['txnId'] ?? null) : null;
        if (!$txnId) return ['success' => false, 'error' => 'ABDM did not return a transaction ID'];

        return [
            'success'    => true,
            'txn_id'     => $txnId,
            'scope'      => $scope,
            'is_address' => $isAddress,
            'message'    => $result['response']['message'] ?? 'OTP sent to the mobile number linked with this ABHA',
        ];
    }

    /** Verify OTP and fetch the profile; $scope must be exactly what abhaLoginSendOtp() returned. */
    public function abhaLoginVerifyOtp(string $txnId, string $otp, array $scope): array
    {
        $isAddress = in_array('abha-address-login', $scope, true);
        $path      = $isAddress ? '/v3/phr/web/login/abha/verify' : '/v3/profile/login/verify';

        $result = $this->verifyLogin($path, $txnId, $otp, $scope, true);
        unset($result['_accounts']); // raw accounts (with tokens) stay server-side
        return $result;
    }

    /** "Login via Aadhaar Number": loginId is the encrypted Aadhaar itself, scope abha-login + aadhaar-verify. */
    public function aadhaarLoginSendOtp(string $aadhaarNumber): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber);
        if (strlen($aadhaar) !== 12) {
            return ['success' => false, 'error' => 'Please enter a valid 12-digit Aadhaar number'];
        }

        $enc = $this->encryption->encrypt($aadhaar);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $scope  = ['abha-login', 'aadhaar-verify'];
        $result = $this->m1Post('/v3/profile/login/request/otp', [
            'scope' => $scope, 'loginHint' => 'aadhaar', 'loginId' => $enc['encrypted'], 'otpSystem' => 'aadhaar',
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $txnId = is_array($result['response']) ? ($result['response']['txnId'] ?? null) : null;
        if (!$txnId) return ['success' => false, 'error' => 'ABDM did not return a transaction ID'];

        return [
            'success' => true,
            'txn_id'  => $txnId,
            'scope'   => $scope,
            'message' => $result['response']['message'] ?? 'OTP sent to the Aadhaar-registered mobile number',
        ];
    }

    public function aadhaarLoginVerifyOtp(string $txnId, string $otp): array
    {
        $result = $this->verifyLogin('/v3/profile/login/verify', $txnId, $otp, ['abha-login', 'aadhaar-verify'], false);
        if (!$result['success']) return $result;

        // "Forgot ABHA" view: every ABHA linked to this Aadhaar (legacy abha_account_ajax.php format).
        $result['abha_numbers'] = $this->summariseAccounts($result['_accounts'] ?? []);
        unset($result['_accounts']);
        return $result;
    }

    private function summariseAccounts(array $accounts): array
    {
        $out = [];
        foreach ($accounts as $a) {
            if (!is_array($a)) continue;
            $name = $a['name'] ?? '';
            $suffix = is_string($name) && $name !== '' ? " ($name)" : '';
            if (!empty($a['ABHANumber'])) $out[] = $a['ABHANumber'] . $suffix;
            if (!empty($a['preferredAbhaAddress'])) $out[] = $a['preferredAbhaAddress'] . $suffix;
        }
        return $out;
    }

    private function verifyLogin(string $path, string $txnId, string $otp, array $scope, bool $withUsers): array
    {
        $enc = $this->encryption->encrypt($otp);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post($path, [
            'scope'    => $scope,
            'authData' => ['authMethods' => ['otp'], 'otp' => ['txnId' => $txnId, 'otpValue' => $enc['encrypted']]],
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $response = is_array($result['response']) ? $result['response'] : [];
        $xToken   = $response['tokens']['token']
            ?? $response['token']
            ?? $response['accounts'][0]['tokens']['token']
            ?? ($withUsers ? ($response['users'][0]['tokens']['token'] ?? null) : null);

        if (!$xToken) {
            // HTTP 2xx with an error-shaped body (e.g. invalid/expired OTP): surface ABDM's real message.
            if (isset($response['message']) || isset($response['error'])) {
                return ['success' => false, 'error' => $this->extractError(['response' => $response, 'http_code' => $result['http_code']])];
            }
            return ['success' => false, 'error' => 'ABDM did not return an access token for this ABHA (response keys: ' . implode(', ', array_keys($response)) . ')'];
        }

        $profile = $this->parseAbhaProfile($response);

        // verify only returns name/ABHANumber/address; fill ONLY the empty fields from the full profile.
        $missing = ['gender', 'year_of_birth', 'date_of_birth', 'address', 'photo', 'mobile', 'mobile_verified'];
        $needsEnrichment = false;
        foreach ($missing as $field) {
            if (empty($profile[$field])) { $needsEnrichment = true; break; }
        }
        if ($needsEnrichment) {
            $full = $this->getAbhaProfile($xToken);
            if ($full['success']) {
                foreach ($missing as $field) {
                    if (empty($profile[$field]) && !empty($full[$field])) $profile[$field] = $full[$field];
                }
            }
            // A failed enrichment never fails the (already verified) login.
        }

        $out = array_merge(['success' => true, 'x_token' => $xToken], $profile);

        $out['_accounts'] = $response['accounts'] ?? [];
        return $out;
    }

    /** GET /v3/profile/account/profile with the X-token from a verify call. */
    public function getAbhaProfile(string $xToken): array
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to get ABDM access token'];

        $result = $this->m1Get('/v3/profile/account/profile', $token, ['X-token' => 'Bearer ' . $xToken]);
        if (!$result['success']) return $this->fail($result);

        return array_merge(['success' => true], $this->parseAbhaProfile(is_array($result['response']) ? $result['response'] : []));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mobile login (all ABHAs linked to a mobile number)
    // ─────────────────────────────────────────────────────────────────────────

    public function mobileSendOtp(string $mobileNumber): array
    {
        $digits = preg_replace('/\D/', '', $mobileNumber);
        if (strlen($digits) !== 10) return ['success' => false, 'error' => 'Mobile number must be 10 digits'];

        $scope = ['abha-login', 'mobile-verify'];
        $enc   = $this->encryption->encrypt($digits);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/profile/login/request/otp', [
            'scope' => $scope, 'loginHint' => 'mobile', 'loginId' => $enc['encrypted'], 'otpSystem' => 'abdm',
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $txnId = is_array($result['response']) ? ($result['response']['txnId'] ?? null) : null;
        if (!$txnId) return ['success' => false, 'error' => 'ABDM did not return a transaction ID'];

        return ['success' => true, 'txn_id' => $txnId, 'scope' => $scope, 'message' => $result['response']['message'] ?? 'OTP sent to this mobile number'];
    }

    public function mobileVerifyOtp(string $txnId, string $otp, array $scope): array
    {
        $enc = $this->encryption->encrypt($otp);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/profile/login/verify', [
            'scope'    => $scope,
            'authData' => ['authMethods' => ['otp'], 'otp' => ['txnId' => $txnId, 'otpValue' => $enc['encrypted']]],
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $response    = is_array($result['response']) ? $result['response'] : [];
        $accountsRaw = $response['accounts'] ?? $response['users'] ?? [];
        $accounts    = [];
        foreach ($accountsRaw as $a) {
            if (!is_array($a)) continue;
            $dob = $a['dob'] ?? null;
            $accounts[] = [
                'abha_number'     => $a['ABHANumber'] ?? $a['abhaNumber'] ?? null,
                'abha_address'    => $a['preferredAbhaAddress'] ?? $a['abhaAddress'] ?? null,
                'name'            => $a['name'] ?? $a['fullName'] ?? null,
                'gender'          => $a['gender'] ?? null,
                'year_of_birth'   => $this->extractYearFromDob(is_string($dob) ? $dob : null),
                'date_of_birth'   => $this->parseDobToDate(is_string($dob) ? $dob : null),
                'status'          => $a['status'] ?? null,
                'kyc_verified'    => !empty($a['kycVerified']),
                'mobile_verified' => !empty($a['mobileVerified']),
                'profile_photo'   => (!empty($a['profilePhoto']) && is_string($a['profilePhoto'])) ? $a['profilePhoto'] : null,
                'x_token'         => $a['tokens']['token'] ?? null,
            ];
        }

        if (empty($accounts)) {
            // HTTP 200 + authResult=failed / a message / no accounts key = genuine ABDM error (wrong OTP).
            $authFailed = isset($response['authResult']) && strtolower((string) $response['authResult']) === 'failed';
            $hasMessage = !empty($response['message']);
            if ($authFailed || $hasMessage || (!array_key_exists('accounts', $response) && !array_key_exists('users', $response))) {
                return ['success' => false, 'error' => $this->extractError(['response' => $response, 'http_code' => $result['http_code']])];
            }
            return ['success' => false, 'error' => 'No ABHA accounts found linked to this mobile number'];
        }

        return ['success' => true, 'accounts' => $accounts];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA account services: update mobile / update email - legacy abha_account_ajax.php
    // (the abdm_update_mobile_* / abdm_update_email_send_link helpers). Login step reuses
    // abhaLoginSendOtp(); the calls below are made with the session-bound X-token.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify the login OTP and return ONLY the X-token (legacy abdm_abha_login_verify_otp() in
     * abha_account_ajax.php - no profile enrichment call, no profile data returned).
     *
     * @return array{success:bool, x_token?:string, error?:string}
     */
    public function accountLoginVerifyOtp(string $txnId, string $otp, array $scope): array
    {
        $isAddress = in_array('abha-address-login', $scope, true);
        $path      = $isAddress ? '/v3/phr/web/login/abha/verify' : '/v3/profile/login/verify';

        $enc = $this->encryption->encrypt($otp);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post($path, [
            'scope'    => $scope,
            'authData' => ['authMethods' => ['otp'], 'otp' => ['txnId' => $txnId, 'otpValue' => $enc['encrypted']]],
        ], $enc['token']);

        if (!$result['success']) return $this->fail($result);

        $response = is_array($result['response']) ? $result['response'] : [];
        $xToken   = $response['tokens']['token']
            ?? $response['token']
            ?? $response['accounts'][0]['tokens']['token']
            ?? $response['users'][0]['tokens']['token']
            ?? null;

        if (!is_string($xToken) || $xToken === '') {
            // HTTP 2xx with an error-shaped body (e.g. invalid/expired OTP): surface ABDM's real message.
            if (isset($response['message']) || isset($response['error'])) {
                return ['success' => false, 'error' => $this->extractError(['response' => $response, 'http_code' => $result['http_code']])];
            }
            return ['success' => false, 'error' => 'ABDM did not return an access token'];
        }

        return ['success' => true, 'x_token' => $xToken];
    }

    /** POST /v3/profile/account/request/otp - OTP to the NEW mobile (scope abha-profile + mobile-verify). */
    public function updateMobileSendOtp(string $xToken, string $mobile): array
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen($digits) !== 10) return ['success' => false, 'error' => 'Invalid mobile number'];

        $enc = $this->encryption->encrypt($digits);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/profile/account/request/otp', [
            'scope' => ['abha-profile', 'mobile-verify'], 'loginHint' => 'mobile', 'loginId' => $enc['encrypted'], 'otpSystem' => 'abdm',
        ], $enc['token'], ['X-token' => 'Bearer ' . $xToken]);

        if (!$result['success']) return $this->fail($result);

        $txnId = is_array($result['response']) ? ($result['response']['txnId'] ?? null) : null;
        if (!is_string($txnId) || $txnId === '') return ['success' => false, 'error' => 'ABDM did not return a transaction ID'];

        return ['success' => true, 'txn_id' => $txnId];
    }

    /** POST /v3/profile/account/verify - confirms the OTP sent to the new mobile and updates the ABHA profile. */
    public function updateMobileVerifyOtp(string $xToken, string $txnId, string $otp): array
    {
        $enc = $this->encryption->encrypt($otp);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/profile/account/verify', [
            'scope'    => ['abha-profile', 'mobile-verify'],
            'authData' => ['authMethods' => ['otp'], 'otp' => ['txnId' => $txnId, 'otpValue' => $enc['encrypted']]],
        ], $enc['token'], ['X-token' => 'Bearer ' . $xToken]);

        if (!$result['success']) return $this->fail($result);

        // Legacy treats any 2xx as success; ABDM also answers 200 + authResult=failed for a wrong OTP.
        $response = is_array($result['response']) ? $result['response'] : [];
        if (isset($response['authResult']) && is_string($response['authResult']) && strtolower($response['authResult']) !== 'success') {
            return ['success' => false, 'error' => $this->extractError(['response' => $response, 'http_code' => $result['http_code']])];
        }

        return ['success' => true];
    }

    /** POST /v3/profile/account/request/emailVerificationLink - ABDM emails a confirmation link to the new address. */
    public function updateEmailSendLink(string $xToken, string $email): array
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) return ['success' => false, 'error' => 'Invalid email address'];

        $enc = $this->encryption->encrypt($email);
        if (!$enc['success']) return ['success' => false, 'error' => $enc['error'] ?? 'Encryption failed'];

        $result = $this->m1Post('/v3/profile/account/request/emailVerificationLink', [
            'scope' => ['abha-profile', 'email-link-verify'], 'loginHint' => 'email', 'loginId' => $enc['encrypted'], 'otpSystem' => 'abdm',
        ], $enc['token'], ['X-token' => 'Bearer ' . $xToken]);

        if (!$result['success']) return $this->fail($result);

        return ['success' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ABHA card download - legacy abha_card.php
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{success:bool, content_type?:string, data?:string, error?:string} data = base64 PNG bytes */
    public function downloadAbhaCard(string $xToken): array
    {
        $xToken = trim($xToken);
        if ($xToken === '') {
            return ['success' => false, 'error' => 'No verified identity to download a card for - please verify an ABHA first'];
        }

        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to get ABDM access token'];

        try {
            $response = Http::withHeaders([
                'Accept'        => '*/*',
                'REQUEST-ID'    => (string) Str::uuid(),
                'TIMESTAMP'     => AbdmTokenService::timestamp(),
                'Authorization' => 'Bearer ' . $token,
                'X-token'       => 'Bearer ' . $xToken,
            ])->timeout(30)->get($this->url('/v3/profile/account/abha-card'));
        } catch (\Exception $e) {
            Log::error('ABDM ABHA card download failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Could not reach ABDM: ' . $e->getMessage()];
        }

        $status = $response->status();
        if ($status < 200 || $status >= 300) {
            // Record ABDM's own reason (never the tokens) - a 401 here means ABDM rejected the X-token, as opposed to
            // this app not recognising it (see AbdmIdentityController::downloadAbhaCard).
            Log::warning('ABDM ABHA card download rejected by ABDM', [
                'http_code' => $status,
                'error'     => $this->extractError(['response' => $response->json() ?? $response->body(), 'http_code' => $status]),
            ]);
            return [
                'success' => false,
                'error'   => $status === 401
                    ? 'ABDM says this verified session has expired - please verify the ABHA again to download the card.'
                    : $this->extractError(['response' => $response->json() ?? $response->body(), 'http_code' => $status]),
            ];
        }

        $contentType = (string) $response->header('Content-Type');

        // Case 1: raw binary image straight from the gateway.
        if (stripos($contentType, 'image') !== false) {
            return ['success' => true, 'content_type' => $contentType, 'data' => base64_encode($response->body())];
        }

        // Case 2: JSON-wrapped base64 - key name varies across sandbox builds.
        $decoded = $response->json();
        if (is_array($decoded)) {
            $card = $decoded['ABHA Card'] ?? $decoded['data'] ?? $decoded['abhaCard'] ?? $decoded['card'] ?? $decoded['png'] ?? null;
            if (is_string($card) && $card !== '') {
                return ['success' => true, 'content_type' => 'image/png', 'data' => $card];
            }
        }

        return ['success' => false, 'error' => 'ABDM returned the ABHA card in an unrecognised format'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Profile parsing - legacy abdm_parse_abha_profile() & DOB helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function parseAbhaProfile(array $response): array
    {
        $profile = $response['accounts'][0] ?? $response['users'][0] ?? $response['ABHAProfile'] ?? $response;
        if (!is_array($profile)) $profile = [];

        $abhaNumber  = $profile['ABHANumber'] ?? $profile['abhaNumber'] ?? $profile['healthIdNumber'] ?? null;
        $abhaAddress = $profile['preferredAbhaAddress'] ?? $profile['phrAddress'] ?? $profile['abhaAddress'] ?? null;
        if (is_array($abhaAddress)) $abhaAddress = $abhaAddress[0] ?? null;

        $xToken = $response['tokens']['token'] ?? $response['token'] ?? $profile['tokens']['token'] ?? null;

        $name = $profile['name'] ?? $profile['fullName'] ?? null;
        if (!$name && (isset($profile['firstName']) || isset($profile['lastName']))) {
            $name = preg_replace('/\s+/', ' ', trim(($profile['firstName'] ?? '') . ' ' . ($profile['middleName'] ?? '') . ' ' . ($profile['lastName'] ?? '')));
        }

        // Full DOB only when day+month+year are all known - never guessed from a year alone.
        if (!empty($profile['dob'])) {
            $dateOfBirth = $this->parseDobToDate((string) $profile['dob']);
        } elseif (!empty($profile['dateOfBirth'])) {
            $dateOfBirth = $this->parseDobToDate((string) $profile['dateOfBirth']);
        } elseif (!empty($profile['dayOfBirth']) && !empty($profile['monthOfBirth']) && !empty($profile['yearOfBirth'])) {
            $dateOfBirth = $this->parseDobToDate(sprintf('%02d-%02d-%04d', (int) $profile['dayOfBirth'], (int) $profile['monthOfBirth'], (int) $profile['yearOfBirth']));
        } else {
            $dateOfBirth = null;
        }

        if ($dateOfBirth) {
            $yearOfBirth = substr($dateOfBirth, 0, 4);
        } elseif (!empty($profile['yearOfBirth']) && preg_match('/^\d{4}$/', (string) $profile['yearOfBirth'])) {
            $yearOfBirth = (string) $profile['yearOfBirth'];
        } elseif (!empty($profile['dob'])) {
            $yearOfBirth = $this->extractYearFromDob((string) $profile['dob']);
        } else {
            $yearOfBirth = null;
        }

        // KYC photo: "photo" (enrol), "profilePhoto" (login/mobile), "kycPhoto" (full profile); plain base64 JPEG.
        $photoRaw = $profile['photo'] ?? $profile['profilePhoto'] ?? $profile['kycPhoto'] ?? null;
        $photo    = (!empty($photoRaw) && is_string($photoRaw)) ? $photoRaw : null;

        $mobile         = (!empty($profile['mobile']) && is_string($profile['mobile'])) ? preg_replace('/\D/', '', $profile['mobile']) : null;
        $mobileVerified = isset($profile['mobileVerified']) ? (bool) $profile['mobileVerified'] : null;

        return [
            'abha_number'     => $abhaNumber,
            'abha_address'    => $abhaAddress,
            'name'            => $name,
            'gender'          => $profile['gender'] ?? null,
            'year_of_birth'   => $yearOfBirth,
            'date_of_birth'   => $dateOfBirth,
            'address'         => $profile['address'] ?? null,
            'photo'           => $photo,
            'mobile'          => $mobile,
            'mobile_verified' => $mobileVerified,
            'x_token'         => $xToken,
            'txn_id'          => $response['txnId'] ?? null,
        ];
    }

    private function extractYearFromDob(?string $dob): ?string
    {
        if (empty($dob)) return null;
        if (preg_match('/^\d{2}-\d{2}-(\d{4})$/', $dob, $m)) return $m[1];
        if (preg_match('/^(\d{4})-\d{2}-\d{2}/', $dob, $m)) return $m[1];
        if (preg_match('/^\d{4}$/', $dob)) return $dob;
        return null;
    }

    /** "DD-MM-YYYY" or "YYYY-MM-DD[...]" -> valid, non-future "YYYY-MM-DD"; otherwise null. */
    private function parseDobToDate(?string $dob): ?string
    {
        if (empty($dob)) return null;

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dob, $m)) {
            [, $day, $month, $year] = $m;
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $m)) {
            [, $year, $month, $day] = $m;
        } else {
            return null;
        }

        if (!checkdate((int) $month, (int) $day, (int) $year)) return null;

        $date = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        return $date > gmdate('Y-m-d') ? null : $date;
    }

    private function isValidAadhaarChecksum(string $aadhaar): bool
    {
        if (!preg_match('/^\d{12}$/', $aadhaar)) return false;
        static $d = [[0,1,2,3,4,5,6,7,8,9],[1,2,3,4,0,6,7,8,9,5],[2,3,4,0,1,7,8,9,5,6],[3,4,0,1,2,8,9,5,6,7],[4,0,1,2,3,9,5,6,7,8],[5,9,8,7,6,0,4,3,2,1],[6,5,9,8,7,1,0,4,3,2],[7,6,5,9,8,2,1,0,4,3],[8,7,6,5,9,3,2,1,0,4],[9,8,7,6,5,4,3,2,1,0]];
        static $p = [[0,1,2,3,4,5,6,7,8,9],[1,5,7,6,2,8,3,0,9,4],[5,8,0,3,7,9,6,1,4,2],[8,9,1,6,0,4,3,5,2,7],[9,4,5,3,1,2,6,8,7,0],[4,2,8,6,5,7,3,9,0,1],[2,7,9,3,8,0,6,4,1,5],[7,0,4,6,9,1,3,2,5,8]];
        $c = 0;
        foreach (array_reverse(str_split($aadhaar)) as $i => $digit) $c = $d[$c][$p[$i % 8][(int) $digit]];
        return $c === 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // New vs returning patient - legacy identity_duplicate_check.php + patient_matching.php
    // ─────────────────────────────────────────────────────────────────────────

    /** Masks an Aadhaar to its last 4 digits - the only form that may ever be compared/stored. */
    public function maskAadhaar(string $aadhaar): string
    {
        $digits = preg_replace('/\D/', '', $aadhaar);
        return 'XXXX XXXX ' . substr($digits, -4);
    }

    /**
     * Another patient already holding this ABHA Number / ABHA Address / (masked) Aadhaar?
     * Blank values are skipped; $excludePatientId is the patient being edited.
     * @return array{field:string,label:string,patient_id:int,patient_name:string}|null
     */
    public function findIdentityConflict(string $aadhaar, string $abhaNumber, string $abhaAddress, ?int $excludePatientId = null): ?array
    {
        $aadhaar = trim($aadhaar);
        if (preg_match('/^\d{12}$/', preg_replace('/[\s-]/', '', $aadhaar))) {
            $aadhaar = $this->maskAadhaar($aadhaar); // a raw Aadhaar is never queried/compared
        }

        $abhaNumber = trim($abhaNumber);
        $numberValues = [$abhaNumber];
        $digits = preg_replace('/\D/', '', $abhaNumber);
        if (strlen($digits) === 14) {
            // Stored canonical form is 91-XXXX-XXXX-XXXX; tolerate the digits-only spelling too.
            $numberValues = [substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4), $digits];
        }

        $checks = [
            ['abha_number',    $abhaNumber === '' ? [] : $numberValues, 'ABHA Number'],
            ['abha_address',   trim($abhaAddress) === '' ? [] : [trim($abhaAddress)], 'ABHA Address'],
            ['aadhaar_number', $aadhaar === '' ? [] : [$aadhaar], 'Aadhaar number'],
        ];

        foreach ($checks as [$column, $values, $label]) {
            if (!$values) continue;
            $query = Patient::query()->whereIn($column, $values);
            if ($excludePatientId) $query->where('id', '!=', $excludePatientId);
            $row = $query->first(['id', 'patient_name']);
            if ($row) {
                return ['field' => $column, 'label' => $label, 'patient_id' => (int) $row->id, 'patient_name' => (string) $row->patient_name];
            }
        }

        return null;
    }

    public function ageFromDob(?string $dob): ?int
    {
        if (empty($dob) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dob, $m)) return null;
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) return null;

        $birth = new \DateTime("{$m[1]}-{$m[2]}-{$m[3]}");
        $today = new \DateTime('today');
        return $birth > $today ? null : $today->diff($birth)->y;
    }

    /** "Male"/"M"/"male" all -> "M"; blank stays blank. */
    public function normaliseGender(?string $gender): string
    {
        $gender = strtoupper(trim((string) $gender));
        return $gender === '' ? '' : $gender[0];
    }

    public function namesMatch(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') return false;

        similar_text(strtolower($a), strtolower($b), $percent);
        return soundex($a) === soundex($b) || $percent >= 70;
    }

    /**
     * ABDM rule: mobile exact AND gender exact AND age within +/-2y AND name close phonetic match.
     * @param array{mobile?:?string,gender?:?string,age?:?int,name?:?string} $incoming
     * @param array{mobile?:?string,gender?:?string,age?:?int,name?:?string} $existing
     */
    public function demographicsMatch(array $incoming, array $existing): bool
    {
        $inMobile = preg_replace('/\D/', '', (string) ($incoming['mobile'] ?? ''));
        $exMobile = preg_replace('/\D/', '', (string) ($existing['mobile'] ?? ''));
        if ($inMobile === '' || $inMobile !== $exMobile) return false;

        $inGender = $this->normaliseGender($incoming['gender'] ?? null);
        if ($inGender === '' || $inGender !== $this->normaliseGender($existing['gender'] ?? null)) return false;

        $inAge = $incoming['age'] ?? null;
        $exAge = $existing['age'] ?? null;
        if ($inAge === null || $exAge === null || abs((int) $inAge - (int) $exAge) > 2) return false;

        return $this->namesMatch((string) ($incoming['name'] ?? ''), (string) ($existing['name'] ?? ''));
    }

    private function patientDemographics(Patient $p): array
    {
        $age = !empty($p->date_of_birth) ? $this->ageFromDob((string) $p->date_of_birth) : null;
        return [
            'mobile' => $p->mobile_number,
            'gender' => $p->gender,
            'age'    => $age ?? (is_numeric($p->age) ? (int) $p->age : null),
            'name'   => $p->patient_name,
        ];
    }

    private function matchPayload(Patient $p, string $reason, ?string $field = null): array
    {
        return [
            'id'            => (int) $p->id,
            'patient_name'  => $p->patient_name,
            'mobile_number' => $p->mobile_number,
            'gender'        => $p->gender,
            'status'        => $p->status,
            'uhid'          => $p->uhid,
            'match_reason'  => $reason,
            'matched_field' => $field,
        ];
    }

    /**
     * Legacy identity_ajax.php "check_identity_match". Never blocks saving - the caller decides.
     *
     * @param array{mobile?:string,name?:string,gender?:string,date_of_birth?:string,abha_number?:string,
     *              abha_address?:string,aadhaar_number?:string} $in
     */
    public function checkIdentityMatch(array $in, int $editPatientId = 0): array
    {
        $mobile  = trim((string) ($in['mobile'] ?? ''));
        $name    = trim((string) ($in['name'] ?? ''));
        $gender  = trim((string) ($in['gender'] ?? ''));
        $dob     = trim((string) ($in['date_of_birth'] ?? ''));
        $number  = trim((string) ($in['abha_number'] ?? ''));
        $address = trim((string) ($in['abha_address'] ?? ''));
        $aadhaar = trim((string) ($in['aadhaar_number'] ?? ''));

        // Tier 1: the exact identity is a CERTAIN signal (these identifiers are unique per patient).
        $conflict = $this->findIdentityConflict($aadhaar, $number, $address, $editPatientId ?: null);

        if ($editPatientId > 0) {
            if ($conflict) {
                return [
                    'success' => true, 'mode' => 'self', 'matches' => null,
                    'identity_conflict' => ['label' => $conflict['label'], 'patient_id' => $conflict['patient_id'], 'patient_name' => $conflict['patient_name']],
                ];
            }
        } elseif ($conflict) {
            $row = Patient::query()->find($conflict['patient_id']);
            if ($row) {
                return ['success' => true, 'mode' => 'search', 'match' => $this->matchPayload($row, 'identity', $conflict['label'])];
            }
        }

        if ($mobile === '' || $name === '' || $gender === '') {
            // Not enough demographics yet - expected right after some verify calls, not an error.
            return ['success' => true, 'match' => null, 'matches' => null, 'identity_conflict' => null];
        }

        $incoming = ['mobile' => $mobile, 'gender' => $gender, 'age' => $this->ageFromDob($dob), 'name' => $name];

        if ($editPatientId > 0) {
            // Edit mode, tier 2: cross-check demographics against the ONE patient record open.
            $row = Patient::query()->find($editPatientId);
            if (!$row) return ['success' => false, 'error' => 'Patient not found'];

            return [
                'success' => true, 'mode' => 'self', 'identity_conflict' => null,
                'matches' => $this->demographicsMatch($incoming, $this->patientDemographics($row)),
            ];
        }

        // Add mode: narrow by exact mobile in SQL, then apply the age/gender/name rule.
        $digits = preg_replace('/\D/', '', $mobile);
        $match  = null;
        if ($digits !== '') {
            foreach (Patient::query()->where('mobile_number', $digits)->get() as $candidate) {
                if ($this->demographicsMatch($incoming, $this->patientDemographics($candidate))) {
                    $match = $this->matchPayload($candidate, 'demographics');
                    break;
                }
            }
        }

        return ['success' => true, 'mode' => 'search', 'match' => $match];
    }
}
