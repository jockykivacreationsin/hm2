<?php

namespace App\Services\Abdm\Fhir\Dcd;

/**
 * Shared state of one bundle under construction: the resource ids every builder needs, the visit timestamp and the
 * entries collected so far.
 */
final class BundleContext
{
    /** @var list<array{fullUrl: string, resource: array<string, mixed>}> */
    private array $entries = [];

    private function __construct(
        public readonly string $now,
        public readonly string $patientId,
        public readonly string $orgId,
        public readonly string $encounterId,
        public readonly ?string $practitionerId,
    ) {
    }

    /** Draws the ids in the order patient, organization, encounter, practitioner (the practitioner only when named). */
    public static function open(string $visitDateTime, string $doctorName): self
    {
        $now = self::timestamp($visitDateTime);
        $patientId = abdm_generate_uuid();
        $orgId = abdm_generate_uuid();
        $encounterId = abdm_generate_uuid();
        $practitionerId = trim($doctorName) !== '' ? abdm_generate_uuid() : null;

        return new self($now, $patientId, $orgId, $encounterId, $practitionerId);
    }

    /** UTC ISO-8601 form of a database date-time, as used throughout the DCD bundles. */
    public static function timestamp(string $dateTime): string
    {
        return gmdate('Y-m-d\TH:i:sP', strtotime($dateTime));
    }

    public static function ref(string $type, string $id): string
    {
        return $type . '/' . $id;
    }

    /** @param array<string, mixed> $resource */
    public function add(string $type, string $id, array $resource): void
    {
        $this->entries[] = ['fullUrl' => self::ref($type, $id), 'resource' => $resource];
    }

    /** @param array<string, mixed> $resource */
    public function prepend(string $type, string $id, array $resource): void
    {
        array_unshift($this->entries, ['fullUrl' => self::ref($type, $id), 'resource' => $resource]);
    }

    /** @return list<array{fullUrl: string, resource: array<string, mixed>}> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return array{reference: string} */
    public function patientReference(): array
    {
        return ['reference' => self::ref('Patient', $this->patientId)];
    }

    /** @return array{reference: string} */
    public function encounterReference(): array
    {
        return ['reference' => self::ref('Encounter', $this->encounterId)];
    }

    /** @return array{reference: string} */
    public function organizationReference(): array
    {
        return ['reference' => self::ref('Organization', $this->orgId)];
    }

    /** Reference string of the practitioner, or the organization when the visit has no named doctor. */
    public function authorReference(): string
    {
        return $this->practitionerId
            ? self::ref('Practitioner', $this->practitionerId)
            : self::ref('Organization', $this->orgId);
    }
}
