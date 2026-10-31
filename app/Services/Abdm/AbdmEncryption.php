<?php

namespace App\Services\Abdm;

use App\Services\AbdmTokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RSA-OAEP encryption utility for ABDM M1 calls.
 * Ported 1:1 from bmhabdm/abdm/encryption.php.
 *
 * Every M1 API call must encrypt the sensitive value (Aadhaar/OTP/loginId)
 * with ABDM's current RSA public key before sending it over the wire. As in
 * legacy, the public certificate is fetched fresh for each encryption (not cached).
 * The plain text is never logged.
 */
class AbdmEncryption
{
    public function __construct(private AbdmTokenService $tokenSvc) {}

    /**
     * Fetch ABDM's current RSA public key (GET {base_url}/v3/profile/public/certificate).
     * @return array{success:bool, pem?:string, algorithm?:string, error?:string}
     */
    public function getPublicKey(string $accessToken): array
    {
        $url = rtrim((string) config('abdm.base_url'), '/') . '/v3/profile/public/certificate';
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'REQUEST-ID'   => (string) Str::uuid(),
                    'TIMESTAMP'    => AbdmTokenService::timestamp(),
                    'X-CM-ID'      => (string) config('abdm.cm_id', 'sbx'),
                ])
                ->get($url);

            if ($response->status() !== 200) {
                return ['success' => false, 'error' => 'Failed to fetch ABDM public key (HTTP ' . $response->status() . ')'];
            }

            $data   = $response->json();
            $rawKey = is_array($data) ? ($data['publicKey'] ?? null) : null;
            if (!$rawKey || !is_string($rawKey)) {
                return ['success' => false, 'error' => 'No publicKey in ABDM response'];
            }

            $pem = str_contains($rawKey, 'BEGIN PUBLIC KEY')
                ? $rawKey
                : "-----BEGIN PUBLIC KEY-----\n" . chunk_split($rawKey, 64, "\n") . "-----END PUBLIC KEY-----\n";

            return ['success' => true, 'pem' => $pem, 'algorithm' => (string) ($data['encryptionAlgorithm'] ?? '')];
        } catch (\Exception $e) {
            Log::error('ABDM getPublicKey failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * RSA-OAEP encrypt $plainText using ABDM's current public key. PKCS1 v1.5 is used only
     * when the advertised algorithm names PKCS1 without OAEP (legacy abdm_rsa_encrypt()).
     *
     * @return array{success:bool, token?:string, encrypted?:string, error?:string}
     */
    public function encrypt(string $plainText): array
    {
        $token = $this->tokenSvc->getAccessToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to get ABDM access token'];

        $keyResult = $this->getPublicKey($token);
        if (!$keyResult['success']) return ['success' => false, 'error' => $keyResult['error']];

        $algorithm = $keyResult['algorithm'] ?? '';
        $padding   = OPENSSL_PKCS1_OAEP_PADDING;
        if (stripos($algorithm, 'PKCS1') !== false && stripos($algorithm, 'OAEP') === false) {
            $padding = OPENSSL_PKCS1_PADDING;
        }

        $keyResource = @openssl_pkey_get_public($keyResult['pem']);
        if (!$keyResource) return ['success' => false, 'error' => 'RSA encryption failed'];

        $encrypted = '';
        if (!@openssl_public_encrypt($plainText, $encrypted, $keyResource, $padding)) {
            return ['success' => false, 'error' => 'RSA encryption failed'];
        }

        return ['success' => true, 'token' => $token, 'encrypted' => base64_encode($encrypted)];
    }
}
