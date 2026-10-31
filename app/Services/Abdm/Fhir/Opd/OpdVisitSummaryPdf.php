<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * OPD Visit Summary PDF, attached as the DocumentReference of the OPConsultation bundle.
 *
 * Data comes from the same queries as before; the document itself is the standard report layout
 * (view pdf.abdm.opd-visit-summary, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class OpdVisitSummaryPdf
{
    /** Returns the raw PDF bytes, or null when the visit is not found. */
    public function render(int $opdId): ?string
    {
        if ($opdId <= 0) {
            return null;
        }

        $row = $this->loadVisit($opdId);
        if (!$row) {
            return null;
        }

        $vitals = $this->vitals($row);
        $prescriptions = $this->prescriptions($opdId);
        $investigations = $this->investigations($opdId);
        $procedures = $this->procedures($opdId);

        return app(LegacyReportRenderer::class)->standardView(
            'pdf.abdm.opd-visit-summary',
            compact('row', 'vitals', 'prescriptions', 'investigations', 'procedures'),
        );
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $opdId): ?array
    {
        return FhirQuery::first("SELECT opds.*, DATE_FORMAT(opds.opd_date_time, '%Y') as iyear, DATE_FORMAT(opds.opd_date_time, '%d-%m-%Y %h:%i %p') as opd_date_time_display, patients.id as patient_id, patients.patient_name, patients.guardian_name, patients.gender, patients.age, patients.address as patient_address, patients.mobile_number as patient_mobile, patients.alternate_mobile_number, patients.photo, patients.patient_category_id, patients.esm_name, patients.card_number, patients.service_number, patients.claim_id, staff_members.staff_name as doctor_name, divisions.division_name, divisions.slogan, divisions.email, divisions.address as division_address, divisions.phone_number, divisions.mobile_number as division_mobile, divisions.logo FROM (((opds INNER JOIN patients ON patients.id = opds.patient_id) INNER JOIN charge_departments ON charge_departments.id = opds.charge_department_id) INNER JOIN divisions ON divisions.id = charge_departments.division_id) LEFT JOIN staff_members ON staff_members.id = opds.consultant_doctor_id WHERE opds.id = ?", 'i', [$opdId]);
    }

    /** @return array<string, string> label => value */
    private function vitals(array $row): array
    {
        $vitals = [];
        // height/weight are free text: a unit is only appended when the entry (e.g. "165cm") has none.
        $height = trim((string) $row['height']);
        if ($height !== '') {
            $vitals['Height'] = $height . (stripos($height, 'cm') === false ? ' cm' : '');
        }
        $weight = trim((string) $row['weight']);
        if ($weight !== '') {
            $vitals['Weight'] = $weight . (stripos($weight, 'kg') === false ? ' kg' : '');
        }
        foreach (['bp' => 'BP', 'pulse' => 'Pulse', 'temperature' => 'Temp', 'respiration' => 'Resp', 'spo2' => 'SpO2'] as $field => $label) {
            if (trim((string) $row[$field]) !== '') {
                $vitals[$label] = (string) $row[$field];
            }
        }

        return $vitals;
    }

    /** @return array<int, array<string, mixed>> */
    private function prescriptions(int $opdId): array
    {
        $prescriptions = FhirQuery::rows("SELECT opd_prescriptions.*, DATE_FORMAT(prescription_date_time, '%d-%m-%Y %h:%i %p') as prescription_date_time_display, staff_members.staff_name FROM opd_prescriptions INNER JOIN staff_members ON staff_members.id = opd_prescriptions.doctor_id WHERE opd_prescriptions.opd_id = ? ORDER BY opd_prescriptions.id", 'i', [$opdId]);

        return array_map(fn (array $prescription) => [
            'date' => $prescription['prescription_date_time_display'],
            'doctor' => $prescription['staff_name'],
            'findings' => $prescription['findings'],
            'medicines' => $this->medicines(intval($prescription['id'])),
        ], $prescriptions);
    }

    /** @return array<int, array<string, mixed>> */
    private function medicines(int $prescriptionId): array
    {
        return FhirQuery::rows("
				SELECT 
					pp.product_name as medicine_name,
					m.dose_quantity,
					ft.display_text as frequency_display,
					mr.relation_name as meal_relation,
					m.duration_days,
					GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_display
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

    /**
     * Lab and radiology services with sub-test results (lab only) and conclusions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function investigations(int $opdId): array
    {
        $services = FhirQuery::rows("SELECT opd_services.*, DATE_FORMAT(opd_services.service_date_time, '%d-%m-%Y %h:%i %p') as service_date_time_display, charge_departments.department_name, charge_departments.category FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id WHERE (charge_departments.category = 'Lab' OR charge_departments.category = 'Radiology') AND opd_services.opd_id = ? ORDER BY opd_services.id", 'i', [$opdId]);

        $investigations = [];
        foreach ($services as $service) {
            $serviceId = intval($service['id']);
            $isRadiology = Xhtml::isRadiology($service['category'] ?? '', $service['department_name'] ?? '');

            $noteSql = $isRadiology
                ? "SELECT note FROM opd_radiology_notes WHERE opd_service_id = ?"
                : "SELECT note FROM opd_lab_investigation_notes WHERE opd_service_id = ?";

            $investigations[] = $this->serviceItem($service) + [
                'groups' => $isRadiology ? [] : $this->labResultGroups($serviceId),
                'note' => $this->noteText(FhirQuery::first($noteSql, 'i', [$serviceId])),
                'note_title' => $isRadiology ? 'Radiology Report:' : 'Lab Conclusion:',
            ];
        }

        return $investigations;
    }

    /**
     * "date - title (charge names)" heading data for a lab, radiology or procedure service.
     *
     * @return array{id: int, date: mixed, title: mixed, charges: mixed}
     */
    private function serviceItem(array $service): array
    {
        $itemsRow = FhirQuery::first("SELECT GROUP_CONCAT(charges.charge_name SEPARATOR ', ') as charges FROM opd_service_detail INNER JOIN charges ON charges.id = opd_service_detail.charge_id WHERE opd_service_detail.opd_service_id = ?", 'i', [intval($service['id'])]);

        return [
            'id' => intval($service['id']),
            'date' => $service['service_date_time_display'],
            'title' => $service['service_title'],
            'charges' => $itemsRow['charges'] ?? '',
        ];
    }

    /**
     * Detailed sub-test results entered for a lab service, grouped by test.
     *
     * @return array<int, array{charge_name: mixed, rows: array<int, array<string, mixed>>}>
     */
    private function labResultGroups(int $serviceId): array
    {
        $groups = FhirQuery::rows("SELECT DISTINCT(opd_lab_test_results.opd_service_detail_id) as opd_service_detail_id, charges.charge_name FROM opd_lab_test_results INNER JOIN opd_service_detail ON opd_service_detail.id = opd_lab_test_results.opd_service_detail_id INNER JOIN charges ON charges.id = opd_service_detail.charge_id WHERE opd_lab_test_results.lab_investigation_id = ? ORDER BY opd_lab_test_results.opd_service_detail_id", 'i', [$serviceId]);

        $result = [];
        foreach ($groups as $group) {
            $subTests = FhirQuery::rows("SELECT opd_lab_test_results.*, biochemistry_sub_tests.sub_test_name, biochemistry_sub_tests.reference_range, bunits.unit_name FROM (opd_lab_test_results INNER JOIN biochemistry_sub_tests ON biochemistry_sub_tests.id = opd_lab_test_results.test_id) INNER JOIN bunits ON bunits.id = biochemistry_sub_tests.unit_id WHERE opd_lab_test_results.opd_service_detail_id = ?", 'i', [$group['opd_service_detail_id']]);

            $result[] = [
                'charge_name' => $group['charge_name'],
                'rows' => array_map(fn (array $subTest) => [
                    'name' => $subTest['sub_test_name'],
                    'value' => $subTest['test_value'] . ' ' . $subTest['unit_name'],
                    'range' => $subTest['reference_range'],
                    'highlighted' => $subTest['highlighted'] == 'Yes',
                ], $subTests),
            ];
        }

        return $result;
    }

    /**
     * Conclusion / report / procedure note as plain text; '' when the note is empty after stripping tags.
     *
     * @param array<string, mixed>|null $noteRow
     */
    private function noteText(?array $noteRow): string
    {
        if (empty($noteRow['note'])) {
            return '';
        }

        return trim(strip_tags($noteRow['note']));
    }

    /** @return array<int, array<string, mixed>> */
    private function procedures(int $opdId): array
    {
        $procedures = FhirQuery::rows("SELECT opd_services.*, DATE_FORMAT(opd_services.service_date_time, '%d-%m-%Y %h:%i %p') as service_date_time_display, charge_departments.department_name FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id WHERE charge_departments.category = 'Procedure' AND opd_services.opd_id = ? ORDER BY opd_services.id", 'i', [$opdId]);

        return array_map(function (array $procedure) {
            $item = $this->serviceItem($procedure);
            $noteRow = FhirQuery::first("SELECT note FROM opd_procedure_notes WHERE opd_service_id = ?", 'i', [$item['id']]);

            return $item + ['note' => $this->noteText($noteRow)];
        }, $procedures);
    }
}
