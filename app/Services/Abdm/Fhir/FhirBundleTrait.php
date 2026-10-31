<?php

namespace App\Services\Abdm\Fhir;

use App\Models\BiochemistryTest;
use App\Models\Charge;
use App\Models\Dcd;
use App\Models\DcdLabTestResult;
use App\Models\Ipd;
use App\Models\Opd;
use App\Models\OpdLabTestResult;
use App\Models\Setting;
use Carbon\CarbonImmutable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Shared FHIR document assembly for the OPD/IPD/DCD builders.
 *
 * Everything lives in this trait (not a base class) because the project uses an
 * authoritative classmap, so new class files are not autoloadable until composer
 * regenerates it. DB access is isolated in loadVisit() so assembly is unit-testable.
 */
trait FhirBundleTrait
{
    abstract protected function kind(): string;

    protected function settings(): array
    {
        return Cache::remember('hospital_settings_fhir', 300, fn () => Setting::find(1)?->toArray() ?? []);
    }

    protected function profile(string $name): array
    {
        return ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/' . $name]];
    }

    protected function reference(string $id): array
    {
        return ['reference' => 'urn:uuid:' . $id];
    }

    protected function date($value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        // date_create() reports failure without the PHP warning Carbon::parse() emits.
        if (date_create((string) $value) === false) {
            throw new InvalidArgumentException('Invalid FHIR record date or consent date bound: ' . $value);
        }
        return CarbonImmutable::parse($value);
    }

    protected function bounds(?string $from, ?string $to): array
    {
        $start = $this->date($from);
        $end = $this->date($to);
        if ($end && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $end = $end->endOfDay();
        }
        // A reversed range is valid input that simply matches nothing.
        return [$start, $end];
    }

    protected function inRange($value, array $bounds): bool
    {
        $date = $this->date($value);
        // Undated clinical content cannot be proven to fall within bounded consent.
        if (!$date) {
            return !$bounds[0] && !$bounds[1];
        }
        return (!$bounds[0] || $date->greaterThanOrEqualTo($bounds[0]))
            && (!$bounds[1] || $date->lessThanOrEqualTo($bounds[1]));
    }

    protected function iso($value): ?string
    {
        return $this->date($value)?->toIso8601String();
    }

    protected function clean($value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function narrative(string $text): array
    {
        return ['status' => 'generated', 'div' => '<div xmlns="http://www.w3.org/1999/xhtml"><p>'
            . htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</p></div>'];
    }

    protected function concept(string $system, string $code, string $display): array
    {
        return ['coding' => [['system' => $system, 'code' => $code, 'display' => $display]], 'text' => $display];
    }

    /** SNOMED displays for section codes, copied from the legacy builders. */
    protected function sectionConcept(string $code, string $title): array
    {
        $display = [
            '422843007' => 'Chief complaint section', '425044008' => 'Physical exam section',
            '371525003' => 'Clinical procedure report', '721912009' => 'Medication summary document',
            '1003606003' => 'Medication history section', '721981007' => 'Diagnostic studies report',
            '397659008' => 'Final diagnosis', '148006' => 'Preliminary diagnosis',
            '1003642006' => 'Past medical history section', '422432008' => 'Family history section',
            '722446000' => 'Allergy record', '736271009' => 'Follow-up care plan',
        ][$code] ?? $title;
        return ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]];
    }

    protected function textSection(array &$sections, string $title, $value, ?string $code = null): void
    {
        $text = $this->clean($value);
        if ($text === '') {
            return;
        }
        $section = ['title' => $title, 'text' => $this->narrative($text)];
        if ($code) {
            $section['code'] = $this->sectionConcept($code, $title);
        }
        $sections[] = $section;
    }

