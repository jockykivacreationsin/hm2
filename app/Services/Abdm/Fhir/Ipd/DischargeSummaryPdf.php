<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;
use Exception;

/**
 * IPD Discharge Summary PDF, attached to the IPD DischargeSummary bundle.
 *
 * The data is loaded here; the document itself is the standard report layout
 * (view pdf.abdm.ipd-discharge-summary, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class DischargeSummaryPdf
{
    private const VITALS = [
        ['pulse', 'Pulse', ' /min'],
        ['temperature', 'Temperature', " \u{00B0}F"],
        ['bp', 'Blood Pressure', ' mmHg'],
        ['respiration', 'Respiration Rate', ' /min'],
        ['spo2', 'SpO2', ' %'],
    ];

    /** @return string|null the PDF bytes, or null when the admission does not exist or rendering failed */
    public function render(int $ipdId): ?string
    {
        if ($ipdId <= 0) {
            return null;
        }

        try {
            return $this->renderPdf($ipdId);
        } catch (Exception $e) {
            error_log('rpt-ipd-discharge-summary.php exception: ' . $e->getMessage());

            return null;
        }
    }

    private function renderPdf(int $ipdId): ?string
    {
        $row = FhirQuery::first("SELECT ipds.*, 
            DATE_FORMAT(ipds.admission_date_time, '%Y') as iyear, 
            DATE_FORMAT(ipds.admission_date_time, '%d-%b-%Y %h:%i %p') as admission_display, 
            DATE_FORMAT(ipd_discharge.discharge_date_time, '%d-%b-%Y %h:%i %p') as discharge_display,
            DATEDIFF(ipd_discharge.discharge_date_time, ipds.admission_date_time) + 1 as stay_days,
            patients.id as patient_id, patients.patient_name, patients.guardian_name, patients.gender, 
            patients.age, patients.address as patient_address, patients.mobile_number as patient_mobile, 
            patients.blood_group, patients.patient_category_id, patients.esm_name, patients.card_number,
            staff_members.staff_name as doctor_name,
            ipd_discharge.discharge_date_time, ipd_discharge.diagnosis as discharge_diagnosis, 
            ipd_discharge.condition_on_discharge, ipd_discharge.follow_up_detail, 
            ipd_discharge.urgent_care_condition, ipd_discharge.note as discharge_note,
            ipd_discharge.id as discharge_id
            FROM ipds 
            INNER JOIN patients ON patients.id = ipds.patient_id
            LEFT JOIN staff_members ON staff_members.id = ipds.consultant_doctor_id
            LEFT JOIN ipd_discharge ON ipd_discharge.ipd_id = ipds.id
            WHERE ipds.id = ?", 'i', [$ipdId]);

        if (!$row) {
            return null;
        }

        $patientFile = $this->patientFile($ipdId);
        $vitals = $this->vitals($ipdId);
        $labCount = $this->labCount($ipdId);
        $procedures = $this->procedures($ipdId, $row);
        $medications = $this->medications($ipdId, $row);
        $discharge = $this->dischargeSections($row);

        return app(LegacyReportRenderer::class)->standardView(
            'pdf.abdm.ipd-discharge-summary',
            compact('row', 'patientFile', 'vitals', 'labCount', 'procedures', 'medications', 'discharge')
        );
    }

    private function plainText(array $row, string $column): string
    {
        return trim(strip_tags($row[$column] ?? ''));
    }

    /**
     * Chief complaints, history, examinations and pain assessment from ipd_patient_files.
     *
     * @return array{history: list<array{string, string}>, allergies: string, examinations: list<array{string, string}>,
     *               cns: list<array{string, string}>, pain: list<array{string, string}>}|null
     */
    private function patientFile(int $ipdId): ?array
    {
        $pf = FhirQuery::first("SELECT * FROM ipd_patient_files WHERE ipd_id = ?", 'i', [$ipdId]);
        if (!$pf) {
            return null;
        }

        return [
            'history' => $this->textSections($pf, [
                ['chief_complaints', 'Chief Complaints'],
                ['history_of_present_illness', 'History of Present Illness'],
                ['provisional_diagnosis', 'Admission Diagnosis (Provisional)'],
                ['past_medical_history', 'Past Medical History'],
            ]),
            'allergies' => $this->plainText($pf, 'allergies'),
            'examinations' => $this->textSections($pf, [
                ['general_examination', 'General Examination'],
                ['systemic_examination', 'Systemic Examination'],
            ]),
            'cns' => $this->cnsExamination($pf),
            'pain' => $this->painAssessment($pf),
        ];
    }

    /**
     * @param list<array{string, string}> $columns [column, title]
     * @return list<array{string, string}> [title, text] of the non-empty columns
     */
    private function textSections(array $pf, array $columns): array
    {
        $sections = [];
        foreach ($columns as [$column, $title]) {
            $text = $this->plainText($pf, $column);
            if ($text !== '') {
                $sections[] = [$title, $text];
            }
        }

        return $sections;
    }

    /** @return list<array{string, string}> [label, text] */
    private function cnsExamination(array $pf): array
    {
        $lines = [];
        $plain = [
            'cns_system_mental_status' => 'Mental Status',
            'cns_system_sensory_examination' => 'Sensory',
            'cns_system_motor_examination_tone' => 'Motor Tone',
            'cns_system_reflexes' => 'Reflexes',
        ];
        foreach ($plain as $column => $label) {
            if (!empty(trim($pf[$column] ?? ''))) {
                $lines[] = [$label, trim($pf[$column])];
            }
        }
        // Only the power field is run through strip_tags.
        if (!empty(trim(strip_tags($pf['cns_system_power'] ?? '')))) {
            $lines[] = ['Power', trim(strip_tags($pf['cns_system_power']))];
        }

        return $lines;
    }

    /** @return list<array{string, string}> [label, value] */
    private function painAssessment(array $pf): array
    {
        $score = trim($pf['pain_score'] ?? '');
        $location = trim($pf['pain_location'] ?? '');

        $lines = [];
        if ($score !== '') {
            $lines[] = ['Pain Score', $score . ' / 10'];
        }
        if ($location !== '') {
            $lines[] = ['Pain Location', $location];
        }

        return $lines;
    }

    /**
     * The most recent vital signs reading (the legacy report labels it "At Discharge").
     *
     * @return list<array{string, string}> [label, value with unit]
     */
    private function vitals(int $ipdId): array
    {
        $day = FhirQuery::first("SELECT id, vital_monitoring_date FROM ipd_vital_monitoring WHERE ipd_id = ? ORDER BY vital_monitoring_date DESC LIMIT 1", 'i', [$ipdId]);
        if (!$day) {
            return [];
        }

        $detail = FhirQuery::first("SELECT * FROM ipd_vital_monitoring_detail WHERE ipd_vital_monitoring_id = ? ORDER BY vital_monitoring_time DESC LIMIT 1", 'i', [$day['id']]);
        if (!$detail) {
            return [];
        }

        $vitals = [];
        foreach (self::VITALS as [$column, $label, $unit]) {
            if (!empty($detail[$column])) {
                $vitals[] = [$label, $detail[$column] . $unit];
            }
        }

        return $vitals;
    }

    private function labCount(int $ipdId): int
    {
        return intval(FhirQuery::first("SELECT COUNT(*) as lab_count FROM biochemistry_tests_result WHERE ipd_id = ?", 'i', [$ipdId])['lab_count']);
    }

    /** @return list<array{name: string, date: string, status: string}> */
    private function procedures(int $ipdId, array $row): array
    {
        $procedures = FhirQuery::rows("SELECT ipd_services.service_title, ipd_services.service_date_time, ipd_services.status
            FROM ipd_services INNER JOIN charge_departments ON charge_departments.id = ipd_services.charge_department_id
            WHERE ipd_services.ipd_id = ? AND charge_departments.category = 'Procedure'
            ORDER BY ipd_services.service_date_time", 'i', [$ipdId]);

        $dischargeProcedures = [];
        if (!empty($row['discharge_id'])) {
            $dischargeProcedures = FhirQuery::rows("SELECT procedure_detail FROM ipd_discharge_procedures WHERE discharge_id = ?", 'i', [$row['discharge_id']]);
        }

        $list = [];
        foreach ($procedures as $proc) {
            $list[] = [
                'name' => (string) $proc['service_title'],
                'date' => date('d-m-Y h:i A', strtotime($proc['service_date_time'])),
                'status' => ucfirst((string) $proc['status']),
            ];
        }

        foreach ($dischargeProcedures as $dProc) {
            $detail = trim(strip_tags($dProc['procedure_detail']));
            if ($detail !== '') {
                $list[] = ['name' => $detail, 'date' => 'During Admission', 'status' => 'Completed'];
            }
        }

        return $list;
    }

    /**
     * Prescribed, given-at-discharge and advised-at-discharge medicines in one list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function medications(int $ipdId, array $row): array
    {
        $dischargeId = $row['discharge_id'] ?? 0;
        $queries = [
            [
                "SELECT pp.product_name as medicine_name, im.dose_quantity, im.duration_days 
                FROM ipd_medicines im 
                INNER JOIN pharma_products pp ON pp.id = im.pharma_product_id
                INNER JOIN ipd_prescriptions ipr ON ipr.id = im.ipd_prescription_id
                WHERE ipr.ipd_id = ?",
                $ipdId,
            ],
            [
                "SELECT medicine_name, dose_quantity, frequency_display, duration_days 
                FROM ipd_discharge_medication_given WHERE discharge_id = ?",
                $dischargeId,
            ],
            [
                "SELECT pp.product_name as medicine_name, dt.dose_quantity, dt.duration_days 
                FROM ipd_discharge_treatment_advised dt
                INNER JOIN pharma_products pp ON pp.id = dt.pharma_product_id
                WHERE dt.discharge_id = ?",
                $dischargeId,
            ],
        ];

        $allMedications = [];
        foreach ($queries as [$sql, $id]) {
            $allMedications = array_merge($allMedications, FhirQuery::rows($sql, 'i', [$id]));
        }

        return $allMedications;
    }

    /**
     * Final diagnosis, condition on discharge and follow-up instructions (empty string = not recorded).
     *
     * @return array{diagnosis: string, condition: string, follow_up: string, urgent_care: string, note: string}
     */
    private function dischargeSections(array $row): array
    {
        return [
            'diagnosis' => trim(strip_tags($row['discharge_diagnosis'] ?? '')),
            'condition' => trim($row['condition_on_discharge'] ?? ''),
            'follow_up' => trim(strip_tags($row['follow_up_detail'] ?? '')),
            'urgent_care' => trim($row['urgent_care_condition'] ?? ''),
            'note' => trim(strip_tags($row['discharge_note'] ?? '')),
        ];
    }
}
