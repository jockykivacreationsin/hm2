<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * hiType DiagnosticReport: lab and radiology services of the visit (opd_services) with their detailed results.
 *
 * NRCeS's DiagnosticReportRecord profile is a CLOSED slice (at most ONE DiagnosticReport and/or ONE
 * DocumentReference entry). The combined lab PDF is therefore attached as a DocumentReference at the end of the
 * bundle - Binary is not allowed in the Composition section.
 */
final class DiagnosticReportBuilder
{
    private const TITLE = 'Diagnostic Report';

    private const SNOMED_CODE = '721981007';

    public function __construct(
        private readonly OpdBundleAssembler $assembler,
        private readonly OpdLabInvestigationsPdf $labPdf,
    ) {
    }

    /** @return array{success: bool, bundle: array<string, mixed>} */
    public function build(OpdBundleContext $context): array
    {
        if (!$this->hasLabServices($context->opdId)) {
            error_log("[ABDM][OPD][DIAGNOSTICREPORT] No lab data found for OPD #{$context->opdId} - returning placeholder bundle");

            return $this->assembler->placeholder($context, 'DiagnosticReport', self::TITLE, self::SNOMED_CODE, 'Diagnostic studies report');
        }

        $context->startDocument();

        $labReportPdf = $this->renderLabPdf($context->opdId);
        $sections = [];
        $labRefs = $this->addInvestigations($context);
        if (!empty($labRefs)) {
            $sections[] = $this->investigationsSection($labRefs);
        }
        if ($labReportPdf !== null) {
            $sections = $this->attachPdf($context, $sections, $labReportPdf);
        }

        $composition = $this->assembler->composition(
            $context,
            self::TITLE,
            $this->assembler->snomed(self::SNOMED_CODE, 'Diagnostic Report')['coding'],
            Xhtml::div('<p>Diagnostic report with lab investigations.</p>'),
            $sections,
        );

        return $this->assembler->finish($context, $composition);
    }