    protected function resourceSection(array &$sections, string $title, array $resources, ?string $code = null): void
    {
        if (!$resources) {
            return;
        }
        $section = ['title' => $title, 'entry' => array_map(fn ($r) => $this->reference($r['id']), $resources)];
        if ($code) {
            $section['code'] = $this->sectionConcept($code, $title);
        }
        $sections[] = $section;
    }

    protected function buildDocumentBundle(array $entries, ?string $identifierValue = null): array
    {
        $id = (string) Str::uuid();
        $now = CarbonImmutable::now()->toIso8601String();
        return [
            'resourceType' => 'Bundle', 'id' => $id,
            'meta' => $this->profile('DocumentBundle') + ['versionId' => '1', 'lastUpdated' => $now],
            'identifier' => ['system' => 'https://ndhm.in', 'value' => $identifierValue ?? $id],
            'type' => 'document', 'timestamp' => $now,
            'entry' => array_map(fn ($r) => ['fullUrl' => 'urn:uuid:' . $r['id'], 'resource' => $r], $entries),
        ];
    }

    protected function buildPatientResource($patient): array
    {
        $resource = [
            'resourceType' => 'Patient', 'id' => (string) Str::uuid(),
            'name' => [['text' => $patient->patient_name]],
            'gender' => in_array(strtolower($patient->gender ?? ''), ['male', 'female', 'other'])
                ? strtolower($patient->gender) : 'unknown',
        ];
        foreach (['abha_number' => 'ABHA Number', 'abha_address' => 'ABHA Address'] as $field => $label) {
            if ($patient->$field) {
                $resource['identifier'][] = ['type' => ['text' => $label], 'system' => 'https://healthid.ndhm.gov.in', 'value' => $patient->$field];
            }
        }
        if ($patient->date_of_birth) {
            $resource['birthDate'] = $this->date($patient->date_of_birth)->toDateString();
        }
        return $resource;
    }

    protected function buildOrganizationResource(): array
    {
        $settings = $this->settings();
        $resource = [
            'resourceType' => 'Organization', 'id' => (string) Str::uuid(),
            'name' => ($settings['facility_name'] ?? '') ?: ($settings['hospital_name'] ?? 'Hospital'),
        ];
        if (!empty($settings['facility_hfr_id'])) {
            $resource['identifier'] = [['system' => 'https://facility.ndhm.gov.in', 'value' => $settings['facility_hfr_id']]];
        }
        return $resource;
    }

    protected function buildPractitionerResource($doctor): ?array
    {
        if (!$doctor || !$this->clean($doctor->staff_name)) {
            return null;
        }
        return ['resourceType' => 'Practitioner', 'id' => (string) Str::uuid(), 'name' => [['text' => $doctor->staff_name]]];
    }

