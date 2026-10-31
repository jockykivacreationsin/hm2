<?php

namespace App\Services\Abdm\Fhir\Ipd;

/**
 * State shared by every section of one IPD bundle: the admission row, the ids of the common
 * resources, and the entry list being filled.
 */
final class IpdContext
{
    public function __construct(
        public readonly int $ipdId,
        /** @var array<string, mixed> admission + patient + discharge row */
        public readonly array $visit,
        public readonly string $patientId,
        public readonly string $orgId,
        public readonly string $encounterId,
        public readonly ?string $practitionerId,
        /** Admission time; the bundle's "recorded at" for everything without a date of its own. */
        public readonly string $now,
        public readonly EntryCollection $entries,
    ) {
    }

    public function isDischarged(): bool
    {
        return !empty($this->visit['discharge_id']);
    }

    /** @return array{reference:string} */
    public function patientRef(): array
    {
        return ['reference' => 'Patient/' . $this->patientId];
    }

    /** @return array{reference:string} */
    public function encounterRef(): array
    {
        return ['reference' => 'Encounter/' . $this->encounterId];
    }

    public function organizationRef(): string
    {
        return 'Organization/' . $this->orgId;
    }

    /** The responsible practitioner, or the facility when the admission has no consultant. */
    public function authorRef(): string
    {
        return $this->practitionerId ? ('Practitioner/' . $this->practitionerId) : $this->organizationRef();
    }

    public function admittedAt(): string
    {
        return $this->visit['admission_date_time'] ? FhirTime::utc($this->visit['admission_date_time']) : $this->now;
    }

    public function dischargedAt(): string
    {
        return $this->visit['discharge_date_time'] ? FhirTime::utc($this->visit['discharge_date_time']) : $this->now;
    }
}
