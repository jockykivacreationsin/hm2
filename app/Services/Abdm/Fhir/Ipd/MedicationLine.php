<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** One medicine row from any of the admission's medication sources, before it becomes a MedicationRequest. */
final class MedicationLine
{
    public function __construct(
        public readonly string $medicineName,
        public readonly mixed $doseQuantity,
        public readonly string $timingNames,
        public readonly string $mealRelation,
        public readonly mixed $durationDays,
        /** 'Admission Prescription', 'Treatment Chart', ... - shown as the request note */
        public readonly string $source,
        public readonly mixed $medicalConditionId = null,
        public readonly mixed $conditionName = null,
        public readonly mixed $snomedCode = null,
        public readonly mixed $snomedDisplay = null,
    ) {
    }
}
