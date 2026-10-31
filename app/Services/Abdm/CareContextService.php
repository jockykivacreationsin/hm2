<?php

namespace App\Services\Abdm;

use App\Services\AbdmTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Str;
use InvalidArgumentException;

class CareContextService
{
    public function __construct(private AbdmTokenService $tokenSvc) {}

    public function opdCareContextRef(int $opdId): string
    {
        return config('abdm.opd_ref_prefix', 'OPD') . str_pad((string) $opdId, 7, '0', STR_PAD_LEFT);
    }

    public function ipdCareContextRef(int $ipdId, string $hiType): string
    {
        return $this->existingRef('ipd_id', $ipdId, $hiType) ?? 'IPD' . str_pad((string) $ipdId, 7, '0', STR_PAD_LEFT);
    }

    public function dcdCareContextRef(int $dcdId, string $hiType): string
    {
        return $this->existingRef('dcd_id', $dcdId, $hiType) ?? 'DCD' . str_pad((string) $dcdId, 7, '0', STR_PAD_LEFT);
    }

    private function existingRef(string $field, int $id, string $hiType): ?string
    {
        return DB::table('abdm_care_contexts')->where($field, $id)->where('hi_type', $hiType)
            ->orderByDesc('id')->value('care_context_reference_number');
    }

    public function patientRef(int $patientId): string
    {
        return config('abdm.patient_ref_prefix', 'BMH') . str_pad((string) $patientId, 7, '0', STR_PAD_LEFT);
    }

    public function careContextDisplayName(string $module, object $visit): string
    {
        $dateField = match ($module) { 'opd' => 'opd_date_time', 'ipd' => 'admission_date_time', 'dcd' => 'dcd_date_time' };
        $doctor = trim((string) DB::table('staff_members')->where('id', $visit->consultant_doctor_id ?? null)->value('staff_name'));
        $label = strtoupper($module);
        $fallback = match ($module) { 'opd' => 'Visit', 'ipd' => 'Admission', 'dcd' => 'Visit' };
        $date = !empty($visit->$dateField) ? CarbonImmutable::parse($visit->$dateField)->format('d-M-Y') : date('d-M-Y');
        return $label . ' ' . ($doctor !== '' ? $doctor : $fallback) . ' ' . $date;
    }

    /**
     * Server-side HI types actually backed by data, using the legacy M2 availability rules.
     * Kept here (not a new class) because the app ships an authoritative classmap.
     *
     * @return string[]
     */
    public function availableHiTypes(string $module, int $id): array
    {
        $module = strtolower($module);
        if (!in_array($module, ['opd', 'ipd', 'dcd'], true)) {
            throw new InvalidArgumentException('Unsupported care-context module.');
        }
        // Legacy abdm_{opd,ipd,dcd}_check_available_data(): ANY data makes ALL 7 HI types
        // of the module linkable in a single care context, so data added later can be shared
        // without re-linking.
        $check = $this->dataCheck($module, $id);
        if (!$check['has_any']) {
            return [];
        }

        return array_values(array_unique((array) config('abdm.' . $module . '_hi_types')));
    }

    /**
     * Is there nothing left to link for this OPD visit / IPD admission / DCD visit? True once at least one care context
     * is linked and every HI type that has data is linked - the same rule the Link-to-ABDM modal uses for "existing",
     * so the overview hides its Link button exactly when the modal would have nothing to do. Data added later brings
     * an unlinked HI type back, and with it the button.
     */
    public function isFullyLinked(string $module, int $id): bool
    {
        $module = strtolower($module);
        if (!in_array($module, ['opd', 'ipd', 'dcd'], true)) {
            throw new InvalidArgumentException('Unsupported care-context module.');
        }

        $linked = DB::table('abdm_care_contexts')->where($module . '_id', $id)->where('status', 'linked')
            ->pluck('hi_type')->unique()->values()->all();

        return $linked !== [] && array_diff($this->availableHiTypes($module, $id), $linked) === [];
    }

