<?php

namespace App\Services\Abdm\Fhir\Dcd;

/** The Bundle envelope around the collected entries (NRCeS DocumentBundle profile). */
final class DocumentBundle
{
    private const PROFILE = 'https://nrces.in/ndhm/fhir/r4/StructureDefinition/DocumentBundle';

    /**
     * Envelope of the DischargeSummary and DiagnosticReport bundles; the identifier names the visit.
     *
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    public static function forVisit(array $entries, string $identifierValue): array
    {
        $timestamp = gmdate('Y-m-d\TH:i:sP');

        return [
            'resourceType' => 'Bundle',
            'id' => abdm_generate_uuid(),
            'meta' => ['versionId' => '1', 'profile' => [self::PROFILE], 'lastUpdated' => $timestamp],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => $identifierValue],
            'type' => 'document',
            'timestamp' => $timestamp,
            'entry' => $entries,
        ];
    }

    /**
     * Envelope of the Prescription bundle: a random identifier value and the profile listed after lastUpdated.
     *
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    public static function withRandomIdentifier(array $entries): array
    {
        $timestamp = gmdate('Y-m-d\TH:i:sP');

        return [
            'resourceType' => 'Bundle',
            'id' => abdm_generate_uuid(),
            'meta' => ['versionId' => '1', 'lastUpdated' => $timestamp, 'profile' => [self::PROFILE]],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => abdm_generate_uuid()],
            'type' => 'document',
            'timestamp' => $timestamp,
            'entry' => $entries,
        ];
    }
}
