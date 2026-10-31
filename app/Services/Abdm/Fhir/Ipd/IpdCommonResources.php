<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;

/** Loads the admission and adds the Patient/Organization/Practitioner/Encounter every IPD bundle needs. */
final class IpdCommonResources
{
    private const ABHA_SYSTEM = 'https://healthid.ndhm.gov.in';

    public function __construct(private readonly ?FacilityContext $facility = null)
    {
    }

    /** @return IpdContext|null null when the admission (or its patient) does not exist */
    public function load(int $ipdId): ?IpdContext
    {
        $visit = FhirQuery::first("SELECT ipds.*, patients.id as patient_id, patients.patient_name,
            patients.gender, patients.date_of_birth, patients.abha_number, patients.abha_address,
            staff_members.staff_name as doctor_name,
            staff_members.hpr_id as doctor_hpr_id,
            staff_members.medical_registration_number as doctor_reg_number,
            staff_members.registration_council as doctor_reg_council,
            ipd_discharge.id as discharge_id, ipd_discharge.discharge_date_time,
            ipd_discharge.diagnosis as discharge_diagnosis,
            ipd_discharge.condition_on_discharge,
            ipd_discharge.follow_up_detail, ipd_discharge.note as discharge_note,
            ipd_discharge.urgent_care_condition
            FROM ipds
            INNER JOIN patients ON patients.id = ipds.patient_id
            LEFT JOIN staff_members ON staff_members.id = ipds.consultant_doctor_id
            LEFT JOIN ipd_discharge ON ipd_discharge.ipd_id = ipds.id
            WHERE ipds.id = ?", 'i', [$ipdId]);

        if (!$visit) {
            return null;
        }

        // The ids are drawn in this order (patient, org, encounter, practitioner); keep it.
        $ctx = new IpdContext(
            $ipdId,
            $visit,
            abdm_generate_uuid(),
            abdm_generate_uuid(),
            abdm_generate_uuid(),
            $visit['doctor_name'] ? abdm_generate_uuid() : null,
            FhirTime::utc($visit['admission_date_time']),
            new EntryCollection(),
        );

        $this->addPatient($ctx);
        $this->addOrganization($ctx);
        $this->addPractitioner($ctx);
        $this->addEncounter($ctx);

        return $ctx;
    }

    private function addPatient(IpdContext $ctx): void
    {
        $visit = $ctx->visit;
        $identifiers = [];
        if (!empty($visit['abha_number'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Number'], 'system' => self::ABHA_SYSTEM, 'value' => $visit['abha_number']];
        }
        if (!empty($visit['abha_address'])) {
            $identifiers[] = ['type' => ['text' => 'ABHA Address'], 'system' => self::ABHA_SYSTEM, 'value' => $visit['abha_address']];
        }
        $genders = ['male' => 'male', 'female' => 'female', 'other' => 'other'];

        $ctx->entries->add([
            'resourceType' => 'Patient',
            'id' => $ctx->patientId,
            'identifier' => $identifiers,
            'name' => [['text' => $visit['patient_name']]],
            'gender' => $genders[strtolower((string) $visit['gender'])] ?? 'unknown',
            'birthDate' => $visit['date_of_birth'] ?: null,
        ]);
    }

    private function addOrganization(IpdContext $ctx): void
    {
        $facility = $this->facility ?? FacilityContext::fromGlobals();

        $ctx->entries->add([
            'resourceType' => 'Organization',
            'id' => $ctx->orgId,
            'identifier' => [['system' => 'https://facility.ndhm.gov.in', 'value' => $facility->hfrId]],
            'name' => $facility->facilityName,
        ]);
    }

    private function addPractitioner(IpdContext $ctx): void
    {
        if (!$ctx->practitionerId) {
            return;
        }

        $visit = $ctx->visit;
        $resource = [
            'resourceType' => 'Practitioner',
            'id' => $ctx->practitionerId,
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

        $ctx->entries->add($resource);
    }

    /** Encounter.class is IMP (inpatient), not AMB - the one structural difference from the OPD Encounter. */
    private function addEncounter(IpdContext $ctx): void
    {
        $visit = $ctx->visit;
        $discharged = $ctx->isDischarged();
        $encounter = [
            'resourceType' => 'Encounter',
            'id' => $ctx->encounterId,
            'status' => $discharged ? 'finished' : 'in-progress',
            'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'IMP', 'display' => 'inpatient encounter'],
            'subject' => $ctx->patientRef(),
            'serviceProvider' => ['reference' => $ctx->organizationRef()],
            'period' => ['start' => $ctx->now],
        ];
        if ($discharged && !empty($visit['discharge_date_time'])) {
            $encounter['period']['end'] = FhirTime::utc($visit['discharge_date_time']);
        }
        if ($ctx->practitionerId) {
            $encounter['participant'] = [['individual' => ['reference' => 'Practitioner/' . $ctx->practitionerId]]];
        }

        $ctx->entries->add($encounter);
    }
}