    /**
     * The legacy data check of a module's encounter (what the Link-to-ABDM modal lists under "Data to be shared").
     *
     * @return array<string,mixed> has_any, message, details and the module's has_* flags
     */
    public function dataCheck(string $module, int $id): array
    {
        return match (strtolower($module)) {
            'opd'   => $this->opdDataCheck($id),
            'ipd'   => $this->ipdDataCheck($id),
            'dcd'   => $this->dcdDataCheck($id),
            default => throw new InvalidArgumentException('Unsupported care-context module.'),
        };
    }

    /**
     * Port of legacy abdm_opd_check_available_data(). Clinical data = symptoms, findings (remarks) or any vital;
     * like legacy, empty(trim(...)) is used, so a literal "0" is not data.
     *
     * @return array{has_clinical:bool, has_prescription:bool, has_diagnostic:bool, has_invoice:bool, has_any:bool, message:string, details:string[]}
     */
    public function opdDataCheck(int $opdId): array
    {
        $result = [
            'has_clinical' => false, 'has_prescription' => false, 'has_diagnostic' => false,
            'has_invoice' => false, 'has_immunization' => false, 'has_wellness' => false,
            'has_health_document' => false, 'has_any' => false, 'message' => '', 'details' => [],
        ];

        $visit = DB::table('opds')->where('id', $opdId)->first();
        if ($visit) {
            $filled = fn (string $column, bool $stripTags = false) => !empty(trim($stripTags ? strip_tags((string) ($visit->$column ?? '')) : (string) ($visit->$column ?? '')));
            $hasSymptoms = $filled('symptoms', true);
            $hasRemarks  = $filled('remarks', true);
            $hasVitals   = false;
            foreach (['height', 'weight', 'bp', 'pulse', 'temperature', 'respiration', 'spo2'] as $vital) {
                $hasVitals = $hasVitals || $filled($vital);
            }

            if ($hasSymptoms || $hasRemarks || $hasVitals) {
                $result['has_clinical'] = true;
                $parts = array_values(array_filter([$hasSymptoms ? 'symptoms' : null, $hasRemarks ? 'findings' : null, $hasVitals ? 'vitals' : null]));
                $result['details'][] = 'OP Consultation (' . implode(', ', $parts) . ')';
            }
        }

        if (DB::table('opd_prescriptions as p')->join('opd_medicines as m', 'm.opd_prescription_id', '=', 'p.id')->where('p.opd_id', $opdId)->exists()) {
            $result['has_prescription'] = true;
            $result['details'][] = 'Prescription (medicines)';
        }

        if (DB::table('opd_services as s')->join('charge_departments as cd', 'cd.id', '=', 's.charge_department_id')
            ->where('s.opd_id', $opdId)->where('cd.category', 'Lab')->exists()) {
            $result['has_diagnostic'] = true;
            $result['details'][] = 'Diagnostic Report (lab results)';
        }

        if (Schema::hasTable('billing_charges') && DB::table('billing_charges')->where('module_id', $opdId)->whereRaw("UPPER(module) = 'OPD'")->exists()) {
            $result['has_invoice'] = true;
            $result['details'][] = 'Invoice (billing charges)';
        }

        if (Schema::hasTable('immunization_records') && DB::table('immunization_records')->where('opd_id', $opdId)->exists()) {
            $result['has_immunization'] = true;
            $result['details'][] = 'Immunization Record';
        }

        if (Schema::hasTable('wellness_records') && DB::table('wellness_records')->where('opd_id', $opdId)->exists()) {
            $result['has_wellness'] = true;
            $result['details'][] = 'Wellness Record';
        }

        if (Schema::hasTable('patient_documents') && DB::table('patient_documents')->where('opd_id', $opdId)->where('status', '!=', 'entered-in-error')->exists()) {
            $result['has_health_document'] = true;
            $result['details'][] = 'Health Document';
        }

        $result['has_any'] = $result['has_clinical'] || $result['has_prescription'] || $result['has_diagnostic'] || $result['has_invoice']
            || $result['has_immunization'] || $result['has_wellness'] || $result['has_health_document'];
        $result['message'] = $result['has_any']
            ? 'Available data: ' . implode(', ', $result['details'])
            : 'No clinical data, prescriptions, lab results, or invoices found for this visit. Please add data before linking to ABDM.';

        return $result;
    }

