<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * OPD lab investigations PDF, attached to the DiagnosticReport bundle.
 *
 * Same data and rules as before; the document is the standard report layout
 * (view pdf.abdm.opd-lab-report, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class OpdLabInvestigationsPdf
{
    /**
     * Raw PDF bytes, or null when the visit has no lab/radiology services (or does not exist).
     * With $serviceId only that service is rendered.
     */
    public function render(int $opdId, ?int $serviceId = null): ?string
    {
        if ($this->countServices($opdId, $serviceId) === 0) {
            return null;
        }

        $visit = FhirQuery::first("SELECT opds.*, patients.patient_name, patients.gender, patients.date_of_birth, patients.mobile_number as patient_phone, patients.abha_number, patients.abha_address, staff_members.staff_name as doctor_name FROM opds INNER JOIN patients ON patients.id = opds.patient_id LEFT JOIN staff_members ON staff_members.id = opds.consultant_doctor_id WHERE opds.id = ?", 'i', [$opdId]);
        if (!$visit) {
            return null;
        }

        return app(LegacyReportRenderer::class)->standardView('pdf.abdm.opd-lab-report', [
            'visit' => $visit,
            'opdId' => $opdId,
            'services' => $this->investigations($opdId, $serviceId),
        ]);
    }

    private function countServices(int $opdId, ?int $serviceId): int
    {
        if ($serviceId) {
            $row = FhirQuery::first("SELECT COUNT(*) as cnt FROM opd_services s 
			INNER JOIN charge_departments cd ON cd.id = s.charge_department_id 
			WHERE s.id = ? AND s.opd_id = ? AND (cd.category = 'Lab' OR cd.category = 'Radiology')", 'ii', [$serviceId, $opdId]);
        } else {
            $row = FhirQuery::first("SELECT COUNT(*) as cnt FROM opd_services s 
			INNER JOIN charge_departments cd ON cd.id = s.charge_department_id 
			WHERE s.opd_id = ? AND (cd.category = 'Lab' OR cd.category = 'Radiology')", 'i', [$opdId]);
        }

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * One entry per lab/radiology service: title, date, charge names, lab result groups (lab only) and the note.
     *
     * @return array<int, array<string, mixed>>
     */
    private function investigations(int $opdId, ?int $serviceId): array
    {
        $services = [];
        foreach ($this->loadServices($opdId, $serviceId) as $service) {
            $labServiceId = intval($service['id']);
            $isRadiology = Xhtml::isRadiology($service['category'], $service['department_name'] ?? '');
            $itemsRow = FhirQuery::first("SELECT GROUP_CONCAT(charges.charge_name SEPARATOR ', ') as charges FROM opd_service_detail INNER JOIN charges ON charges.id = opd_service_detail.charge_id WHERE opd_service_detail.opd_service_id = ?", 'i', [$labServiceId]);

            $services[] = [
                'title' => $service['service_title'],
                'date' => $service['service_date_time_display'],
                'charges' => $itemsRow['charges'] ?? '',
                'is_radiology' => $isRadiology,
                // Detailed results are only shown for Lab, not Radiology.
                'groups' => $isRadiology ? [] : $this->resultGroups($labServiceId),
                'note' => $this->note($labServiceId, $isRadiology),
            ];
        }

        return $services;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadServices(int $opdId, ?int $serviceId): array
    {
        if ($serviceId) {
            return FhirQuery::rows("SELECT opd_services.*, charge_departments.category, charge_departments.department_name, DATE_FORMAT(opd_services.service_date_time, '%d-%m-%Y %h:%i %p') as service_date_time_display FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id WHERE opd_services.id = ? AND opd_services.opd_id = ? ORDER BY opd_services.id", 'ii', [$serviceId, $opdId]);
        }

        return FhirQuery::rows("SELECT opd_services.*, charge_departments.category, charge_departments.department_name, DATE_FORMAT(opd_services.service_date_time, '%d-%m-%Y %h:%i %p') as service_date_time_display FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id WHERE (charge_departments.category = 'Lab' OR charge_departments.category = 'Radiology') AND opd_services.opd_id = ? ORDER BY opd_services.id", 'i', [$opdId]);
    }

    /** @return array<int, array{name: string, rows: array<int, array<string, mixed>>}> */
    private function resultGroups(int $serviceId): array
    {
        $groups = FhirQuery::rows("SELECT DISTINCT(opd_lab_test_results.opd_service_detail_id) as opd_service_detail_id, charges.charge_name FROM opd_lab_test_results INNER JOIN opd_service_detail ON opd_service_detail.id = opd_lab_test_results.opd_service_detail_id INNER JOIN charges ON charges.id = opd_service_detail.charge_id WHERE opd_lab_test_results.lab_investigation_id = ? ORDER BY opd_lab_test_results.opd_service_detail_id", 'i', [$serviceId]);

        return array_map(fn (array $group) => [
            'name' => $group['charge_name'],
            'rows' => FhirQuery::rows("SELECT opd_lab_test_results.*, biochemistry_sub_tests.sub_test_name, biochemistry_sub_tests.reference_range, bunits.unit_name FROM (opd_lab_test_results INNER JOIN biochemistry_sub_tests ON biochemistry_sub_tests.id = opd_lab_test_results.test_id) INNER JOIN bunits ON bunits.id = biochemistry_sub_tests.unit_id WHERE opd_lab_test_results.opd_service_detail_id = ?", 'i', [$group['opd_service_detail_id']]),
        ], $groups);
    }

    /** Conclusion (lab) or report (radiology) note with the author and edit time. @return array<string, mixed>|null */
    private function note(int $serviceId, bool $isRadiology): ?array
    {
        if ($isRadiology) {
            $sql = "SELECT note, updated_on, staff_members.staff_name FROM opd_radiology_notes LEFT JOIN staff_members ON staff_members.id = opd_radiology_notes.user_id WHERE opd_radiology_notes.opd_service_id = ?";
        } else {
            $sql = "SELECT note, updated_on, staff_members.staff_name FROM opd_lab_investigation_notes LEFT JOIN staff_members ON staff_members.id = opd_lab_investigation_notes.user_id WHERE opd_lab_investigation_notes.opd_service_id = ?";
        }

        return FhirQuery::first($sql, 'i', [$serviceId]);
    }
}
