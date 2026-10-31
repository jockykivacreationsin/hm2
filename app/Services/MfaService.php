<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\StaffMember;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Handles the second factor (email OTP) for staff login.
 *
 * Session keys used (all cleared on success or expiry):
 *   mfa_pending_user_id   — StaffMember primary key awaiting verification
 *   mfa_otp_hash          — bcrypt hash of the 6-digit OTP
 *   mfa_otp_expires_at    — Unix timestamp after which the OTP is invalid
 *   mfa_resend_count      — how many times the code has been resent this session
 *   mfa_wrong_attempts    — consecutive wrong OTP entries (max 3 before reset)
 *
 * Trusted-device cookie:
 *   Name  : mfa_trusted_<user_id>
 *   Value : a random 64-char token stored as a bcrypt hash in the session
 *           (cookie contains the raw token; session contains the hash)
 *   TTL   : MFA_TRUST_DAYS env var (default 7 days)
 */
class MfaService
{
    /** OTP validity window in seconds. */
    public const OTP_TTL_SECONDS = 300; // 5 minutes

    /** Max consecutive wrong OTP attempts before the challenge is reset. */
    public const MAX_WRONG_ATTEMPTS = 3;

    /** Max times a code can be resent in one session. */
    public const MAX_RESENDS = 3;

    // ── Session key constants ──────────────────────────────────────────────────

    private const KEY_USER_ID      = 'mfa_pending_user_id';
    private const KEY_OTP_HASH     = 'mfa_otp_hash';
    private const KEY_EXPIRES_AT   = 'mfa_otp_expires_at';
    private const KEY_RESEND_COUNT = 'mfa_resend_count';
    private const KEY_WRONG        = 'mfa_wrong_attempts';

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Returns true when the given user already has a valid trusted-device cookie
     * for this browser, meaning MFA can be skipped this login.
     *
     * The cookie value is a raw 64-char random token. We verify it by comparing
     * its SHA-256 HMAC (keyed with APP_KEY) against the expected value stored in
     * the staff_members trusted_device_token column — no session lookup needed,
     * so it survives logout correctly.
     */
    public function isTrustedDevice(StaffMember $user): bool
    {
        $cookieName = $this->cookieName((int) $user->id);
        $raw        = request()->cookie($cookieName);

        if (! $raw || ! is_string($raw)) {
            return false;
        }

        $stored = $user->trusted_device_token;
        if (! $stored) {
            return false;
        }

        // Constant-time comparison of the stored SHA-256 hash against the cookie.
        return hash_equals($stored, $this->hashToken($raw));
    }

    /**
     * Generate a fresh OTP, store its hash in the session, and send the email.
     * Returns false if the staff member has no email address.
     */
    public function sendOtp(StaffMember $user): bool
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return false;
        }

        $otp = $this->generateOtp();

        session([
            self::KEY_USER_ID    => (int) $user->id,
            self::KEY_OTP_HASH   => Hash::make($otp),
            self::KEY_EXPIRES_AT => time() + self::OTP_TTL_SECONDS,
            self::KEY_WRONG      => 0,
        ]);

        try {
            Mail::to($email)->send(new LoginOtpMail($user->staff_name, $otp));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MfaService: failed to send OTP email', [
                'staff_id'  => $user->id,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Attempt to verify the submitted OTP.
     *
     * Returns one of:
     *   'ok'        — correct, session cleared
     *   'wrong'     — wrong code (increments attempt counter)
     *   'expired'   — OTP window has passed
     *   'max_tries' — too many wrong attempts, session cleared
     *   'no_session'— no pending MFA in session
     */
    public function verifyOtp(string $submitted): string
    {
        if (! $this->hasPendingSession()) {
            return 'no_session';
        }

        if ($this->isExpired()) {
            $this->clearSession();
            return 'expired';
        }

        $wrong = (int) session(self::KEY_WRONG, 0);

        if (! Hash::check($submitted, session(self::KEY_OTP_HASH))) {
            $wrong++;
            if ($wrong >= self::MAX_WRONG_ATTEMPTS) {
                $this->clearSession();
                return 'max_tries';
            }
            session([self::KEY_WRONG => $wrong]);
            return 'wrong';
        }

        $this->clearSession();
        return 'ok';
    }

    /**
     * Resend a fresh OTP to the user whose ID is pending in the session.
     * Returns false when the resend limit is reached or no session exists.
     */
    public function resendOtp(StaffMember $user): bool
    {
        if (! $this->hasPendingSession()) {
            return false;
        }

        $resends = (int) session(self::KEY_RESEND_COUNT, 0);
        if ($resends >= self::MAX_RESENDS) {
            return false;
        }

        session([self::KEY_RESEND_COUNT => $resends + 1]);

        // Re-use sendOtp but preserve the resend counter.
        $count = (int) session(self::KEY_RESEND_COUNT);
        $this->sendOtp($user);
        session([self::KEY_RESEND_COUNT => $count]);

        return true;
    }

    /** Pending user ID in the session, or null if none. */
    public function pendingUserId(): ?int
    {
        $id = session(self::KEY_USER_ID);
        return $id ? (int) $id : null;
    }

    /**
     * Queue a trusted-device cookie for the given user and persist the token hash
     * in staff_members.trusted_device_token so it survives session invalidation on logout.
     */
    public function trustDevice(StaffMember $user): void
    {
        $raw     = \Illuminate\Support\Str::random(64);
        $minutes = (int) config('mfa.trust_days', 7) * 1440;

        // Persist SHA-256 hash to DB — survives logout (no session dependency).
        $user->trusted_device_token = $this->hashToken($raw);
        $user->save();

        Cookie::queue(
            $this->cookieName((int) $user->id),
            $raw,
            $minutes,
            '/',
            null,
            config('session.secure', false),  // Secure flag matches session config
            true,                              // HttpOnly
            false,
            config('session.same_site', 'lax')
        );
    }

    /** Seconds remaining before the current OTP expires (0 if none / expired). */
    public function secondsRemaining(): int
    {
        $exp = (int) session(self::KEY_EXPIRES_AT, 0);
        return max(0, $exp - time());
    }

    /** How many resends have been used this session. */
    public function resendsUsed(): int
    {
        return (int) session(self::KEY_RESEND_COUNT, 0);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hasPendingSession(): bool
    {
        return session()->has(self::KEY_USER_ID);
    }

    private function isExpired(): bool
    {
        return time() > (int) session(self::KEY_EXPIRES_AT, 0);
    }

    private function clearSession(): void
    {
        session()->forget([
            self::KEY_USER_ID,
            self::KEY_OTP_HASH,
            self::KEY_EXPIRES_AT,
            self::KEY_RESEND_COUNT,
            self::KEY_WRONG,
        ]);
    }

    private function cookieName(int $userId): string
    {
        return 'mfa_trusted_' . $userId;
    }

    /**
     * SHA-256 HMAC of the raw token, keyed with APP_KEY.
     * Stored in DB; never stored in the cookie itself.
     */
    private function hashToken(string $raw): string
    {
        return hash_hmac('sha256', $raw, config('app.key'));
    }
}
