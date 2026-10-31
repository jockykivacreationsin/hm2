<?php

namespace App\Services\Abdm\Fhir\Dcd;

/** Facility identity and letterhead values the DCD bundles need (filled from the settings table by DcdFhirBuilder). */
final class FacilityContext
{
    public function __construct(
        public readonly string $hfrId = '',
        public readonly string $name = '',
        public readonly string $signDesignation = '',
    ) {
    }
}
