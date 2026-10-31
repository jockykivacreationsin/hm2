<?php

namespace App\Services\Abdm;

use App\Models\Patient;
use App\Services\AbdmTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ABDM V3 Profile Share / Scan-and-Share and Running-Token-Status business logic.
 * Ported from bmhabdm/abdm/opd_share.php and bmhabdm/abdm/running_token.php.
 *
 * When a patient scans this facility's QR code in their ABHA app:
 *   1. Find the existing patient by ABHA Number / ABHA Address ONLY (the legacy deliberately never
 *      matches on mobile/name: a family shares one phone, and nobody is there to confirm a guess)
 *   2. Get or create today's OPD visit (per doctor, per day) with a sequential token number
 *   3. Send the on-share acknowledgement back to ABDM with the token
 *
 * The callbacks are unauthenticated by ABDM design, so every field is treated as untrusted:
 * type-checked, length-bounded and never written to the logs (ids and counts only).
 *
 * Result arrays carry an HTTP status + legacy error code so the controller can answer ABDM exactly
 * like the legacy endpoints: 400 INVALID_*, 500 DB_ERROR / ACK_FAILED, 202 ACCEPTED.
 */
class OpdShareService
{
    /** How long a REQUEST-ID's issued token label is remembered, so an ABDM retry gets the same label. */
    private const REQUEST_CACHE_TTL = 3600;

