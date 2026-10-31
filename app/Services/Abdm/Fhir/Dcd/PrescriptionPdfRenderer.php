<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * DCD prescription PDF attached to the Prescription bundle.
 * Lists the medicines prescribed during the stay and, once discharged, the treatment advised on discharge
 * (dcd_medicines and dcd_discharge_treatment_advised).
 *
 * Same data as before; the document is the standard report layout
 * (view pdf.abdm.dcd-prescription, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class PrescriptionPdfRenderer
{
    /** @return array{success: bool, error?: string, pdf_base64?: string, pdf_size?: int} */
    public function generate(int $dcdId): array
    {
        error_log("[DCD PDF] Starting generate_dcd_prescription_pdf for DCD ID=$dcdId");

        $row = $this->loadVisit($dcdId);
        if (!$row) {
            error_log("[DCD PDF] ERROR: DCD visit not found for ID=$dcdId");
            return ['success' => false, 'error' => 'DCD visit not found'];
        }

        error_log("[DCD PDF] Found DCD ID=$dcdId, DCD Date=" . $row['dcd_date']);

        $isDischarged = !empty($row['discharge_id']);
        $pdfContent = app(LegacyReportRenderer::class)->standardView('pdf.abdm.dcd-prescription', [
            'row' => $row,
            'isDischarged' => $isDischarged,
            'prescriptions' => $this->admissionPrescriptions($dcdId),
            'dischargeMeds' => $isDischarged ? $this->dischargeMedications($dcdId) : [],
        ]);

        return [
            'success' => true,
            'pdf_base64' => base64_encode($pdfContent),
            'pdf_size' => strlen($pdfContent),
        ];
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $dcdId): ?array
    {
        return FhirQuery::first("SELECT dcds.*, 
        DATE_FORMAT(dcds.dcd_date_time, '%d-%m-%Y') as dcd_date,
        dcd_discharge.id as discharge_id,
        DATE_FORMAT(dcd_discharge.discharge_date_time, '%d-%m-%Y') as discharge_date,
        dcd_discharge.diagnosis,
        patients.id as patient_id, patients.patient_name, patients.guardian_name, 
        patients.gender, patients.age, patients.address, patients.mobile_number,
        divisions.division_name, divisions.address as hospital_address, 
        divisions.phone_number, divisions.mobile_number as hospital_mobile, divisions.logo,
        staff_members.staff_name as doctor_name, staff_members.qualification
        FROM ((dcds 
        INNER JOIN patients ON patients.id = dcds.patient_id)
        INNER JOIN opds ON opds.id = dcds.opd_id)
        INNER JOIN charge_departments ON charge_departments.id = opds.charge_department_id
        INNER JOIN divisions ON divisions.id = charge_departments.division_id
        LEFT JOIN dcd_discharge ON dcd_discharge.dcd_id = dcds.id
        LEFT JOIN staff_members ON staff_members.id = dcds.consultant_doctor_id
        WHERE dcds.id = ?", 'i', [$dcdId]);
    }

    /**
     * Section 1: every prescription written during the stay with its medicines and findings.
     *
     * @return array<int, array{row: array<string, mixed>, medicines: array<int, array<string, mixed>>}>
     */
    private function admissionPrescriptions(int $dcdId): array
    {
        $prescriptions = FhirQuery::rows("SELECT id, findings, DATE_FORMAT(prescription_date_time, '%d-%m-%Y %h:%i %p') as prescription_datetime FROM dcd_prescriptions WHERE dcd_id = ? ORDER BY prescription_date_time", 'i', [$dcdId]);

        return array_map(fn (array $pres) => [
            'row' => $pres,
            'medicines' => $this->admissionMedicines(intval($pres['id'])),
        ], $prescriptions);
    }

    /** @return array<int, array<string, mixed>> */
    private function admissionMedicines(int $prescriptionId): array
    {
        return FhirQuery::rows("
                SELECT 
                    m.id,
                    pp.product_name as medicine_name,
                    m.dose_quantity,
                    ft.display_text as frequency_display,
                    mr.relation_name as meal_relation,
                    m.duration_days,
                    GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
                FROM dcd_medicines m
                INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
                LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
                LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
                LEFT JOIN dcd_medicine_timing_selections mts ON m.id = mts.dcd_medicine_id
                LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
                WHERE m.dcd_prescription_id = ?
                GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, m.duration_days
                ORDER BY m.id", 'i', [$prescriptionId]);
    }

    /**
     * Section 2: treatment advised on discharge (only for discharged visits).
     *
     * @return array<int, array<string, mixed>>
     */
    private function dischargeMedications(int $dcdId): array
    {
        return FhirQuery::rows("
            SELECT 
                ta.id,
                pp.product_name as medicine_name,
                ta.dose_quantity,
                ft.display_text as frequency_display,
                mr.relation_name as meal_relation,
                ta.duration_days,
                mc.condition_name as reason,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM dcd_discharge_treatment_advised ta
            INNER JOIN dcd_discharge ON dcd_discharge.id = ta.discharge_id
            INNER JOIN pharma_products pp ON ta.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON ta.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON ta.meal_relation_id = mr.id
            LEFT JOIN medical_conditions mc ON ta.medical_condition_id = mc.id
            LEFT JOIN dcd_discharge_treatment_timing_selections tts ON ta.id = tts.treatment_advised_id
            LEFT JOIN medicine_timing_of_day t ON tts.timing_id = t.id
            WHERE dcd_discharge.dcd_id = ?
            GROUP BY ta.id, pp.product_name, ta.dose_quantity, ft.display_text, mr.relation_name, ta.duration_days, mc.condition_name
            ORDER BY ta.id", 'i', [$dcdId]);
    }
}