    /**
     * Port of legacy abdm_dcd_check_available_data(). Prescription = admission prescriptions (dcd_medicines)
     * and/or discharge treatment advised.
     *
     * @return array{has_discharge:bool, has_prescription:bool, has_diagnostic:bool, has_invoice:bool, has_any:bool, message:string, details:string[]}
     */
    public function dcdDataCheck(int $dcdId): array
    {
        $result = [
            'has_discharge' => false, 'has_prescription' => false, 'has_diagnostic' => false,
            'has_invoice' => false, 'has_immunization' => false, 'has_wellness' => false,
            'has_health_document' => false, 'has_any' => false, 'message' => '', 'details' => [],
        ];

        if (DB::table('dcd_discharge')->where('dcd_id', $dcdId)->exists()) {
            $result['has_discharge'] = true;
            $result['details'][] = 'Discharge Summary';
        }

        $admissionRx = DB::table('dcd_prescriptions as p')->join('dcd_medicines as m', 'm.dcd_prescription_id', '=', 'p.id')->where('p.dcd_id', $dcdId)->exists();
        $dischargeRx = $this->dischargeMedicines('dcd', 'treatment_advised', $dcdId);
        if ($admissionRx || $dischargeRx) {
            $result['has_prescription'] = true;
            $result['details'][] = 'Prescription (' . implode(' + ', array_values(array_filter([$admissionRx ? 'admission' : null, $dischargeRx ? 'discharge' : null]))) . ')';
        }

        if (DB::table('dcd_services as s')->join('charge_departments as cd', 'cd.id', '=', 's.charge_department_id')
            ->where('s.dcd_id', $dcdId)->where('cd.category', 'Lab')->exists()) {
            $result['has_diagnostic'] = true;
            $result['details'][] = 'Diagnostic Report (lab results)';
        }

        if (Schema::hasTable('billing_charges') && DB::table('billing_charges')->where('module_id', $dcdId)->whereRaw("UPPER(module) = 'DCD'")->exists()) {
            $result['has_invoice'] = true;
            $result['details'][] = 'Invoice (billing charges)';
        }

        if (Schema::hasTable('immunization_records') && DB::table('immunization_records')->where('dcd_id', $dcdId)->exists()) {
            $result['has_immunization'] = true;
            $result['details'][] = 'Immunization Record';
        }

        if (Schema::hasTable('wellness_records') && DB::table('wellness_records')->where('dcd_id', $dcdId)->exists()) {
            $result['has_wellness'] = true;
            $result['details'][] = 'Wellness Record';
        }

        if (Schema::hasTable('patient_documents') && DB::table('patient_documents')->where('dcd_id', $dcdId)->where('status', '!=', 'entered-in-error')->exists()) {
            $result['has_health_document'] = true;
            $result['details'][] = 'Health Document';
        }

        $result['has_any'] = $result['has_discharge'] || $result['has_prescription'] || $result['has_diagnostic'] || $result['has_invoice']
            || $result['has_immunization'] || $result['has_wellness'] || $result['has_health_document'];
        $result['message'] = $result['has_any']
            ? 'Available data: ' . implode(', ', $result['details'])
            : 'No discharge summary, prescriptions, lab results, or invoices found for this visit. Please add data before linking to ABDM.';

        return $result;
    }

    private function dischargeMedicines(string $module, string $table, int $id): bool
    {
        return $this->dischargeMedicineCount($module, $table, $id) > 0;
    }

    private function dischargeMedicineCount(string $module, string $table, int $id): int
    {
        return DB::table($module . '_discharge_' . $table . ' as m')
            ->join($module . '_discharge as d', 'd.id', '=', 'm.discharge_id')->where('d.' . $module . '_id', $id)->count();
    }

