<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** CodeableConcept shapes repeated across the discharge summary resources. */
final class FhirCoding
{
    public const SNOMED = 'http://snomed.info/sct';
    public const CONDITION_CLINICAL = 'http://terminology.hl7.org/CodeSystem/condition-clinical';
    public const CONDITION_VERIFICATION = 'http://terminology.hl7.org/CodeSystem/condition-ver-status';
    public const CONDITION_CATEGORY = 'http://terminology.hl7.org/CodeSystem/condition-category';

    /** @return array{coding: list<array{system:string, code:string, display:string}>} */
    public static function concept(string $system, string $code, string $display): array
    {
        return ['coding' => [['system' => $system, 'code' => $code, 'display' => $display]]];
    }

    public static function snomed(string $code, string $display): array
    {
        return self::concept(self::SNOMED, $code, $display);
    }

    /** Condition.category for every diagnosis-like Condition. */
    public static function encounterDiagnosis(): array
    {
        return [self::concept(self::CONDITION_CATEGORY, 'encounter-diagnosis', 'Encounter Diagnosis')];
    }
}
