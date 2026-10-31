<?php

namespace App\Services\Abdm;

use App\Services\AbdmTokenService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ABDM settings page backend (legacy: abdm-settings.php).
 *
 *  - credentials / HFR (HIP) id / HIU id / bridge callback base URL live in the single `settings` row (id = 1)
 *  - the page is unlocked with `settings.admin_password` (stored hashed; a legacy plain-text value is upgraded on first use)
 *  - "Update Bridge URL" PATCHes the bridge url on the ABDM gateway, "Check Current Bridge URL" reads it back
 *
 * Every result of the two gateway calls keeps the legacy array shape so the view can render it verbatim:
 *   ['success'=>bool, 'step'=>'session'|'patch', 'http_code'=>?, 'response'=>?, 'error'=>?, ...]
 */
class AbdmSettingsService
{
    private const COLUMNS = [
        'abdm_client_id', 'abdm_client_secret', 'facility_hfr_id', 'abdm_hiu_id', 'abdm_bridge_callback_base_url',
    ];

    /** Session flag set once the admin password was entered (legacy $_SESSION['abdm_settings_authenticated']). */
    public const SESSION_FLAG = 'abdm_settings_authenticated';

    /**
     * Whether `$entered` is the settings admin password. The password is stored as a bcrypt hash; a plain-text value left
     * by the legacy page still matches and is replaced by its hash on the first successful unlock.
     */
    public function passwordMatches(string $entered): bool
    {
        $stored = (string) DB::table('settings')->where('id', 1)->value('admin_password');

        if ($stored === '' || $entered === '') {
            return false;
        }

        if (str_starts_with($stored, '$2y$')) {
            return Hash::check($entered, $stored);
        }

        if (! hash_equals($stored, $entered)) {
            return false;
        }

        $this->setPassword($entered);

        return true;
    }

    /** Stores the settings admin password as a hash. */
    public function setPassword(string $plain): void
    {
        DB::table('settings')->where('id', 1)->update(['admin_password' => Hash::make($plain)]);
    }

    /** @return array<string,string> the five ABDM columns of the settings row ('' when unset) */
    public function current(): array
    {
        $row = DB::table('settings')->where('id', 1)->first(self::COLUMNS);
        $out = [];
        foreach (self::COLUMNS as $col) {
            $out[$col] = (string) ($row->{$col} ?? '');
        }

        return $out;
    }

    /** @param array<string,string|null> $values keyed by settings column */
    public function save(array $values): void
    {
        $update = [];
        foreach (self::COLUMNS as $col) {
            $update[$col] = trim((string) ($values[$col] ?? ''));
        }
        DB::table('settings')->where('id', 1)->update($update);

        // The legacy page dropped its session/file caches here: the cached settings row and the
        // gateway access token (issued for the old client id/secret) must not outlive an edit.
        SettingsService::clearCache();
        AbdmTokenService::clearCache();
    }

    /** PATCH /bridge/url, then read /bridge-services back for verification. */
    public function updateBridgeUrl(string $clientId, string $clientSecret, string $bridgeUrl): array
    {
        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'step' => 'session', 'error' => 'ABDM Client ID / Client Secret are not set - save them first.'];
        }
        if ($bridgeUrl === '') {
            return ['success' => false, 'step' => 'session', 'error' => 'Bridge Callback Base URL is not set - save it first.'];
        }

        $session = $this->openSession($clientId, $clientSecret);
        if (isset($session['failure'])) {
            return $session['failure'];
        }
        $token = $session['token'];

        $patch = $this->call('PATCH', $this->base() . '/bridge/url', $token, ['url' => $bridgeUrl]);
        if (!in_array($patch['http_code'], [200, 202], true)) {
            return ['success' => false, 'step' => 'patch', 'http_code' => $patch['http_code'], 'response' => $patch['body'], 'error' => $patch['error']];
        }

        $verify = $this->call('GET', $this->base() . '/bridge-services', $token);

        return [
            'success'          => true,
            'bridge_url'       => $bridgeUrl,
            'patch_http_code'  => $patch['http_code'],
            'patch_response'   => $patch['body'],
            'verify_http_code' => $verify['http_code'],
            'verify_response'  => $verify['body'],
        ];
    }

    /** GET /bridge-services - the bridge URL ABDM is currently calling back. */
    public function fetchBridgeServices(string $clientId, string $clientSecret): array
    {
        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'step' => 'session', 'error' => 'ABDM Client ID / Client Secret are not set - save them first.'];
        }

        $session = $this->openSession($clientId, $clientSecret);
        if (isset($session['failure'])) {
            return $session['failure'];
        }

        $result = $this->call('GET', $this->base() . '/bridge-services', $session['token']);
        $decoded = json_decode((string) $result['body'], true);

        return [
            'success'      => $result['http_code'] === 200,
            'http_code'    => $result['http_code'],
            'raw_response' => $result['body'],
            'bridge'       => is_array($decoded) ? ($decoded['bridge'] ?? null) : null,
            'error'        => $result['error'],
        ];
    }

    /** Gateway v3 root, e.g. https://dev.abdm.gov.in/api/hiecm/gateway/v3 (legacy hard-coded it). */
    private function base(): string
    {
        return rtrim((string) config('abdm.gateway_url'), '/') . '/v3';
    }

    /** @return array{token:string}|array{failure:array} */
    private function openSession(string $clientId, string $clientSecret): array
    {
        $res = $this->call('POST', $this->base() . '/sessions', null, [
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'grantType'    => 'client_credentials',
        ]);

        if ($res['http_code'] !== 200) {
            return ['failure' => ['success' => false, 'step' => 'session', 'http_code' => $res['http_code'], 'response' => $res['body'], 'error' => $res['error']]];
        }
        $token = json_decode((string) $res['body'], true)['accessToken'] ?? null;
        if (empty($token)) {
            return ['failure' => ['success' => false, 'step' => 'session', 'http_code' => $res['http_code'], 'response' => $res['body'], 'error' => 'No accessToken in response']];
        }

        return ['token' => (string) $token];
    }

    /** @return array{http_code:int,body:string,error:string} */
    private function call(string $method, string $url, ?string $bearer, ?array $body = null): array
    {
        $headers = [
            'X-CM-ID'    => (string) config('abdm.cm_id', 'sbx'),
            'REQUEST-ID' => (string) Str::uuid(),
            'TIMESTAMP'  => AbdmTokenService::timestamp(),
        ];
        if ($bearer !== null) {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }

        try {
            $req = Http::withHeaders($headers)->timeout(30)
                ->withBody($body !== null ? json_encode($body) : '', 'application/json');
            $res = $req->send($method, $url);

            return ['http_code' => $res->status(), 'body' => $res->body(), 'error' => ''];
        } catch (\Throwable $e) {
            // Connection failure / timeout - legacy surfaced curl_error() with http_code 0.
            return ['http_code' => 0, 'body' => '', 'error' => $e->getMessage()];
        }
    }
}
