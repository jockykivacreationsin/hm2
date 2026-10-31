<?php

namespace App\Services\Abdm\Fhir\Opd;

/**
 * Builds the FHIR R4 "document" bundle of an OPD visit, one per HI type (OPConsultation, Prescription,
 * DiagnosticReport), like IPD/DCD: when a consent only allows e.g. "Prescription", only that bundle is built.
 *
 * Every method returns ['success' => true, 'bundle' => array] or ['success' => false, 'error' => string].
 * Visits without data for the HI type yield a placeholder bundle ("Data Not Yet Recorded").
 */
final class OpdBundleBuilder
{
    public function __construct(
        private readonly OpdCommonResources $common,
        private readonly OpConsultationBuilder $opConsultation,
        private readonly PrescriptionBuilder $prescription,
        private readonly DiagnosticReportBuilder $diagnosticReport,
    ) {
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function opConsultation(int $opdId): array
    {
        return $this->forVisit($opdId, $this->opConsultation->build(...));
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function prescription(int $opdId): array
    {
        return $this->forVisit($opdId, $this->prescription->build(...));
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function diagnosticReport(int $opdId): array
    {
        return $this->forVisit($opdId, $this->diagnosticReport->build(...));
    }

    /**
     * @param callable(OpdBundleContext): array $build
     * @return array{success: bool, error?: string, bundle?: array<string, mixed>}
     */
    private function forVisit(int $opdId, callable $build): array
    {
        $context = $this->common->load($opdId);
        if (!$context) {
            return ['success' => false, 'error' => 'OPD visit not found'];
        }

        return $build($context);
    }
}
