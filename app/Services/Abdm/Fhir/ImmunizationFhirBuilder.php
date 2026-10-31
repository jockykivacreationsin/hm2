<?php

namespace App\Services\Abdm\Fhir;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

/**
 * Builds a FHIR R4 ImmunizationRecord bundle for ABDM HI type "ImmunizationRecord".
 *
 * Source tables: immunization_records (+ vaccines, staff_members for performer)
 * One bundle per visit (OPD/IPD/DCD). All immunization records for the visit that
 * fall within the consent date range are included.
 */
class ImmunizationFhirBuilder
{
    use FhirBundleTrait;

    protected function kind(): string
    {
        return 'IMMUNIZATION';
    }

    /**
     * @param string      $module   'OPD' | 'IPD' | 'DCD'
     * @param int         $id       visit primary key
     * @param string|null $dateFrom consent range start (ISO-8601 UTC)
     * @param string|null $dateTo   consent range end   (ISO-8601 UTC)
     * @return array|null FHIR Bundle or null when nothing to share
     */
    public function buildBundle(string $module, int $id, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $module = strtoupper($module);
        if (!in_array($module, ['OPD', 'IPD', 'DCD'], true)) {
            return null;
        }

        $visitTable = strtolower($module) . 's';
        $visit      = DB::table($visitTable)->where('id', $id)->first();
        if (!$visit) {
            return null;
        }

        $patient = DB::table('patients')->where('id', $visit->patient_id ?? null)->first();
        if (!$patient) {
            return null;
        }

        $bounds = $this->bounds($dateFrom, $dateTo);

        // Load immunization records for this visit.
        $moduleCol = strtolower($module) . '_id';
        $records   = DB::table('immunization_records')
            ->leftJoin('vaccines', 'vaccines.id', '=', 'immunization_records.vaccine_id')
            ->leftJoin('staff_members', 'staff_members.id', '=', 'immunization_records.performer_staff_id')
            ->where('immunization_records.' . $moduleCol, $id)
            ->select(
                'immunization_records.*',
                'vaccines.vaccine_name', 'vaccines.snomed_code as vaccine_snomed_code',
                'vaccines.snomed_display as vaccine_snomed_display',
                'staff_members.staff_name as performer_name'
            )
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $patientResource      = $this->buildPatientResource($patient);
        $organizationResource = $this->buildOrganizationResource();
        $encounterId          = (string) Str::uuid();

        $doctorId        = $visit->consultant_doctor_id ?? ($records[0]->performer_staff_id ?? null);
        $doctorRow       = $doctorId ? DB::table('staff_members')->where('id', $doctorId)->first() : null;
        $practitionerRes = $this->buildPractitionerResource($doctorRow);

        $context = [
            'patient'      => $patientResource['id'],
            'organization' => $organizationResource['id'],
            'encounter'    => $encounterId,
            'practitioner' => $practitionerRes ? $practitionerRes['id'] : null,
        ];

        $entries = [$patientResource, $organizationResource];
        if ($practitionerRes) {
            $entries[] = $practitionerRes;
        }
        $entries[] = $this->buildSimpleEncounter($visit, $module, $context);

        $immunizationResources = [];
        foreach ($records as $rec) {
            if (!$this->inRange($rec->occurrence_date_time, $bounds)) {
                continue;
            }
            $immunizationResources[] = $this->buildImmunizationResource($rec, $context);
        }

        if (!$immunizationResources) {
            return null;
        }

        $sections = [[
            'title' => 'Immunization',
            'code'  => $this->concept('http://snomed.info/sct', '41000179103', 'Immunization record'),
            'entry' => array_map(fn ($r) => $this->reference($r['id']), $immunizationResources),
        ]];

        $date = match ($module) {
            'OPD' => $visit->opd_date_time, 'IPD' => $visit->admission_date_time, default => $visit->dcd_date_time,
        };

        $composition = $this->buildCompositionResource($context, $sections, ['ImmunizationRecord'], $date, $module);
        $composition['identifier'] = ['system' => 'https://ndhm.in/phr', 'value' => "{$module}-ImmunizationRecord-{$id}"];
        array_push($entries, ...$immunizationResources);
        array_unshift($entries, $composition);

        return $this->buildDocumentBundle($entries, "{$module}-ImmunizationRecord-{$id}");
    }

    private function buildSimpleEncounter(object $visit, string $module, array $context): array
    {
        $start    = match ($module) { 'OPD' => $visit->opd_date_time, 'IPD' => $visit->admission_date_time, default => $visit->dcd_date_time };
        $resource = [
            'resourceType'   => 'Encounter',
            'id'             => $context['encounter'],
            'status'         => 'finished',
            'class'          => [
                'system'  => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code'    => $module === 'OPD' ? 'AMB' : 'IMP',
                'display' => $module === 'OPD' ? 'ambulatory' : 'inpatient encounter',
            ],
            'subject'        => $this->reference($context['patient']),
            'serviceProvider'=> $this->reference($context['organization']),
        ];
        if (!empty($context['practitioner'])) {
            $resource['participant'] = [['individual' => $this->reference($context['practitioner'])]];
        }
        if ($start) {
            $resource['period']['start'] = $this->iso($start);
            $resource['period']['end']   = $this->iso($start);
        }
        return $resource;
    }

    private function buildImmunizationResource(object $rec, array $context): array
    {
        $resource = [
            'resourceType'      => 'Immunization',
            'id'                => (string) Str::uuid(),
            'meta'              => $this->profile('Immunization'),
            'status'            => in_array($rec->status ?? '', ['completed', 'not-done', 'entered-in-error'], true) ? $rec->status : 'completed',
            'vaccineCode'       => $rec->vaccine_snomed_code
                ? $this->concept('http://snomed.info/sct', $rec->vaccine_snomed_code, $rec->vaccine_snomed_display ?? $rec->vaccine_name ?? 'Vaccine')
                : ['text' => $rec->vaccine_name ?? 'Vaccine'],
            'patient'           => $this->reference($context['patient']),
            'encounter'         => $this->reference($context['encounter']),
            'occurrenceDateTime'=> $this->iso($rec->occurrence_date_time) ?? CarbonImmutable::now()->toIso8601String(),
        ];

        if ($rec->dose_number) {
            $resource['protocolApplied'] = [['doseNumberPositiveInt' => (int) $rec->dose_number]];
        }
        if ($rec->lot_number) {
            $resource['lotNumber'] = $rec->lot_number;
        }
        if ($rec->expiry_date) {
            $resource['expirationDate'] = $this->date($rec->expiry_date)?->toDateString();
        }
        if ($rec->manufacturer) {
            $resource['manufacturer'] = ['display' => $rec->manufacturer];
        }
        if ($rec->site) {
            $resource['site'] = ['text' => $rec->site];
        }
        if ($rec->performer_name) {
            $resource['performer'] = [['actor' => ['display' => $rec->performer_name]]];
        }
        if ($rec->remarks) {
            $resource['note'] = [['text' => $this->clean($rec->remarks)]];
        }

        return $resource;
    }
}