    /**
     * What an IPD admission can share on ABDM - port of legacy abdm_ipd_check_available_data().
     * Prescription medicines are counted across the 4 legacy sources: doctor prescriptions, the treatment
     * chart, discharge medications given and discharge treatment advised.
     *
     * @return array{has_discharge:bool, has_prescription:bool, has_diagnostic:bool, has_invoice:bool, has_any:bool, message:string, details:string[]}
     */
    public function ipdDataCheck(int $ipdId): array
    {
        $result = [
            'has_discharge' => false, 'has_prescription' => false, 'has_diagnostic' => false,
            'has_invoice' => false, 'has_immunization' => false, 'has_wellness' => false,
            'has_health_document' => false, 'has_any' => false, 'message' => '', 'details' => [],
        ];

        if (DB::table('ipd_discharge')->where('ipd_id', $ipdId)->exists()) {
            $result['has_discharge'] = true;
            $result['details'][] = 'Discharge Summary';
        }

        $medicines = DB::table('ipd_prescriptions as p')
                ->join('ipd_medicines as m', 'm.ipd_prescription_id', '=', 'p.id')->where('p.ipd_id', $ipdId)->count()
            + DB::table('ipd_treatments as t')
                ->join('ipd_treatment_detail as d', 'd.ipd_treatment_id', '=', 't.id')->where('t.ipd_id', $ipdId)->count()
            + $this->dischargeMedicineCount('ipd', 'medication_given', $ipdId)
            + $this->dischargeMedicineCount('ipd', 'treatment_advised', $ipdId);
        if ($medicines > 0) {
            $result['has_prescription'] = true;
            $result['details'][] = 'Prescription (' . $medicines . ' medications)';
        }

        $hasLab = DB::table('ipd_services as s')
            ->join('charge_departments as cd', 'cd.id', '=', 's.charge_department_id')
            ->where('s.ipd_id', $ipdId)->where('cd.category', 'Lab')->exists();
        if ($hasLab) {
            $result['has_diagnostic'] = true;
            $result['details'][] = 'Diagnostic Report (lab results)';
        }

        if (Schema::hasTable('billing_charges') && DB::table('billing_charges')->where('module_id', $ipdId)->whereRaw("UPPER(module) = 'IPD'")->exists()) {
            $result['has_invoice'] = true;
            $result['details'][] = 'Invoice (billing charges)';
        }

        if (Schema::hasTable('immunization_records') && DB::table('immunization_records')->where('ipd_id', $ipdId)->exists()) {
            $result['has_immunization'] = true;
            $result['details'][] = 'Immunization Record';
        }

        if (Schema::hasTable('wellness_records') && DB::table('wellness_records')->where('ipd_id', $ipdId)->exists()) {
            $result['has_wellness'] = true;
            $result['details'][] = 'Wellness Record';
        }

        if (Schema::hasTable('patient_documents') && DB::table('patient_documents')->where('ipd_id', $ipdId)->where('status', '!=', 'entered-in-error')->exists()) {
            $result['has_health_document'] = true;
            $result['details'][] = 'Health Document';
        }

        $result['has_any'] = $result['has_discharge'] || $result['has_prescription'] || $result['has_diagnostic'] || $result['has_invoice']
            || $result['has_immunization'] || $result['has_wellness'] || $result['has_health_document'];
        $result['message'] = $result['has_any']
            ? 'Available data: ' . implode(', ', $result['details'])
            : 'No discharge summary, prescriptions, lab results, or invoices found for this admission. Please add data before linking to ABDM.';

        return $result;
    }

    public function getLinkedOpd(int $opdId): ?object
    {
        return DB::table('abdm_care_contexts')->where('opd_id', $opdId)->where('status', 'linked')->orderByDesc('id')->first();
    }

    public function getLinkedIpd(int $ipdId, string $hiType): ?object
    {
        return DB::table('abdm_care_contexts')->where('ipd_id', $ipdId)->where('hi_type', $hiType)->where('status', 'linked')->orderByDesc('id')->first();
    }

    public function getLinkedDcd(int $dcdId, string $hiType): ?object
    {
        return DB::table('abdm_care_contexts')->where('dcd_id', $dcdId)->where('hi_type', $hiType)->where('status', 'linked')->orderByDesc('id')->first();
    }

    private function validateIdField(string $field): void
    {
        if (!in_array($field, ['opd_id', 'ipd_id', 'dcd_id'], true)) {
            throw new InvalidArgumentException('Unsupported care-context source field.');
        }
    }

