<?php

namespace App\Services\Abdm\Fhir\Dcd;

/**
 * Builds the ABDM FHIR document bundles of a day-care (DCD) visit, one public method per HI type.
 * Every method returns ['success' => bool, 'bundle' => array] or ['success' => false, 'error' => string].
 */
final class DcdBundleBuilder
{
    private readonly CommonResources $common;

    public function __construct(private readonly FacilityContext $facility)
    {
        $this->common = new CommonResources($facility);
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function dischargeSummary(int $dcdId): array
    {
        return (new DischargeSummaryBundle($this->common, new DischargeSummaryPdfRenderer($this->facility)))->build($dcdId);
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function prescription(int $dcdId): array
    {
        return (new PrescriptionBundle($this->common, new PrescriptionPdfRenderer()))->build($dcdId);
    }

    /** @return array{success: bool, error?: string, bundle?: array<string, mixed>} */
    public function diagnosticReport(int $dcdId): array
    {
        return (new DiagnosticReportBundle($this->common, new LabInvestigationsPdfRenderer()))->build($dcdId);
    }
}
