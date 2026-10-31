<?php

namespace App\Services\Abdm\Fhir\Opd;

/**
 * State shared by the sections of one OPD document bundle: the visit row, the pinned timestamp, the ids of the
 * common resources and the growing list of bundle entries.
 *
 * UUIDs are drawn in a fixed order (see OpdCommonResources::load() and startDocument()); the golden-output
 * harness depends on that order, so do not reorder the calls.
 */
final class OpdBundleContext
{
    public string $bundleId = '';

    public string $compositionId = '';

    /**
     * @param array<string, mixed> $visit row from OpdCommonResources
     * @param array<int, array{fullUrl: string, resource: array<string, mixed>}> $entries
     */
    public function __construct(
        public readonly int $opdId,
        public readonly array $visit,
        public readonly string $now,
        public readonly string $patientId,
        public readonly string $orgId,
        public readonly string $encounterId,
        public readonly ?string $practitionerId,
        public array $entries = [],
    ) {
    }

    /** Draws the Bundle and Composition ids (always in this order, after the data-availability checks). */
    public function startDocument(): void
    {
        $this->bundleId = abdm_generate_uuid();
        $this->compositionId = abdm_generate_uuid();
    }

    /**
     * Appends a resource to the bundle.
     *
     * @param array<string, mixed> $resource
     * @return array{reference: string} reference for use in a Composition section
     */
    public function add(string $type, string $id, array $resource): array
    {
        $this->entries[] = ['fullUrl' => $type . '/' . $id, 'resource' => $resource];

        return ['reference' => $type . '/' . $id];
    }

    /** @return array{reference: string} */
    public function subject(): array
    {
        return ['reference' => 'Patient/' . $this->patientId];
    }

    /** @return array{reference: string} */
    public function encounter(): array
    {
        return ['reference' => 'Encounter/' . $this->encounterId];
    }

    /** @return array{reference: string} */
    public function organization(): array
    {
        return ['reference' => 'Organization/' . $this->orgId];
    }

    /** The prescribing/consulting doctor when known, otherwise the facility. */
    public function authorReference(): string
    {
        return $this->practitionerId ? ('Practitioner/' . $this->practitionerId) : ('Organization/' . $this->orgId);
    }

    /** Database datetime -> FHIR dateTime in UTC offset form, as used throughout the OPD bundles. */
    public static function dateTime(?string $databaseDateTime): string
    {
        return gmdate('Y-m-d\TH:i:sP', strtotime((string) $databaseDateTime));
    }
}