    public function __construct(private AbdmTokenService $tokenSvc) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Profile share
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle a profile-share webhook payload.
     *
     * @param array       $data       Decoded JSON body from ABDM's callback
     * @param string      $requestId  The (already validated) REQUEST-ID of the incoming request
     * @param string|null $facilityId Request-local facility (HIP) id, used for log correlation only
     * @return array{success:bool, status:int, code?:string, error?:string, details?:?string, opd_id?:int,
     *               token_number?:int, token_label?:string, patient_id?:int}
     */
    public function handleShare(array $data, string $requestId, ?string $facilityId = null): array
    {
        $metadata = is_array($data['metaData'] ?? null) ? $data['metaData'] : [];
        $patient  = is_array($data['profile']['patient'] ?? null) ? $data['profile']['patient'] : null;

        // context = consultant_doctor_id (staff_members.id) per legacy convention
        $contextRaw = $this->scalarString($metadata['context'] ?? '', 20);
        if (!preg_match('/^\d{1,9}$/', $contextRaw) || (int) $contextRaw <= 0) {
            return $this->fail(400, 'MISSING_CONTEXT', 'metaData.context (doctor id) is required');
        }
        $doctorId = (int) $contextRaw;

        if ($patient === null) {
            return $this->fail(400, 'INVALID_PROFILE', 'profile.patient is required');
        }

        $dob = $this->parseDob(
            $this->intValue($patient['dayOfBirth'] ?? 0),
            $this->intValue($patient['monthOfBirth'] ?? 0),
            $this->intValue($patient['yearOfBirth'] ?? 0)
        );

        $patientData = [
            'name'          => $this->cleanText($patient['name'] ?? '', 255),
            'gender'        => $this->normaliseGender($patient['gender'] ?? null),
            'mobile'        => $this->normaliseMobile($patient['phoneNumber'] ?? ''),
            'date_of_birth' => $dob,
            'age'           => $this->computeAge($dob, $patient['yearOfBirth'] ?? null),
            'address'       => $this->buildAddress($patient['address'] ?? null),
            'abha_number'   => $this->normaliseAbhaNumber($patient['abhaNumber'] ?? ''),
            'abha_address'  => $this->normaliseAbhaAddress($patient['abhaAddress'] ?? ''),
        ];

        // A share without any usable ABHA identity can neither be matched (so repeats would create
        // duplicate patients) nor be acknowledged (ABDM needs an abhaAddress).
        if ($patientData['abha_number'] === '' && $patientData['abha_address'] === '') {
            return $this->fail(400, 'INVALID_PROFILE', 'A valid abhaNumber or abhaAddress is required');
        }

        try {
            $result = DB::transaction(function () use ($patientData, $doctorId) {
                // Per-doctor mutex: serialises token numbering even for the doctor's first visit of
                // the day, where MAX() has no row to lock (MySQL gap locks do not cover that case).
                if (DB::table('staff_members')->where('id', $doctorId)->lockForUpdate()->value('id') === null) {
                    throw new \InvalidArgumentException('UNKNOWN_DOCTOR');
                }

                $patientResult = $this->findOrCreatePatient($patientData, $doctorId);
                $opdResult     = $this->getOrCreateOpdVisit($patientResult['patient_id'], $doctorId);

                return $opdResult + ['patient_id' => $patientResult['patient_id'], 'patient_created' => $patientResult['created']];
            }, 3);
        } catch (\InvalidArgumentException $e) {
            return $this->fail(400, 'MISSING_CONTEXT', 'metaData.context does not identify a known doctor');
        } catch (\Throwable $e) {
            // Exception messages from the DB layer embed SQL bindings (patient data) - log the class only.
            Log::error('ABDM profile-share DB error', ['request_id' => $requestId, 'exception' => get_class($e), 'code' => $e->getCode()]);
            return $this->fail(500, 'DB_ERROR', 'Could not register the visit');
        }

        $tokenLabel = $this->tokenLabelFor($requestId, $result);

        $abhaForAck = $patientData['abha_address'] !== '' ? $patientData['abha_address'] : $patientData['abha_number'];
        $ack = $this->postAck(config('abdm.profile_on_share_url'), [
            'acknowledgement' => [
                'status'      => 'SUCCESS',
                'abhaAddress' => $abhaForAck,
                'profile'     => [
                    'context'     => $contextRaw,
                    'tokenNumber' => $tokenLabel,
                    'expiry'      => (string) max(1, (int) config('abdm.profile_share_ack_expiry_seconds', 1800)),
                ],
            ],
            'response' => ['requestId' => $requestId],
        ]);

        $base = [
            'opd_id'       => $result['opd_id'],
            'token_number' => $result['token_number'],
            'token_label'  => $tokenLabel,
            'patient_id'   => $result['patient_id'],
        ];

        if (!$ack['success']) {
            // The OPD visit is real and committed - only the outbound acknowledgement failed.
            Log::error('ABDM profile-share ack failed', [
                'request_id' => $requestId, 'facility' => $facilityId, 'opd_id' => $result['opd_id'],
                'http_code' => $ack['http_code'] ?? null, 'reason' => $ack['reason'] ?? null,
            ]);
            return $base + ['success' => false, 'status' => 500, 'code' => 'ACK_FAILED',
                'error' => 'OPD created but the ABDM acknowledgement failed', 'details' => $ack['error'] ?? null];
        }

        Log::info('ABDM profile-share processed', [
            'request_id' => $requestId, 'facility' => $facilityId, 'patient_id' => $result['patient_id'],
            'patient_created' => $result['patient_created'], 'opd_id' => $result['opd_id'],
            'token' => $result['token_number'], 'visit_created' => $result['created'],
        ]);

        return $base + ['success' => true, 'status' => 202];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Running token status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle a running-token status callback: report the lowest token of today that is still pending
     * for the doctor given in the TOP-LEVEL "context" (not the highest issued), or "0" if the queue is empty.
     *
     * @param string $hipId Request-local facility id echoed back in token.hipId
     * @return array{success:bool, status:int, code?:string, error?:string, details?:?string, running_token?:string}
     */
    public function handleRunningToken(array $body, string $requestId, string $hipId): array
    {
        $contextRaw = $this->scalarString($body['context'] ?? '', 20);
        if (!preg_match('/^\d{1,9}$/', $contextRaw) || (int) $contextRaw <= 0) {
            return $this->fail(400, 'MISSING_CONTEXT', 'context (doctor id) is required');
        }
        $doctorId = (int) $contextRaw;

        try {
            [$start, $end] = $this->todayRange();
            $current = DB::table('opds')
                ->where('consultant_doctor_id', $doctorId)
                ->where('opd_date_time', '>=', $start)
                ->where('opd_date_time', '<', $end)
                // 'New' = legacy "not consulted yet"; this app's manual OPD create flow stores 'Active'.
                ->whereIn('status', ['New', 'Active'])
                ->orderBy('token_number')
                ->first(['id', 'token_number']);
        } catch (\Throwable $e) {
            Log::error('ABDM running-token DB error', ['request_id' => $requestId, 'exception' => get_class($e), 'code' => $e->getCode()]);
            return $this->fail(500, 'DB_ERROR', 'Could not read the queue');
        }

        $running = $current ? (string) $current->token_number : '0';

        $ack = $this->postAck(config('abdm.running_token_on_status_url'), [
            'token' => [
                'hipId'                            => $hipId,
                'context'                          => (string) $doctorId,
                'runningTokenNumber'               => $running,
                'averageTokenServiceTimeInMinutes' => max(1, (int) config('abdm.running_token_avg_service_minutes', 5)),
            ],
            'response' => ['requestId' => $requestId],
        ]);

        if (!$ack['success']) {
            Log::error('ABDM running-token ack failed', [
                'request_id' => $requestId, 'doctor_id' => $doctorId,
                'http_code' => $ack['http_code'] ?? null, 'reason' => $ack['reason'] ?? null,
            ]);
            return ['success' => false, 'status' => 500, 'code' => 'ACK_FAILED', 'running_token' => $running,
                'error' => 'Running token computed but the ABDM acknowledgement failed', 'details' => $ack['error'] ?? null];
        }

        Log::info('ABDM running-token processed', ['request_id' => $requestId, 'doctor_id' => $doctorId, 'running_token' => $running]);
        return ['success' => true, 'status' => 202, 'running_token' => $running];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Find or create patient (ABHA-based match only - no fuzzy demographics)
    // ─────────────────────────────────────────────────────────────────────────

    private function findOrCreatePatient(array $p, int $createdBy): array
    {
        $existing = $this->findExistingPatient($p['abha_number'], $p['abha_address']);

        if (!$existing) {
            try {
                $new = DB::transaction(fn () => Patient::create([
                    'patient_category_id'     => 1, // General - patient_categories
                    'patient_name'            => $p['name'],
                    'guardian_name'           => '',
                    'gender'                  => $p['gender'],
                    'date_of_birth'           => $p['date_of_birth'],
                    'age'                     => $p['age'],
                    'blood_group'             => '',
                    'mobile_number'           => $p['mobile'],
                    'alternate_mobile_number' => '',
                    'address'                 => $p['address'],
                    'esm_name'                => '',
                    'card_number'             => '',
                    'service_number'          => '',
                    'claim_id'                => '',
                    'photo'                   => '',
                    'aadhaar_number'          => '', // never present in a profile-share payload
                    // NULL (not '') when absent: both columns carry a UNIQUE index.
                    'abha_number'             => $p['abha_number'] !== '' ? $p['abha_number'] : null,
                    'abha_address'            => $p['abha_address'] !== '' ? $p['abha_address'] : null,
                    'status'                  => 'New',
                    'created_on'              => now()->toDateTimeString(),
                    'user_id'                 => $createdBy, // no logged-in user on a webhook: legacy uses the doctor id
                ]));

                return ['patient_id' => (int) $new->id, 'created' => true];
            } catch (QueryException $e) {
                // Lost a race against a concurrent share for the same ABHA: use the winner's row.
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->findExistingPatient($p['abha_number'], $p['abha_address']);
                if (!$existing) {
                    throw $e;
                }
            }
        }

        // "Update if available": only overwrite a field when this scan supplied a non-blank value.
        $updates = array_filter([
            'patient_name'  => $p['name'] !== '' ? $p['name'] : null,
            'mobile_number' => $p['mobile'] !== '' ? $p['mobile'] : null,
            'date_of_birth' => $p['date_of_birth'],
            'age'           => $p['age'] > 0 ? $p['age'] : null,
            'address'       => $p['address'] !== '' ? $p['address'] : null,
        ], fn ($v) => $v !== null);

        if ($updates) {
            $existing->update($updates);
        }

        return ['patient_id' => (int) $existing->id, 'created' => false];
    }

    private function findExistingPatient(string $abhaNumber, string $abhaAddress): ?Patient
    {
        if ($abhaNumber !== '') {
            // Stored hyphenated (91-1234-5678-9012) by the legacy app, but tolerate digits-only rows.
            $found = Patient::whereIn('abha_number', [$abhaNumber, str_replace('-', '', $abhaNumber)])->orderBy('id')->first();
            if ($found) {
                return $found;
            }
        }
        if ($abhaAddress !== '') {
            return Patient::whereRaw('LOWER(abha_address) = ?', [$abhaAddress])->orderBy('id')->first();
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Get or create today's OPD visit (reuse if patient already has one today)
    // Must run inside the caller's transaction, after the doctor-row lock was taken.
    // ─────────────────────────────────────────────────────────────────────────

    private function getOrCreateOpdVisit(int $patientId, int $doctorId): array
    {
        [$start, $end] = $this->todayRange();

        $existing = DB::table('opds')
            ->where('patient_id', $patientId)
            ->where('consultant_doctor_id', $doctorId)
            ->where('opd_date_time', '>=', $start)
            ->where('opd_date_time', '<', $end)
            ->orderByDesc('id')
            ->first(['id', 'token_number']);

        if ($existing) {
            return ['opd_id' => (int) $existing->id, 'token_number' => (int) $existing->token_number, 'created' => false];
        }

        // Per-doctor, per-day sequence (shared with the manual "Create OPD" flow).
        $tokenNumber = (int) (DB::table('opds')
            ->where('consultant_doctor_id', $doctorId)
            ->where('opd_date_time', '>=', $start)
            ->where('opd_date_time', '<', $end)
            ->lockForUpdate()
            ->max('token_number') ?? 0) + 1;

        $now = now()->toDateTimeString();

        $opdId = DB::table('opds')->insertGetId([
            'opd_date_time'        => $now,
            'token_number'         => $tokenNumber,
            'patient_id'           => $patientId,
            // Legacy: id_field_settings.opd_department_id (0 when the settings row is missing)
            'charge_department_id' => (int) (DB::table('id_field_settings')->where('id', 1)->value('opd_department_id') ?? 0),
            'consultant_doctor_id' => $doctorId,
            'height' => '', 'weight' => '', 'bp' => '', 'pulse' => '',
            'temperature' => '', 'respiration' => '', 'spo2' => '',
            'symptoms' => '', 'remarks' => '',
            'opd_charges'  => 0,
            'payment_mode' => '',
            'status'       => 'New',
            'source'       => 'abha',
            'created_on'   => $now,
            'user_id'      => $doctorId,
        ]);

        // opd_doctors bookkeeping row (mirrors manual OPD create flow)
        DB::table('opd_doctors')->insert([
            'doctor_id'  => $doctorId,
            'opd_id'     => $opdId,
            'created_on' => $now,
            'user_id'    => $doctorId,
        ]);

        return ['opd_id' => $opdId, 'token_number' => $tokenNumber, 'created' => true];
    }

    /**
     * 'Token: N' for a fresh visit, 'Already Generated : N' for a repeat scan. A retry of the SAME
     * REQUEST-ID (e.g. after ACK_FAILED) gets the label it was first issued with.
     */
    private function tokenLabelFor(string $requestId, array $visit): string
    {
        $key  = 'abdm:share:' . sha1($requestId);
        $fresh = 'Token: ' . $visit['token_number'];

        try {
            if ($visit['created']) {
                Cache::put($key, ['opd_id' => $visit['opd_id'], 'label' => $fresh], self::REQUEST_CACHE_TTL);
                return $fresh;
            }
            $cached = Cache::get($key);
            if (is_array($cached) && ($cached['opd_id'] ?? null) === $visit['opd_id'] && is_string($cached['label'] ?? null)) {
                return $cached['label'];
            }
        } catch (\Throwable $e) {
            // Cache is best-effort only.
        }

        return $visit['created'] ? $fresh : 'Already Generated : ' . $visit['token_number'];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Outbound acknowledgement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST an acknowledgement to the ABDM gateway.
     * Headers match legacy (REQUEST-ID, TIMESTAMP, X-CM-ID, Bearer). Legacy sends no X-HIP-ID on these
     * two acknowledgements - the facility is identified by response.requestId / token.hipId.
     *
     * @return array{success:bool, http_code?:int, error?:?string, reason?:string}
     */
    private function postAck(string $url, array $payload): array
    {
        try {
            $token = $this->tokenSvc->getAccessToken();
        } catch (\Throwable $e) {
            $token = null;
        }
        if (!$token) {
            return ['success' => false, 'reason' => 'no_token', 'error' => 'Failed to generate ABDM access token'];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(30)
                ->withHeaders([
                    'REQUEST-ID' => (string) Str::uuid(),
                    'TIMESTAMP'  => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                    'X-CM-ID'    => (string) config('abdm.cm_id', 'sbx'),
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'reason' => 'transport:' . get_class($e), 'error' => 'ABDM gateway unreachable'];
        }

        if ($response->successful()) {
            return ['success' => true, 'http_code' => $response->status()];
        }

        $decoded = $response->json();
        $message = is_array($decoded) ? ($decoded['message'] ?? $decoded['error']['message'] ?? $decoded['error'] ?? null) : null;
        $message = is_string($message) ? mb_substr($message, 0, 200) : 'Acknowledgement failed';

        return ['success' => false, 'http_code' => $response->status(), 'reason' => 'http_' . $response->status(), 'error' => $message];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function fail(int $status, string $code, string $message): array
    {
        return ['success' => false, 'status' => $status, 'code' => $code, 'error' => $message];
    }

    /** @return array{0:string,1:string} [today 00:00:00, tomorrow 00:00:00] in the app timezone (legacy: Asia/Calcutta) */
    private function todayRange(): array
    {
        $start = now()->startOfDay();
        return [$start->toDateTimeString(), $start->copy()->addDay()->toDateTimeString()];
    }

    private function scalarString(mixed $v, int $max): string
    {
        return is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
    }

    private function intValue(mixed $v): int
    {
        return is_int($v) || (is_string($v) && preg_match('/^\d{1,6}$/', trim($v))) ? (int) $v : 0;
    }

    /** Single-line, tag-free, control-character-free text bounded to the column width. */
    private function cleanText(mixed $v, int $max): string
    {
        if (!is_scalar($v)) {
            return '';
        }
        $s = strip_tags((string) $v);
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';
        return mb_substr(trim($s), 0, $max);
    }

    /** ABHA number: 14 digits, stored as 91-1234-5678-9012. Anything else is treated as absent. */
    private function normaliseAbhaNumber(mixed $v): string
    {
        $digits = preg_replace('/\D/', '', $this->scalarString($v, 30)) ?? '';
        if (strlen($digits) !== 14) {
            return '';
        }
        return substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4);
    }

    /** ABHA address (name@domain), lower-cased; malformed values are treated as absent. */
    private function normaliseAbhaAddress(mixed $v): string
    {
        $s = mb_strtolower($this->scalarString($v, 255));
        return preg_match('/^[a-z0-9._\-]{1,100}@[a-z0-9.\-]{1,50}$/', $s) ? $s : '';
    }

    /** Digits only (legacy); a +91 / 0 trunk prefix is dropped so the number matches the app's 10-digit entries. */
    private function normaliseMobile(mixed $v): string
    {
        $d = preg_replace('/\D/', '', $this->scalarString($v, 30)) ?? '';
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        } elseif (strlen($d) === 11 && $d[0] === '0') {
            $d = substr($d, 1);
        }
        return substr($d, 0, 50);
    }

    /** Full real calendar date only, never in the future (legacy abdm_parse_dob_to_mysql_date). */
    private function parseDob(int $day, int $month, int $year): ?string
    {
        if ($day <= 0 || $month <= 0 || $year < 1900 || !checkdate($month, $day, $year)) {
            return null;
        }
        $dob = sprintf('%04d-%02d-%02d', $year, $month, $day);
        return $dob > now()->toDateString() ? null : $dob;
    }

    private function computeAge(?string $mysqlDob, mixed $yearOfBirth): int
    {
        if ($mysqlDob) {
            return (int) now()->startOfDay()->diffInYears(\Carbon\Carbon::parse($mysqlDob)->startOfDay(), true);
        }
        $year = $this->intValue($yearOfBirth);
        if ($year >= 1900 && $year <= (int) now()->year) {
            return (int) now()->year - $year;
        }
        return 0;
    }

    private function normaliseGender(mixed $g): string
    {
        return match (strtoupper($this->scalarString($g, 10))) {
            'M', 'MALE'   => 'Male',
            'F', 'FEMALE' => 'Female',
            'O', 'OTHER'  => 'Other',
            default       => '',
        };
    }

    /** Legacy abdm_share_build_address: "line", with the pincode appended only if not already in it. */
    private function buildAddress(mixed $address): string
    {
        if (!is_array($address) || empty($address)) {
            return '';
        }
        $line    = $this->cleanText($address['line'] ?? '', 255);
        $pincode = $this->cleanText($address['pincode'] ?? '', 10);
        if ($line !== '' && $pincode !== '' && !str_contains($line, $pincode)) {
            $line .= ' - ' . $pincode;
        }
        return mb_substr($line, 0, 255);
    }
}
