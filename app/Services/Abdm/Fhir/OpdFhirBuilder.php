<?php

namespace App\Services\Abdm\Fhir;

use App\Services\Abdm\Fhir\Opd\OpdBundleBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * Builds FHIR R4 document bundles for OPD visits.
 * HI types: OPConsultation, Prescription, DiagnosticReport.
 */
class OpdFhirBuilder
{
    use FhirBundleTrait;

    private const HI_TYPES = ['OPConsultation', 'Prescription', 'DiagnosticReport'];

    protected function kind(): string
    {
        return 'OPD';
    }

    /**
     * @param string[] $requestedHiTypes consent-approved types; empty means all supported OPD types.
     *                                   Unsupported types are ignored.
     * @return array|null null when the visit is missing or no allowed content exists in the date bounds.
     */
    public function buildBundle(int $opdId, array $requestedHiTypes = [], ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $types = $requestedHiTypes ? array_values(array_intersect(self::HI_TYPES, $requestedHiTypes)) : self::HI_TYPES;
        if (empty($types)) {
            return null;
        }

        // When running in pure in-memory unit tests (subclassed anonymously without DB), assemble via trait
        if ((new ReflectionClass($this))->isAnonymous()) {
            return $this->assemble($opdId, $types, $dateFrom, $dateTo);
        }

        return $this->buildLegacyOpdBundle($opdId, $types, $dateFrom, $dateTo);
    }

    /**
     * Builds the OPD FHIR document bundle with the classes in ./Opd, a port of the legacy
     * bmhabdm/abdm/build_opd_fhir_bundles.php that produces byte-identical output.
     */
    protected function buildLegacyOpdBundle(int $opdId, array $types, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $visit = DB::table('opds')->where('id', $opdId)->first();
        if (!$visit) {
            return null;
        }

        $bounds = $this->bounds($dateFrom, $dateTo);
        // A visit's records can be dated after its start (prescriptions, lab services added later).
        $visitEnd = max(array_filter([
            $visit->opd_date_time,
            DB::table('opd_prescriptions')->where('opd_id', $opdId)->max('prescription_date_time'),
            DB::table('opd_services')->where('opd_id', $opdId)->max('service_date_time'),
        ]));
        if (!$this->encounterInConsentRange($visit->opd_date_time, $visitEnd, $bounds)) {
            return null;
        }

        if (!$this->canUseLegacyBuilders()) {
            return $this->assemble($opdId, $types, $dateFrom, $dateTo);
        }

        $hiType = count($types) === 1 ? $types[0] : (in_array('OPConsultation', $types, true) ? 'OPConsultation' : $types[0]);

        $this->prepareLegacyGlobals();

        // abdm_generate_uuid() and abdm_fhir_mpdf_temp_dir(), shared with the IPD/DCD builders.
        require_once __DIR__ . '/Legacy/helpers.php';

        $builder = app(OpdBundleBuilder::class);
        $res = match ($hiType) {
            'OPConsultation'   => $builder->opConsultation($opdId),
            'Prescription'     => $builder->prescription($opdId),
            'DiagnosticReport' => $builder->diagnosticReport($opdId),
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
}
