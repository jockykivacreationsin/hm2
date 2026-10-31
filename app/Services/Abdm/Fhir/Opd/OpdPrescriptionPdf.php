<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * OPD prescription PDF attached as the Binary of the Prescription bundle.
 *
 * Data comes from the same queries as before; the document itself is the standard report layout
 * (view pdf.abdm.opd-prescription, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class OpdPrescriptionPdf
{
    /**
     * @param int|null $prescriptionId render only this prescription (default: all prescriptions of the visit)
     * @return array{success: bool, error?: string, pdf_base64?: string, pdf_size?: int}
     */
    public function render(int $opdId, ?int $prescriptionId = null): array
    {
        $row = $this->loadVisit($opdId);
        if (!$row) {
            return ['success' => false, 'error' => 'OPD visit not found'];
        }

        $prescriptions = array_map(
            fn (array $prescription) => [
                'row' => $prescription,
                'medicines' => $this->loadMedicines(intval($prescription['id'])),
            ],
            $this->loadPrescriptions($opdId, $prescriptionId)
        );

        $pdf = app(LegacyReportRenderer::class)->standardView('pdf.abdm.opd-prescription', compact('row', 'prescriptions'));

        return [
            'success' => true,
            'pdf_base64' => base64_encode($pdf),
            'pdf_size' => strlen($pdf),
        ];
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $opdId): ?array
    {
        $result = FhirQuery::rows("SELECT opds.*, 
        DATE_FORMAT(opds.opd_date_time, '%d-%m-%Y') as opd_date,
        patients.id as patient_id, patients.patient_name, patients.guardian_name, 
        patients.gender, patients.age, patients.address, patients.mobile_number,
        divisions.division_name, divisions.address as hospital_address, 
        divisions.phone_number, divisions.mobile_number as hospital_mobile, divisions.logo,
        staff_members.staff_name as doctor_name, staff_members.qualification
        FROM ((opds 
        INNER JOIN patients ON patients.id = opds.patient_id)
        INNER JOIN charge_departments ON charge_departments.id = opds.charge_department_id)
        INNER JOIN divisions ON divisions.id = charge_departments.division_id
        LEFT JOIN staff_members ON staff_members.id = opds.consultant_doctor_id
        WHERE opds.id = ?", 'i', [$opdId]);

        return array_shift($result);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadPrescriptions(int $opdId, ?int $prescriptionId): array
    {
        if ($prescriptionId) {
            return FhirQuery::rows("SELECT id, doctor_id, findings, 
            DATE_FORMAT(prescription_date_time, '%d-%m-%Y %h:%i %p') as prescription_date_time 
            FROM opd_prescriptions WHERE id = ?", 'i', [$prescriptionId]);
        }

        return FhirQuery::rows("SELECT id, doctor_id, findings,
            DATE_FORMAT(prescription_date_time, '%d-%m-%Y %h:%i %p') as prescription_date_time
            FROM opd_prescriptions WHERE opd_id = ? ORDER BY id", 'i', [$opdId]);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadMedicines(int $prescriptionId): array
    {
        return FhirQuery::rows("
            SELECT 
                m.id as medicine_id,
                pp.product_name as medicine_name,
                m.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                m.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM opd_medicines m
            INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
            LEFT JOIN opd_medicine_timing_selections mts ON m.id = mts.opd_medicine_id
            LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
            WHERE m.opd_prescription_id = ?
            GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, m.duration_days
            ORDER BY m.id
        ", 'i', [$prescriptionId]);
    }
}
