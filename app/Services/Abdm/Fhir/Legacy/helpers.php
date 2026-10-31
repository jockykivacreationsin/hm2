<?php
/**
 * Helpers shared by the vendored legacy FHIR builders in this folder (IPD / OPD / DCD).
 *
 * The builders are plain procedural files taken from bmhabdm/abdm/build_*_fhir_bundles.php (and the
 * rpt-*.php PDF helpers they use). They are NOT autoloaded classes - the project ships an authoritative
 * classmap - so each builder is require_once'd by its App\Services\Abdm\Fhir\*FhirBuilder.
 *
 * What replaces the legacy environment (bmhabdm/db.php + abdm/common.php):
 *   - the $abdm_facility_* / $hospital_* / $sign_* globals are supplied by
 *     FhirBundleTrait::prepareLegacyGlobals(); every query goes through FhirQuery (Laravel's DB layer);
 *   - abdm_generate_uuid() and abdm_fhir_mpdf_temp_dir() live here;
 *   - mPDF comes from composer and relative "assets/..." paths resolve against public/.
 */

if (!function_exists('abdm_generate_uuid')) {
    function abdm_generate_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('abdm_fhir_mpdf_temp_dir')) {
    /** Writable mPDF temp directory (storage/app/mpdf), created on first use. */
    function abdm_fhir_mpdf_temp_dir(): string
    {
        $dir = storage_path('app/mpdf');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
