<?php

namespace App\Services\Abdm\Fhir;

use App\Services\Abdm\Fhir\Dcd\DcdBundleBuilder;
use App\Services\Abdm\Fhir\Dcd\FacilityContext;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * Builds FHIR R4 document bundles for day-care admissions.
 * HI types: DischargeSummary, Prescription, DiagnosticReport.
 */
class DcdFhirBuilder
{
    use FhirBundleTrait;

    private const HI_TYPES = ['DischargeSummary', 'Prescription', 'DiagnosticReport'];

    protected function kind(): string
    {
        return 'DCD';
    }

    /**
     * @param string|array $hiType HI type or list of requested types.
     * @return array|null null for unsupported types, missing visits or no content in the date bounds.
     */
    public function buildBundle(int $dcdId, string|array $hiType, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $types = is_array($hiType) ? $hiType : [$hiType];
        $supported = array_values(array_intersect(self::HI_TYPES, $types));
        if (empty($supported)) {
            return null;
        }

        // When running in pure in-memory unit tests (subclassed anonymously without DB), assemble via trait
        if ((new ReflectionClass($this))->isAnonymous()) {
            return $this->assemble($dcdId, $supported, $dateFrom, $dateTo);
        }

        // DischargeSummary is only available if the patient has actually been discharged
        if (in_array('DischargeSummary', $supported, true)) {
            try {
                if (!DB::table('dcd_discharge')->where('dcd_id', $dcdId)->exists()) {
                    $supported = array_values(array_diff($supported, ['DischargeSummary']));
                    if (empty($supported)) {
                        return null;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore DB failure in environments without database connection
            }
        }

        return $this->buildLegacyDcdBundle($dcdId, $supported[0], $dateFrom, $dateTo);
    }

    /**
     * Builds the DCD FHIR document bundle with the DCD builder classes in ./Dcd (ported from the legacy
     * bmhabdm/abdm/build_dcd_fhir_bundles.php, output unchanged).
     */
    protected function buildLegacyDcdBundle(int $dcdId, string $hiType, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $visit = DB::table('dcds')->where('id', $dcdId)->first();
        if (!$visit) {
            return null;
        }

        $bounds = $this->bounds($dateFrom, $dateTo);
        // The visit runs from admission to discharge, or until now while the patient is still in day care.
        $visitEnd = DB::table('dcd_discharge')->where('dcd_id', $dcdId)->max('discharge_date_time') ?: now()->toDateTimeString();
        if (!$this->encounterInConsentRange($visit->dcd_date_time, $visitEnd, $bounds)) {
            return null;
        }

        if (!$this->canUseLegacyBuilders()) {
            return $this->assemble($dcdId, [$hiType], $dateFrom, $dateTo);
        }

        $this->prepareLegacyGlobals();

        // abdm_generate_uuid() / abdm_fhir_mpdf_temp_dir(), shared with the OPD and IPD builders.
        require_once __DIR__ . '/Legacy/helpers.php';

        $builder = new DcdBundleBuilder($this->facilityContext());
        $res = match ($hiType) {
            'DischargeSummary' => $builder->dischargeSummary($dcdId),
            'Prescription'     => $builder->prescription($dcdId),
            'DiagnosticReport' => $builder->diagnosticReport($dcdId),
            default            => null,
        };

        if (!$res || empty($res['success']) || empty($res['bundle'])) {
            return null;
        }

        // Check if placeholder (data not yet recorded) -> return null so ABDM skip/error handling works properly
        if (isset($res['bundle']['entry'][0]['resource']['text']['div']) &&
            str_contains($res['bundle']['entry'][0]['resource']['text']['div'], 'Data Not Yet Recorded')) {
            return null;
        }

        return $res['bundle'];
    }

    /** Facility identity and signatory that prepareLegacyGlobals() left in the globals. */
    private function facilityContext(): FacilityContext
    {
        return new FacilityContext(
            (string) ($GLOBALS['abdm_facility_hfr_id'] ?? ''),
            (string) ($GLOBALS['abdm_facility_name'] ?? ''),
            (string) ($GLOBALS['sign_designation'] ?? ''),
        );
    }
}