    private function hasLabServices(int $opdId): bool
    {
        $row = FhirQuery::first("SELECT COUNT(*) as cnt FROM opd_services s 
            INNER JOIN charge_departments cd ON cd.id = s.charge_department_id 
            WHERE s.opd_id = ? AND cd.category = 'Lab'", 'i', [$opdId]);

        return (int) ($row['cnt'] ?? 0) !== 0;
    }

    /** One combined lab PDF for the visit; null when it is empty or not a PDF (the bundle is then built without it). */
    private function renderLabPdf(int $opdId): ?string
    {
        $pdf = $this->labPdf->render($opdId);
        if ($pdf === null || $pdf === '') {
            error_log('[ABDM][OPD][DIAGNOSTIC] Lab PDF generation returned empty for opd_id=' . $opdId);

            return null;
        }
        if (strncmp($pdf, '%PDF', 4) !== 0) {
            error_log('[ABDM][OPD][DIAGNOSTIC] Generated lab output is not a valid PDF (bad header) for opd_id=' . $opdId);

            return null;
        }
        error_log('[ABDM][OPD][DIAGNOSTIC] Lab PDF size: ' . strlen($pdf) . ' bytes');

        return $pdf;
    }

    /** @return array<int, array{reference: string}> */
    private function addInvestigations(OpdBundleContext $context): array
    {
        $services = FhirQuery::rows("SELECT opd_services.id, opd_services.service_title, opd_services.service_date_time, charge_departments.category, charge_departments.department_name
            FROM opd_services INNER JOIN charge_departments ON charge_departments.id = opd_services.charge_department_id
            WHERE opd_services.opd_id = ? AND (charge_departments.category = 'Lab' OR charge_departments.category = 'Radiology')", 'i', [$context->opdId]);

        $refs = [];
        foreach ($services as $service) {
            $tests = FhirQuery::rows("SELECT opd_service_detail.id as detail_id, charges.charge_name
                FROM opd_service_detail
                INNER JOIN charges ON charges.id = opd_service_detail.charge_id
                WHERE opd_service_detail.opd_service_id = ?", 'i', [$service['id']]);

            if (Xhtml::isRadiology($service['category'] ?? '', $service['department_name'] ?? '')) {
                $refs[] = $this->addRadiologyReport($context, $service, $tests);
                continue;
            }
            // Only Lab services get one DiagnosticReport per test with detailed sub-test results.
            foreach ($tests as $test) {
                $refs[] = $this->addLabReport($context, $service, $test);
            }
        }

        return $refs;
    }

    /**
     * One DiagnosticReport for a lab test, with an Observation per sub-test result.
     *
     * @return array{reference: string}
     */
    private function addLabReport(OpdBundleContext $context, array $service, array $test): array
    {
        $observationRefs = $this->addSubTestObservations($context, $service, (int) $test['detail_id']);

        $drId = abdm_generate_uuid();
        $note = FhirQuery::first("SELECT note FROM opd_lab_investigation_notes WHERE opd_service_id = ?", "i", [$service['id']]);
        $report = $this->diagnosticReport($context, $drId, $service, 'LAB', 'Laboratory', $test['charge_name']);
        $report['result'] = $observationRefs;

        return $context->add('DiagnosticReport', $drId, $this->withConclusion($report, $note));
    }

    /**
     * Radiology gets a single DiagnosticReport with the report note only (no sub-test results).
     *
     * @return array{reference: string}
     */
    private function addRadiologyReport(OpdBundleContext $context, array $service, array $tests): array
    {
        $drId = abdm_generate_uuid();
        $note = FhirQuery::first("SELECT note FROM opd_radiology_notes WHERE opd_service_id = ?", 'i', [$service['id']]);

        $testNames = array_column($tests, 'charge_name');
        $codeText = $service['service_title'] . (!empty($testNames) ? ' (' . implode(', ', $testNames) . ')' : '');
        $report = $this->diagnosticReport($context, $drId, $service, 'RAD', 'Radiology', $codeText);

        return $context->add('DiagnosticReport', $drId, $this->withConclusion($report, $note));
    }

    /** @return array<int, array{reference: string}> */
    private function addSubTestObservations(OpdBundleContext $context, array $service, int $detailId): array
    {
        $results = FhirQuery::rows("SELECT
                    opd_lab_test_results.test_value,
                    opd_lab_test_results.highlighted,
                    biochemistry_sub_tests.sub_test_name,
                    biochemistry_sub_tests.reference_range,
                    bunits.unit_name
                    FROM opd_lab_test_results
                    INNER JOIN biochemistry_sub_tests ON biochemistry_sub_tests.id = opd_lab_test_results.test_id
                    LEFT JOIN bunits ON bunits.id = biochemistry_sub_tests.unit_id
                    WHERE opd_lab_test_results.opd_service_detail_id = ?", 'i', [$detailId]);

        $refs = [];
        foreach ($results as $result) {
            $obsId = abdm_generate_uuid();
            $refs[] = $context->add('Observation', $obsId, $this->subTestObservation($context, $obsId, $service, $result));
        }

        return $refs;
    }

    /** @return array<string, mixed> */
    private function subTestObservation(OpdBundleContext $context, string $obsId, array $service, array $result): array
    {
        $observation = [
            'resourceType' => 'Observation',
            'id' => $obsId,
            'status' => 'final',
            'code' => ['text' => $result['sub_test_name']],
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'effectiveDateTime' => OpdBundleContext::dateTime($service['service_date_time']),
        ];

        if (is_numeric($result['test_value'])) {
            $observation['valueQuantity'] = [
                'value' => (float) $result['test_value'],
                'unit' => $result['unit_name'] ?? '',
            ];
        } else {
            $observation['valueString'] = $result['test_value'];
        }

        if (!empty($result['reference_range'])) {
            $observation['referenceRange'] = [['text' => $result['reference_range']]];
        }

        return $observation;
    }

    /**
     * DiagnosticReport without results/conclusion (added by the callers, in that order).
     *
     * @return array<string, mixed>
     */
    private function diagnosticReport(OpdBundleContext $context, string $drId, array $service, string $categoryCode, string $categoryDisplay, ?string $codeText): array
    {
        return [
            'resourceType' => 'DiagnosticReport',
            'id' => $drId,
            'status' => 'final',
            'category' => [[
                'coding' => [[
                    'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
                    'code' => $categoryCode,
                    'display' => $categoryDisplay,
                ]],
            ]],
            'code' => ['text' => $codeText],
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'effectiveDateTime' => OpdBundleContext::dateTime($service['service_date_time']),
            'issued' => $context->now,
            'performer' => [$context->organization()],
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    private function withConclusion(array $report, ?array $note): array
    {
        if (!empty($note['note'])) {
            $report['conclusion'] = trim($note['note']);
        }

        return $report;
    }

    /**
     * @param array<int, array{reference: string}> $references
     * @return array<string, mixed>
     */
    private function investigationsSection(array $references): array
    {
        return $this->assembler->entrySection(
            'Investigations',
            ['coding' => [['system' => 'http://loinc.org', 'code' => '30954-2', 'display' => 'Relevant diagnostic tests/laboratory data Narrative']]],
            $references,
        );
    }

    /**
     * Adds the combined lab PDF as the last DocumentReference and lists it last in the Investigations section
     * (the section is created when the visit has no DiagnosticReport entries).
     *
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function attachPdf(OpdBundleContext $context, array $sections, string $pdf): array
    {
        $docRefId = abdm_generate_uuid();
        $context->add('DocumentReference', $docRefId, [
            'resourceType' => 'DocumentReference',
            'id' => $docRefId,
            'status' => 'current',
            'type' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => self::SNOMED_CODE, 'display' => 'Diagnostic studies report']], 'text' => 'Diagnostic Report'],
            'subject' => $context->subject(),
            'date' => $context->now,
            'author' => [['reference' => $context->authorReference()]],
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'language' => 'en-IN',
                    'data' => base64_encode($pdf),
                    'title' => 'Diagnostic Report',
                    'creation' => $context->now,
                ],
            ]],
        ]);

        if (empty($sections)) {
            $sections[] = $this->investigationsSection([]);
        }
        $sections[0]['entry'][] = ['reference' => 'DocumentReference/' . $docRefId];

        return $sections;
    }
}
