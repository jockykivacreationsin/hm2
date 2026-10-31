<?php

namespace App\Services\Abdm\Fhir\Opd;

/** Vital-sign Observations of the OPConsultation bundle. */
final class FhirObservationFactory
{
    private const INTERPRETATION_LABELS = ['N' => 'Normal', 'H' => 'High', 'L' => 'Low'];

    /**
     * One vital-sign Observation with a numeric value.
     *
     * @param array{0: string, 1: string, 2: string, 3: string} $definition LOINC code, LOINC display, unit, UCUM code
     * @return array<string, mixed>
     */
    public function vital(OpdBundleContext $context, string $id, array $definition, float $value): array
    {
        [$loincCode, $loincDisplay, $unit, $ucumCode] = $definition;

        return [
            'resourceType' => 'Observation',
            'id' => $id,
            'status' => 'final',
            'category' => $this->vitalSignsCategory(),
            'code' => ['coding' => [[
                'system' => 'http://loinc.org',
                'code' => $loincCode,
                'display' => $loincDisplay,
            ]], 'text' => $loincDisplay],
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'effectiveDateTime' => $context->now,
            'valueQuantity' => [
                'value' => $value,
                'unit' => $unit,
                'system' => 'http://unitsofmeasure.org',
                'code' => $ucumCode,
            ],
        ];
    }

    /**
     * Blood pressure panel parsed from free text such as "120/80"; null when the text is not in that form.
     *
     * @return array<string, mixed>|null
     */
    public function bloodPressure(OpdBundleContext $context, string $id, string $bpText): ?array
    {
        if (!preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', $bpText, $m)) {
            return null;
        }

        $systolic = (float) $m[1];
        $diastolic = (float) $m[2];
        $systolicCode = $systolic < 90 ? 'L' : ($systolic < 130 ? 'N' : 'H');
        $diastolicCode = $diastolic < 60 ? 'L' : ($diastolic < 80 ? 'N' : 'H');

        $observation = [
            'resourceType' => 'Observation',
            'id' => $id,
            'status' => 'final',
            'category' => $this->vitalSignsCategory(),
            'code' => ['coding' => [[
                'system' => 'http://loinc.org',
                'code' => '85354-9',
                'display' => 'Blood pressure panel',
            ]], 'text' => 'Blood pressure panel'],
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'effectiveDateTime' => $context->now,
            'component' => [
                $this->bloodPressureComponent('8480-6', 'Systolic blood pressure', $systolic, $systolicCode),
                $this->bloodPressureComponent('8462-4', 'Diastolic blood pressure', $diastolic, $diastolicCode),
            ],
        ];
        $observation['interpretation'] = [$this->interpretation($this->panelCode($systolicCode, $diastolicCode))];

        return $observation;
    }

    /** @return array<string, mixed> */
    private function bloodPressureComponent(string $loincCode, string $display, float $value, string $interpretationCode): array
    {
        return [
            'code' => ['coding' => [['system' => 'http://loinc.org', 'code' => $loincCode, 'display' => $display]]],
            'valueQuantity' => ['value' => $value, 'unit' => 'mmHg', 'system' => 'http://unitsofmeasure.org', 'code' => 'mm[Hg]'],
            'interpretation' => [$this->interpretation($interpretationCode)],
        ];
    }

    /**
     * Panel-level interpretation: High if either value is high, else Low if either is low, else Normal
     * (Low is checked before Normal so e.g. 85/55 is not reported as Normal).
     */
    private function panelCode(string $systolicCode, string $diastolicCode): string
    {
        if ($systolicCode === 'H' || $diastolicCode === 'H') {
            return 'H';
        }

        return ($systolicCode === 'L' || $diastolicCode === 'L') ? 'L' : 'N';
    }

    /**
     * CodeableConcept with BOTH coding and text: some viewers (incl. the ABHA app) read interpretation.text
     * and show "NA" when it is missing.
     *
     * @return array<string, mixed>
     */
    private function interpretation(string $code): array
    {
        return [
            'coding' => [[
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation',
                'code' => $code,
                'display' => self::INTERPRETATION_LABELS[$code],
            ]],
            'text' => self::INTERPRETATION_LABELS[$code],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function vitalSignsCategory(): array
    {
        return [['coding' => [[
            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
            'code' => 'vital-signs',
            'display' => 'Vital Signs',
        ]]]];
    }
}
