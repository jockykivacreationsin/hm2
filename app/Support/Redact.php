<?php

namespace App\Support;

/**
 * Removes credentials from data before it is logged or stored for diagnostics.
 *
 * ABDM callbacks and answers can carry bearer credentials (link tokens, X-tokens, access tokens, OTPs, key material)
 * next to patient data. Log files and "raw response" columns end up in backups and support e-mails, so anything that
 * is only a credential is replaced with "[redacted]" while the harmless fields (ids, status, error text) stay readable.
 *
 * Use value() for an array body, text() for a JSON string / free text (it also masks bare JWTs).
 */
final class Redact
{
    public const MASK = '[redacted]';

    /** Field names (compared lower-case, ignoring "_" and "-") whose value is a credential. */
    private const SECRET_KEYS = [
        'linktoken', 'token', 'accesstoken', 'refreshtoken', 'xtoken', 'authorization', 'password', 'passwd',
        'secret', 'clientsecret', 'otp', 'otpvalue', 'encryptedotp',
        'keyvalue', 'privatekey', 'publickey', 'nonce', 'dhpublickey', 'keymaterial', 'apikey', 'kioskkey',
    ];

    /** @return array<mixed> the same structure with credential fields masked, recursively */
    public static function value(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                $out[$key] = self::MASK;
            } elseif (is_array($value)) {
                $out[$key] = self::value($value);
            } elseif (is_string($value)) {
                $out[$key] = self::text($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /** Masks credential fields in JSON text, and any bare JWT in free text. */
    public static function text(?string $text): string
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $decoded = ($text[0] === '{' || $text[0] === '[') ? json_decode($text, true) : null;
        if (is_array($decoded)) {
            return (string) json_encode(self::value($decoded), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Three base64url segments, the first starting "eyJ" ({"...): a JWT, wherever it sits in the text.
        return (string) preg_replace('/\beyJ[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]*/', self::MASK, $text);
    }

    private static function isSecretKey(string $key): bool
    {
        return in_array(strtolower(str_replace(['_', '-'], '', $key)), self::SECRET_KEYS, true);
    }
}
