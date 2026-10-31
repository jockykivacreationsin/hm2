<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;
use Exception;

/**
 * DCD discharge summary PDF, attached to the DischargeSummary bundle as a DocumentReference.
 *
 * Data comes from the same queries as before; the document itself is the standard report layout
 * (view pdf.abdm.dcd-discharge-summary, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class DischargeSummaryPdfRenderer
{
    public function __construct(private readonly FacilityContext $facility)
    {
    }

    /** @return string|null raw PDF bytes, or null when the visit does not exist or rendering failed */
    public function render(int $dcdId): ?string
    {
        if ($dcdId <= 0) {
            return null;
        }

        try {
            $row = $this->loadVisit($dcdId);
            if (!$row) {
                return null;
            }

            return app(LegacyReportRenderer::class)->standardView('pdf.abdm.dcd-discharge-summary', [
                'row' => $row,
                'stay' => $this->stayDisplay($row),
                'printed' => date('d-M-Y h:i A'),
                'procedures' => $this->procedures($dcdId, $row['discharge_id']),
                'investigations' => $this->investigations($dcdId, $row['discharge_id']),
                'medications' => $this->medications($dcdId, $row['discharge_id']),
                'notes' => $this->notes($row),
                'signDesignation' => $this->facility->signDesignation,
            ]);
        } catch (Exception $e) {
            error_log("rpt-dcd-discharge-summary.php exception: " . $e->getMessage());
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $dcdId): ?array
    {
        return FhirQuery::first("SELECT dcds.*, 
		DATE_FORMAT(dcds.dcd_date_time, '%d-%b-%Y %h:%i %p') as admission_display, 
		DATE_FORMAT(dcd_discharge.discharge_date_time, '%d-%b-%Y %h:%i %p') as discharge_display,
		TIMESTAMPDIFF(HOUR, dcds.dcd_date_time, dcd_discharge.discharge_date_time) as stay_hours,
		patients.id as patient_id, patients.patient_name, patients.guardian_name, patients.gender, 
		patients.age, patients.address as patient_address, patients.mobile_number as patient_mobile, 
		patients.blood_group,
		divisions.division_name, divisions.address as division_address, 
		divisions.phone_number as division_phone, divisions.mobile_number as division_mobile,
		staff_members.staff_name as doctor_name, staff_members.qualification,
		dcd_discharge.discharge_date_time, dcd_discharge.diagnosis as discharge_diagnosis, 
		dcd_discharge.condition_on_discharge, dcd_discharge.follow_up_detail, 
		dcd_discharge.urgent_care_condition, dcd_discharge.note as discharge_note,
		dcd_discharge.id as discharge_id
		FROM dcds 
		INNER JOIN patients ON patients.id = dcds.patient_id
		INNER JOIN opds ON opds.id = dcds.opd_id
		INNER JOIN charge_departments ON charge_departments.id = opds.charge_department_id
		INNER JOIN divisions ON divisions.id = charge_departments.division_id
		LEFT JOIN staff_members ON staff_members.id = dcds.consultant_doctor_id
		LEFT JOIN dcd_discharge ON dcd_discharge.dcd_id = dcds.id
		WHERE dcds.id = ?", 'i', [$dcdId]);
    }

    /** @param array<string, mixed> $row */
    private function stayDisplay(array $row): string
    {
        $stayHours = intval($row['stay_hours'] ?? 0);

        return $stayHours > 24
            ? intval($stayHours / 24) . ' day(s)'
            : $stayHours . ' hour(s)';
    }

    /**
     * Procedures billed during the stay plus those recorded on the discharge.
     *
     * @return array<int, array{title: string, when: string, status: string}>
     */
    private function procedures(int $dcdId, mixed $dischargeId): array
    {
        $procedures = FhirQuery::rows("SELECT dcd_services.service_title, dcd_services.service_date_time, dcd_services.status
		FROM dcd_services INNER JOIN charge_departments ON charge_departments.id = dcd_services.charge_department_id
		WHERE dcd_services.dcd_id = ? AND charge_departments.category = 'Procedure'
		ORDER BY dcd_services.service_date_time", 'i', [$dcdId]);

        $dischargeProcedures = [];
        if (!empty($dischargeId)) {
            $dischargeProcedures = FhirQuery::rows("SELECT procedure_detail FROM dcd_discharge_procedures WHERE discharge_id = ?", 'i', [$dischargeId]);
        }

        $list = [];
        foreach ($procedures as $proc) {
            $list[] = [
                'title' => (string) $proc['service_title'],
                'when' => date('d-M-Y h:i A', strtotime($proc['service_date_time'])),
                'status' => ucfirst((string) $proc['status']),
            ];
        }
        foreach ($dischargeProcedures as $dProc) {
            $procDetail = trim(strip_tags((string) $dProc['procedure_detail']));
            if ($procDetail !== '') {
                $list[] = ['title' => $procDetail, 'when' => 'During Admission', 'status' => 'Completed'];
            }
        }

        return $list;
    }

    /**
     * Count of recorded lab results plus the investigations typed into the discharge.
     *
     * @return array{lab_count: int, rows: array<int, array<string, mixed>>}
     */
    private function investigations(int $dcdId, mixed $dischargeId): array
    {
        $labCount = FhirQuery::first("SELECT COUNT(*) as lab_count FROM dcd_lab_test_results WHERE dcd_id = ?", 'i', [$dcdId])['lab_count'];

        $dischargeInvestigations = [];
        if (!empty($dischargeId)) {
            $dischargeInvestigations = FhirQuery::rows("SELECT investigation, result FROM dcd_discharge_investigations WHERE discharge_id = ?", 'i', [$dischargeId]);
        }

        return ['lab_count' => intval($labCount), 'rows' => $dischargeInvestigations];
    }

    /**
     * Admission medicines followed by those given and advised on discharge.
     *
     * @return array<int, array<string, mixed>>
     */
    private function medications(int $dcdId, mixed $dischargeId): array
    {
        $allMedications = FhirQuery::rows("SELECT pp.product_name as medicine_name, dm.dose_quantity, dm.duration_days 
		FROM dcd_medicines dm 
		INNER JOIN pharma_products pp ON pp.id = dm.pharma_product_id
		INNER JOIN dcd_prescriptions dp ON dp.id = dm.dcd_prescription_id
		WHERE dp.dcd_id = ?", 'i', [$dcdId]);

        if (!empty($dischargeId)) {
            $givenMeds = FhirQuery::rows("SELECT medicine_name, dose_quantity, frequency_display, duration_days 
			FROM dcd_discharge_medication_given WHERE discharge_id = ?", 'i', [$dischargeId]);
            $allMedications = array_merge($allMedications, $givenMeds);

            $advisedMeds = FhirQuery::rows("SELECT pp.product_name as medicine_name, dt.dose_quantity, dt.duration_days 
			FROM dcd_discharge_treatment_advised dt
			INNER JOIN pharma_products pp ON pp.id = dt.pharma_product_id
			WHERE dt.discharge_id = ?", 'i', [$dischargeId]);
            $allMedications = array_merge($allMedications, $advisedMeds);
        }

        return $allMedications;
    }

    /**
     * Plain-text discharge notes ('' when empty); the view escapes them and keeps the line breaks.
     *
     * @param array<string, mixed> $row
     * @return array{diagnosis: string, condition: string, follow_up: string, urgent_care: string, additional: string}
     */
    private function notes(array $row): array
    {
        return [
            'diagnosis' => trim(strip_tags($row['discharge_diagnosis'] ?? '')),
            'condition' => trim($row['condition_on_discharge'] ?? ''),
            'follow_up' => trim(strip_tags($row['follow_up_detail'] ?? '')),
            'urgent_care' => trim($row['urgent_care_condition'] ?? ''),
            'additional' => trim(strip_tags($row['discharge_note'] ?? '')),
        ];
    }
}