    public function getCooldown(string $idField, int $id, ?string $hiType = null): ?array
    {
        $this->validateIdField($idField);
        $query = DB::table('abdm_care_context_attempts')->where($idField, $id);
        if ($hiType !== null) {
            $query->where(function ($q) use ($hiType) {
                $q->where('hi_type', $hiType)->orWhereNull('hi_type');
            });
        }
        // Evaluate the latest attempt per type so a success for one type cannot
        // mask an unresolved failure/cooldown for another type in a bulk request.
        $seen = [];
        $cooldowns = [];
        foreach ($query->orderByDesc('id')->get() as $last) {
            $key = $last->hi_type ?? '*';
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($last->success) {
                continue;
            }
            $seconds = LinkTokenService::cooldownSeconds((bool) $last->is_duplicate_error, $last->error_message, 'care_context');
            $remaining = CarbonImmutable::parse($last->created_on, 'UTC')->getTimestamp() + $seconds - now('UTC')->getTimestamp();
            if ($remaining > 0) {
                $cooldowns[] = ['seconds_remaining' => $remaining, 'is_duplicate' => (bool) $last->is_duplicate_error, 'error_message' => $last->error_message];
            }
        }
        usort($cooldowns, fn ($a, $b) => $b['seconds_remaining'] <=> $a['seconds_remaining']);

        return $cooldowns[0] ?? null;
    }

    public function getPending(string $idField, int $id, ?string $hiType = null): ?object
    {
        $this->validateIdField($idField);
        $query = DB::table('abdm_care_contexts')->where($idField, $id)->where('status', 'pending')
            ->where('created_on', '>', now('UTC')->subSeconds(config('abdm.care_context_pending_timeout', 600))->toDateTimeString());
        if ($hiType !== null) {
            $query->where('hi_type', $hiType);
        }

        return $query->orderByDesc('id')->first();
    }

    public function linkOpd(int $opdId, int $patientId, string $abhaAddress, string $patientName, int $userId): array
    {
        return $this->doLink('opd', $opdId, $patientId, $abhaAddress, $patientName, $userId);
    }

    public function linkIpd(int $ipdId, int $patientId, string $abhaAddress, string $patientName, int $userId): array
    {
        return $this->doLink('ipd', $ipdId, $patientId, $abhaAddress, $patientName, $userId);
    }

    public function linkDcd(int $dcdId, int $patientId, string $abhaAddress, string $patientName, int $userId): array
    {
        return $this->doLink('dcd', $dcdId, $patientId, $abhaAddress, $patientName, $userId);
    }

