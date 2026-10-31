<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;
use App\Services\Pdf\LegacyReportRenderer;

/**
 * DCD laboratory report PDF, attached to the DiagnosticReport bundle as a DocumentReference.
 *
 * Data comes from the same queries as before; the document itself is the standard report layout
 * (view pdf.abdm.dcd-lab-report, shared header + report-standard.css, see docs/PDF_REPORTS.md).
 */
final class LabInvestigationsPdfRenderer
{
    /** @return string|null raw PDF bytes, or null when the visit or its results do not exist */
    public function render(int $dcdId): ?string
    {
        if ($dcdId <= 0) {
            return null;
        }

        $visit = FhirQuery::first("SELECT dcds.id, dcds.dcd_date_time, patients.patient_name, patients.gender, patients.date_of_birth
            FROM dcds INNER JOIN patients ON patients.id = dcds.patient_id WHERE dcds.id = ?", 'i', [$dcdId]);
        if (!$visit) {
            return null;
        }

        $results = $this->loadResults($dcdId);
        if (!$results) {
            return null;
        }

        $age = !empty($visit['date_of_birth'])
            ? floor((time() - strtotime($visit['date_of_birth'])) / 31556926)
            : null;
        $groups = $this->groups($results);

        return app(LegacyReportRenderer::class)->standardView('pdf.abdm.dcd-lab-report', compact('visit', 'age', 'groups'));
    }

    /** @return array<int, array<string, mixed>> */
    private function loadResults(int $dcdId): array
    {
        return FhirQuery::rows("SELECT r.test_value, r.highlighted, s.sub_test_name, s.reference_range, u.unit_name,
                c.charge_name, ds.service_date_time
            FROM dcd_lab_test_results r
            INNER JOIN dcd_services ds ON ds.id = r.lab_investigation_id AND ds.dcd_id = r.dcd_id
            INNER JOIN dcd_service_detail dsd ON dsd.id = r.dcd_service_detail_id
            INNER JOIN charges c ON c.id = dsd.charge_id
            INNER JOIN biochemistry_sub_tests s ON s.id = r.test_id
            LEFT JOIN bunits u ON u.id = s.unit_id
            WHERE r.dcd_id = ? AND TRIM(COALESCE(r.test_value, '')) <> ''
            ORDER BY ds.service_date_time, r.dcd_service_detail_id, s.id", 'i', [$dcdId]);
    }

    /**
     * Results grouped per ordered test (consecutive rows share the charge name and service time).
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array{title: string, rows: array<int, array<string, mixed>>}>
     */
    private function groups(array $results): array
    {
        $groups = [];
        foreach ($results as $row) {
            $title = $row['charge_name'] . ' (' . date('d-M-Y h:i A', strtotime($row['service_date_time'])) . ')';
            if (!$groups || end($groups)['title'] !== $title) {
                $groups[] = ['title' => $title, 'rows' => []];
            }

            $groups[array_key_last($groups)]['rows'][] = [
                'name' => $row['sub_test_name'],
                'value' => trim((string) $row['test_value']),
                'range' => $row['reference_range'],
                'unit' => trim((string) ($row['unit_name'] ?? '')),
                // Legacy quirk: only the exact spelling "Yes" is flagged here, while the FHIR Observation matches case-insensitively.
                'highlighted' => (string) $row['highlighted'] === 'Yes',
            ];
        }

        return $groups;
    }
}
