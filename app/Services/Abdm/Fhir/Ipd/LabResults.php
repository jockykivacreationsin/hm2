<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** What was collected from an admission's lab services while building its DiagnosticReport. */
final class LabResults
{
    /** @var list<array{reference:string}> */
    public array $observationRefs = [];

    /** @var list<string> */
    public array $testTitles = [];

    public ?string $latestEffective = null;
}
