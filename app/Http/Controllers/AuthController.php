<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\PatientCategory;
use App\Models\StaffMember;
use App\Models\UserRolePermission;
use App\Services\AuditLogger;
use App\Services\MfaService;
use App\Services\PermissionService;
use App\Support\CurrentUser;
use App\Support\RememberedLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request, PermissionService $permissions)
    {
        // Auth::check() signs a device in again from its "Remember Me" cookie; a deactivated user is refused here.
        Auth::check();
        RememberedLogin::restore($request);

        if (Auth::check()) {
            // Same landing rule as login(): roles without the dashboard permission start at OPD.
            return redirect()->route($permissions->hasPermission('m_dashboard') ? 'dashboard' : 'opd.index');
        }

        try {
            $categories = PatientCategory::orderBy('id')->get();
        } catch (\Throwable $e) {
            $categories = collect();
        }

        return view('auth.login', compact('categories'));
    }

    /**
     * Step 1 — Password check.
     *
     * On success, either:
     *  (a) trusted device → full login immediately, OR
     *  (b) unknown device → send email OTP, return {result:'mfa_required'}
     *
     * Always returns {result:'0'} for any credential failure (no user/pass hint).
     */
    public function login(LoginRequest $request, MfaService $mfa): JsonResponse
    {
        if ($request->isThrottled()) {
            $seconds = $request->secondsUntilUnlocked();

            app(AuditLogger::class)->loginBlocked($request->userName(), $seconds);

            return response()->json([
                'result'  => '0',
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $user = $this->findActiveStaff($request->userName());

        if (! $user || ! $this->passwordMatches($user, $request->plainPassword())) {
            $request->recordFailure();
            app(AuditLogger::class)->loginFailure($request->userName());

            return response()->json(['result' => '0']);
        }

        $request->clearFailures();

        // ── Option B: skip OTP for this browser if the device is already trusted ──
        if ($mfa->isTrustedDevice($user)) {
            app(AuditLogger::class)->mfaSkipped((int) $user->id, (string) $user->staff_name);
            return $this->completeLogin($request, $user);
        }

        // ── Send OTP ──────────────────────────────────────────────────────────────
        $sent = $mfa->sendOtp($user);

        if (! $sent) {
            // Staff member has no email — cannot send OTP; fall back to direct login
            // and log a warning so the admin knows the account needs an email address.
            Log::warning('MFA: staff member has no email address, MFA bypassed.', [
                'staff_id' => $user->id,
                'name'     => $user->staff_name,
            ]);
            app(AuditLogger::class)->mfaBypassed((int) $user->id, (string) $user->staff_name, 'no_email');
            return $this->completeLogin($request, $user);
        }

        // Store remember-me intent in session so verifyOtp() can honour it later.
        session([
            'mfa_wants_remember'      => $this->shouldRemember($request),
            'mfa_patient_category_id' => $request->input('patient_category_id'),
        ]);

        app(AuditLogger::class)->mfaOtpSent((int) $user->id, (string) $user->staff_name);

        return response()->json([
            'result'           => 'mfa_required',
            'seconds_remaining' => MfaService::OTP_TTL_SECONDS,
            'resends_remaining' => MfaService::MAX_RESENDS - $mfa->resendsUsed(),
        ]);
    }

    /**
     * Step 2 — OTP verification.
     *
     * Accepts {otp: '123456'}.
     * On success, finishes the login and queues a trusted-device cookie.
     */
    public function verifyOtp(Request $request, MfaService $mfa): JsonResponse
    {
        // Read the pending user ID before any verify call, as verifyOtp() clears the session on success.
        $pendingId   = $mfa->pendingUserId();
        $throttleKey = 'mfa_otp|' . ($pendingId ?? '0') . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, MfaService::MAX_WRONG_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            app(AuditLogger::class)->mfaOtpBlocked($pendingId);
            return response()->json([
                'result'  => 'blocked',
                'message' => "Too many incorrect codes. Try again in {$seconds} seconds.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $submitted = trim((string) $request->input('otp', ''));
        $outcome   = $mfa->verifyOtp($submitted);

        if ($outcome === 'ok') {
            RateLimiter::clear($throttleKey);

            $user = StaffMember::find($pendingId);

            if (! $user) {
                return response()->json(['result' => '0']);
            }

            // Trust this device for the configured number of days.
            $mfa->trustDevice($user);

            app(AuditLogger::class)->mfaOtpSuccess((int) $user->id, (string) $user->staff_name);

            // Reconstruct a fake LoginRequest-compatible object from the session values
            // stored in login() so completeLogin() can read the remember + category intent.
            return $this->completeLoginFromSession($request, $user);
        }

        RateLimiter::hit($throttleKey, 300);

        $messages = [
            'wrong'      => 'Incorrect code. Please try again.',
            'expired'    => 'The code has expired. Please request a new one.',
            'max_tries'  => 'Too many incorrect attempts. Please log in again.',
            'no_session' => 'Session expired. Please log in again.',
        ];

        $status = in_array($outcome, ['max_tries', 'no_session', 'expired'], true) ? 'reset' : 'error';

        app(AuditLogger::class)->mfaOtpFailed($pendingId, $outcome);

        return response()->json([
            'result'  => $status,
            'message' => $messages[$outcome] ?? 'Verification failed.',
        ]);
    }

    /**
     * Resend the OTP — rate-limited by session (max 3 resends per login attempt).
     */
    public function resendOtp(Request $request, MfaService $mfa): JsonResponse
    {
        $userId = $mfa->pendingUserId();
        if (! $userId) {
            return response()->json(['result' => '0', 'message' => 'Session expired. Please log in again.']);
        }

        $user = StaffMember::find($userId);
        if (! $user) {
            return response()->json(['result' => '0', 'message' => 'Session expired. Please log in again.']);
        }

        $sent = $mfa->resendOtp($user);

        if (! $sent) {
            return response()->json([
                'result'  => '0',
                'message' => 'Maximum resend limit reached. Please start the login again.',
            ]);
        }

        app(AuditLogger::class)->mfaOtpResent((int) $user->id, (string) $user->staff_name);

        return response()->json([
            'result'           => '1',
            'seconds_remaining' => MfaService::OTP_TTL_SECONDS,
            'resends_remaining' => MfaService::MAX_RESENDS - $mfa->resendsUsed(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->logout();

        PermissionService::clearCache(CurrentUser::roleId());

        // Also rotates the stored remember token, so the cookie on this (and any copied) device stops working.
        Auth::logout();
        Cookie::queue(Cookie::forget(RememberedLogin::CATEGORY_COOKIE));

        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Finish authentication: call Auth::login(), write session context, redirect.
     * Used when the device is trusted (no OTP needed) or no email is on file.
     */
    private function completeLogin(LoginRequest $request, StaffMember $user): JsonResponse
    {
        $remember = $this->shouldRemember($request);

        $this->invalidateOtherSessions($user);

        if ($remember) {
            Auth::setRememberDuration((int) config('auth.remember_minutes', 20160));
        }

        Auth::login($user, $remember);

        app(AuditLogger::class)->loginSuccess(
            (int) $user->id,
            (string) $user->staff_name,
            (int) $user->user_role_id
        );

        $this->rememberSessionContext($request, $user);
        $this->rememberCategory($request, $remember);
        PermissionService::clearCache((int) $user->user_role_id);

        $hasDashboard = UserRolePermission::where('user_role_id', $user->user_role_id)
            ->where('permission', 'm_dashboard')
            ->exists();

        return response()->json([
            'result'   => '1',
            'redirect' => route($hasDashboard ? 'dashboard' : 'opd.index'),
        ]);
    }

    /**
     * Finish authentication after a successful OTP, using values stored in the
     * session during login() (remember preference + patient category).
     */
    private function completeLoginFromSession(Request $request, StaffMember $user): JsonResponse
    {
        $remember   = (bool) session('mfa_wants_remember', false);
        $categoryId = session('mfa_patient_category_id');

        session()->forget(['mfa_wants_remember', 'mfa_patient_category_id']);

        $this->invalidateOtherSessions($user);

        if ($remember) {
            Auth::setRememberDuration((int) config('auth.remember_minutes', 20160));
        }

        Auth::login($user, $remember);

        app(AuditLogger::class)->loginSuccess(
            (int) $user->id,
            (string) $user->staff_name,
            (int) $user->user_role_id
        );

        // Write session context manually (mirrors rememberSessionContext).
        $sess = [
            'logged_in_user_id' => $user->id,
            'user_role_id'      => (int) $user->user_role_id,
            'display_name'      => $user->staff_name,
        ];
        if ($categoryId !== null) {
            $sess['patient_category_id']        = (int) $categoryId;
            $sess['logged_in_patient_category_id'] = (int) $categoryId;
        }
        session($sess);

        // Category cookie mirrors remember-me duration.
        if ($remember && $categoryId !== null) {
            Cookie::queue(RememberedLogin::CATEGORY_COOKIE, (string) (int) $categoryId, (int) config('auth.remember_minutes', 20160));
        } else {
            Cookie::queue(Cookie::forget(RememberedLogin::CATEGORY_COOKIE));
        }

        PermissionService::clearCache((int) $user->user_role_id);

        $hasDashboard = UserRolePermission::where('user_role_id', $user->user_role_id)
            ->where('permission', 'm_dashboard')
            ->exists();

        return response()->json([
            'result'   => '1',
            'redirect' => route($hasDashboard ? 'dashboard' : 'opd.index'),
        ]);
    }

    /**
     * Whether to keep this device signed in. Needs staff_members.remember_token, which a database imported from the legacy
     * schema may not have yet (database/migrations/2026_10_21_000002...); without it the box is ignored instead of the login
     * failing with a database error.
     */
    private function shouldRemember(LoginRequest $request): bool
    {
        if (! $request->wantsRemember()) {
            return false;
        }

        if (! Schema::hasColumn('staff_members', 'remember_token')) {
            Log::warning('Remember Me ignored: staff_members.remember_token is missing; run the remember_token upgrade SQL/migration.');

            return false;
        }

        return true;
    }

    /** The chosen patient category outlives the session only as long as the remember cookie does. */
    private function rememberCategory(LoginRequest $request, bool $remember): void
    {
        if ($remember && $request->filled('patient_category_id')) {
            Cookie::queue(RememberedLogin::CATEGORY_COOKIE, (string) (int) $request->input('patient_category_id'), (int) config('auth.remember_minutes', 20160));

            return;
        }

        Cookie::queue(Cookie::forget(RememberedLogin::CATEGORY_COOKIE));
    }

    /**
     * Delete all existing DB sessions for this user so only one active session
     * exists at a time (concurrent session control — HDMP recommendation).
     * The current request's session is not affected; Auth::login() will
     * regenerate it immediately after this call.
     */
    private function invalidateOtherSessions(StaffMember $user): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        } catch (\Throwable $e) {
            // Non-fatal — log and continue. A failed purge must never block login.
            Log::warning('AuthController: could not purge old sessions', [
                'staff_id'  => $user->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function findActiveStaff(string $userName): ?StaffMember
    {
        if ($userName === '') {
            return null;
        }

        return StaffMember::where(function ($query) use ($userName) {
            $query->where('email', $userName)->orWhere('mobile_number', $userName);
        })->where('active', 'Yes')->first();
    }

    /** Checks the password; upgrades a legacy plain-text one to a hash on success. */
    private function passwordMatches(StaffMember $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $stored = (string) $user->password;

        if (str_starts_with($stored, '$2y$')) {
            return Hash::check($password, $stored);
        }

        // Legacy plain-text password.
        if (! hash_equals($stored, $password)) {
            return false;
        }

        $user->password = Hash::make($password);
        $user->save();

        return true;
    }

    /**
     * The rest of the application reads these keys (role for permissions, the chosen patient category, the name in the
     * header). They are derived from the authenticated user here, once, at login.
     */
    private function rememberSessionContext(LoginRequest $request, StaffMember $user): void
    {
        $session = [
            'logged_in_user_id' => $user->id,
            'user_role_id'      => (int) $user->user_role_id,
            'display_name'      => $user->staff_name,
        ];

        // The patient category chosen on the login form decides which category's data the user works with.
        if ($request->has('patient_category_id')) {
            $categoryId = (int) $request->input('patient_category_id');
            $session['patient_category_id']           = $categoryId;
            $session['logged_in_patient_category_id'] = $categoryId;
        }

        session($session);
    }
}
