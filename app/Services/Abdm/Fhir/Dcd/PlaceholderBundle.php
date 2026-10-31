<?php

namespace App\Services\Abdm\Fhir\Dcd;

/**
 * Minimal placeholder bundle for when clinical data is not yet available: the common resources plus a Composition
 * saying that the data is pending. DcdFhirBuilder recognises its "Data Not Yet Recorded" text and skips such bundles.
 */
final class PlaceholderBundle
{
    /**
     * @param string $hiType DischargeSummary, Prescription or DiagnosticReport
     * @param string $title human-readable title of the Composition
     * @return array{success: bool, bundle: array<string, mixed>}
     */
    public function build(BundleContext $ctx, string $hiType, string $title, string $snomedCode, string $snomedDisplay): array
    {
        $bundleId = abdm_generate_uuid();
        $compositionId = abdm_generate_uuid();

        $ctx->prepend('Composition', $compositionId, $this->composition($ctx, $compositionId, $title, $snomedCode, $snomedDisplay));

        $bundle = [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => ['lastUpdated' => $ctx->now],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => $bundleId],
            'type' => 'document',
            'timestamp' => $ctx->now,
            'entry' => $ctx->entries(),
        ];

        error_log("[ABDM][DCD][$hiType] No data available - returning empty bundle (will be skipped by data push)");

        return ['success' => true, 'bundle' => $bundle];
    }

    /** @return array<string, mixed> */
    private function composition(BundleContext $ctx, string $id, string $title, string $snomedCode, string $snomedDisplay): array
    {
        return [
            'resourceType' => 'Composition',
            'id' => $id,
            'status' => 'final',
            'type' => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $snomedCode, 'display' => $snomedDisplay]], 'text' => $title],
            'subject' => $ctx->patientReference(),
            'encounter' => $ctx->encounterReference(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorReference()]],
            'title' => $title,
            'custodian' => $ctx->organizationReference(),
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p><b>Data Not Yet Recorded</b></p><p>Clinical data for this section has not been recorded at the time of this visit. This care context was created to enable future data sharing if the information becomes available.</p></div>',
            ],
            'section' => [[
                'title' => 'Status Note',
                'code' => ['text' => 'Clinical Status'],
                'text' => [
                    'status' => 'additional',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>Data not yet recorded for this visit.</p></div>',
                ],
            ]],
        ];
    }
}
