<?php

namespace App\Services\Abdm\Fhir\Ipd;

/**
 * A minimal bundle (common resources + a Composition saying "Data Not Yet Recorded") for when clinical data
 * is not available yet. IpdFhirBuilder turns such a bundle into null so the data push skips it.
 *
 * Not used by the three HI-type builders at the moment (nothing in the app calls it).
 */
final class PlaceholderBundle
{
    /** @return array{success:bool, bundle:array} */
    public function build(IpdContext $ctx, string $hiType, string $title, string $snomedCode, string $snomedDisplay): array
    {
        $bundleId = abdm_generate_uuid();
        $compositionId = abdm_generate_uuid();

        $ctx->entries->prepend([
            'resourceType' => 'Composition',
            'id' => $compositionId,
            'status' => 'final',
            'type' => ['coding' => [['system' => FhirCoding::SNOMED, 'code' => $snomedCode, 'display' => $snomedDisplay]], 'text' => $title],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'date' => $ctx->now,
            'author' => [['reference' => $ctx->authorRef()]],
            'title' => $title,
            'custodian' => ['reference' => $ctx->organizationRef()],
            'text' => [
                'status' => 'generated',
                'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p><b>Data Not Yet Recorded</b></p><p>Clinical data for this section has not been recorded at the time of this admission. This care context was created to enable future data sharing if the information becomes available.</p></div>',
            ],
            'section' => [[
                'title' => 'Status Note',
                'code' => ['text' => 'Clinical Status'],
                'text' => [
                    'status' => 'additional',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>Data not yet recorded for this admission.</p></div>',
                ],
            ]],
        ]);

        $bundle = [
            'resourceType' => 'Bundle',
            'id' => $bundleId,
            'meta' => ['lastUpdated' => $ctx->now],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => $bundleId],
            'type' => 'document',
            'timestamp' => $ctx->now,
            'entry' => $ctx->entries->all(),
        ];

        error_log("[ABDM][IPD][$hiType] No data available - returning empty bundle (will be skipped by data push)");

        return ['success' => true, 'bundle' => $bundle];
    }
}
