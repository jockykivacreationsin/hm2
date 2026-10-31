<?php

namespace App\Services\Abdm\Fhir\Opd;

/**
 * Read-only access to the letterhead / facility globals that FhirBundleTrait::prepareLegacyGlobals() fills
 * before the builders run ($abdm_facility_*, $hospital_*, $phone_number, $mobile_number, $sign_*).
 */
final class LegacyGlobals
{
    public static function string(string $name): string
    {
        return (string) ($GLOBALS[$name] ?? '');
    }
}
