<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\CurrentUser;
use App\Support\Redact;
use Illuminate\Support\Facades\Log;

/**
 * HDMP §9 / ABDM PHI Audit Logger.
 *
 * Every method is fail-safe: a database failure while writing an audit entry
 * is caught and logged to the application error log — it never propagates to
 * the caller and never breaks a request.
 *
 * Raw passwords, tokens, OTPs, and key material are redacted via Redact::value()
 * before any data is persisted.
 *
 * Usage example:
 *   app(AuditLogger::class)->loginSuccess($user->id, $user->staff_name, $user->user_role_id);
 *   app(AuditLogger::class)->viewPatient($patient->id, $patient->patient_name);
 */
class AuditLogger
{
    // ──────────────────────────────────────────────────────────────────────────
    // Auth events
    // ──────────────────────────────────────────────────────────────────────────

    /** Called after Auth::login() succeeds — user context is now available. */
    public function loginSuccess(int $staffId, string $staffName, int $roleId): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'role_id'        => $roleId,
            'event_category' => 'auth',
            'action'         => 'login_success',
            'description'    => "Login succeeded for {$staffName}",
            'status'         => 'success',
        ]);
    }

    /**
     * Called when credentials do not match — no authenticated user yet;
     * we record the attempted username as the description for traceability.
     */
    public function loginFailure(string $attemptedUsername): void
    {
        $this->write([
            'staff_id'       => null,
            'staff_name'     => null,
            'role_id'        => null,
            'event_category' => 'auth',
            'action'         => 'login_failure',
            'description'    => 'Login failed for: ' . mb_substr($attemptedUsername, 0, 255),
            'status'         => 'failure',
        ]);
    }

    /** Called when the rate-limiter blocks a login attempt. */
    public function loginBlocked(string $attemptedUsername, int $secondsLocked): void
    {
        $this->write([
            'staff_id'       => null,
            'staff_name'     => null,
            'role_id'        => null,
            'event_category' => 'auth',
            'action'         => 'login_blocked',
            'description'    => "Login blocked for {$attemptedUsername} — locked for {$secondsLocked}s",
            'status'         => 'blocked',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // MFA events
    // ──────────────────────────────────────────────────────────────────────────

    /** OTP email successfully sent — user must now verify. */
    public function mfaOtpSent(int $staffId, string $staffName): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'event_category' => 'auth',
            'action'         => 'mfa_otp_sent',
            'description'    => "MFA OTP sent to {$staffName}",
            'status'         => 'success',
        ]);
    }

    /** OTP verified correctly — full login will proceed. */
    public function mfaOtpSuccess(int $staffId, string $staffName): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'event_category' => 'auth',
            'action'         => 'mfa_otp_success',
            'description'    => "MFA OTP verified for {$staffName}",
            'status'         => 'success',
        ]);
    }

    /** Wrong OTP, expired OTP, or session expired. */
    public function mfaOtpFailed(?int $staffId, string $reason): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'event_category' => 'auth',
            'action'         => 'mfa_otp_failed',
            'description'    => "MFA OTP failed (reason: {$reason})" . ($staffId ? " for staff #{$staffId}" : ''),
            'status'         => 'failure',
        ]);
    }

    /** Rate-limiter blocked the OTP verify endpoint. */
    public function mfaOtpBlocked(?int $staffId): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'event_category' => 'auth',
            'action'         => 'mfa_otp_blocked',
            'description'    => 'MFA OTP verify blocked by rate-limiter' . ($staffId ? " for staff #{$staffId}" : ''),
            'status'         => 'blocked',
        ]);
    }

    /** OTP resent by the user. */
    public function mfaOtpResent(int $staffId, string $staffName): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'event_category' => 'auth',
            'action'         => 'mfa_otp_resent',
            'description'    => "MFA OTP resent for {$staffName}",
            'status'         => 'success',
        ]);
    }

    /** Trusted-device cookie present — MFA step skipped. */
    public function mfaSkipped(int $staffId, string $staffName): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'event_category' => 'auth',
            'action'         => 'mfa_skipped',
            'description'    => "MFA skipped for {$staffName} (trusted device)",
            'status'         => 'success',
        ]);
    }

    /** MFA could not be enforced (e.g. no email on file) — login allowed with a warning. */
    public function mfaBypassed(int $staffId, string $staffName, string $reason): void
    {
        $this->write([
            'staff_id'       => $staffId,
            'staff_name'     => $staffName,
            'event_category' => 'auth',
            'action'         => 'mfa_bypassed',
            'description'    => "MFA bypassed for {$staffName} (reason: {$reason})",
            'status'         => 'warning',
        ]);
    }

    /** Called before Auth::logout(). */
    public function logout(): void
    {
        $this->write([
            'event_category' => 'auth',
            'action'         => 'logout',
            'description'    => 'User logged out: ' . (CurrentUser::displayName() ?? 'unknown'),
            'status'         => 'success',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Patient events
    // ──────────────────────────────────────────────────────────────────────────

    public function viewPatient(int $patientId, string $patientName): void
    {
        $this->write([
            'event_category' => 'patient',
            'action'         => 'view',
            'resource_type'  => 'patient',
            'resource_id'    => (string) $patientId,
            'description'    => "Viewed patient #{$patientId} — {$patientName}",
        ]);
    }

    public function createPatient(int $patientId, string $patientName): void
    {
        $this->write([
            'event_category' => 'patient',
            'action'         => 'create',
            'resource_type'  => 'patient',
            'resource_id'    => (string) $patientId,
            'description'    => "Created patient #{$patientId} — {$patientName}",
        ]);
    }

    public function updatePatient(int $patientId, string $patientName, array $oldValues, array $newValues): void
    {
        $this->write([
            'event_category' => 'patient',
            'action'         => 'update',
            'resource_type'  => 'patient',
            'resource_id'    => (string) $patientId,
            'description'    => "Updated patient #{$patientId} — {$patientName}",
            'old_values'     => Redact::value($oldValues),
            'new_values'     => Redact::value($newValues),
        ]);
    }

    public function deletePatient(int $patientId, string $patientName): void
    {
        $this->write([
            'event_category' => 'patient',
            'action'         => 'delete',
            'resource_type'  => 'patient',
            'resource_id'    => (string) $patientId,
            'description'    => "Deleted patient #{$patientId} — {$patientName}",
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Clinical record views (OPD / IPD / DCD)
    // ──────────────────────────────────────────────────────────────────────────

    public function viewOpd(int $opdId, ?int $patientId = null, ?string $patientName = null): void
    {
        $this->write([
            'event_category' => 'opd',
            'action'         => 'view',
            'resource_type'  => 'opd',
            'resource_id'    => (string) $opdId,
            'description'    => $this->clinicalDesc('OPD', $opdId, $patientId, $patientName),
        ]);
    }

    public function viewIpd(int $ipdId, ?int $patientId = null, ?string $patientName = null): void
    {
        $this->write([
            'event_category' => 'ipd',
            'action'         => 'view',
            'resource_type'  => 'ipd',
            'resource_id'    => (string) $ipdId,
            'description'    => $this->clinicalDesc('IPD', $ipdId, $patientId, $patientName),
        ]);
    }

    public function viewDcd(int $dcdId, ?int $patientId = null, ?string $patientName = null): void
    {
        $this->write([
            'event_category' => 'dcd',
            'action'         => 'view',
            'resource_type'  => 'dcd',
            'resource_id'    => (string) $dcdId,
            'description'    => $this->clinicalDesc('DCD', $dcdId, $patientId, $patientName),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PDF downloads
    // ──────────────────────────────────────────────────────────────────────────

    public function downloadPdf(string $filename): void
    {
        $this->write([
            'event_category' => 'pdf',
            'action'         => 'download',
            'resource_type'  => 'pdf_file',
            'resource_id'    => $filename,
            'description'    => "Downloaded PDF: {$filename}",
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Database backup events
    // ──────────────────────────────────────────────────────────────────────────

    public function createBackup(string $filename): void
    {
        $this->write([
            'event_category' => 'backup',
            'action'         => 'create',
            'resource_type'  => 'backup_file',
            'resource_id'    => $filename,
            'description'    => "Created database backup: {$filename}",
        ]);
    }

    public function downloadBackup(string $filename): void
    {
        $this->write([
            'event_category' => 'backup',
            'action'         => 'download',
            'resource_type'  => 'backup_file',
            'resource_id'    => $filename,
            'description'    => "Downloaded database backup: {$filename}",
        ]);
    }

    public function deleteBackup(string $filename, bool $succeeded): void
    {
        $this->write([
            'event_category' => 'backup',
            'action'         => 'delete',
            'resource_type'  => 'backup_file',
            'resource_id'    => $filename,
            'description'    => $succeeded
                ? "Deleted database backup: {$filename}"
                : "Delete attempted but backup not found: {$filename}",
            'status'         => $succeeded ? 'success' : 'failure',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Role / permission change events
    // ──────────────────────────────────────────────────────────────────────────

    public function createRole(int $roleId, string $roleName): void
    {
        $this->write([
            'event_category' => 'roles',
            'action'         => 'create',
            'resource_type'  => 'role',
            'resource_id'    => (string) $roleId,
            'description'    => "Created role #{$roleId}: {$roleName}",
        ]);
    }

    public function updateRole(int $roleId, string $roleName): void
    {
        $this->write([
            'event_category' => 'roles',
            'action'         => 'update',
            'resource_type'  => 'role',
            'resource_id'    => (string) $roleId,
            'description'    => "Updated role #{$roleId}: {$roleName}",
        ]);
    }

    public function deleteRole(int $roleId): void
    {
        $this->write([
            'event_category' => 'roles',
            'action'         => 'delete',
            'resource_type'  => 'role',
            'resource_id'    => (string) $roleId,
            'description'    => "Deleted role #{$roleId}",
        ]);
    }

    public function permissionChange(int $targetRoleId, string $targetRoleName, array $newPermissions): void
    {
        $this->write([
            'event_category' => 'roles',
            'action'         => 'permission_change',
            'resource_type'  => 'role',
            'resource_id'    => (string) $targetRoleId,
            'description'    => "Saved permissions for role #{$targetRoleId} ({$targetRoleName}): "
                . count($newPermissions) . ' permission(s)',
            'new_values'     => ['permissions' => array_values($newPermissions)],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Build a clinical description string, e.g. "Viewed OPD #42 — Ramesh Kumar (Patient #7)". */
    private function clinicalDesc(string $type, int $recordId, ?int $patientId, ?string $patientName): string
    {
        $desc = "Viewed {$type} #{$recordId}";
        if ($patientName || $patientId) {
            $desc .= ' — ' . ($patientName ?? 'Unknown');
            if ($patientId) {
                $desc .= " (Patient #{$patientId})";
            }
        }

        return $desc;
    }

    /**
     * Insert a single audit row.
     *
     * Always resolves the current request context (ip, user-agent, method, url) and
     * the current user context (staff_id, staff_name, role_id) — callers only need to
     * pass what is specific to the event.
     *
     * Wrapped in try/catch: a failure here must NEVER crash the calling request.
     *
     * @param array<string, mixed> $attributes
     */
    private function write(array $attributes): void
    {
        try {
            $request = request();

            $defaults = [
                // Caller-specific fields have priority; these are fallback defaults.
                'staff_id'       => CurrentUser::id(),
                'staff_name'     => CurrentUser::displayName(),
                'role_id'        => CurrentUser::roleId() ?: null,
                'resource_type'  => null,
                'resource_id'    => null,
                'description'    => null,
                'old_values'     => null,
                'new_values'     => null,
                'status'         => 'success',
                // Always captured from the live request.
                'ip_address'     => $request->ip() ?? '0.0.0.0',
                'user_agent'     => mb_substr((string) $request->userAgent(), 0, 500),
                'request_method' => $request->method(),
                'request_url'    => mb_substr($request->fullUrl(), 0, 1000),
                'created_at'     => now()->format('Y-m-d H:i:s.v'),
            ];

            // Caller attributes win over defaults (array_merge order: defaults first, caller second).
            AuditLog::create(array_merge($defaults, $attributes));
        } catch (\Throwable $e) {
            // Audit failure is never fatal — record it in the app error log only.
            Log::error('AuditLogger: failed to write audit row', [
                'exception' => $e->getMessage(),
                'event'     => $attributes['action'] ?? 'unknown',
                'category'  => $attributes['event_category'] ?? 'unknown',
            ]);
        }
    }
}
