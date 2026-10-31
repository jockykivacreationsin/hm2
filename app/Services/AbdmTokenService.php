<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Client-credentials access token for the ABDM gateway.
 * Mirrors bmhabdm/abdm/common.php abdm_get_access_token(): POST to the gateway
 * session endpoint (config abdm.session_url = legacy ABDM_SESSION_URL), cache the token
 * (shared by all users) until ~60s before its JWT expiry, retry transient failures.
 */
class AbdmTokenService
{
    private const CACHE_KEY = 'abdm_access_token';

    /** ABDM REQUEST-ID/TIMESTAMP header format - legacy abdm_get_timestamp(): UTC, ".000Z". */
    public static function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s.000\Z');
    }

    public function getAccessToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchNewToken();
        if ($token) {
            // Cache until 60s before the JWT expires (legacy), else the legacy 25-minute fallback.
            $exp = $this->jwtExpiry($token);
            $ttl = $exp !== null ? max(30, $exp - time() - 60) : 1500;
            Cache::put(self::CACHE_KEY, $token, $ttl);
        }

        return $token;
    }

    private function fetchNewToken(): ?string
    {
        $clientId     = (string) config('abdm.client_id');
        $clientSecret = (string) config('abdm.client_secret');
        if ($clientId === '' || $clientSecret === '') {
            Log::error('ABDM token fetch skipped: client ID/secret not configured');
            return null;
        }

        $url    = (string) config('abdm.session_url');
        $delays = (array) config('abdm.token_retry_delays_ms', [1000, 2000, 4000]); // legacy: 1s, 2s, 4s

        for ($attempt = 0; ; $attempt++) {
            $status = 0;
            $body   = '';
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'X-CM-ID'      => (string) config('abdm.cm_id', 'sbx'),
                    'REQUEST-ID'   => (string) Str::uuid(),
                    'TIMESTAMP'    => self::timestamp(),
                ])->timeout(10)->post($url, [
                    'clientId'     => $clientId,
                    'clientSecret' => $clientSecret,
                    'grantType'    => 'client_credentials',
                ]);

                $status = $response->status();
                $body   = $response->body();

                if ($response->successful()) {
                    $token = $response->json('accessToken');
                    if (is_string($token) && $token !== '') {
                        return $token;
                    }
                    Log::error('ABDM token fetch: no accessToken in response');
                    return null;
                }
            } catch (\Exception $e) {
                // Connection failure / timeout - retried below like a 5xx (legacy $curl_error).
                $status = 0;
                $body   = $e->getMessage();
            }

            $retryable = $status === 0 || ($status >= 500 && $status < 600);
            if ($retryable && isset($delays[$attempt])) {
                usleep(max(0, (int) $delays[$attempt]) * 1000);
                continue;
            }

            // Never log the request (client secret) - only status and a truncated response body.
            Log::error('ABDM token fetch failed', ['status' => $status, 'body' => substr($body, 0, 500)]);
            return null;
        }
    }

    private function jwtExpiry(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;

        $b64    = strtr($parts[1], '-_', '+/');
        $json   = base64_decode(str_pad($b64, strlen($b64) + ((4 - strlen($b64) % 4) % 4), '='), true);
        $claims = $json !== false ? json_decode($json, true) : null;

        return (is_array($claims) && isset($claims['exp'])) ? (int) $claims['exp'] : null;
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
