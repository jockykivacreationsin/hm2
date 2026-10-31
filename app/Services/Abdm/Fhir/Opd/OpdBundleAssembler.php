<?php

namespace App\Services\Abdm\Fhir\Opd;

/** Composition / Bundle wrapper and the "no data yet" placeholder shared by the three OPD HI types. */
final class OpdBundleAssembler
{
    private const PLACEHOLDER_SUMMARY = '<p><b>Data Not Yet Recorded</b></p><p>Clinical data for this section has not been recorded at the time of this visit. This care context was created to enable future data sharing if the information becomes available.</p>';

    /**
     * Minimal bundle (common resources + Composition with a status note) for a visit that has nothing to share
     * for the HI type. OpdFhirBuilder detects the "Data Not Yet Recorded" text and turns it into null.
     *
     * @return array{success: bool, bundle: array<string, mixed>}
     */
    public function placeholder(OpdBundleContext $context, string $hiType, string $title, string $snomedCode, string $snomedDisplay): array
    {
        $context->startDocument();

        $statusNote = [
            'title' => 'Status Note',
            'code' => ['text' => 'Clinical Status'],
            'text' => [
                'status' => 'additional',
                'div' => Xhtml::div('<p>Data not yet recorded for this visit.</p>'),
            ],
        ];
        $composition = $this->composition(
            $context,
            $title,
            $this->snomed($snomedCode, $snomedDisplay)['coding'],
            Xhtml::div(self::PLACEHOLDER_SUMMARY),
            [$statusNote],
        );

        error_log("[ABDM][OPD][$hiType] No data available - returning empty bundle (will be skipped by data push)");

        return $this->finish($context, $composition);
    }

    /**
     * @param array<int, array<string, mixed>> $typeCoding Composition.type.coding
     * @param array<int, array<string, mixed>> $sections
     * @return array<string, mixed>
     */
    public function composition(OpdBundleContext $context, string $title, array $typeCoding, string $narrativeDiv, array $sections): array
    {
        return [
            'resourceType' => 'Composition',
            'id' => $context->compositionId,
            'status' => 'final',
            'type' => ['coding' => $typeCoding, 'text' => $title],
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'date' => $context->now,
            'author' => [['reference' => $context->authorReference()]],
            'title' => $title,
            'custodian' => $context->organization(),
            'text' => ['status' => 'generated', 'div' => $narrativeDiv],
            'section' => $sections,
        ];
    }

    /**
     * Puts the Composition first in the bundle and wraps everything in the document Bundle.
     *
     * @param array<string, mixed> $composition
     * @return array{success: bool, bundle: array<string, mixed>}
     */
    public function finish(OpdBundleContext $context, array $composition): array
    {
        array_unshift($context->entries, ['fullUrl' => 'Composition/' . $context->compositionId, 'resource' => $composition]);

        return ['success' => true, 'bundle' => [
            'resourceType' => 'Bundle',
            'id' => $context->bundleId,
            'meta' => ['lastUpdated' => $context->now],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => $context->bundleId],
            'type' => 'document',
            'timestamp' => $context->now,
            'entry' => $context->entries,
        ]];
    }

    /** @return array{coding: array<int, array<string, string>>} */
    public function snomed(string $code, string $display): array
    {
        return ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]];
    }

    /**
     * Composition section listing resource references.
     *
     * @param array<string, mixed> $code CodeableConcept
     * @param array<int, array<string, string>> $references
     * @return array<string, mixed>
     */
    public function entrySection(string $title, array $code, array $references): array
    {
        return ['title' => $title, 'code' => $code, 'entry' => $references];
    }
}