    private function doLink(string $module, int $id, int $patientId, string $abhaAddress, string $patientName, int $userId): array
    {
        $field = $module . '_id';
        $requestId = (string) Str::uuid();
        $reservation = DB::transaction(function () use ($module, $field, $id, $patientId, $abhaAddress, $userId, $requestId) {
            // The encounter row serializes even the first link request. Pending rows
            // are committed before HTTP, preventing double-clicks and callback races.
            $visit = DB::table($module . 's')->where('id', $id)->lockForUpdate()->first();
            $patient = DB::table('patients')->where('id', $patientId)->first();
            if (!$visit || (int) $visit->patient_id !== $patientId || !$patient || $patient->abha_address !== $abhaAddress) {
                return ['success' => false, 'error' => 'Encounter and patient ABHA identity do not match.'];
            }
            // Same checks, in the same order and wording, as the legacy abdm_{opd,ipd,dcd}_care_context_link().
            $number = preg_replace('/[^0-9]/', '', (string) $patient->abha_number);
            if (trim($abhaAddress) === '' || strlen($number) !== 14) {
                return ['success' => false, 'error' => 'This patient has no ABHA Number/Address on file'];
            }
            if (!config('abdm.hip_id')) {
                return ['success' => false, 'error' => 'Facility HFR ID is not configured in Settings'];
            }
            if ($this->getPending($field, $id)) {
                return ['success' => false, 'pending' => true, 'error' => "A care-context link request for this {$this->encounterNoun($module)} is still awaiting ABDM's confirmation - please wait before trying again."];
            }
            $types = $this->availableHiTypes($module, $id);
            if (!$types) {
                return ['success' => false, 'error' => $this->noDataMessage($module)];
            }
            $linked = DB::table('abdm_care_contexts')->where($field, $id)->where('status', 'linked')->pluck('hi_type')->all();
            $types = array_values(array_diff($types, $linked));
            if (!$types) {
                return ['success' => true, 'existing' => true, 'message' => 'All available HI types are already linked.'];
            }
            foreach ($types as $type) {
                if ($cooldown = $this->getCooldown($field, $id, $type)) {
                    return ['success' => false, 'error' => $cooldown['error_message'] ?? 'Please wait before retrying.', 'cooldown_seconds_remaining' => $cooldown['seconds_remaining']];
                }
            }
            $linkToken = app(LinkTokenService::class)->getValidToken($patientId, $abhaAddress);
            if (!$linkToken) {
                return ['success' => false, 'error' => 'No valid link token found for this patient - complete Step 1 (Get Linking Token) first.'];
            }
            $label = strtoupper($module);
            $display = $this->careContextDisplayName($module, $visit);
            $items = [];
            foreach ($types as $type) {
                $existing = DB::table('abdm_care_contexts')->where($field, $id)->where('hi_type', $type)->orderByDesc('id')->first();
                // Preserve references issued by the earlier typed Laravel implementation.
                $ref = $existing->care_context_reference_number ?? ($module === 'opd' ? $this->opdCareContextRef($id) : $label . str_pad((string) $id, 7, '0', STR_PAD_LEFT));
                $patRef = $existing->patient_reference_number ?? $this->patientRef($patientId);
                $contextDisplay = $existing->care_context_display ?? $display;
                $items[] = ['referenceNumber' => $patRef, 'display' => $patient->patient_name,
                    'careContexts' => [['referenceNumber' => $ref, 'display' => $contextDisplay]], 'hiType' => $type, 'count' => 1];
                $values = [$field => $id, 'source_type' => $module, 'source_id' => $id,
                    'patient_id' => $patientId, 'abha_address' => $abhaAddress,
                    'patient_reference_number' => $patRef, 'care_context_reference_number' => $ref,
                    'care_context_display' => $contextDisplay, 'hi_type' => $type, 'request_id' => $requestId,
                    'status' => 'pending', 'raw_response' => '', 'created_on' => now('UTC')->toDateTimeString(), 'user_id' => $userId];
                if ($existing) {
                    DB::table('abdm_care_contexts')->where('id', $existing->id)->update($values);
                    $contextId = $existing->id;
                } else {
                    $contextId = DB::table('abdm_care_contexts')->insertGetId($values);
                }
                DB::table('abdm_care_context_hi_types')->updateOrInsert(['care_context_id' => $contextId, 'hi_type' => $type], [
                    'status' => 'pending', 'linked_at' => null, 'error_message' => null, 'created_on' => now('UTC')->toDateTimeString(),
                ]);
            }

            return ['payload' => ['abhaAddress' => $abhaAddress, 'abhaNumber' => $number, 'patient' => $items], 'link_token' => $linkToken->link_token, 'hi_types' => $types];
        });
        if (!isset($reservation['payload'])) {
            return $reservation;
        }

        try {
            $token = $this->tokenSvc->getAccessToken();
            if (!$token) {
                // No call reached ABDM, so (like legacy) this is not a failed attempt and starts no cooldown:
                // release the pending rows reserved above and let staff retry straight away.
                $this->releaseReservation($requestId);

                return ['success' => false, 'error' => 'Could not get ABDM access token'];
            }
            $response = Http::withToken($token)->acceptJson()->timeout(60)->connectTimeout(30)->withHeaders([
                'REQUEST-ID' => $requestId, 'TIMESTAMP' => now('UTC')->format('Y-m-d\TH:i:s.v\Z'),
                'X-CM-ID' => config('abdm.cm_id', 'sbx'), 'X-HIP-ID' => config('abdm.hip_id'), 'X-Link-Token' => $reservation['link_token'],
            ])->post(config('abdm.care_context_add_url'), $reservation['payload']);
            $body = $response->json();
            if (!$response->successful() || !empty($body['error'])) {
                return $this->handleOnCareContext(['requestId' => $requestId, 'error' => $body['error'] ?? $body ?? $response->body() ?: 'ABDM request failed (HTTP ' . $response->status() . ')']);
            }
            DB::table('abdm_care_contexts')->where('request_id', $requestId)->where('status', 'pending')->update(['raw_response' => \App\Support\Redact::text($response->body())]);

            return ['success' => true, 'pending' => true, 'request_id' => $requestId, 'hi_types' => $reservation['hi_types'], 'message' => 'Care-context request submitted. Awaiting ABDM confirmation.'];
        } catch (\Exception $e) {
            return $this->handleOnCareContext(['requestId' => $requestId, 'error' => $e->getMessage()]);
        }
    }

