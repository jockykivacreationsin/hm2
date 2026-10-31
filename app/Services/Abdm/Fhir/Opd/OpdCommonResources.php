<?php

namespace App\Services\Abdm\Fhir\Opd;

use App\Services\Abdm\Fhir\FhirQuery;

/**
 * Resources every OPD HI type starts with: Patient, Organization, Practitioner (when the visit has a doctor)
 * and Encounter.
 */
final class OpdCommonResources
{
    private const GENDERS = ['male' => 'male', 'female' => 'female', 'other' => 'other'];

    /** @return OpdBundleContext|null null when the visit does not exist */
    public function load(int $opdId): ?OpdBundleContext
    {
        $visit = FhirQuery::first("SELECT opds.*, patients.id as patient_id, patients.patient_name,
            patients.gender, patients.date_of_birth, patients.abha_number, patients.abha_address,
            staff_members.staff_name as doctor_name,
            staff_members.hpr_id as doctor_hpr_id,
            staff_members.medical_registration_number as doctor_reg_number,
            staff_members.registration_council as doctor_reg_council
            FROM opds
            INNER JOIN patients ON patients.id = opds.patient_id
            LEFT JOIN staff_members ON staff_members.id = opds.consultant_doctor_id
            WHERE opds.id = ?", 'i', [$opdId]);

        if (!$visit) {
            return null;
        }

        $patientId = abdm_generate_uuid();
        $orgId = abdm_generate_uuid();
        $encounterId = abdm_generate_uuid();
        $practitionerId = $visit['doctor_name'] ? abdm_generate_uuid() : null;

        $context = new OpdBundleContext(
            $opdId,
            $visit,
            OpdBundleContext::dateTime($visit['opd_date_time']),
            $patientId,
            $orgId,
            $encounterId,
            $practitionerId,
        );

        $context->add('Patient', $patientId, $this->patient($visit, $patientId));
        $context->add('Organization', $orgId, $this->organization($orgId));
        if ($practitionerId) {
            $context->add('Practitioner', $practitionerId, $this->practitioner($visit, $practitionerId));
        }
        $context->add('Encounter', $encounterId, $this->encounter($context));

        return $context;
    }

    /** @return array<string, mixed> */
    private function patient(array $visit, string $patientId): array
    {
        $identifiers = [];
        if (!empty($visit['abha_number'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Number'], 'system' => 'https://healthid.ndhm.gov.in', 'value' => $visit['abha_number']];
        }
        if (!empty($visit['abha_address'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Address'], 'system' => 'https://healthid.ndhm.gov.in', 'value' => $visit['abha_address']];
        }

        return [
            'resourceType' => 'Patient',
            'id' => $patientId,
            'identifier' => $identifiers,
            'name' => [['text' => $visit['patient_name']]],
            'gender' => self::GENDERS[strtolower((string) $visit['gender'])] ?? 'unknown',
            'birthDate' => $visit['date_of_birth'] ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function organization(string $orgId): array
    {
        return [
            'resourceType' => 'Organization',
            'id' => $orgId,
            'identifier' => [['system' => 'https://facility.ndhm.gov.in', 'value' => LegacyGlobals::string('abdm_facility_hfr_id')]],
            'name' => LegacyGlobals::string('abdm_facility_name'),
        ];
    }

    /** @return array<string, mixed> */
    private function practitioner(array $visit, string $practitionerId): array
    {
        $resource = [
            'resourceType' => 'Practitioner',
            'id' => $practitionerId,
            'name' => [['text' => $visit['doctor_name']]],
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

        return $resource;
    }

    /** @return array<string, mixed> */
    private function encounter(OpdBundleContext $context): array
    {
        $encounter = [
            'resourceType' => 'Encounter',
            'id' => $context->encounterId,
            'status' => 'finished',
            'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'AMB', 'display' => 'ambulatory'],
            'subject' => $context->subject(),
            'serviceProvider' => $context->organization(),
            'period' => ['start' => $context->now, 'end' => $context->now],
        ];
        if ($context->practitionerId) {
            $encounter['participant'] = [['individual' => ['reference' => 'Practitioner/' . $context->practitionerId]]];
        }

        return $encounter;
    }
}
