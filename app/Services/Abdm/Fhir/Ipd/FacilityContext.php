<?php

namespace App\Services\Abdm\Fhir\Ipd;

/**
 * Facility identity and PDF letterhead, as published by FhirBundleTrait::prepareLegacyGlobals()
 * (the shared helper still hands them over as globals; this is the one place they are read).
 */
final class FacilityContext
{
    public function __construct(
        public readonly string $hfrId,
        public readonly string $facilityName,
        public readonly string $hospitalName,
        public readonly string $hospitalAddress,
        public readonly string $phoneNumber,
        public readonly string $mobileNumber,
        public readonly string $signName,
        public readonly string $signDesignation,
    ) {
    }

    /** @param string $defaultHospitalName letterhead name when the globals were never prepared */
    public static function fromGlobals(string $defaultHospitalName = ''): self
    {
        return new self(
            (string) ($GLOBALS['abdm_facility_hfr_id'] ?? ''),
            (string) ($GLOBALS['abdm_facility_name'] ?? ''),
            $GLOBALS['hospital_name'] ?? $defaultHospitalName,
            $GLOBALS['hospital_address'] ?? '',
            $GLOBALS['phone_number'] ?? '',
            $GLOBALS['mobile_number'] ?? '',
            $GLOBALS['sign_name'] ?? '',
            $GLOBALS['sign_designation'] ?? '',
        );
    }
}
