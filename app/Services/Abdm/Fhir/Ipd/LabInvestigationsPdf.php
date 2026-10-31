<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * IPD Lab Investigations report PDF, used as the DocumentReference attachment of the IPD DiagnosticReport bundle.
 *
 * The data is loaded here; the document itself is the standard report layout
 * (view pdf.abdm.ipd-lab-report, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class LabInvestigationsPdf
{
    /** @return string|null the PDF bytes, or null when the admission does not exist */
    public function render(int $ipdId): ?string
    {
        if ($ipdId <= 0) {
            return null;
        }

        $row = FhirQuery::first("SELECT ipds.*, DATE_FORMAT(ipds.admission_date_time, '%Y') as iyear,
            patients.id as patient_id, patients.patient_name, patients.guardian_name,
            patients.gender, patients.age, patients.address, patients.mobile_number,
            patients.alternate_mobile_number, patients.photo
            FROM ipds
            INNER JOIN patients ON patients.id = ipds.patient_id
            WHERE ipds.id = ?", 'i', [$ipdId]);

        if (!$row) {
            return null;
        }

        $groups = $this->resultGroups($ipdId);

        return app(LegacyReportRenderer::class)->standardView('pdf.abdm.ipd-lab-report', compact('row', 'groups'));
    }

    /**
     * One group per lab service detail (date + charge name) with its sub-test results.
     *
     * @return list<array{date: string, charge_name: string, results: array<int, array<string, mixed>>}>
     */
    private function resultGroups(int $ipdId): array
    {
        $outerRows = FhirQuery::rows("SELECT DISTINCT biochemistry_tests_result.ipd_service_detail_id as ipd_service_detail_id,
            DATE_FORMAT(ipd_services.service_date_time, '%d-%m-%Y %h:%i %p') as ipd_service_date_time,
            charges.charge_name
            FROM ((biochemistry_tests_result
            INNER JOIN ipd_service_detail ON ipd_service_detail.id = biochemistry_tests_result.ipd_service_detail_id)
            INNER JOIN charges ON charges.id = ipd_service_detail.charge_id)
            INNER JOIN ipd_services ON ipd_services.id = ipd_service_detail.ipd_service_id
            WHERE biochemistry_tests_result.ipd_id = ?
            ORDER BY biochemistry_tests_result.ipd_service_detail_id", 'i', [$ipdId]);

        $groups = [];
        foreach ($outerRows as $outerRow) {
            $groups[] = [
                'date' => (string) $outerRow['ipd_service_date_time'],
                'charge_name' => (string) $outerRow['charge_name'],
                'results' => $this->results(intval($outerRow['ipd_service_detail_id'])),
            ];
        }

        return $groups;
    }

    /** @return array<int, array<string, mixed>> */
    private function results(int $serviceDetailId): array
    {
        return FhirQuery::rows("SELECT biochemistry_tests_result.*, biochemistry_sub_tests.sub_test_name,
            biochemistry_sub_tests.reference_range, bunits.unit_name
            FROM (biochemistry_tests_result
            INNER JOIN biochemistry_sub_tests ON biochemistry_sub_tests.id = biochemistry_tests_result.test_id)
            INNER JOIN bunits ON bunits.id = biochemistry_sub_tests.unit_id
            WHERE biochemistry_tests_result.ipd_service_detail_id = ?", 'i', [$serviceDetailId]);
    }
}
