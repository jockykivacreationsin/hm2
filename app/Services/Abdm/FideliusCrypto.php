<?php

namespace App\Services\Abdm;

/**
 * ABDM Fidelius encryption — ported verbatim from bmhabdm/abdm/FideliusCrypto.php.
 * Uses BouncyCastle Curve25519 (Weierstrass form) + HKDF-SHA256 + AES-256-GCM.
 * Requires ext-gmp and ext-openssl.
 *
 * See bmhabdm/abdm/FideliusCrypto.php header for full algorithm description.
 */
class FideliusCrypto
{
    // ---- BouncyCastle Curve25519 (Weierstrass form) domain parameters ----
    const P_HEX = '7FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFED';
    const A_HEX = '2AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA984914A144';
    const B_HEX = '7B425ED097B425ED097B425ED097B425ED097B425ED097B4260B5E9C7710C864';
    const N_HEX = '1000000000000000000000000000000014DEF9DEA2F79CD65812631A5CF5D3ED';
    const GX_HEX = '2AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD245A';
    const GY_HEX = '20AE19A1B8A086B4E01EDD2C7748D14C923D4D7E6D7C61B229E9C5A27ECED3D9';

    private static function p() { return gmp_init(self::P_HEX, 16); }
    private static function a() { return gmp_init(self::A_HEX, 16); }
    private static function n() { return gmp_init(self::N_HEX, 16); }
    private static function G() { return [gmp_init(self::GX_HEX, 16), gmp_init(self::GY_HEX, 16)]; }
    private static function bConst() { return gmp_init(self::B_HEX, 16); }

    private static function pointAdd($P, $Q)
    {
        $p = self::p();
        if ($P === null) return $Q;
        if ($Q === null) return $P;
        [$x1, $y1] = $P; [$x2, $y2] = $Q;
        if (gmp_cmp($x1, $x2) === 0) {
            if (gmp_cmp(gmp_mod(gmp_add($y1, $y2), $p), 0) === 0) return null;
            $num = gmp_add(gmp_mul(gmp_init(3), gmp_mul($x1, $x1)), self::a());
            $den = gmp_invert(gmp_mod(gmp_mul(gmp_init(2), $y1), $p), $p);
            $lam = gmp_mod(gmp_mul($num, $den), $p);
        } else {
            $num = gmp_mod(gmp_sub($y2, $y1), $p);
            $den = gmp_invert(gmp_mod(gmp_sub($x2, $x1), $p), $p);
            $lam = gmp_mod(gmp_mul($num, $den), $p);
        }
        $x3 = gmp_mod(gmp_sub(gmp_sub(gmp_mul($lam, $lam), $x1), $x2), $p);
        $y3 = gmp_mod(gmp_sub(gmp_mul($lam, gmp_sub($x1, $x3)), $y1), $p);
        return [$x3, $y3];
    }

    private static function scalarMult($k, $P)
    {
        $R = null; $Q = $P;
        while (gmp_cmp($k, 0) > 0) {
            if (gmp_testbit($k, 0)) $R = self::pointAdd($R, $Q);
            $Q = self::pointAdd($Q, $Q);
            $k = gmp_div_q($k, 2);
        }
        return $R;
    }

    private static function isOnCurve($P): bool
    {
        $p = self::p(); [$x, $y] = $P;
        $lhs = gmp_mod(gmp_mul($y, $y), $p);
        $rhs = gmp_mod(gmp_add(gmp_add(gmp_pow($x, 3), gmp_mul(self::a(), $x)), self::bConst()), $p);
        return gmp_cmp($lhs, $rhs) === 0;
    }

    private static function gmpToFixedBytes($num, int $len): string
    {
        $hex = gmp_strval($num, 16);
        if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
        $bytes = hex2bin($hex);
        $pad = $len - strlen($bytes);
        if ($pad > 0) $bytes = str_repeat("\x00", $pad) . $bytes;
        elseif ($pad < 0) throw new \Exception('Encoded value longer than expected length');
        return $bytes;
    }

    private static function bytesToGmp(string $bytes) { return gmp_init(bin2hex($bytes), 16); }

