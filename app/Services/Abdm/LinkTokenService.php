<?php

namespace App\Services\Abdm;

use App\Models\AbdmLinkToken;
use App\Services\AbdmTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LinkTokenService
{
    public function __construct(private AbdmTokenService $tokenSvc) {}

    public function getValidToken(int $patientId, string $abhaAddress): ?object
    {
        return DB::table('abdm_link_tokens')->where('patient_id', $patientId)
            ->where('abha_address', $abhaAddress)->where('status', 'active')
            ->whereNotNull('link_token')->where('expires_at', '>', now('UTC')->toDateTimeString())
            ->orderByDesc('id')->first();
    }

    public function getPendingRequest(int $patientId, string $abhaAddress): ?object
    {
        return DB::table('abdm_link_tokens')->where('patient_id', $patientId)
            ->where('abha_address', $abhaAddress)->where('status', 'pending')
            ->where('created_on', '>', now('UTC')->subSeconds(config('abdm.link_token_pending_timeout', 600))->toDateTimeString())
            ->orderByDesc('id')->first();
    }

    public function getCooldown(int $patientId, string $abhaAddress): ?array
    {
        $last = DB::table('abdm_link_token_attempts')->where('patient_id', $patientId)
            ->where('abha_address', $abhaAddress)->orderByDesc('id')->first();
        if (!$last || $last->success) {
            return null;
        }
        $seconds = self::cooldownSeconds((bool) $last->is_duplicate_error, $last->error_message, 'link_token');
        $remaining = CarbonImmutable::parse($last->created_on, 'UTC')->getTimestamp() + $seconds - now('UTC')->getTimestamp();

        return $remaining > 0 ? ['seconds_remaining' => $remaining, 'is_duplicate' => (bool) $last->is_duplicate_error, 'error_message' => $last->error_message] : null;
    }

    public function requestToken(int $patientId, string $abhaAddress, string $abhaNumber, array $patientData, int $userId): array
    {
        $requestId = (string) Str::uuid();
        // Lock the parent even when there are no token rows yet. Commit the reservation
        // before HTTP so an immediate callback can see it without holding a network transaction.
        $reservation = DB::transaction(function () use ($patientId, $abhaAddress, $abhaNumber, $patientData, $userId, $requestId) {
            $patient = DB::table('patients')->where('id', $patientId)->lockForUpdate()->first();
            $storedNumber = preg_replace('/[^0-9]/', '', (string) $patient?->abha_number);
            if (!$patient || $patient->abha_address !== $abhaAddress || $storedNumber === '' || $storedNumber !== preg_replace('/[^0-9]/', '', $abhaNumber)) {
                return ['success' => false, 'error' => 'This patient has no ABHA Number/Address on file'];
            }
            if ($existing = $this->getValidToken($patientId, $abhaAddress)) {
                // The modal only needs the lifetime; the token itself never goes to the browser.
                return ['success' => true, 'existing' => true, 'token_row' => [
                    'id' => $existing->id, 'abha_address' => $existing->abha_address,
                    'issued_at' => $existing->issued_at, 'expires_at' => $existing->expires_at,
                ]];
            }
            if ($this->getPendingRequest($patientId, $abhaAddress)) {
                return ['success' => false, 'pending' => true, 'error' => 'A link-token request is awaiting ABDM confirmation.'];
            }
            if ($cooldown = $this->getCooldown($patientId, $abhaAddress)) {
                return ['success' => false, 'error' => $cooldown['error_message'] ?? 'Please wait before retrying.', 'cooldown_seconds_remaining' => $cooldown['seconds_remaining']];
            }

            // Use the persisted demographics, not an editable request/UI snapshot. Messages and order are the legacy
            // abdm_link_request_token() ones, so staff are told exactly which field to fix.
            $dob = (string) $patient->date_of_birth;
            if ($dob === '' || str_starts_with($dob, '0000')) {
                return ['success' => false, 'error' => "This patient's date of birth is missing - it's needed to compute the Year of Birth ABDM requires"];
            }
            $gender = match (strtolower(trim((string) $patient->gender))) {
                'male', 'm' => 'M', 'female', 'f' => 'F', 'other', 'o' => 'O', default => '',
            };
            if ($gender === '') {
                return ['success' => false, 'error' => "This patient's gender must be Male, Female or Other - required by ABDM"];
            }
            if (!config('abdm.hip_id')) {
                return ['success' => false, 'error' => 'Facility HFR ID is not configured in Settings'];
            }
            $year = (int) substr($dob, 0, 4);
            $number = $storedNumber;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $dob) || !checkdate((int) substr($dob, 5, 2), (int) substr($dob, 8, 2), $year)
                || $year > (int) now('UTC')->format('Y') || strlen($number) !== 14 || trim($abhaAddress) === '' || trim($patient->patient_name) === '') {
                return ['success' => false, 'error' => 'Valid ABHA identity, name, gender, date of birth and facility HFR ID are required.'];
            }
            $payload = ['abhaNumber' => $number, 'abhaAddress' => $abhaAddress, 'name' => $patient->patient_name, 'gender' => $gender, 'yearOfBirth' => $year];
            AbdmLinkToken::create([
                'patient_id' => $patientId, 'abha_address' => $abhaAddress, 'abha_number' => $number,
                'patient_name' => $payload['name'], 'gender' => $gender, 'year_of_birth' => $year,
                'request_id' => $requestId, 'status' => 'pending', 'raw_response' => '',
                'created_on' => now('UTC')->toDateTimeString(), 'user_id' => $userId,
            ]);

            return ['payload' => $payload];
        });
        if (!isset($reservation['payload'])) {
            // Nothing was sent to ABDM: say why (cooldown, pending, missing data, ...) so a click that "does nothing" is explained.
            Log::info('ABDM link token not requested', [
                'patient_id' => $patientId,
                'reason'     => $reservation['error'] ?? 'existing valid token',
                'pending'    => !empty($reservation['pending']),
                'existing'   => !empty($reservation['existing']),
                'cooldown_seconds_remaining' => $reservation['cooldown_seconds_remaining'] ?? null,
            ]);

            return $reservation;
        }

        try {
            $token = $this->tokenSvc->getAccessToken();
            if (!$token) {
                // Nothing reached ABDM: like legacy, no failed attempt (so no cooldown) is recorded; drop the
                // pending row reserved above so staff can retry at once.
                DB::table('abdm_link_tokens')->where('request_id', $requestId)->where('status', 'pending')->delete();
                Log::warning('ABDM link token not requested: no ABDM access token', ['patient_id' => $patientId, 'request_id' => $requestId]);

                return ['success' => false, 'error' => 'Could not get ABDM access token'];
            }
            $response = Http::withToken($token)->acceptJson()->timeout(60)->connectTimeout(30)->withHeaders([
                'REQUEST-ID' => $requestId, 'TIMESTAMP' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
                'X-CM-ID' => config('abdm.cm_id', 'sbx'), 'X-HIP-ID' => config('abdm.hip_id'),
            ])->post(config('abdm.link_token_generate_url'), $reservation['payload']);
            $body = $response->json();
            // What ABDM answered to the request itself (the token arrives later, by callback). No token/secret is in
            // this body; it is only logged so a "stuck on Awaiting ABDM confirmation" case can be diagnosed.
            Log::info('ABDM link token requested', [
                'request_id' => $requestId,
                'http_code'  => $response->status(),
                'body'       => mb_substr(preg_replace('/"linkToken"\s*:\s*"[^"]*"/', '"linkToken":"[redacted]"', $response->body()), 0, 500),
                'callback_url_hint' => rtrim((string) config('abdm.callback_base_url'), '/') . '/v3/hip/token/on-generate-token',
            ]);
            if (!$response->successful() || !empty($body['error'])) {
                return $this->handleOnGenerateToken(['requestId' => $requestId, 'error' => $body['error'] ?? $body ?? $response->body() ?: 'ABDM request failed (HTTP ' . $response->status() . ')']);
            }
            if (!empty($body['linkToken'])) {
                // Some ABDM environments answer synchronously: report the stored token like legacy token_row.
                $result = $this->handleOnGenerateToken(['requestId' => $requestId] + $body);
                if (!empty($result['success']) && empty($result['ignored'])) {
                    $row = DB::table('abdm_link_tokens')->where('request_id', $requestId)->first(['id', 'abha_address', 'issued_at', 'expires_at']);
                    $result['token_row'] = $row ? (array) $row : [];
                }

                return $result;
            }
            // ABDM answers 202 with an EMPTY body, so keep a short note of what it did answer: it is readable in the
            // table whatever LOG_LEVEL is, and is replaced by the callback body once the token arrives.
            $note = $response->body() !== '' ? \App\Support\Redact::text($response->body())
                : 'HTTP ' . $response->status() . ' (empty body) at ' . now('UTC')->toDateTimeString() . ' UTC - awaiting on-generate-token callback';
            DB::table('abdm_link_tokens')->where('request_id', $requestId)->where('status', 'pending')->update(['raw_response' => $note]);

            return ['success' => true, 'pending' => true, 'request_id' => $requestId, 'message' => 'Link token request submitted. Awaiting ABDM confirmation.'];
        } catch (\Exception $e) {
            Log::error('ABDM link token request failed before an answer', ['patient_id' => $patientId, 'request_id' => $requestId, 'error' => $e->getMessage()]);

            return $this->handleOnGenerateToken(['requestId' => $requestId, 'error' => $e->getMessage()]);
        }
    }

    public function handleOnGenerateToken(array $payload): array
    {
        $requestId = $payload['requestId'] ?? $payload['response']['requestId'] ?? null;
        if (!$requestId) {
            Log::warning('ABDM on-generate-token: answer carries no requestId', ['keys' => array_keys($payload)]);

            return ['success' => false, 'error' => 'Missing requestId'];
        }

        return DB::transaction(function () use ($requestId, $payload) {
            $row = AbdmLinkToken::where('request_id', $requestId)->lockForUpdate()->first();
            if (!$row || $row->status !== 'pending') {
                Log::warning('ABDM on-generate-token: ignored, no pending request matches', [
                    'request_id' => $requestId, 'row_status' => $row?->status, 'has_link_token' => !empty($payload['linkToken']),
                ]);

                return ['success' => true, 'ignored' => true];
            }
            $token = $payload['linkToken'] ?? null;
            $error = !empty($payload['error']) ? self::errorMessage($payload['error']) : (!$token ? 'ABDM did not return a linkToken' : null);
            DB::table('abdm_link_token_attempts')->insert([
                'patient_id' => $row->patient_id, 'abha_address' => $row->abha_address,
                'success' => $error === null, 'is_duplicate_error' => $error !== null && self::isLongCooldownError($error),
                'error_message' => $error, 'created_on' => now('UTC')->toDateTimeString(), 'user_id' => $row->user_id,
            ]);
            if ($error !== null) {
                Log::warning('ABDM on-generate-token: ABDM refused the link token', [
                    'request_id' => $requestId, 'patient_id' => $row->patient_id, 'error' => $error,
                ]);
                $row->delete();
                return ['success' => false, 'error' => $error];
            }
            $claims = self::decodeJwtClaims((string) $token);
            Log::info('ABDM on-generate-token: link token stored', [
                'request_id' => $requestId, 'patient_id' => $row->patient_id,
                'iat' => $claims['iat'] ?? null, 'exp' => $claims['exp'] ?? null,
            ]);
            $row->update([
                // The token lives in link_token only; raw_response is a diagnostic copy of the callback, so it must not repeat the credential.
                                'link_token' => $token, 'status' => 'active', 'raw_response' => json_encode(\App\Support\Redact::value($payload)),
                'issued_at' => isset($claims['iat']) && is_numeric($claims['iat']) ? gmdate('Y-m-d H:i:s', (int) $claims['iat']) : now('UTC')->toDateTimeString(),
                'expires_at' => isset($claims['exp']) && is_numeric($claims['exp']) ? gmdate('Y-m-d H:i:s', (int) $claims['exp']) : null,
            ]);

            return ['success' => true];
        });
    }

    /** Legacy abdm_decode_jwt_claims(): lifetime metadata only, not signature validation. */
    public static function decodeJwtClaims(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $b64 = strtr($parts[1], '-_', '+/');
        $json = base64_decode(str_pad($b64, strlen($b64) + ((4 - strlen($b64) % 4) % 4), '='), true);
        $claims = $json === false ? null : json_decode($json, true);

        return is_array($claims) ? $claims : null;
    }

    /** Mirrors legacy abdm_extract_error_message() for callback/API error envelopes. */
    public static function errorMessage(mixed $error): string
    {
        if (is_array($error) && array_is_list($error) && isset($error[0]) && is_array($error[0])) {
            $error = $error[0];
        }
        if (!is_array($error)) {
            return trim((string) $error) ?: 'ABDM request failed with no response body';
        }
        $error = isset($error['error']) && is_array($error['error']) ? $error['error'] : $error;
        $message = $error['message'] ?? ($error['details'][0]['message'] ?? null);
        if ($message) {
            $code = is_string($error['code'] ?? null) ? rtrim(trim($error['code']), ':') : '';

            return $code !== '' ? $code . ': ' . $message : (string) $message;
        }
        foreach ($error as $key => $value) {
            if (!in_array($key, ['timestamp', 'txnId', 'requestId'], true) && is_string($value) && $value !== '') {
                return $value;
            }
        }

        return json_encode($error);
    }

    /** Same detection as legacy abdm_link_is_long_cooldown_error(): ABDM saw this request already, or blocked us. */
    public static function isLongCooldownError(string $error): bool
    {
        return stripos($error, 'duplicate') !== false || stripos($error, 'block') !== false;
    }

    /**
     * How long a failed attempt keeps the "try again" button disabled. $prefix is 'link_token' or 'care_context'.
     * A duplicate (ABDM already holds this request) and a block both wait 24 h, as the legacy app did; the
     * duplicate length can be changed with ABDM_*_DUPLICATE_COOLDOWN in .env.
     */
    public static function cooldownSeconds(bool $long, ?string $error, string $prefix): int
    {
        if (!$long) {
            return (int) config("abdm.{$prefix}_retry_cooldown", 300);
        }
        if (stripos((string) $error, 'block') !== false) {
            return (int) config("abdm.{$prefix}_block_cooldown", 86400);
        }

        return (int) config("abdm.{$prefix}_duplicate_cooldown", 86400);
    }
}
