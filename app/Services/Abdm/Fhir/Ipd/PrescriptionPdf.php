<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * IPD prescription PDF for the Prescription bundle: the admission's medicines from all four sources, each in its own
 * section (doctor prescriptions, treatment chart, given at discharge, advised at discharge).
 *
 * The data is loaded here; the document itself is the standard report layout
 * (view pdf.abdm.ipd-prescription, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class PrescriptionPdf
{
    /** @return array{success:bool, error?:string, pdf_base64?:string, pdf_size?:int} */
    public function generate(int $ipdId): array
    {
        $row = FhirQuery::first("SELECT ipds.*, 
            DATE_FORMAT(ipds.admission_date_time, '%d-%b-%Y') as admission_date,
            DATE_FORMAT(ipds.admission_date_time, '%Y') as iyear,
            patients.id as patient_id, patients.patient_name, patients.guardian_name, 
            patients.gender, patients.age, patients.address as patient_address, patients.mobile_number as patient_mobile,
            staff_members.staff_name as doctor_name, staff_members.qualification,
            ipd_discharge.discharge_date_time,
            DATE_FORMAT(ipd_discharge.discharge_date_time, '%d-%b-%Y') as discharge_date
            FROM ipds
            INNER JOIN patients ON patients.id = ipds.patient_id
            LEFT JOIN staff_members ON staff_members.id = ipds.consultant_doctor_id
            LEFT JOIN ipd_discharge ON ipd_discharge.ipd_id = ipds.id
            WHERE ipds.id = ?", 'i', [$ipdId]);

        if (!$row) {
            return ['success' => false, 'error' => 'IPD admission not found'];
        }

        $prescriptions = $this->doctorPrescriptions($ipdId);
        $treatments = $this->treatmentChart($ipdId);
        $isDischarged = !empty($row['discharge_date_time']);
        [$given, $advised] = $isDischarged ? $this->dischargeMedications($ipdId) : [[], []];

        $pdf = app(LegacyReportRenderer::class)->standardView(
            'pdf.abdm.ipd-prescription',
            compact('row', 'prescriptions', 'treatments', 'isDischarged', 'given', 'advised')
        );

        return [
            'success' => true,
            'pdf_base64' => base64_encode($pdf),
            'pdf_size' => strlen($pdf),
        ];
    }

    /**
     * 1. ipd_prescriptions + ipd_medicines
     *
     * @return list<array{row: array<string, mixed>, medicines: list<object>}>
     */
    private function doctorPrescriptions(int $ipdId): array
    {
        $prescriptions = FhirQuery::rows("SELECT id, findings, DATE_FORMAT(prescription_date_time, '%d-%b-%Y %h:%i %p') as prescription_datetime FROM ipd_prescriptions WHERE ipd_id = ? ORDER BY prescription_date_time", 'i', [$ipdId]);

        return array_map(fn (array $prescription) => [
            'row' => $prescription,
            'medicines' => $this->objects(FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                m.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                m.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_medicines m
            INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
            LEFT JOIN ipd_medicine_timing_selections mts ON m.id = mts.ipd_medicine_id
            LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
            WHERE m.ipd_prescription_id = ?
            GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, m.duration_days
            ORDER BY m.id", 'i', [intval($prescription['id'])])),
        ], $prescriptions);
    }

    /**
     * 2. ipd_treatments + ipd_treatment_detail
     *
     * @return list<array{row: array<string, mixed>, medicines: list<object>}>
     */
    private function treatmentChart(int $ipdId): array
    {
        $treatments = FhirQuery::rows("SELECT id, DATE_FORMAT(treatment_date_time, '%d-%b-%Y %h:%i %p') as treatment_datetime FROM ipd_treatments WHERE ipd_id = ? ORDER BY treatment_date_time", 'i', [$ipdId]);

        return array_map(fn (array $treatment) => [
            'row' => $treatment,
            'medicines' => $this->objects(FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                d.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                d.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_treatment_detail d
            INNER JOIN pharma_products pp ON d.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON d.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON d.meal_relation_id = mr.id
            LEFT JOIN ipd_treatment_timing_selections tts ON d.id = tts.treatment_detail_id
            LEFT JOIN medicine_timing_of_day t ON tts.timing_id = t.id
            WHERE d.ipd_treatment_id = ?
            GROUP BY d.id, pp.product_name, d.dose_quantity, ft.display_text, mr.relation_name, d.duration_days
            ORDER BY d.id", 'i', [intval($treatment['id'])])),
        ], $treatments);
    }

    /**
     * 3. ipd_discharge_medication_given and 4. ipd_discharge_treatment_advised (only once discharged).
     *
     * @return array{list<object>, list<object>} [given (free-text timing_display), advised (timing_names)]
     */
    private function dischargeMedications(int $ipdId): array
    {
        $given = FhirQuery::rows("SELECT medicine_name, dose_quantity, frequency_display, timing_display, meal_relation, duration_days
            FROM ipd_discharge_medication_given
            INNER JOIN ipd_discharge ON ipd_discharge.id = ipd_discharge_medication_given.discharge_id
            WHERE ipd_discharge.ipd_id = ?", 'i', [$ipdId]);

        $advised = FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                ta.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                ta.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_discharge_treatment_advised ta
            INNER JOIN ipd_discharge ON ipd_discharge.id = ta.discharge_id
            INNER JOIN pharma_products pp ON ta.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON ta.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON ta.meal_relation_id = mr.id
            LEFT JOIN ipd_discharge_treatment_timing_selections dts ON ta.id = dts.treatment_advised_id
            LEFT JOIN medicine_timing_of_day t ON dts.timing_id = t.id
            WHERE ipd_discharge.ipd_id = ?
            GROUP BY ta.id, pp.product_name, ta.dose_quantity, ft.display_text, mr.relation_name, ta.duration_days
            ORDER BY ta.id", 'i', [$ipdId]);

        return [$this->objects($given), $this->objects($advised)];
    }

    /**
     * The shared medicine table partial (pdf.partials.ipd-rx-table) reads its rows as objects.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return list<object>
     */
    private function objects(array $rows): array
    {
        return array_map(static fn (array $row) => (object) $row, array_values($rows));
    }
}