    /** Legacy wording for "visit" vs "admission" in messages shown to staff. */
    private function encounterNoun(string $module): string
    {
        return $module === 'ipd' ? 'admission' : 'visit';
    }

    /** The legacy "nothing to share" error of each module (its data rules differ, so does the text). */
    private function noDataMessage(string $module): string
    {
        return 'No data available to share. Please add '
            . match ($module) {
                'opd'   => 'clinical data, prescriptions, or lab results',
                'ipd'   => 'discharge summary, prescriptions, or lab results',
                default => 'discharge summary or lab results',
            }
            . ' before linking to ABDM.';
    }

    /** Undo the pending rows of a request that never reached ABDM; rows that were already linked are untouched. */
    private function releaseReservation(string $requestId): void
    {
        DB::transaction(function () use ($requestId) {
            $ids = DB::table('abdm_care_contexts')->where('request_id', $requestId)->where('status', 'pending')->lockForUpdate()->pluck('id');
            DB::table('abdm_care_context_hi_types')->whereIn('care_context_id', $ids)->delete();
            DB::table('abdm_care_contexts')->whereIn('id', $ids)->delete();
        });
    }

    public function handleOnCareContext(array $payload): array
    {
        $requestId = $payload['requestId'] ?? $payload['response']['requestId'] ?? null;
        if (!$requestId) {
            return ['success' => false, 'error' => 'Missing requestId'];
        }

        return DB::transaction(function () use ($requestId, $payload) {
            $rows = DB::table('abdm_care_contexts')->where('request_id', $requestId)->where('status', 'pending')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return ['success' => true, 'ignored' => true];
            }
            $error = !empty($payload['error']) ? LinkTokenService::errorMessage($payload['error']) : null;

            foreach ($rows as $row) {
                DB::table('abdm_care_context_attempts')->insert([
                    'opd_id' => $row->opd_id, 'ipd_id' => $row->ipd_id, 'dcd_id' => $row->dcd_id,
                    'source_type' => $row->source_type ?? null, 'source_id' => $row->source_id ?? null,
                    'abha_address' => $row->abha_address, 'hi_type' => $row->hi_type,
                    'success' => $error === null, 'is_duplicate_error' => $error !== null && LinkTokenService::isLongCooldownError($error),
                    'error_message' => $error !== null ? Str::limit($error, 500, '') : null,
                    'created_on' => now('UTC')->toDateTimeString(), 'user_id' => $row->user_id,
                ]);
                if ($error !== null) {
                    // The parent enum has no failed state. Keep failure history in the
                    // legacy attempts table and remove unconfirmed rows and associations.
                    DB::table('abdm_care_context_hi_types')->where('care_context_id', $row->id)->delete();
                    DB::table('abdm_care_contexts')->where('id', $row->id)->delete();
                } else {
                    DB::table('abdm_care_contexts')->where('id', $row->id)->update(['status' => 'linked', 'raw_response' => json_encode(\App\Support\Redact::value($payload))]);
                    DB::table('abdm_care_context_hi_types')->where('care_context_id', $row->id)->update([
                        'status' => 'linked', 'linked_at' => now('UTC')->toDateTimeString(), 'error_message' => null,
                    ]);
                }
            }

            return $error === null ? ['success' => true] : ['success' => false, 'error' => $error];
        });
    }
}