    protected function buildEncounterResource($visit, string $kind, array $context): array
    {
        $start = $kind === 'OPD' ? $visit->opd_date_time : ($kind === 'IPD' ? $visit->admission_date_time : $visit->dcd_date_time);
        $discharge = $kind === 'OPD' ? null : ($visit->discharge ?? null);
        $resource = [
            'resourceType' => 'Encounter', 'id' => $context['encounter'],
            'status' => $kind === 'OPD' || $discharge ? 'finished' : 'in-progress',
            'class' => ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => $kind === 'OPD' ? 'AMB' : 'IMP', 'display' => $kind === 'OPD' ? 'ambulatory' : 'inpatient encounter'],
            'subject' => $this->reference($context['patient']),
            'serviceProvider' => $this->reference($context['organization']),
        ];
        if ($start) {
            $resource['period']['start'] = $this->iso($start);
        }
        $end = $kind === 'OPD' ? $start : $discharge?->discharge_date_time;
        if ($end) {
            $resource['period']['end'] = $this->iso($end);
        }
        if ($context['practitioner']) {
            $resource['participant'] = [['individual' => $this->reference($context['practitioner'])]];
        }
        return $resource;
    }

    protected function clinicalResource(string $type, array $context): array
    {
        return ['resourceType' => $type, 'id' => (string) Str::uuid(),
            'subject' => $this->reference($context['patient']), 'encounter' => $this->reference($context['encounter'])];
    }

    protected function buildMedicationRequestResource($medicine, $date, array $context): ?array
    {
        $name = $this->clean(data_get($medicine, 'medicine_name') ?: data_get($medicine, 'product.product_name'));
        if ($name === '') {
            return null;
        }
        $resource = $this->clinicalResource('MedicationRequest', $context) + [
            'meta' => $this->profile('MedicationRequest'), 'status' => 'active', 'intent' => 'order',
            'medicationCodeableConcept' => ['text' => $name],
            'requester' => $this->reference($context['practitioner'] ?? $context['organization']),
        ];
        if ($date) {
            $resource['authoredOn'] = $this->iso($date);
        }
        $parts = [];
        foreach (['dose_quantity', 'frequency_display', 'timing_display', 'meal_relation', 'remarks'] as $field) {
            $text = $this->clean($medicine->$field ?? '');
            if ($text !== '') {
                $parts[] = $text;
            }
        }
        if ($medicine->duration_days ?? null) {
            $parts[] = $medicine->duration_days . ' days';
        }
        if ($parts) {
            $resource['dosageInstruction'] = [['text' => implode('; ', $parts)]];
        }
        return $resource;
    }

    protected function vitalResources($record, $date, array $context): array
    {
        $resources = [];
        $definitions = [
            'pulse' => ['8867-4', 'Heart rate', '/min'],
            'temperature' => ['8310-5', 'Body temperature', '[degF]'],
            'respiration' => ['9279-1', 'Respiratory rate', '/min'],
            'spo2' => ['2708-6', 'Oxygen saturation', '%'],
            'height' => ['8302-2', 'Body height', 'cm'],
            'weight' => ['29463-7', 'Body weight', 'kg'],
        ];
        $base = ['status' => 'final', 'category' => [$this->concept('http://terminology.hl7.org/CodeSystem/observation-category', 'vital-signs', 'Vital Signs')]];
        if ($date) {
            $base['effectiveDateTime'] = $this->iso($date);
        }
        foreach ($definitions as $field => [$code, $display, $unit]) {
            if (is_numeric($record->$field ?? null)) {
                $resources[] = $this->clinicalResource('Observation', $context) + $base + [
                    'code' => $this->concept('http://loinc.org', $code, $display),
                    'valueQuantity' => ['value' => (float) $record->$field, 'unit' => $unit, 'system' => 'http://unitsofmeasure.org', 'code' => $unit],
                ];
            }
        }
        if (preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', $record->bp ?? '', $matches)) {
            $bp = $this->clinicalResource('Observation', $context) + $base + ['code' => $this->concept('http://loinc.org', '85354-9', 'Blood pressure panel')];
            foreach ([['8480-6', 'Systolic blood pressure'], ['8462-4', 'Diastolic blood pressure']] as $i => [$code, $display]) {
                $bp['component'][] = ['code' => $this->concept('http://loinc.org', $code, $display),
                    'valueQuantity' => ['value' => (float) $matches[$i + 1], 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]']];
            }
            $resources[] = $bp;
        }
        return $resources;
    }

    protected function buildCompositionResource(array $context, array $sections, array $types, $date, string $kind): array
    {
        $type = count($types) === 1 ? $types[0] : 'OPConsultation';
        [$profile, $code, $display] = match ($type) {
            'Prescription'         => ['PrescriptionRecord', '440545006', 'Prescription record'],
            'DiagnosticReport'     => ['DiagnosticReportRecord', '721981007', 'Diagnostic Report'],
            'DischargeSummary'     => ['DischargeSummaryRecord', '373942005', 'Discharge summary'],
            'ImmunizationRecord'   => ['ImmunizationRecord', '41000179103', 'Immunization record'],
            'WellnessRecord'       => ['WellnessRecord', '736377005', 'Wellness record'],
            'HealthDocumentRecord' => ['HealthDocumentRecord', '419891008', 'Health document record'],
            default                => [null, '371530004', 'Clinical consultation report'],
        };
        $resource = [
            'resourceType' => 'Composition', 'id' => (string) Str::uuid(), 'status' => 'final',
            'type' => $this->concept('http://snomed.info/sct', $code, $display),
            'subject' => $this->reference($context['patient']), 'encounter' => $this->reference($context['encounter']),
            'date' => $this->iso($date) ?? CarbonImmutable::now()->toIso8601String(),
            'author' => [$this->reference($context['practitioner'] ?? $context['organization'])],
            'title' => $kind . ' ' . $display, 'custodian' => $this->reference($context['organization']),
            'text' => $this->narrative($kind . ' ' . $display), 'section' => $sections,
        ];
        if ($profile) {
            $resource['meta'] = $this->profile($profile);
        }
        return $resource;
    }

    protected function loadVisit(int $id, array $types): ?Model
    {
        $kind = $this->kind();
        $model = match ($kind) { 'OPD' => Opd::class, 'IPD' => Ipd::class, 'DCD' => Dcd::class };
        $relations = ['patient', 'doctor'];
        $summary = in_array('DischargeSummary', $types, true);
        if ($kind !== 'OPD') {
            $relations[] = 'discharge';
        }
        if ($summary) {
            $relations[] = 'patientFile';
            if ($kind === 'IPD') {
                $relations[] = 'vitalMonitorings.details';
            }
        }
        if ($summary || in_array('Prescription', $types, true) || in_array('OPConsultation', $types, true)) {
            $relations[] = 'prescriptions.medicines.product';
        }
        if ($kind === 'IPD' && ($summary || in_array('Prescription', $types, true))) {
            $relations[] = 'treatments.details.product';
        }
        if ($summary || in_array('DiagnosticReport', $types, true) || in_array('OPConsultation', $types, true)) {
            // DcdServiceDetail has no charge() relation, so charge names are batch-loaded below.
            $relations = array_merge($relations, ['services.details', 'services.chargeDepartment', 'services.procedureNote']);
        }
        $visit = $model::with($relations)->find($id);
        if (!$visit) {
            return null;
        }
        $prefix = strtolower($kind);
        if ($visit->relationLoaded('services')) {
            $chargeIds = $visit->services->flatMap->details->pluck('charge_id')->filter()->unique()->all();
            $visit->setRelation('fhirChargeNames', $chargeIds ? Charge::whereIn('id', $chargeIds)->pluck('charge_name', 'id') : collect());
        }
        if ($summary || in_array('DiagnosticReport', $types, true)) {
            $results = match ($kind) { 'OPD' => OpdLabTestResult::class, 'IPD' => BiochemistryTest::class, 'DCD' => DcdLabTestResult::class };
            $visit->setRelation('fhirLabResults', $results::with('subTest.unit')->where($prefix . '_id', $id)->get());
        }
        if ($kind !== 'OPD' && ($summary || in_array('Prescription', $types, true))) {
            $details = [];
            if ($visit->discharge) {
                foreach (['investigations', 'procedures', 'medication_given', 'treatment_advised'] as $table) {
                    $query = DB::table($prefix . '_discharge_' . $table)->where('discharge_id', $visit->discharge->id);
                    if ($table === 'treatment_advised') {
                        $query->leftJoin('pharma_products', 'pharma_products.id', '=', $prefix . '_discharge_treatment_advised.pharma_product_id')
                            ->select($prefix . '_discharge_treatment_advised.*', 'pharma_products.product_name as medicine_name');
                    }
                    $details[$table] = $query->get();
                }
            }
            $visit->setRelation('fhirDischargeDetails', collect($details));
        }
        return $visit;
    }

    protected function assemble(int $id, array $types, ?string $dateFrom, ?string $dateTo): ?array
    {
        $bounds = $this->bounds($dateFrom, $dateTo);
        if (!$types) {
            return null;
        }
        $visit = $this->loadVisit($id, $types);
        if (!$visit || !$visit->patient) {
            return null;
        }
        $kind = $this->kind();
        $date = match ($kind) { 'OPD' => $visit->opd_date_time, 'IPD' => $visit->admission_date_time, 'DCD' => $visit->dcd_date_time };
        $patient = $this->buildPatientResource($visit->patient);
        $organization = $this->buildOrganizationResource();
        $practitioner = $this->buildPractitionerResource($visit->doctor);
        $context = ['patient' => $patient['id'], 'organization' => $organization['id'],
            'practitioner' => $practitioner['id'] ?? null, 'encounter' => (string) Str::uuid()];
        $entries = [$patient, $organization];
        if ($practitioner) {
            $entries[] = $practitioner;
        }
        $entries[] = $this->buildEncounterResource($visit, $kind, $context);
        $sections = [];
        if (in_array('OPConsultation', $types, true)) {
            if ($this->inRange($date, $bounds)) {
                $this->textSection($sections, 'Chief Complaint', $visit->symptoms, '422843007');
                $this->textSection($sections, 'Notes', $visit->remarks);
                $vitals = $this->vitalResources($visit, $date, $context);
                array_push($entries, ...$vitals);
                $this->resourceSection($sections, 'Vital Signs', $vitals, '425044008');
            }
            foreach ($visit->prescriptions as $rx) {
                if ($this->inRange($rx->prescription_date_time, $bounds)) {
                    foreach (['findings' => 'Findings', 'follow_up_detail' => 'Follow-up', 'procedures' => 'Procedures advised'] as $field => $title) {
                        $this->textSection($sections, $title, $rx->$field);
                    }
                }
            }
            $this->addProcedures($entries, $sections, $visit, $bounds, $context);
        }
        $summary = in_array('DischargeSummary', $types, true);
        if ($summary) {
            $this->addSummary($entries, $sections, $visit, $date, $bounds, $context);
            $this->addProcedures($entries, $sections, $visit, $bounds, $context);
        }
        if ($summary || in_array('Prescription', $types, true)) {
            $this->addMedications($entries, $sections, $visit, $bounds, $context, $summary);
        }
        if ($summary || in_array('DiagnosticReport', $types, true)) {
            $this->addLabReport($entries, $sections, $visit, $bounds, $context);
        }
        if (!$sections) {
            return null;
        }
        // A document's first entry must be its Composition, even for narrative-only content.
        array_unshift($entries, $this->buildCompositionResource($context, $sections, $types, $date, $kind));
        return $this->buildDocumentBundle($entries);
    }

    protected function addProcedures(array &$entries, array &$sections, $visit, array $bounds, array $context): void
    {
        $resources = [];
        foreach ($visit->services as $service) {
            if (strtolower($service->chargeDepartment?->category ?? '') !== 'procedure'
                || !$this->inRange($service->service_date_time, $bounds)) {
                continue;
            }
            $charges = $visit->getRelation('fhirChargeNames');
            $names = $service->details->map(fn ($detail) => $this->clean($charges->get($detail->charge_id)))->filter()->all();
            $text = $this->clean($service->service_title) . ($names ? ' (' . implode(', ', $names) . ')' : '');
            if (trim($text) === '') {
                continue;
            }
            $resource = $this->clinicalResource('Procedure', $context) + [
                'status' => $this->kind() === 'OPD' || strtolower($service->status ?? '') === 'completed' ? 'completed' : 'in-progress',
                'code' => ['text' => $text],
            ];
            if ($service->service_date_time) {
                $resource['performedDateTime'] = $this->iso($service->service_date_time);
            }
            if ($note = $this->clean($service->procedureNote?->note)) {
                $resource['note'] = [['text' => $note]];
            }
            $resources[] = $resource;
        }
        array_push($entries, ...$resources);
        $this->resourceSection($sections, 'Procedures', $resources, '371525003');
    }

    protected function addMedications(array &$entries, array &$sections, $visit, array $bounds, array $context, bool $inSummary): void
    {
        $resources = [];
        foreach ($visit->prescriptions as $rx) {
            if (!$this->inRange($rx->prescription_date_time, $bounds)) {
                continue;
            }
            foreach ($rx->medicines as $medicine) {
                if ($resource = $this->buildMedicationRequestResource($medicine, $rx->prescription_date_time, $context)) {
                    $resources[] = $resource;
                }
            }
        }
        if ($this->kind() === 'IPD') {
            foreach ($visit->treatments as $treatment) {
                if (!$this->inRange($treatment->treatment_date_time, $bounds)) {
                    continue;
                }
                foreach ($treatment->details as $medicine) {
                    if ($resource = $this->buildMedicationRequestResource($medicine, $treatment->treatment_date_time, $context)) {
                        $resources[] = $resource;
                    }
                }
            }
        }
        if ($this->kind() !== 'OPD' && $visit->discharge && $this->inRange($visit->discharge->discharge_date_time, $bounds)) {
            $details = $visit->getRelation('fhirDischargeDetails');
            foreach (['medication_given', 'treatment_advised'] as $source) {
                foreach ($details->get($source, []) as $medicine) {
                    if ($resource = $this->buildMedicationRequestResource($medicine, $visit->discharge->discharge_date_time, $context)) {
                        $resources[] = $resource;
                    }
                }
            }
        }
        array_push($entries, ...$resources);
        // Section title/code follow the legacy builders: Prescription record vs. discharge-summary medication history.
        $inSummary
            ? $this->resourceSection($sections, 'Medications', $resources, '1003606003')
            : $this->resourceSection($sections, 'Prescription', $resources, '721912009');
    }

    protected function addLabReport(array &$entries, array &$sections, $visit, array $bounds, array $context): void
    {
        $observations = [];
        $latest = null;
        $detailKey = strtolower($this->kind()) . '_service_detail_id';
        $results = $visit->getRelation('fhirLabResults')->groupBy($detailKey);
        foreach ($visit->services as $service) {
            if (strtolower($service->chargeDepartment?->category ?? '') !== 'lab') {
                continue;
            }
            foreach ($service->details as $detail) {
                foreach ($results->get($detail->id, []) as $result) {
                    $value = $this->clean($result->test_value);
                    $date = $result->test_date_time ?: $service->service_date_time;
                    if ($value === '' || !$this->inRange($date, $bounds)) {
                        continue;
                    }
                    $observation = $this->clinicalResource('Observation', $context) + [
                        'meta' => $this->profile('Observation'), 'status' => 'final',
                        'category' => [$this->concept('http://terminology.hl7.org/CodeSystem/observation-category', 'laboratory', 'Laboratory')],
                        'code' => ['text' => $this->clean($result->subTest?->sub_test_name) ?: $this->clean($service->service_title)],
                        'performer' => [$this->reference($context['organization'])],
                    ];
                    if ($date) {
                        $observation['effectiveDateTime'] = $this->iso($date);
                        if (!$latest || $this->date($date)->greaterThan($this->date($latest))) {
                            $latest = $date;
                        }
                    }
                    $unit = $result->subTest?->unit;
                    if (is_numeric($value) && $unit?->ucum_code) {
                        $observation['valueQuantity'] = ['value' => (float) $value, 'unit' => $unit->unit_name,
                            'system' => 'http://unitsofmeasure.org', 'code' => $unit->ucum_code];
                    } else {
                        $observation['valueString'] = $value;
                    }
                    if ($range = $this->clean($result->subTest?->reference_range)) {
                        $observation['referenceRange'] = [['text' => $range]];
                    }
                    $observations[] = $observation;
                }
            }
        }
        if (!$observations) {
            return;
        }
        // DiagnosticReportRecord permits only one report: consolidate all services.
        $report = $this->clinicalResource('DiagnosticReport', $context) + [
            'meta' => $this->profile('DiagnosticReportLab'), 'status' => 'final',
            'code' => $this->concept('http://loinc.org', '11502-2', 'Laboratory report'),
            'performer' => [$this->reference($context['organization'])],
            'resultsInterpreter' => [$this->reference($context['practitioner'] ?? $context['organization'])],
            'contained' => $observations,
            'result' => array_map(fn ($observation) => ['reference' => '#' . $observation['id']], $observations),
        ];
        if ($latest) {
            $report['effectiveDateTime'] = $this->iso($latest);
            $report['issued'] = $this->iso($latest);
        }
        $entries[] = $report;
        $this->resourceSection($sections, 'Investigations', [$report], '721981007');
    }

    protected function addSummary(array &$entries, array &$sections, $visit, $date, array $bounds, array $context): void
    {
        // Patient-file fields have no own timestamps: anchor them to the admission.
        if ($visit->patientFile && $this->inRange($date, $bounds)) {
            $fields = [
                'chief_complaints' => ['Chief Complaints', '422843007'],
                'provisional_diagnosis' => ['Admission Diagnosis', '148006'],
                'history_of_present_illness' => ['History of Present Illness', '422843007'],
                'past_medical_history' => ['Medical History', '1003642006'],
                'family_ho' => ['Family History', '422432008'],
                'allergies' => ['Allergies', '722446000'],
                'general_examination' => ['General Examination', '425044008'],
                'systemic_examination' => ['Systemic Examination', '425044008'],
                'cns' => ['CNS Examination', '425044008'], 'rs' => ['Respiratory Examination', '425044008'],
                'cvs' => ['Cardiovascular Examination', '425044008'], 'per_abdomen' => ['Abdominal Examination', '425044008'],
            ];
            foreach ($fields as $field => [$title, $code]) {
                $this->textSection($sections, $title, $visit->patientFile->$field, $code);
            }
            $this->textSection($sections, 'Previous Medical Note', $visit->previous_medical_note);
        }
        if ($this->kind() === 'IPD') {
            $vitals = [];
            foreach ($visit->vitalMonitorings as $day) {
                foreach ($day->details as $detail) {
                    $effective = $day->vital_monitoring_date
                        ? $day->vital_monitoring_date . ' ' . ($detail->vital_monitoring_time ?: '00:00:00') : null;
                    if ($this->inRange($effective, $bounds)) {
                        array_push($vitals, ...$this->vitalResources($detail, $effective, $context));
                    }
                }
            }
            array_push($entries, ...$vitals);
            $this->resourceSection($sections, 'Vital Signs', $vitals, '425044008');
        }
        $discharge = $visit->discharge;
        if (!$discharge || !$this->inRange($discharge->discharge_date_time, $bounds)) {
            return;
        }
        if ($diagnosis = $this->clean($discharge->diagnosis)) {
            $condition = $this->clinicalResource('Condition', $context) + ['code' => ['text' => $diagnosis],
                'verificationStatus' => $this->concept('http://terminology.hl7.org/CodeSystem/condition-ver-status', 'confirmed', 'Confirmed')];
            // A discharge does not itself establish that a diagnosis has resolved.
            if ($discharge->discharge_date_time) {
                $condition['recordedDate'] = $this->iso($discharge->discharge_date_time);
            }
            $entries[] = $condition;
            $this->resourceSection($sections, 'Discharge Diagnosis', [$condition], '397659008');
        }
        foreach (['condition_on_discharge' => 'Condition on Discharge', 'follow_up_detail' => 'Follow-up',
            'note' => 'Discharge Note', 'urgent_care_condition' => 'Urgent Care', 'discharged_to' => 'Discharged To'] as $field => $title) {
            $this->textSection($sections, $title, $discharge->$field, $field === 'follow_up_detail' ? '736271009' : null);
        }
        $details = $visit->getRelation('fhirDischargeDetails');
        foreach ($details->get('investigations', []) as $investigation) {
            $this->textSection($sections, 'Discharge Investigation', $investigation->investigation . ': ' . $investigation->result, '721981007');
        }
        $procedures = [];
        foreach ($details->get('procedures', []) as $procedure) {
            if ($text = $this->clean($procedure->procedure_detail)) {
                $resource = $this->clinicalResource('Procedure', $context) + ['status' => 'completed', 'code' => ['text' => $text]];
                if ($discharge->discharge_date_time) {
                    $resource['performedDateTime'] = $this->iso($discharge->discharge_date_time);
                }
                $procedures[] = $resource;
            }
        }
        array_push($entries, ...$procedures);
        $this->resourceSection($sections, 'Discharge Procedures', $procedures, '371525003');
    }

    /**
     * Does an encounter belong in a consent's date range?
     *
     * ABDM's range is about the dates of the health records, so an encounter qualifies when the PERIOD it
     * covers [$start, $end] overlaps [from, to] - not only when its start date falls inside. An admission that
     * began before the range but is still running (or was discharged inside it) holds records in the range.
     * $end defaults to $start (a single-day encounter).
     *
     * config('abdm.consent_date_range'): 'overlap' (default) or 'off' = legacy behaviour, which never looked
     * at the range (every linked care context is delivered whatever dates the consent carries).
     *
     * @param array{0:?\Carbon\CarbonImmutable,1:?\Carbon\CarbonImmutable} $bounds from bounds()
     */
    protected function encounterInConsentRange($start, $end, array $bounds): bool
    {
        if (config('abdm.consent_date_range', 'overlap') === 'off' || (!$bounds[0] && !$bounds[1])) {
            return true;
        }

        $from = $this->date($start);
        if (!$from) {
            return false; // undated: it cannot be shown to fall inside a bounded consent
        }
        $to = $this->date($end) ?? $from;
        if ($to->lessThan($from)) {
            $to = $from;
        }

        return (!$bounds[1] || $from->lessThanOrEqualTo($bounds[1]))
            && (!$bounds[0] || $to->greaterThanOrEqualTo($bounds[0]));
    }

    // ── Vendored legacy builders (./Legacy) ───────────────────────────────────

    /**
     * The vendored legacy builders run hand-written MySQL (DATE_FORMAT, ...) through FhirQuery on the application's
     * own database connection. On any other driver (the SQLite test database) callers fall back to the Eloquent
     * assembly in this trait.
     */
    protected function canUseLegacyBuilders(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    /**
     * Globals the legacy builders and their PDF helpers read (set by legacy abdm/config.php and db.php):
     * the facility identity for the Organization resource and the hospital letterhead for the PDFs.
     */
    protected function prepareLegacyGlobals(): void
    {
        $settings = DB::table('settings')->find(1);

        $GLOBALS['abdm_facility_hfr_id'] = ($settings->facility_hfr_id ?? '') ?: config('abdm.hip_id', '');
        $GLOBALS['abdm_facility_name']   = ($settings->facility_name ?? '') ?: ($settings->hospital_name ?? '');

        $GLOBALS['hospital_name']    = (string) ($settings->hospital_name ?? '');
        $GLOBALS['hospital_address'] = (string) ($settings->address ?? '');
        $GLOBALS['phone_number']     = (string) ($settings->phone_number ?? '');
        $GLOBALS['mobile_number']    = (string) ($settings->mobile_number ?? '');
        // Legacy took these from the logged-in session user; the push webhook has no session, so they stay blank.
        $GLOBALS['sign_name']        = '';
        $GLOBALS['sign_designation'] = '';
    }
}
