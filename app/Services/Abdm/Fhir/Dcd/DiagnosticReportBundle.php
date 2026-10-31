<?php

namespace App\Services\Abdm\Fhir\Dcd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * DiagnosticReport bundle of a day-care visit's lab results. Only built when saved lab test results exist; the lab
 * report PDF is attached as a separate DocumentReference (same pattern as IPD).
 */
final class DiagnosticReportBundle
{
    private const PROFILE_BASE = 'https://nrces.in/ndhm/fhir/r4/StructureDefinition/';

    public function __construct(
        private readonly CommonResources $common,
        private readonly LabInvestigationsPdfRenderer $pdf,
    ) {
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function build(int $dcdId): array
    {
        $visit = $this->loadVisit($dcdId);
        if (!$visit) {
            return ['success' => false, 'error' => 'DCD record not found'];
        }

        $results = $this->loadResults($dcdId);
        if (!$results) {
            return ['success' => false, 'error' => 'No DCD lab test results recorded'];
        }
        $labConclusion = $this->loadConclusion($results[0]['service_id'] ?? null);

        $ctx = BundleContext::open((string) $visit['dcd_date_time'], (string) ($visit['staff_name'] ?? ''));
        $this->addCommonResources($ctx, $visit);

        $observations = $this->addObservations($ctx, $results);
        $sectionEntries = [$this->addReport($ctx, $observations, $labConclusion)];
        $documentReference = $this->addPdfDocumentReference($ctx, $dcdId);
        if ($documentReference !== null) {
            $sectionEntries[] = $documentReference;
        }
        $this->addComposition($ctx, $sectionEntries, $dcdId);

        return ['success' => true, 'bundle' => DocumentBundle::forVisit($ctx->entries(), 'DCD-DiagnosticReport-' . $dcdId)];
    }

    /** @return array<string, mixed>|null */
    private function loadVisit(int $dcdId): ?array
    {
        return FhirQuery::first("SELECT dcds.dcd_date_time, patients.id AS patient_id, patients.patient_name,
                patients.gender, patients.date_of_birth, staff_members.staff_name,
                staff_members.hpr_id AS doctor_hpr_id,
                staff_members.medical_registration_number AS doctor_reg_number,
                staff_members.registration_council AS doctor_reg_council
            FROM dcds INNER JOIN patients ON patients.id = dcds.patient_id
            LEFT JOIN staff_members ON staff_members.id = dcds.consultant_doctor_id WHERE dcds.id = ?", 'i', [$dcdId]);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadResults(int $dcdId): array
    {
        return FhirQuery::rows("SELECT r.test_value, r.highlighted, r.test_id, s.sub_test_name, s.reference_range,
                u.unit_name, u.ucum_code, ds.service_date_time, ds.id as service_id
            FROM dcd_lab_test_results r
            INNER JOIN dcd_services ds ON ds.id = r.lab_investigation_id AND ds.dcd_id = r.dcd_id
            INNER JOIN dcd_service_detail dsd ON dsd.id = r.dcd_service_detail_id AND dsd.dcd_service_id = ds.id
            INNER JOIN biochemistry_sub_tests s ON s.id = r.test_id
            LEFT JOIN bunits u ON u.id = s.unit_id
            WHERE r.dcd_id = ? AND TRIM(COALESCE(r.test_value, '')) <> ''
            ORDER BY ds.service_date_time, r.dcd_service_detail_id, s.id", 'i', [$dcdId]);
    }

    /** Lab investigation conclusion from dcd_lab_investigation_notes, looked up for the first result's service only. */
    private function loadConclusion(mixed $serviceId): string
    {
        if (empty($serviceId)) {
            return '';
        }

        $noteResult = FhirQuery::first("SELECT note FROM dcd_lab_investigation_notes WHERE dcd_service_id = ?", 'i', [$serviceId]);
        if ($noteResult && !empty($noteResult['note'])) {
            return trim($noteResult['note']);
        }

        return '';
    }

    /** @param array<string, mixed> $visit */
    private function addCommonResources(BundleContext $ctx, array $visit): void
    {
        $this->common->addPatient($ctx, $visit, false);
        $this->common->addOrganization($ctx);
        $this->common->addPractitioner($ctx, $visit);
        $this->common->addAmbulatoryEncounter($ctx);
    }

    /** @param array<int, array<string, mixed>> $results */
    private function addObservations(BundleContext $ctx, array $results): LabObservations
    {
        $references = [];
        $testNames = [];
        $latest = $ctx->now;
        foreach ($results as $row) {
            // Legacy quirk: "latest" is simply the last row's date; rows are ordered by service date so it is the newest.
            $latest = BundleContext::timestamp((string) $row['service_date_time']);
            $testNames[] = $row['sub_test_name'];
            $resource = $this->observation($ctx, $row, $latest);
            $ctx->add('Observation', $resource['id'], $resource);
            $references[] = ['reference' => BundleContext::ref('Observation', $resource['id'])];
        }

        return new LabObservations($references, $testNames, $latest);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function observation(BundleContext $ctx, array $row, string $effective): array
    {
        $resource = [
            'resourceType' => 'Observation',
            'id' => abdm_generate_uuid(),
            'meta' => ['profile' => [self::PROFILE_BASE . 'Observation']],
            'status' => 'final',
            'category' => [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/observation-category', 'code' => 'laboratory', 'display' => 'Laboratory']]]],
            'code' => ['text' => $row['sub_test_name']],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'effectiveDateTime' => $effective,
            'performer' => [$ctx->organizationReference()],
        ];

        $value = trim((string) $row['test_value']);
        $unit = trim((string) ($row['unit_name'] ?? ''));
        if ($value !== '' && is_numeric($value)) {
            $resource['valueQuantity'] = ['value' => (float) $value, 'unit' => $unit, 'code' => trim((string) ($row['ucum_code'] ?? '')) ?: null, 'system' => 'http://unitsofmeasure.org'];
        } else {
            $resource['valueString'] = trim($value . ' ' . $unit);
        }
        if (trim((string) $row['reference_range']) !== '') {
            $resource['referenceRange'] = [['text' => trim((string) $row['reference_range'])]];
        }
        if (strtolower((string) $row['highlighted']) === 'yes') {
            $resource['interpretation'] = [['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation', 'code' => 'A', 'display' => 'Abnormal']]]];
        }

        return $resource;
    }

    /** @return array{reference: string, type: string} the Composition section entry of the report */
    private function addReport(BundleContext $ctx, LabObservations $observations, string $labConclusion): array
    {
        $reportId = abdm_generate_uuid();
        // The recorded conclusion wins; without one the report lists the distinct test names.
        $conclusionText = !empty($labConclusion) ? $labConclusion : 'Tests: ' . implode(', ', array_unique($observations->testNames));

        $ctx->add('DiagnosticReport', $reportId, [
            'resourceType' => 'DiagnosticReport',
            'id' => $reportId,
            'meta' => ['profile' => [self::PROFILE_BASE . 'DiagnosticReportLab']],
            'status' => 'final',
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '11502-2', 'display' => 'Laboratory report']], 'text' => 'Lab'],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'effectiveDateTime' => $observations->latest,
            'issued' => $observations->latest,
            'performer' => [$ctx->organizationReference()],
            'resultsInterpreter' => [['reference' => $ctx->authorReference()]],
            'conclusion' => $conclusionText,
            'result' => $observations->references,
        ]);

        return ['reference' => BundleContext::ref('DiagnosticReport', $reportId), 'type' => 'DiagnosticReport'];
    }

    /**
     * Lab report PDF as a separate DocumentReference.
     *
     * @return array{reference: string}|null the Composition section entry, or null when no PDF could be produced
     */
    private function addPdfDocumentReference(BundleContext $ctx, int $dcdId): ?array
    {
        $pdf = $this->pdf->render($dcdId);
        if ($pdf === null) {
            return null;
        }

        $docRefId = abdm_generate_uuid();
        $ctx->add('DocumentReference', $docRefId, [
            'resourceType' => 'DocumentReference',
            'id' => $docRefId,
            'meta' => ['profile' => [self::PROFILE_BASE . 'DocumentReference']],
            'status' => 'current',
            'type' => [
                'coding' => [['system' => 'http://snomed.info/sct', 'code' => '721981007', 'display' => 'Diagnostic studies report']],
                'text' => 'DCD Lab Investigations',
            ],
            'subject' => $ctx->patientReference(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorReference()]],
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language' => 'en-IN',
                    'data' => base64_encode($pdf),
                    'title' => 'DCD Lab Investigations',
                    'creation' => $ctx->now,
                ],
            ]],
        ]);

        return ['reference' => BundleContext::ref('DocumentReference', $docRefId)];
    }

    /** @param list<array<string, string>> $sectionEntries */
    private function addComposition(BundleContext $ctx, array $sectionEntries, int $dcdId): void
    {
        $compositionId = abdm_generate_uuid();
        $ctx->prepend('Composition', $compositionId, [
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'meta' => ['profile' => [self::PROFILE_BASE . 'DiagnosticReportRecord']],
            'identifier' => ['system' => 'https://ndhm.in/phr', 'value' => 'DCD-DiagnosticReport-' . $dcdId],
            'status' => 'final',
            'type' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '721981007', 'display' => 'Diagnostic Report']], 'text' => 'DCD Diagnostic Report - Lab'],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorReference()]],
            'title' => 'DCD Diagnostic Report - Lab',
            'custodian' => $ctx->organizationReference(),
            'text' => ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>DCD laboratory investigation results.</p></div>'],
            'section' => [[
                'title' => 'Diagnostic report: Lab',
                'code' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => '721981007', 'display' => 'Diagnostic Report']]],
                'entry' => $sectionEntries,
            ]],
        ]);
    }
}
