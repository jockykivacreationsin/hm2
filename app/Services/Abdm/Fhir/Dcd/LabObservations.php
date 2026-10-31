<?php

namespace App\Services\Abdm\Fhir\Dcd;

/** What the Observation resources of a DiagnosticReport contribute to the report itself. */
final class LabObservations
{
    /**
     * @param list<array{reference: string}> $references one per Observation, in result order
     * @param list<mixed> $testNames sub-test name per Observation, in result order
     * @param string $latest effective date-time of the LAST result row (not the maximum; see DiagnosticReportBundle)
     */
    public function __construct(
        public readonly array $references,
        public readonly array $testNames,
        public readonly string $latest,
    ) {
    }
}
