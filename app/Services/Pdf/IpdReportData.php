<?php

namespace App\Services\Pdf;

use App\Services\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Support\CurrentUser;

/**
 * Data layer for the legacy rpt-ipd-*.php print reports.
 * Every query is a 1:1 port of the legacy SQL (same joins/filters/date formats) but uses
 * bindings, and replaces the per-row "inner loop" statements with a single batched query.
 */
class IpdReportData
{
    public function __construct(private SettingsService $settings) {}

    // ─────────────────────────────────────────────────────────
    // Shared pieces (legacy db.php globals)
    // ─────────────────────────────────────────────────────────

    /** Hospital letterhead values: $hospital_name, $hospital_address, $phone_number, ... from settings row #1 */
    public function hospital(): array
    {
        $s = $this->settings->getSettings();

        return [
            'name'           => (string) ($s['hospital_name'] ?? ''),
            'address'        => (string) ($s['address'] ?? ''),
            'phone'          => (string) ($s['phone_number'] ?? ''),
            'mobile'         => (string) ($s['mobile_number'] ?? ''),
            'slogan_general' => (string) ($s['slogan_general'] ?? ''),
            'slogan_hospital'=> (string) ($s['slogan_hospital'] ?? ''),
            'logo'           => (string) ($s['logo'] ?? ''),
            'square_logo'    => (string) ($s['square_logo'] ?? ''),
            'signer'         => (string) ($s['signer'] ?? ''),
        ];
    }

