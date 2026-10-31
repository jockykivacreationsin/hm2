<?php

namespace App\Services\Abdm\Fhir\Ipd;

/** Observation resources (lab results and vital signs) and the quantity/unit handling they share. */
final class ObservationFactory
{
    private const OBSERVATION_CATEGORY = 'http://terminology.hl7.org/CodeSystem/observation-category';
    private const UCUM = 'http://unitsofmeasure.org';

    /**
     * Normalises the hospital DB's stored lab-unit text into plain, JSON-safe display text without changing its
     * meaning (`cells/mm³` -> `cells/mm3`). Deliberately separate from UCUM coding: a unit can be safely
     * normalised for display even when no UCUM code should be guessed for it.
     */
    public function normalizeUnitText(string $unitName): string
    {
        $unitName = html_entity_decode(trim($unitName), ENT_QUOTES, 'UTF-8');
        $unitName = str_replace(["\u{00B5}", "\u{03BC}"], 'u', $unitName);

        return str_replace(["\u{00B3}", '&sup3;'], '3', $unitName);
    }

    /**
     * Observation.valueQuantity from the unit text plus the explicitly configured UCUM code (bunits.ucum_code).
     * Without a stored code only the human-readable `unit` is sent; system/code are omitted rather than guessed.
     *
     * @return array{value:float, unit?:string, system?:string, code?:string}
     */
    public function quantity(float $value, string $unitName, ?string $ucumCode = null): array
    {
        $quantity = ['value' => $value];
        // Legacy quirk: callers already normalised the text, and it is normalised again here. The decode is not
        // idempotent for double-encoded entities, so both passes must stay.
        $unitName = $this->normalizeUnitText($unitName);
        $ucumCode = $ucumCode !== null ? trim(html_entity_decode($ucumCode, ENT_QUOTES, 'UTF-8')) : null;
        if ($unitName !== '') {
            $quantity['unit'] = $unitName;
        }

        if ($ucumCode !== null && $ucumCode !== '') {
            $quantity['system'] = self::UCUM;
            $quantity['code'] = $ucumCode;
        }

        return $quantity;
    }

    /**
     * One lab sub-test result.
     *
     * @param array<string, mixed> $sub row of biochemistry_tests_result joined to its sub test and unit
     */
    public function lab(IpdContext $ctx, array $sub, string $effectiveDateTime): array
    {
        $id = abdm_generate_uuid();
        $testValue = trim((string) $sub['test_value']);
        $unitName = $this->normalizeUnitText((string) $sub['unit_name']);
        $ucumCode = isset($sub['ucum_code']) ? trim((string) $sub['ucum_code']) : '';

        $observation = [
            'resourceType' => 'Observation',
            'id' => $id,
            'meta' => ['profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/Observation']],
            'status' => 'final',
            'category' => [['coding' => [[
                'system' => self::OBSERVATION_CATEGORY,
                'code' => 'laboratory',
                'display' => 'Laboratory',
            ]]]],
            'code' => ['text' => $sub['sub_test_name']],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'effectiveDateTime' => $effectiveDateTime,
            'performer' => [['reference' => $ctx->organizationRef()]],
        ];

        if ($testValue !== '' && is_numeric($testValue)) {
            $observation['valueQuantity'] = $this->quantity((float) $testValue, $unitName, $ucumCode);
        } else {
            $observation['valueString'] = trim($testValue . ' ' . $unitName);
        }

        if (!empty($sub['reference_range'])) {
            // trim(): the stored range text occasionally carries trailing tabs/whitespace from data entry.
            $observation['referenceRange'] = [['text' => trim((string) $sub['reference_range'])]];
        }

        if (strtolower((string) $sub['highlighted']) === 'yes') {
            $observation['interpretation'] = [['coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                'code' => 'A',
                'display' => 'Abnormal',
            ]]]];
        }

        return $observation;
    }

    /** A single-value vital sign (pulse, temperature, respiration, SpO2). */
    public function vitalSign(IpdContext $ctx, string $loincCode, string $loincDisplay, string $unit, float $value, string $effectiveDateTime): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => abdm_generate_uuid(),
            'status' => 'final',
            'category' => [$this->vitalSignsCategory()],
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $loincDisplay]], 'text' => $loincDisplay],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'effectiveDateTime' => $effectiveDateTime,
            'valueQuantity' => ['value' => $value, 'unit' => $unit, 'system' => self::UCUM, 'code' => $unit],
        ];
    }

    /** Blood pressure panel with systolic and diastolic components. */
    public function bloodPressure(IpdContext $ctx, float $systolic, float $diastolic, string $effectiveDateTime): array
    {
        return [
            'resourceType' => 'Observation',
            'id' => abdm_generate_uuid(),
            'status' => 'final',
            'category' => [$this->vitalSignsCategory()],
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel']], 'text' => 'Blood pressure panel'],
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'effectiveDateTime' => $effectiveDateTime,
            'component' => [
                $this->bloodPressureComponent('8480-6', 'Systolic blood pressure', $systolic),
                $this->bloodPressureComponent('8462-4', 'Diastolic blood pressure', $diastolic),
            ],
        ];
    }

    private function bloodPressureComponent(string $loincCode, string $display, float $value): array
    {
        return [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $display]]],
            'valueQuantity' => ['value' => $value, 'unit' => 'mmHg', 'system' => self::UCUM, 'code' => 'mm[Hg]'],
        ];
    }

    private function vitalSignsCategory(): array
    {
        return ['coding' => [['system' => self::OBSERVATION_CATEGORY, 'code' => 'vital-signs', 'display' => 'Vital Signs']]];
    }
}
