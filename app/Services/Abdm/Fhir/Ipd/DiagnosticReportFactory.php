<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * ONE consolidated DiagnosticReport (+ its Observation results) covering every lab service of the admission
 * (ipd_services category 'Lab' + ipd_service_detail + biochemistry_tests_result).
 *
 * Deliberately a single report, not one per lab service: the DiagnosticReportRecord profile's section only allows
 * one DiagnosticReport entry per bundle (closed slice, 0..1).
 */
final class DiagnosticReportFactory
{
    public function __construct(private readonly ObservationFactory $observations = new ObservationFactory())
    {
    }

    /**
     * Adds the DiagnosticReport and Observations to the context's entries.
     *
     * @param array<string, mixed>|null $presentedForm optional attachment for DiagnosticReport.presentedForm
     * @return array{reference:string, type:string}|null section entry for the report; null when there is no lab data
     */
    public function build(IpdContext $ctx, ?array $presentedForm = null): ?array
    {
        $labServices = FhirQuery::rows("SELECT ipd_services.id, ipd_services.service_title, ipd_services.service_date_time, ipd_services.status
            FROM ipd_services INNER JOIN charge_departments ON charge_departments.id = ipd_services.charge_department_id
            WHERE ipd_services.ipd_id = ? AND charge_departments.category = 'Lab'", 'i', [$ctx->ipdId]);

        if (empty($labServices)) {
            return null;
        }

        $conclusion = $this->labConclusion($labServices[0]);
        $results = $this->collectResults($ctx, $labServices);

        $report = $this->reportResource($ctx, $results, $conclusion, $presentedForm);
        $ctx->entries->add($report);

        // DiagnosticReportRecord slices its section entries "closed by value:type", so the reference carries
        // Reference.type (fixed "DiagnosticReport" by the profile).
        return ['reference' => 'DiagnosticReport/' . $report['id'], 'type' => 'DiagnosticReport'];
    }

    /** The lab investigation note of the first lab service, or '' when there is none. */
    private function labConclusion(array $firstService): string
    {
        if (empty($firstService['id'])) {
            return '';
        }

        $note = FhirQuery::first("SELECT note FROM ipd_lab_investigation_notes WHERE ipd_service_id = ?", 'i', [$firstService['id']]);

        return ($note && !empty($note['note'])) ? trim($note['note']) : '';
    }

    private function collectResults(IpdContext $ctx, array $labServices): LabResults
    {
        $results = new LabResults();

        foreach ($labServices as $labService) {
            $results->testTitles[] = $labService['service_title'];
            // Legacy quirk: the report is dated by the LAST service in query order, not the latest one.
            $effective = FhirTime::utc($labService['service_date_time']);
            $results->latestEffective = $effective;

            $items = FhirQuery::rows("SELECT ipd_service_detail.id as detail_id, charges.charge_name FROM ipd_service_detail
                INNER JOIN charges ON charges.id = ipd_service_detail.charge_id
                WHERE ipd_service_detail.ipd_service_id = ?", 'i', [$labService['id']]);

            foreach ($items as $item) {
                $this->collectItemResults($ctx, $item['detail_id'], $effective, $results);
            }
        }

        return $results;
    }

    private function collectItemResults(IpdContext $ctx, mixed $detailId, string $effective, LabResults $results): void
    {
        $subResults = FhirQuery::rows("SELECT biochemistry_tests_result.test_value, biochemistry_tests_result.highlighted,
            biochemistry_sub_tests.sub_test_name, biochemistry_sub_tests.reference_range, bunits.unit_name, bunits.ucum_code
            FROM (biochemistry_tests_result INNER JOIN biochemistry_sub_tests ON biochemistry_sub_tests.id = biochemistry_tests_result.test_id)
            INNER JOIN bunits ON bunits.id = biochemistry_sub_tests.unit_id
            WHERE biochemistry_tests_result.ipd_service_detail_id = ?", 'i', [$detailId]);

        foreach ($subResults as $sub) {
            $results->observationRefs[] = $ctx->entries->add($this->observations->lab($ctx, $sub, $effective));
        }
    }

    private function reportResource(IpdContext $ctx, LabResults $results, string $conclusion, ?array $presentedForm): array
    {
        $report = [
            'resourceType' => 'DiagnosticReport',
            'id' => abdm_generate_uuid(),
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/DiagnosticReportLab']],
            'status' => !empty($results->observationRefs) ? 'final' : 'registered',
            // category is omitted on purpose: NRCeS fixes its coding system to SNOMED and there is no verified
            // mapping from the free-text hospital data (an earlier HL7 v2-0074 'LAB' category was a profile mismatch).
            // code uses the generic LOINC 11502-2 "Laboratory report" - per-test LOINC guesses would be a clinical risk.
            'code' => [
                'coding' => [['system' => 'http://loinc.org', 'code' => '11502-2', 'display' => 'Laboratory report']],
                'text' => 'Lab',
            ],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'effectiveDateTime' => $results->latestEffective,
            'issued' => $results->latestEffective,
            'performer' => [['reference' => $ctx->organizationRef()]],
            // Required (1..*) by DiagnosticReportLab; omitting it gave the ABHA app's "Your Data is incorrect" error.
            'resultsInterpreter' => [['reference' => $ctx->authorRef()]],
        ];

        // conclusion is required (1..1): the lab note when there is one, else the list of test titles.
        $report['conclusion'] = !empty($conclusion) ? $conclusion : 'Tests: ' . implode(', ', array_unique($results->testTitles));
        if (!empty($presentedForm)) {
            $report['presentedForm'] = [$presentedForm];
        }
        if (!empty($results->observationRefs)) {
            // result is also required (1..*) by the profile when there are results.
            $report['result'] = $results->observationRefs;
        }

        return $report;
    }
}