    /** Legacy $sign_name / $sign_designation = the logged-in staff member (empty for headless/ABDM calls). */
    public function signature(): array
    {
        $name = '';
        $designation = '';
        $userId = (int) CurrentUser::id() ?? 0;

        if ($userId > 0) {
            $row = DB::table('staff_members')
                ->join('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('staff_members.id', $userId)
                ->first(['staff_members.staff_name', 'designations.designation']);
            $name = (string) ($row->staff_name ?? '');
            $designation = (string) ($row->designation ?? '');
        }

        return ['name' => $name, 'designation' => $designation];
    }

    /** Trimmed plain text (legacy trim(strip_tags(..))). */
    public static function plain(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    /** Legacy pf_has(): true when a value has real, non-blank, non-markup-only content. */
    public static function hasContent(mixed $value): bool
    {
        return trim(strip_tags(html_entity_decode((string) $value))) !== '';
    }

    /**
     * Rich-text (Summernote) content: entity-decoded so mPDF renders the formatting, but limited to
     * presentational tags so stored markup cannot inject scripts/iframes/forms into the document.
     */
    public static function richText(mixed $value): string
    {
        $html = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');

        return strip_tags($html, '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><table><thead><tbody><tfoot><tr><td><th><span><div><h1><h2><h3><h4><h5><h6><sub><sup><blockquote><hr><font><img><pre><code><a>');
    }

    // ─────────────────────────────────────────────────────────
    // rpt-ipd-bill.php / rpt-ipd-bill-selected.php
    // ─────────────────────────────────────────────────────────

    /**
     * Single-charge IPD bills (rpt-ipd-bill.php). Accepts several ids (bill-selected).
     * Each item: ['row' => billing charge + patient + division, 'details' => Collection]
     *
     * NOTE: the legacy SELECT listed divisions.address / divisions.mobile_number after the patient
     * columns, so (mysqli assoc) the "Address / Mobile" line silently printed the division's values
     * instead of the patient's. The patient's address / mobile are what the line is labelled as, so
     * they are used here.
     */
    public function bills(array $chargeIds): Collection
    {
        $rows = DB::table('billing_charges as bc')
            ->join('ipds', function ($j) {
                $j->on('ipds.id', '=', 'bc.module_id')->where('bc.module', '=', 'IPD');
            })
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->join('charge_departments as cd', 'cd.id', '=', 'bc.charge_department_id')
            ->join('divisions', 'divisions.id', '=', 'cd.division_id')
            ->whereIn('bc.id', $chargeIds)
            ->orderBy('bc.id')
            ->selectRaw("bc.*,
                DATE_FORMAT(bc.charge_date_time, '%d-%m-%Y %h:%i %p') AS charge_date_display,
                DATE_FORMAT(ipds.admission_date_time, '%Y') AS iyear,
                patients.patient_name, patients.gender, patients.age,
                patients.address AS patient_address, patients.mobile_number AS patient_mobile,
                divisions.division_name, divisions.slogan,
                divisions.phone_number AS division_phone, divisions.mobile_number AS division_mobile,
                divisions.logo")
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $details = DB::table('billing_charges_detail as d')
            ->join('charges', 'charges.id', '=', 'd.charge_id')
            ->whereIn('d.module_charge_id', $rows->pluck('id'))
            ->orderBy('d.id')
            ->get(['d.module_charge_id', 'd.quantity', 'd.total', 'charges.charge_name'])
            ->groupBy('module_charge_id');

        return $rows->map(fn ($row) => [
            'row'     => $row,
            'details' => $details->get($row->id, collect()),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // IPD header (shared by several reports)
    // ─────────────────────────────────────────────────────────

    /** ipds + patients (rpt-ipd-discharge-bill / detailed-bill header). */
    public function ipdWithPatient(int $ipdId): ?object
    {
        return DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.*, DATE_FORMAT(ipds.admission_date_time, '%Y') AS iyear,
                patients.id AS patient_id, patients.patient_name, patients.guardian_name, patients.gender, patients.age,
                patients.address, patients.mobile_number, patients.alternate_mobile_number, patients.photo,
                patients.patient_category_id, patients.esm_name, patients.card_number, patients.service_number, patients.claim_id")
            ->first();
    }

    /**
     * All billing charges of an admission: IPD charges + the linked OPD's charges + charges of DCDs linked
     * to the same OPD (rpt-ipd-discharge-bill / detailed-bill).
     */
    public function admissionCharges(int $ipdId, int $opdId): Collection
    {
        $dcdIds = $opdId > 0 ? DB::table('dcds')->where('opd_id', $opdId)->pluck('id')->all() : [];

        return DB::table('billing_charges as bc')
            ->join('charge_departments as cd', 'cd.id', '=', 'bc.charge_department_id')
            ->where(function ($q) use ($ipdId, $opdId, $dcdIds) {
                $q->where(fn ($w) => $w->where('bc.module', 'IPD')->where('bc.module_id', $ipdId));
                if ($opdId > 0) {
                    $q->orWhere(fn ($w) => $w->where('bc.module', 'OPD')->where('bc.module_id', $opdId));
                }
                if (!empty($dcdIds)) {
                    $q->orWhere(fn ($w) => $w->where('bc.module', 'DCD')->whereIn('bc.module_id', $dcdIds));
                }
            })
            ->orderBy('bc.charge_date_time')
            ->orderBy('bc.id')
            ->selectRaw("bc.*, DATE_FORMAT(bc.charge_date_time, '%d-%m-%Y') AS charge_date_display, cd.department_name")
            ->get();
    }

    /** rpt-ipd-discharge-bill.php (consolidated, one row per charge). */
    public function dischargeBill(int $ipdId): ?array
    {
        $ipd = $this->ipdWithPatient($ipdId);
        if (!$ipd) {
            return null;
        }

        $charges = $this->admissionCharges($ipdId, (int) ($ipd->opd_id ?? 0));
        $hidePaid = ((int) $ipd->patient_category_id !== 1);

        // Code column (only for non-category-1 patients): distinct charge codes of each charge's detail lines
        $codes = collect();
        if ($hidePaid && $charges->isNotEmpty()) {
            $codes = DB::table('billing_charges_detail as bcd')
                ->join('charges as c', 'c.id', '=', 'bcd.charge_id')
                ->whereIn('bcd.module_charge_id', $charges->pluck('id'))
                ->distinct()
                ->get(['bcd.module_charge_id', 'c.charge_code'])
                ->filter(fn ($r) => !empty($r->charge_code))
                ->groupBy('module_charge_id')
                ->map(fn ($g) => $g->pluck('charge_code')->implode(', '));
        }

        $total = 0;
        $totalPaid = 0;
        foreach ($charges as $c) {
            $total += $c->total;
            $totalPaid += $c->paid_amount;
            $c->code_display = $codes->get($c->id, '-');
        }

        return [
            'ipd'        => $ipd,
            'charges'    => $charges,
            'hide_paid'  => $hidePaid,
            'total'      => $total,
            'total_paid' => $totalPaid,
            'payable'    => $total - $totalPaid,
        ];
    }

    /** rpt-ipd-discharge-detailed-bill.php (every charge with its detail lines). */
    public function dischargeDetailedBill(int $ipdId): ?array
    {
        $ipd = $this->ipdWithPatient($ipdId);
        if (!$ipd) {
            return null;
        }

        $charges = $this->admissionCharges($ipdId, (int) ($ipd->opd_id ?? 0));
        $hidePaid = ((int) $ipd->patient_category_id !== 1);

        $details = $charges->isEmpty() ? collect() : DB::table('billing_charges_detail as bcd')
            ->join('charges as c', 'c.id', '=', 'bcd.charge_id')
            ->whereIn('bcd.module_charge_id', $charges->pluck('id'))
            ->orderBy('bcd.id')
            ->get(['bcd.module_charge_id', 'bcd.total', 'c.charge_name', 'c.charge_code'])
            ->groupBy('module_charge_id');

        $grandTotal = 0;
        $grandPaid = 0;
        $blocks = [];

        foreach ($charges as $charge) {
            $lines = $details->get($charge->id, collect());
            if ($lines->isEmpty()) {
                // legacy: a charge without detail lines prints only its heading and is not part of the totals
                $blocks[] = ['charge' => $charge, 'items' => [], 'subtotal' => 0, 'subtotal_paid' => 0];
                continue;
            }

            $items = [];
            $chargeTotal = 0;
            $chargePaid = 0;
            foreach ($lines as $line) {
                $chargeTotal += $line->total;
                $itemPaid = 0;
                if (!$hidePaid) {
                    // paid amount is spread over the detail lines in proportion to their share of the charge total
                    $itemPaid = ($charge->paid_amount > 0 && $charge->total > 0)
                        ? round(($line->total / $charge->total) * $charge->paid_amount, 2)
                        : 0;
                    $chargePaid += $itemPaid;
                }
                $items[] = ['line' => $line, 'paid' => $itemPaid];
            }

            $grandTotal += $chargeTotal;
            $grandPaid += $chargePaid;
            $blocks[] = ['charge' => $charge, 'items' => $items, 'subtotal' => $chargeTotal, 'subtotal_paid' => $chargePaid];
        }

        return [
            'ipd'         => $ipd,
            'blocks'      => $blocks,
            'hide_paid'   => $hidePaid,
            'grand_total' => $grandTotal,
            'grand_paid'  => $grandPaid,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Charts: treatment / vital monitoring / intake-output
    // ─────────────────────────────────────────────────────────

    private function chartPatient(int $ipdId): ?object
    {
        return DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.id, COALESCE(DATE_FORMAT(ipds.admission_date_time, '%Y'), YEAR(CURDATE())) AS iyear,
                patients.id AS patient_id, COALESCE(DATE_FORMAT(patients.created_on, '%Y'), DATE_FORMAT(ipds.admission_date_time, '%Y'), YEAR(CURDATE())) AS pyear,
                patients.patient_name, patients.gender, patients.age")
            ->first();
    }

    /** rpt-ipd-treatment.php */
    public function treatmentChart(int $ipdId, string $timingSeparator = ' + '): ?array
    {
        $patient = $this->chartPatient($ipdId);
        if (!$patient) {
            return null;
        }

        // legacy header "Consultant" = doctor of the first treatment row (or IPD consultant doctor)
        $consultant = DB::table('ipd_treatments as t')
            ->join('staff_members', 'staff_members.id', '=', 't.doctor_id')
            ->where('t.ipd_id', $ipdId)->orderBy('t.id')->value('staff_members.staff_name');

        if (empty($consultant)) {
            $consultant = DB::table('ipds')
                ->join('staff_members', 'staff_members.id', '=', 'ipds.consultant_doctor_id')
                ->where('ipds.id', $ipdId)->value('staff_members.staff_name') ?? '';
        }

        $treatments = DB::table('ipd_treatments as t')
            ->leftJoin('staff_members as t1', 't1.id', '=', 't.doctor_id')
            ->leftJoin('staff_members as t2', 't2.id', '=', 't.user_id')
            ->leftJoin('ipds', 'ipds.id', '=', 't.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', DB::raw('COALESCE(t.bed_id, ipds.bed_id)'))
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('t.ipd_id', $ipdId)
            ->orderBy('t.id')
            ->selectRaw("t.id, t.remarks, DATE_FORMAT(t.treatment_date_time, '%d-%m-%Y %h:%i %p') AS treatment_dt,
                t1.staff_name AS prescribed_by, t2.staff_name AS added_by, beds.bed_name, ward_categories.ward_category")
            ->get();

        $details = $treatments->isEmpty() ? collect() : DB::table('ipd_treatment_detail as td')
            ->join('pharma_products as pp', 'td.pharma_product_id', '=', 'pp.id')
            ->leftJoin('medicine_frequency_types as ft', 'td.frequency_type_id', '=', 'ft.id')
            ->leftJoin('medicine_meal_relation as mr', 'td.meal_relation_id', '=', 'mr.id')
            ->leftJoin('ipd_treatment_timing_selections as tts', 'td.id', '=', 'tts.treatment_detail_id')
            ->leftJoin('medicine_timing_of_day as t', 'tts.timing_id', '=', 't.id')
            ->leftJoin('medical_conditions as mc', 'mc.id', '=', 'td.medical_condition_id')
            ->whereIn('td.ipd_treatment_id', $treatments->pluck('id'))
            ->groupBy('td.id', 'td.ipd_treatment_id', 'pp.product_name', 'td.dose_quantity', 'ft.display_text',
                'mr.relation_name', 'td.duration_days', 'td.medical_condition_id', 'mc.condition_name')
            ->orderBy('td.id')
            ->selectRaw("td.id, td.ipd_treatment_id, pp.product_name, td.dose_quantity, ft.display_text AS frequency_display,
                mr.relation_name AS meal_name, td.duration_days, td.medical_condition_id, mc.condition_name,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR " . DB::getPdo()->quote($timingSeparator) . ") AS timing_names")
            ->get()
            ->groupBy('ipd_treatment_id');

        return [
            'patient'    => $patient,
            'consultant' => $consultant,
            'treatments' => $treatments->map(fn ($t) => ['row' => $t, 'details' => $details->get($t->id, collect())]),
        ];
    }

    /** rpt-ipd-vital-monitoring.php */
    public function vitalMonitoring(int $ipdId): ?array
    {
        $patient = $this->chartPatient($ipdId);
        if (!$patient) {
            return null;
        }

        return [
            'patient' => $patient,
            'days'    => $this->vitalDays($ipdId),
        ];
    }

    /** Vital monitoring days + their time rows (also used by the patient file). */
    public function vitalDays(int $ipdId): Collection
    {
        $days = DB::table('ipd_vital_monitoring as v')
            ->leftJoin('staff_members', 'staff_members.id', '=', 'v.user_id')
            ->leftJoin('ipds', 'ipds.id', '=', 'v.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', DB::raw('COALESCE(v.bed_id, ipds.bed_id)'))
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('v.ipd_id', $ipdId)
            ->orderBy('v.id')
            ->selectRaw("v.id, v.remarks, DATE_FORMAT(v.vital_monitoring_date, '%d-%m-%Y') AS day_display,
                staff_members.staff_name AS added_by, beds.bed_name, ward_categories.ward_category")
            ->get();

        $details = $days->isEmpty() ? collect() : DB::table('ipd_vital_monitoring_detail as d')
            ->whereIn('d.ipd_vital_monitoring_id', $days->pluck('id'))
            ->orderBy('d.id')
            ->selectRaw("d.*, DATE_FORMAT(d.vital_monitoring_time, '%h:%i %p') AS time_display")
            ->get()
            ->groupBy('ipd_vital_monitoring_id');

        return $days->map(fn ($d) => ['row' => $d, 'details' => $details->get($d->id, collect())]);
    }

    /** rpt-ipd-intake-output.php */
    public function intakeOutput(int $ipdId): ?array
    {
        $patient = $this->chartPatient($ipdId);
        if (!$patient) {
            return null;
        }

        return [
            'patient' => $patient,
            'days'    => $this->intakeOutputDays($ipdId),
        ];
    }

    /** Intake/output days + their time rows (also used by the patient file). */
    public function intakeOutputDays(int $ipdId): Collection
    {
        $days = DB::table('ipd_intake_output as io')
            ->leftJoin('staff_members', 'staff_members.id', '=', 'io.user_id')
            ->leftJoin('ipds', 'ipds.id', '=', 'io.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', DB::raw('COALESCE(io.bed_id, ipds.bed_id)'))
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('io.ipd_id', $ipdId)
            ->orderBy('io.id')
            ->selectRaw("io.id, io.remarks, DATE_FORMAT(io.intake_output_date, '%d-%m-%Y') AS day_display,
                staff_members.staff_name AS added_by, beds.bed_name, ward_categories.ward_category")
            ->get();

        $details = $days->isEmpty() ? collect() : DB::table('ipd_intake_output_detail as d')
            ->whereIn('d.ipd_intake_output_id', $days->pluck('id'))
            ->orderBy('d.id')
            ->selectRaw("d.*, DATE_FORMAT(d.intake_output_time, '%h:%i %p') AS time_display")
            ->get()
            ->groupBy('ipd_intake_output_id');

        return $days->map(fn ($d) => ['row' => $d, 'details' => $details->get($d->id, collect())]);
    }

    // ─────────────────────────────────────────────────────────
    // rpt-ipd-lab-investigations.php
    // ─────────────────────────────────────────────────────────

    public function labInvestigations(int $ipdId): ?array
    {
        $ipd = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.*, DATE_FORMAT(ipds.admission_date_time, '%Y') AS iyear,
                patients.id AS patient_id, patients.patient_name, patients.guardian_name, patients.gender, patients.age,
                patients.address, patients.mobile_number, patients.alternate_mobile_number, patients.photo")
            ->first();
        if (!$ipd) {
            return null;
        }

        $services = DB::table('biochemistry_tests_result as r')
            ->join('ipd_service_detail as sd', 'sd.id', '=', 'r.ipd_service_detail_id')
            ->join('charges', 'charges.id', '=', 'sd.charge_id')
            ->join('ipd_services as s', 's.id', '=', 'sd.ipd_service_id')
            ->where('r.ipd_id', $ipdId)
            ->distinct()
            ->orderBy('r.ipd_service_detail_id')
            ->selectRaw("r.ipd_service_detail_id AS ipd_service_detail_id,
                DATE_FORMAT(s.service_date_time, '%d-%m-%Y %h:%i %p') AS service_date_time, charges.charge_name")
            ->get();

        $results = $services->isEmpty() ? collect() : DB::table('biochemistry_tests_result as r')
            ->join('biochemistry_sub_tests as st', 'st.id', '=', 'r.test_id')
            ->join('bunits', 'bunits.id', '=', 'st.unit_id')
            ->whereIn('r.ipd_service_detail_id', $services->pluck('ipd_service_detail_id'))
            ->orderBy('r.id')
            ->get(['r.ipd_service_detail_id', 'r.test_value', 'r.highlighted', 'st.sub_test_name', 'st.reference_range', 'bunits.unit_name'])
            ->groupBy('ipd_service_detail_id');

        return [
            'ipd'      => $ipd,
            'services' => $services->map(fn ($s) => [
                'service' => $s,
                'results' => $results->get($s->ipd_service_detail_id, collect()),
            ]),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // rpt-ipd-prescription-pdf.php
    // ─────────────────────────────────────────────────────────

    /** SELECT used by every medicine table of the prescription PDF: name, dose, frequency, meal, duration, timings. */
    private function medicineRows(string $table, string $timingTable, string $timingFk, array $parentIds, string $parentColumn): Collection
    {
        return DB::table($table . ' as m')
            ->join('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
            ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
            ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
            ->leftJoin($timingTable . ' as mts', 'm.id', '=', 'mts.' . $timingFk)
            ->leftJoin('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
            ->whereIn('m.' . $parentColumn, $parentIds)
            ->groupBy('m.id', 'm.' . $parentColumn, 'pp.product_name', 'm.dose_quantity', 'ft.display_text', 'mr.relation_name', 'm.duration_days')
            ->orderBy('m.id')
            ->selectRaw("m.id, m.{$parentColumn} AS parent_id, pp.product_name AS medicine_name, m.dose_quantity,
                ft.display_text AS dose_interval, mr.relation_name AS meal_relation, m.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') AS timing_names")
            ->get();
    }

    public function prescription(int $ipdId, ?int $prescriptionId = null): ?array
    {
        $ipd = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->leftJoin('staff_members', 'staff_members.id', '=', 'ipds.consultant_doctor_id')
            ->leftJoin('ipd_discharge', 'ipd_discharge.ipd_id', '=', 'ipds.id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.*, DATE_FORMAT(ipds.admission_date_time, '%d-%m-%Y') AS admission_date,
                DATE_FORMAT(ipds.admission_date_time, '%Y') AS iyear,
                patients.id AS patient_id, patients.patient_name, patients.guardian_name, patients.gender, patients.age,
                patients.address AS patient_address, patients.mobile_number AS patient_mobile,
                staff_members.staff_name AS doctor_name, staff_members.qualification,
                ipd_discharge.discharge_date_time,
                DATE_FORMAT(ipd_discharge.discharge_date_time, '%d-%m-%Y') AS discharge_date")
            ->first();
        if (!$ipd) {
            return null;
        }

        // 1. Doctor prescriptions (ipd_medicines)
        $pQuery = DB::table('ipd_prescriptions')
            ->where('ipd_id', $ipdId)
            ->orderBy('prescription_date_time')
            ->selectRaw("id, findings, DATE_FORMAT(prescription_date_time, '%d-%m-%Y %h:%i %p') AS prescription_datetime");
        if ($prescriptionId) {
            $pQuery->where('id', $prescriptionId);
        }
        $prescriptions = $pQuery->get();
        $rxMeds = $prescriptions->isEmpty() ? collect()
            : $this->medicineRows('ipd_medicines', 'ipd_medicine_timing_selections', 'ipd_medicine_id', $prescriptions->pluck('id')->all(), 'ipd_prescription_id')
                ->groupBy('parent_id');

        // 2. Nurse-station treatment chart (ipd_treatment_detail)
        $treatments = DB::table('ipd_treatments')
            ->where('ipd_id', $ipdId)
            ->orderBy('treatment_date_time')
            ->selectRaw("id, DATE_FORMAT(treatment_date_time, '%d-%m-%Y %h:%i %p') AS treatment_datetime")
            ->get();
        $trMeds = $treatments->isEmpty() ? collect()
            : $this->medicineRows('ipd_treatment_detail', 'ipd_treatment_timing_selections', 'treatment_detail_id', $treatments->pluck('id')->all(), 'ipd_treatment_id')
                ->groupBy('parent_id');

        $isDischarged = !empty($ipd->discharge_date_time);
        $given = collect();
        $advised = collect();

        if ($isDischarged) {
            // 3. Medicines handed over at discharge
            $given = DB::table('ipd_discharge_medication_given as g')
                ->join('ipd_discharge', 'ipd_discharge.id', '=', 'g.discharge_id')
                ->where('ipd_discharge.ipd_id', $ipdId)
                ->orderBy('g.id')
                ->get(['g.medicine_name', 'g.dose_quantity', 'g.frequency_display', 'g.timing_display', 'g.meal_relation', 'g.duration_days']);

            // 4. Medicines advised to continue
            $advised = DB::table('ipd_discharge_treatment_advised as m')
                ->join('ipd_discharge', 'ipd_discharge.id', '=', 'm.discharge_id')
                ->join('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
                ->leftJoin('ipd_discharge_treatment_timing_selections as mts', 'm.id', '=', 'mts.treatment_advised_id')
                ->leftJoin('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
                ->where('ipd_discharge.ipd_id', $ipdId)
                ->groupBy('m.id', 'pp.product_name', 'm.dose_quantity', 'ft.display_text', 'mr.relation_name', 'm.duration_days')
                ->orderBy('m.id')
                ->selectRaw("pp.product_name AS medicine_name, m.dose_quantity, ft.display_text AS dose_interval,
                    mr.relation_name AS meal_relation, m.duration_days,
                    GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') AS timing_names")
                ->get();
        }

        return [
            'ipd'           => $ipd,
            'is_discharged' => $isDischarged,
            'prescriptions' => $prescriptions->map(fn ($p) => ['row' => $p, 'medicines' => $rxMeds->get($p->id, collect())]),
            'treatments'    => $treatments->map(fn ($t) => ['row' => $t, 'medicines' => $trMeds->get($t->id, collect())]),
            'given'         => $given,
            'advised'       => $advised,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // rpt-ipd-discharge-summary.php
    // ─────────────────────────────────────────────────────────

    public function dischargeSummary(int $ipdId): ?array
    {
        $row = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->leftJoin('staff_members', 'staff_members.id', '=', 'ipds.consultant_doctor_id')
            ->leftJoin('ipd_discharge', 'ipd_discharge.ipd_id', '=', 'ipds.id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.*,
                DATE_FORMAT(ipds.admission_date_time, '%Y') AS iyear,
                DATE_FORMAT(ipds.admission_date_time, '%d-%m-%Y %h:%i %p') AS admission_display,
                DATE_FORMAT(ipd_discharge.discharge_date_time, '%d-%m-%Y %h:%i %p') AS discharge_display,
                TIMESTAMPDIFF(DAY, ipds.admission_date_time, ipd_discharge.discharge_date_time) AS stay_days,
                patients.id AS patient_id, patients.patient_name, patients.guardian_name, patients.gender,
                patients.age, patients.address AS patient_address, patients.mobile_number AS patient_mobile,
                patients.blood_group, patients.patient_category_id, patients.esm_name, patients.card_number,
                staff_members.staff_name AS doctor_name,
                ipd_discharge.discharge_date_time, ipd_discharge.diagnosis AS discharge_diagnosis,
                ipd_discharge.condition_on_discharge, ipd_discharge.follow_up_detail,
                ipd_discharge.urgent_care_condition, ipd_discharge.note AS discharge_note,
                ipd_discharge.id AS discharge_id")
            ->first();
        if (!$row) {
            return null;
        }

        $pf = DB::table('ipd_patient_files')->where('ipd_id', $ipdId)->first();

        // Latest vitals row of the latest monitoring day
        $vitalDetail = null;
        $vitalDay = DB::table('ipd_vital_monitoring')->where('ipd_id', $ipdId)
            ->orderByDesc('vital_monitoring_date')->first(['id', 'vital_monitoring_date']);
        if ($vitalDay) {
            $vitalDetail = DB::table('ipd_vital_monitoring_detail')
                ->where('ipd_vital_monitoring_id', $vitalDay->id)
                ->orderByDesc('vital_monitoring_time')->first();
        }

        $labCount = (int) DB::table('biochemistry_tests_result')->where('ipd_id', $ipdId)->count();

        $procedures = DB::table('ipd_services')
            ->join('charge_departments', 'charge_departments.id', '=', 'ipd_services.charge_department_id')
            ->where('ipd_services.ipd_id', $ipdId)
            ->where('charge_departments.category', 'Procedure')
            ->orderBy('ipd_services.service_date_time')
            ->get(['ipd_services.service_title', 'ipd_services.service_date_time', 'ipd_services.status']);

        $dischargeId = (int) ($row->discharge_id ?? 0);
        $dischargeProcedures = $dischargeId > 0
            ? DB::table('ipd_discharge_procedures')->where('discharge_id', $dischargeId)->get(['procedure_detail'])
            : collect();

        // Medications: prescriptions during stay + medicines given + treatment advised at discharge
        $medications = DB::table('ipd_medicines as im')
            ->join('pharma_products as pp', 'pp.id', '=', 'im.pharma_product_id')
            ->join('ipd_prescriptions as ipr', 'ipr.id', '=', 'im.ipd_prescription_id')
            ->where('ipr.ipd_id', $ipdId)
            ->get(['pp.product_name as medicine_name', 'im.dose_quantity', 'im.duration_days'])
            ->map(fn ($m) => (array) $m)->all();

        $given = DB::table('ipd_discharge_medication_given')->where('discharge_id', $dischargeId)
            ->get(['medicine_name', 'dose_quantity', 'frequency_display', 'duration_days'])
            ->map(fn ($m) => (array) $m)->all();

        $advised = DB::table('ipd_discharge_treatment_advised as dt')
            ->join('pharma_products as pp', 'pp.id', '=', 'dt.pharma_product_id')
            ->where('dt.discharge_id', $dischargeId)
            ->get(['pp.product_name as medicine_name', 'dt.dose_quantity', 'dt.duration_days'])
            ->map(fn ($m) => (array) $m)->all();

        return [
            'row'                  => $row,
            'pf'                   => $pf,
            'vital'                => $vitalDetail,
            'lab_count'            => $labCount,
            'procedures'           => $procedures,
            'discharge_procedures' => $dischargeProcedures,
            'medications'          => array_merge($medications, $given, $advised),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // rpt-ipd-patients-file.php
    // ─────────────────────────────────────────────────────────

    public function patientsFile(int $ipdId): ?array
    {
        // Legacy used INNER JOIN opds; admissions that have no OPD record would then print "Record not found",
        // so the OPD vitals are a LEFT JOIN here (they are simply blank when there is no OPD).
        $row = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->join('beds', 'beds.id', '=', 'ipds.bed_id')
            ->join('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->leftJoin('opds', 'opds.id', '=', 'ipds.opd_id')
            ->leftJoin('ipd_patient_files as pf', 'pf.ipd_id', '=', 'ipds.id')
            ->where('ipds.id', $ipdId)
            ->selectRaw("ipds.*, DATE_FORMAT(ipds.admission_date_time, '%Y') AS admission_year,
                DATE_FORMAT(ipds.admission_date_time, '%d/%m/%Y %h:%i %p') AS admission_date_time,
                DATE_FORMAT(ipds.admission_date_time, '%d/%m/%Y') AS admission_date,
                DATE_FORMAT(ipds.admission_date_time, '%h:%i %p') AS admission_time,
                patients.id AS patient_id, DATE_FORMAT(patients.created_on, '%Y') AS pyear,
                patients.patient_name, patients.guardian_name, patients.gender, patients.age, patients.address,
                patients.mobile_number, patients.alternate_mobile_number, patients.photo,
                beds.bed_name, ward_categories.ward_category,
                opds.height, opds.weight, opds.bp, opds.pulse, opds.temperature, opds.respiration, opds.spo2,
                pf.relationship, pf.relative_name, pf.witness_name, pf.admission_type, pf.patient_condition,
                pf.source_of_history, pf.general_condition, pf.chief_complaints, pf.history_of_present_illness,
                pf.past_medical_history, pf.pain_score, pf.pain_location, pf.intervention, pf.family_ho,
                pf.current_medication, pf.allergies, pf.ho_blood_transfusion, pf.substance_abuse,
                pf.nutritional_status, pf.general_examination, pf.systemic_examination,
                pf.cns_system_mental_status, pf.cns_system_sensory_examination, pf.cns_system_motor_examination_tone,
                pf.cns_system_reflexes, pf.cns_system_power, pf.provisional_diagnosis, pf.diet_plan,
                pf.progress_sheet, pf.rmo_notes")
            ->first();
        if (!$row) {
            return null;
        }

        // legacy: doctor of the (first) treatment row is the consultant on the file
        $row->prescribed_by = DB::table('ipd_treatments as t')
            ->join('staff_members', 'staff_members.id', '=', 't.doctor_id')
            ->where('t.ipd_id', $ipdId)->orderBy('t.id')->value('staff_members.staff_name');

        return [
            'row'          => $row,
            'treatments'   => $this->treatmentChart($ipdId, ', ')['treatments'] ?? collect(),
            'vitals'       => $this->vitalDays($ipdId),
            'intake_output'=> $this->intakeOutputDays($ipdId),
            'assessment'   => $this->assessmentHtml($row),
        ];
    }

    /**
     * "INPATIENT INITIAL ASSESSMENT & HISTORY SHEET" body. Returns '' when the patient file holds no data.
     * Plain-text columns are escaped; Summernote columns are sanitised rich text (see richText()).
     */
    private function assessmentHtml(object $row): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $has = [self::class, 'hasContent'];

        $f = [];
        foreach (['admission_type', 'patient_condition', 'source_of_history', 'general_condition', 'pain_score', 'pain_location',
            'intervention', 'family_ho', 'current_medication', 'allergies', 'ho_blood_transfusion', 'substance_abuse',
            'nutritional_status', 'general_examination', 'systemic_examination', 'bp', 'pulse', 'temperature', 'respiration',
            'spo2', 'weight', 'height', 'cns_system_mental_status', 'cns_system_sensory_examination',
            'cns_system_motor_examination_tone', 'cns_system_reflexes', 'cns_system_power', 'provisional_diagnosis',
            'diet_plan', 'relationship'] as $k) {
            $f[$k] = $e($row->{$k} ?? '');
        }
        // Rich text (Summernote): decoded, not escaped, so mPDF renders the formatting
        foreach (['chief_complaints', 'history_of_present_illness', 'past_medical_history'] as $k) {
            $f[$k] = self::richText($row->{$k} ?? '');
        }

        $any = false;
        foreach ($f as $v) {
            if ($has($v)) {
                $any = true;
                break;
            }
        }
        if (!$any) {
            return '';
        }

        $h = '<p><b>Patient Name</b> ' . $e($row->patient_name) . ' <b>Ward</b> ' . $e($row->ward_category)
            . ' <b>Bed No</b> ' . $e($row->bed_name) . ' <b>Date &amp; Time of Assessment</b> ' . $e($row->admission_date_time)
            . ' <b>Consultant</b> ' . $e($row->prescribed_by) . '</p>';

        $line2 = [];
        if ($has($f['admission_type'])) { $line2[] = '<b>Admission</b> ' . $f['admission_type']; }
        if ($has($f['patient_condition'])) { $line2[] = '<b>Patient\'s Condition</b> ' . $f['patient_condition']; }
        if ($has($f['source_of_history'])) { $line2[] = '<b>Source of History</b> ' . $f['source_of_history']; }
        if ($has($f['relationship'])) { $line2[] = '<b>Relationship</b> ' . $f['relationship']; }
        if ($has($f['general_condition'])) { $line2[] = '<b>General Condition</b> ' . $f['general_condition']; }
        if ($line2) { $h .= '<p>' . implode('<br>', $line2) . '</p>'; }

        $line3 = [];
        if ($has($f['chief_complaints'])) { $line3[] = '<b>Chief Complaints</b><br>' . $f['chief_complaints']; }
        if ($has($f['history_of_present_illness'])) { $line3[] = '<b>History of Present Illness</b><br>' . $f['history_of_present_illness']; }
        if ($line3) { $h .= '<p>' . implode('<br>', $line3) . '</p>'; }

        $pain = [];
        if ($has($f['pain_score'])) { $pain[] = $f['pain_score']; }
        if ($has($f['pain_location'])) { $pain[] = $f['pain_location']; }
        if ($has($f['intervention'])) { $pain[] = $f['intervention']; }
        $line4 = [];
        if ($pain) { $line4[] = '<b>Pain Score</b> ' . implode(' ', $pain); }
        if ($has($f['past_medical_history'])) { $line4[] = '<b>Past History</b><br>' . $f['past_medical_history']; }
        if ($line4) { $h .= '<p>' . implode('<br>', $line4) . '</p>'; }

        $line5 = [];
        if ($has($f['family_ho'])) { $line5[] = '<b>Family H/O</b> ' . $f['family_ho']; }
        if ($has($f['current_medication'])) { $line5[] = '<b>Current Medication</b> ' . $f['current_medication']; }
        if ($has($f['allergies'])) { $line5[] = '<b>Allergies</b> ' . $f['allergies']; }
        if ($has($f['ho_blood_transfusion'])) { $line5[] = '<b>H/O Blood Transfusion</b> ' . $f['ho_blood_transfusion']; }
        if ($has($f['substance_abuse'])) { $line5[] = '<b>Psychological H/O Substance Abuse</b> ' . $f['substance_abuse']; }
        if ($has($f['nutritional_status'])) { $line5[] = '<b>Nutritional Status</b> ' . $f['nutritional_status']; }
        if ($has($f['general_examination'])) { $line5[] = '<b>General Examination</b> ' . $f['general_examination']; }
        if ($has($f['systemic_examination'])) { $line5[] = '<b>Systemic Examination</b> ' . $f['systemic_examination']; }
        if ($line5) { $h .= '<p>' . implode('<br>', $line5) . '</p>'; }

        $vitals = [];
        if ($has($f['bp'])) { $vitals[] = '<b>BP</b> ' . $f['bp'] . 'mm/hg'; }
        if ($has($f['pulse'])) { $vitals[] = '<b>Pulse</b> ' . $f['pulse'] . '/mm'; }
        if ($has($f['temperature'])) { $vitals[] = '<b>Temp.</b> ' . $f['temperature']; }
        if ($has($f['respiration'])) { $vitals[] = '<b>Resp.</b> ' . $f['respiration']; }
        if ($has($f['spo2'])) { $vitals[] = '<b>Spo2</b> ' . $f['spo2']; }
        if ($has($f['weight'])) { $vitals[] = '<b>Weight</b> ' . $f['weight']; }
        if ($has($f['height'])) { $vitals[] = '<b>Height</b> ' . $f['height'] . 'cm'; }
        if ($vitals) { $h .= '<p><b>Vitals:</b> ' . implode(', ', $vitals) . '</p>'; }

        $cns = [];
        if ($has($f['cns_system_mental_status'])) { $cns[] = '<b>Mental Status</b> ' . $f['cns_system_mental_status']; }
        if ($has($f['cns_system_sensory_examination'])) { $cns[] = '<b>Sensory Examination</b> ' . $f['cns_system_sensory_examination']; }
        if ($has($f['cns_system_motor_examination_tone'])) { $cns[] = '<b>Motor Examination Tool</b> ' . $f['cns_system_motor_examination_tone']; }
        if ($has($f['cns_system_reflexes'])) { $cns[] = '<b>Reflexes</b> ' . $f['cns_system_reflexes']; }
        if ($has($f['cns_system_power'])) { $cns[] = '<b>Power</b> ' . $f['cns_system_power']; }
        if ($cns) { $h .= '<p><b>CNS System:</b> ' . implode(', ', $cns) . '</p>'; }

        $line8 = [];
        if ($has($f['provisional_diagnosis'])) { $line8[] = '<b>Provisional Diagnosis</b> ' . $f['provisional_diagnosis']; }
        if ($has($f['diet_plan'])) { $line8[] = '<b>Diet Plan</b> ' . $f['diet_plan']; }
        if ($line8) { $h .= '<p>' . implode('<br>', $line8) . '</p>'; }

        return $h;
    }
}
