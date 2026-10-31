<?php

namespace App\Services\Abdm\Fhir;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

/**
 * Builds a FHIR R4 WellnessRecord bundle for ABDM HI type "WellnessRecord".
 *
 * Source tables: wellness_records + wellness_observations + wellness_observation_types.
 * One bundle per visit (OPD/IPD/DCD). All observations within the consent date range
 * are expressed as FHIR Observation resources.
 */
class WellnessFhirBuilder
{
    use FhirBundleTrait;

    protected function kind(): string
    {
        return 'WELLNESS';
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

        $bounds    = $this->bounds($dateFrom, $dateTo);
        $moduleCol = strtolower($module) . '_id';

        // Load all wellness records for this visit along with their observations.
        $records = DB::table('wellness_records')
            ->where($moduleCol, $id)
            ->orderBy('recorded_at')
            ->get();

        if ($records->isEmpty()) {
            return null;
        }

        $recordIds = $records->pluck('id')->all();

        // Batch load observations with their type metadata.
        $observations = DB::table('wellness_observations')
            ->join('wellness_observation_types', 'wellness_observation_types.id', '=', 'wellness_observations.type_id')
            ->whereIn('wellness_observations.wellness_record_id', $recordIds)
            ->select(
                'wellness_observations.*',
                'wellness_observation_types.display_name',
                'wellness_observation_types.code_system',
                'wellness_observation_types.code',
                'wellness_observation_types.code_display',
                'wellness_observation_types.panel_code',
                'wellness_observation_types.panel_display',
                'wellness_observation_types.value_type',
                'wellness_observation_types.ucum_code',
                'wellness_observation_types.unit_display',
                'wellness_observation_types.category'
            )
            ->get()
            ->groupBy('wellness_record_id');

        $patientResource      = $this->buildPatientResource($patient);
        $organizationResource = $this->buildOrganizationResource();
        $encounterId          = (string) Str::uuid();

        $doctorId        = $visit->consultant_doctor_id ?? null;
        $doctorRow       = $doctorId ? DB::table('staff_members')->where('id', $doctorId)->first() : null;
        $practitionerRes = $this->buildPractitionerResource($doctorRow);

        $context = [
            'patient'      => $patientResource['id'],
            'organization' => $organizationResource['id'],
            'encounter'    => $encounterId,
            'practitioner' => $practitionerRes ? $practitionerRes['id'] : null,
        ];

        $date    = match ($module) { 'OPD' => $visit->opd_date_time, 'IPD' => $visit->admission_date_time, default => $visit->dcd_date_time };
        $entries = [$patientResource, $organizationResource];
        if ($practitionerRes) {
            $entries[] = $practitionerRes;
        }
        $entries[] = $this->buildSimpleEncounter($visit, $module, $context, $date);

        $observationResources = [];
        foreach ($records as $rec) {
            if (!$this->inRange($rec->recorded_at, $bounds)) {
                continue;
            }
            foreach ($observations->get($rec->id, collect()) as $obs) {
                $res = $this->buildObservationResource($obs, $rec->recorded_at, $context);
                if ($res) {
                    $observationResources[] = $res;
                }
            }
        }

        if (!$observationResources) {
            return null;
        }

        $sections = [[
            'title' => 'Wellness Record',
            'code'  => $this->concept('http://snomed.info/sct', '736377005', 'Wellness record'),
            'entry' => array_map(fn ($r) => $this->reference($r['id']), $observationResources),
        ]];

        $composition = $this->buildCompositionResource($context, $sections, ['WellnessRecord'], $date, $module);
        $composition['identifier'] = ['system' => 'https://ndhm.in/phr', 'value' => "{$module}-WellnessRecord-{$id}"];
        array_push($entries, ...$observationResources);
        array_unshift($entries, $composition);

        return $this->buildDocumentBundle($entries, "{$module}-WellnessRecord-{$id}");
    }

    private function buildSimpleEncounter(object $visit, string $module, array $context, $date): array
    {
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
        if ($date) {
            $resource['period'] = ['start' => $this->iso($date), 'end' => $this->iso($date)];
        }
        return $resource;
    }

    private function buildObservationResource(object $obs, $recordedAt, array $context): ?array
    {
        $hasNumeric = is_numeric($obs->value_numeric ?? null);
        $hasText    = !empty(trim((string) ($obs->value_text ?? '')));

        if (!$hasNumeric && !$hasText) {
            return null;
        }

        $codeSystem = $obs->code_system ?? 'http://loinc.org';
        $code       = $obs->code ?? '';
        $display    = $obs->code_display ?? $obs->display_name ?? '';

        $resource = [
            'resourceType' => 'Observation',
            'id'           => (string) Str::uuid(),
            'meta'         => $this->profile('Observation'),
            'status'       => 'final',
            'category'     => [$this->concept(
                'http://terminology.hl7.org/CodeSystem/observation-category',
                'survey',
                'Survey'
            )],
            'code'         => $code
                ? $this->concept($codeSystem, $code, $display)
                : ['text' => $display],
            'subject'      => $this->reference($context['patient']),
            'encounter'    => $this->reference($context['encounter']),
        ];

        if ($recordedAt) {
            $resource['effectiveDateTime'] = $this->iso($recordedAt);
        }

        if ($hasNumeric && !empty($obs->ucum_code)) {
            $resource['valueQuantity'] = [
                'value'  => (float) $obs->value_numeric,
                'unit'   => $obs->unit_display ?? $obs->ucum_code,
                'system' => 'http://unitsofmeasure.org',
                'code'   => $obs->ucum_code,
            ];
        } elseif ($hasNumeric) {
            $resource['valueQuantity'] = ['value' => (float) $obs->value_numeric];
        } else {
            $resource['valueString'] = trim((string) $obs->value_text);
        }

        return $resource;
    }
}
