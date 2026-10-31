<?php

namespace App\Services\Abdm\Fhir\Dcd;

/** Patient / Organization / Practitioner / Encounter resources shared by the DCD bundles. */
final class CommonResources
{
    private const ACT_CODE_SYSTEM = 'http://terminology.hl7.org/CodeSystem/v3-ActCode';

    public function __construct(private readonly FacilityContext $facility)
    {
    }

    /**
     * @param array<string, mixed> $visit needs patient_name, gender, date_of_birth (+ abha_number / abha_address)
     * @param bool $withAbhaIdentifiers the DiagnosticReport patient carries no identifier element at all
     */
    public function addPatient(BundleContext $ctx, array $visit, bool $withAbhaIdentifiers): void
    {
        $resource = ['resourceType' => 'Patient', 'id' => $ctx->patientId];
        if ($withAbhaIdentifiers) {
            $resource['identifier'] = $this->abhaIdentifiers($visit);
        }
        $resource['name'] = [['text' => $visit['patient_name']]];
        $resource['gender'] = $this->gender($visit['gender']);
        $resource['birthDate'] = $visit['date_of_birth'] ?: null;

        $ctx->add('Patient', $ctx->patientId, $resource);
    }

    public function addOrganization(BundleContext $ctx): void
    {
        $ctx->add('Organization', $ctx->orgId, [
            'resourceType' => 'Organization',
            'id' => $ctx->orgId,
            'identifier' => [['system' => 'https://facility.ndhm.gov.in', 'value' => $this->facility->hfrId]],
            'name' => $this->facility->name,
        ]);
    }

    /**
     * @param array<string, mixed> $visit must contain doctor_name (or staff_name for DiagnosticReport),
     *   and optionally doctor_hpr_id, doctor_reg_number, doctor_reg_council.
     */
    public function addPractitioner(BundleContext $ctx, array $visit): void
    {
        if (!$ctx->practitionerId) {
            return;
        }

        $doctorName = $visit['doctor_name'] ?? $visit['staff_name'] ?? '';
        $resource = [
            'resourceType' => 'Practitioner',
            'id' => $ctx->practitionerId,
            'name' => [['text' => $doctorName]],
        ];

        if (!empty($visit['doctor_hpr_id'])) {
            $resource['identifier'] = [['system' => 'https://hpr.abdm.gov.in', 'value' => $visit['doctor_hpr_id']]];
        }

        if (!empty($visit['doctor_reg_number'])) {
            $qualification = ['identifier' => [['value' => $visit['doctor_reg_number']]]];
            if (!empty($visit['doctor_reg_council'])) {
                $qualification['issuer'] = ['display' => $visit['doctor_reg_council']];
            }
            $resource['qualification'] = [$qualification];
        }

        $ctx->add('Practitioner', $ctx->practitionerId, $resource);
    }

    /**
     * Finished ambulatory encounter used by the Prescription and DiagnosticReport bundles.
     * Legacy quirk: no participant is listed here even when the visit has a doctor (the DischargeSummary lists one).
     */
    public function addAmbulatoryEncounter(BundleContext $ctx): void
    {
        $encounter = [
            'resourceType' => 'Encounter',
            'id' => $ctx->encounterId,
            'status' => 'finished',
            'class' => ['system' => self::ACT_CODE_SYSTEM, 'code' => 'AMB', 'display' => 'ambulatory'],
            'subject' => $ctx->patientReference(),
            'serviceProvider' => $ctx->organizationReference(),
            'period' => ['start' => $ctx->now],
        ];
        if ($ctx->practitionerId) {
            $encounter['participant'] = [['individual' => ['reference' => BundleContext::ref('Practitioner', $ctx->practitionerId)]]];
        }
        $ctx->add('Encounter', $ctx->encounterId, $encounter);
    }

    /** Day-care stay as an inpatient-class encounter for the DischargeSummary: open until the discharge is recorded. */
    public function addInpatientEncounter(BundleContext $ctx, bool $isDischarged, mixed $dischargeDateTime): void
    {
        $encounter = [
            'resourceType' => 'Encounter',
            'id' => $ctx->encounterId,
            'status' => $isDischarged ? 'finished' : 'in-progress',
            'class' => ['system' => self::ACT_CODE_SYSTEM, 'code' => 'IMP', 'display' => 'inpatient encounter'],
            'subject' => $ctx->patientReference(),
            'serviceProvider' => $ctx->organizationReference(),
            'period' => ['start' => $ctx->now],
        ];
        if ($isDischarged && !empty($dischargeDateTime)) {
            $encounter['period']['end'] = BundleContext::timestamp((string) $dischargeDateTime);
        }
        if ($ctx->practitionerId) {
            $encounter['participant'] = [['individual' => ['reference' => BundleContext::ref('Practitioner', $ctx->practitionerId)]]];
        }

        $ctx->add('Encounter', $ctx->encounterId, $encounter);
    }

    /** @return list<array<string, mixed>> */
    private function abhaIdentifiers(array $visit): array
    {
        $identifiers = [];
        if (!empty($visit['abha_number'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Number'], 'system' => 'https://healthid.ndhm.gov.in', 'value' => $visit['abha_number']];
        }
        if (!empty($visit['abha_address'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Address'], 'system' => 'https://healthid.ndhm.gov.in', 'value' => $visit['abha_address']];
        }

        return $identifiers;
    }

    private function gender(mixed $gender): string
    {
        return ['male' => 'male', 'female' => 'female', 'other' => 'other'][strtolower((string) $gender)] ?? 'unknown';
    }
}