    private static function encodePoint($P): string
    {
        [$x, $y] = $P;
        return "\x04" . self::gmpToFixedBytes($x, 32) . self::gmpToFixedBytes($y, 32);
    }

    private static function encodeX509PublicKey($P): string
    {
        $generator = self::encodePoint(self::G());
        $point = self::encodePoint($P);
        $parameters =
            "\x02\x01\x01" .
            "\x30\x2b\x06\x07\x2a\x86\x48\xce\x3d\x01\x01" .
            "\x02\x20" . self::gmpToFixedBytes(self::p(), 32) .
            "\x30\x44\x04\x20" . self::gmpToFixedBytes(self::a(), 32) .
            "\x04\x20" . self::gmpToFixedBytes(self::bConst(), 32) .
            "\x04\x41" . $generator .
            "\x02\x20" . self::gmpToFixedBytes(self::n(), 32) .
            "\x02\x01\x08";
        return "\x30\x82\x01\x31" .
            "\x30\x81\xea\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" .
            "\x30\x81\xde" . $parameters .
            "\x03\x42\x00" . $point;
    }

    private static function decodePoint(string $raw): array
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04")
            throw new \Exception('Expected 65-byte uncompressed EC point, got ' . strlen($raw) . ' bytes');
        $x = self::bytesToGmp(substr($raw, 1, 32));
        $y = self::bytesToGmp(substr($raw, 33, 32));
        $P = [$x, $y];
        if (!self::isOnCurve($P)) throw new \Exception('Remote public key point is not on the curve');
        return $P;
    }

    private static function readDerLength(string $der, int &$offset): int
    {
        if ($offset >= strlen($der)) throw new \Exception('DER length is truncated');
        $first = ord($der[$offset++]);
        if (($first & 0x80) === 0) return $first;
        $numBytes = $first & 0x7F;
        if ($numBytes === 0 || $offset + $numBytes > strlen($der)) throw new \Exception('DER length is invalid');
        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) $length = ($length << 8) | ord($der[$offset++]);
        return $length;
    }

    private static function extractSpkiPublicKey(string $der): ?string
    {
        if (strlen($der) < 16 || $der[0] !== "\x30") return null;
        try {
            $offset = 1;
            $totalLen = self::readDerLength($der, $offset);
            $end = $offset + $totalLen;
            if ($end > strlen($der) || $offset >= strlen($der) || $der[$offset] !== "\x30") return null;
            $offset++;
            $algLen = self::readDerLength($der, $offset);
            $algEnd = $offset + $algLen;
            if ($algEnd > $end) return null;
            $offset = $algEnd;
            if ($offset >= strlen($der) || $der[$offset] !== "\x03") return null;
            $offset++;
            $bitLen = self::readDerLength($der, $offset);
            if ($offset + $bitLen > strlen($der) || $bitLen < 1) return null;
            $unusedBits = ord($der[$offset++]);
            if ($unusedBits !== 0) return null;
            $key = substr($der, $offset, $bitLen - 1);
            if (strlen($key) === 32 || (strlen($key) === 65 && $key[0] === "\x04")) return $key;
        } catch (\Exception $e) { return null; }
        return null;
    }

    public static function normalizePublicKey(string $publicKeyB64): string
    {
        $decoded = base64_decode($publicKeyB64, true);
        if ($decoded === false) throw new \Exception('Unable to base64-decode public key');
        if (strlen($decoded) === 32) return $decoded;
        if (strlen($decoded) === 65 && $decoded[0] === "\x04") return $decoded;
        if (strlen($decoded) > 0 && $decoded[0] === "\x30") {
            $spkiKey = self::extractSpkiPublicKey($decoded);
            if ($spkiKey !== null) return $spkiKey;
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($decoded), 64) . "-----END PUBLIC KEY-----\n";
            $pkey = openssl_pkey_get_public($pem);
            if ($pkey !== false) {
                $details = openssl_pkey_get_details($pkey);
                if (!empty($details['key']) && is_string($details['key']) && strlen($details['key']) === 32) return $details['key'];
            }
        }
        throw new \Exception('Unsupported public key format: got ' . strlen($decoded) . ' bytes');
    }

    public static function generateKeyPair(): array
    {
        $n = self::n();
        do { $candidate = self::bytesToGmp(random_bytes(32)); $d = gmp_mod($candidate, $n); } while (gmp_cmp($d, 0) === 0);
        $Q = self::scalarMult($d, self::G());
        return [
            'privateKey'    => base64_encode(self::gmpToFixedBytes($d, 32)),
            'publicKey'     => base64_encode(self::encodePoint($Q)),
            'x509PublicKey' => base64_encode(self::encodeX509PublicKey($Q)),
        ];
    }

    public static function generateNonce(): string { return base64_encode(random_bytes(32)); }

    public static function computeSharedSecret(string $myPrivateKeyB64, string $theirPublicKeyB64): string
    {
        $myPrivateKey = base64_decode($myPrivateKeyB64, true);
        if ($myPrivateKey === false || strlen($myPrivateKey) !== 32)
            throw new \Exception('Private key must be 32 bytes');
        $theirPublicKeyRaw = self::normalizePublicKey($theirPublicKeyB64);
        if (strlen($theirPublicKeyRaw) === 32 && function_exists('sodium_crypto_scalarmult'))
            return sodium_crypto_scalarmult($myPrivateKey, $theirPublicKeyRaw);
        $Q = self::decodePoint($theirPublicKeyRaw);
        $d = self::bytesToGmp($myPrivateKey);
        $S = self::scalarMult($d, $Q);
        if ($S === null) throw new \Exception('ECDH resulted in point at infinity');
        return self::gmpToFixedBytes($S[0], 32);
    }

    /** @return array{0:string,1:string} [salt(20 bytes), iv(12 bytes)] */
    public static function deriveSaltAndIv(string $myNonceB64, string $theirNonceB64): array
    {
        $a = base64_decode($myNonceB64); $b = base64_decode($theirNonceB64);
        if (strlen($a) !== 32 || strlen($b) !== 32) throw new \Exception('Both nonces must be 32 bytes');
        $xor = $a ^ $b;
        return [substr($xor, 0, 20), substr($xor, 20, 12)];
    }

    public static function deriveAesKey(string $sharedSecret, string $salt): string
    {
        return hash_hkdf('sha256', $sharedSecret, 32, '', $salt);
    }

    public static function encryptPayload(string $aesKey, string $iv, string $plaintext): string
    {
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) throw new \Exception('AES-256-GCM encryption failed: ' . openssl_error_string());
        return base64_encode($ciphertext . $tag);
    }

    public static function decryptPayload(string $aesKey, string $iv, string $encodedPayload): string
    {
        $raw = base64_decode($encodedPayload);
        $ciphertext = substr($raw, 0, -16); $tag = substr($raw, -16);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) throw new \Exception('AES-256-GCM decryption failed');
        return $plaintext;
    }

    public static function selfTest(): bool
    {
        if (!self::isOnCurve(self::G())) throw new \Exception('Generator point invalid');
        $hip = self::generateKeyPair(); $hiu = self::generateKeyPair();
        $hipNonce = self::generateNonce(); $hiuNonce = self::generateNonce();
        $secretAtHip = self::computeSharedSecret($hip['privateKey'], $hiu['publicKey']);
        $secretAtHiu = self::computeSharedSecret($hiu['privateKey'], $hip['publicKey']);
        if (!hash_equals(bin2hex($secretAtHip), bin2hex($secretAtHiu))) throw new \Exception('Shared secrets differ');
        [$saltH, $ivH] = self::deriveSaltAndIv($hipNonce, $hiuNonce);
        [$saltU, $ivU] = self::deriveSaltAndIv($hiuNonce, $hipNonce);
        if ($saltH !== $saltU || $ivH !== $ivU) throw new \Exception('Salt/IV mismatch');
        $key = self::deriveAesKey($secretAtHip, $saltH);
        $pt  = json_encode(['resourceType' => 'Bundle', 'test' => true]);
        return self::decryptPayload($key, $ivH, self::encryptPayload($key, $ivH, $pt)) === $pt;
    }
}
