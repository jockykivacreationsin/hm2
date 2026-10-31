<?php

namespace App\Services\Abdm\Fhir\Opd;

/**
 * Turns one opd_medicines row (joined with its timings, meal relation and condition) into a FHIR MedicationRequest.
 *
 * The many [ABDM][OPD][PRESCRIPTION] log lines are kept from the legacy code: they are the only trace of how a
 * dosage was derived when a PHR app shows something unexpected.
 */
final class MedicationRequestFactory
{
    /** @return array<string, mixed> */
    public function build(OpdBundleContext $context, string $medId, array $medicine, array $prescription): array
    {
        error_log('[ABDM][OPD][PRESCRIPTION] Processing medicine: ' . $medicine['medicine_name']);
        error_log('[ABDM][OPD][PRESCRIPTION] Raw timing_names from DB: ' . ($medicine['timing_names'] ?? 'NULL'));
        error_log('[ABDM][OPD][PRESCRIPTION] Raw dose_quantity: ' . ($medicine['dose_quantity'] ?? 'NULL'));
        error_log('[ABDM][OPD][PRESCRIPTION] Raw duration_days: ' . ($medicine['duration_days'] ?? 'NULL'));

        $actualFrequency = $this->frequencyFromTimings($medicine);
        $dosageText = $this->dosageText($medicine, $actualFrequency);
        error_log('[ABDM][OPD][PRESCRIPTION] Built dosage text: ' . $dosageText);

        [$whenCode, $frequency] = $this->schedule($medicine, $actualFrequency);
        error_log('[ABDM][OPD][PRESCRIPTION] whenCode array: ' . json_encode($whenCode));

        $request = [
            'resourceType' => 'MedicationRequest',
            'id' => $medId,
            'meta' => [
                'profile' => ['https://nrces.in/ndhm/fhir/r4/StructureDefinition/MedicationRequest'],
            ],
            'status' => 'active',
            'intent' => 'order',
            'subject' => $context->subject(),
            'encounter' => $context->encounter(),
            'authoredOn' => $context->now,
            'medicationCodeableConcept' => ['text' => $medicine['medicine_name']],
            'dosageInstruction' => [$this->dosageInstruction($medicine, $dosageText, $frequency, $whenCode)],
        ];

        error_log('[ABDM][OPD][PRESCRIPTION] Created doseAndRate with value: ' . ($this->doseQuantity($medicine) * $frequency));

        if ($context->practitionerId) {
            $request['requester'] = ['reference' => 'Practitioner/' . $context->practitionerId];
        }
        $reason = $this->reasonCode($medicine, $prescription);
        if ($reason !== null) {
            $request['reasonCode'] = $reason;
        }
        $dispense = $this->dispenseRequest($medicine, $frequency, $context->now);
        if ($dispense !== null) {
            $request['dispenseRequest'] = $dispense;
        }

        $this->logSummary($context, $request, $medicine, $prescription, $dosageText, $actualFrequency, $whenCode, $frequency);

        return $request;
    }

    /** Number of selected timings (e.g. "Morning, Night" = 2); defaults to 1 when none are selected. */
    private function frequencyFromTimings(array $medicine): int
    {
        $timingCount = 0;
        if (!empty($medicine['timing_names'])) {
            $timings = array_filter(array_map('trim', explode(',', $medicine['timing_names'])));
            $timingCount = count($timings);
            error_log('[ABDM][OPD][PRESCRIPTION] Exploded timings: [' . implode('], [', $timings) . '], count=' . $timingCount);
        }
        $actualFrequency = $timingCount > 0 ? $timingCount : 1;
        error_log('[ABDM][OPD][PRESCRIPTION] Final actualFrequency: ' . $actualFrequency);

        return $actualFrequency;
    }

    /**
     * Dose per intake: float from dose_quantity, or the int 1 when unset.
     * The int/float distinction is part of the JSON output (1 vs 1.0), so it is kept as legacy had it.
     */
    private function doseQuantity(array $medicine): float|int
    {
        return !empty($medicine['dose_quantity']) ? (float) $medicine['dose_quantity'] : 1;
    }

    /** "Take 1 tablet twice daily - morning, night after meal for 5 days" */
    private function dosageText(array $medicine, int $actualFrequency): string
    {
        $doseQty = $this->doseQuantity($medicine);
        // 1.00 -> 1, 1.50 -> 1.5
        if ($doseQty == floor($doseQty)) {
            $doseQty = (int) $doseQty;
        }

        $timingText = !empty($medicine['timing_names']) ? strtolower($medicine['timing_names']) : 'as directed';
        $mealText = !empty($medicine['meal_relation']) ? strtolower($medicine['meal_relation']) : '';
        $durationText = !empty($medicine['duration_days']) ? ' for ' . $medicine['duration_days'] . ' days' : '';

        $tabletWord = $doseQty == 1 ? 'tablet' : 'tablets';
        $frequencyText = $actualFrequency == 1 ? 'once' : ($actualFrequency == 2 ? 'twice' : $actualFrequency . ' times');

        $dosageText = "Take {$doseQty} {$tabletWord} {$frequencyText} daily - {$timingText}";
        if (!empty($mealText)) {
            $dosageText .= " {$mealText}";
        }

        return $dosageText . $durationText;
    }

    /**
     * FHIR timing.repeat "when" codes and the per-day frequency.
     * Timing selections win; the free-text dose interval is only a fallback.
     *
     * @return array{0: string[], 1: int}
     */
    private function schedule(array $medicine, int $actualFrequency): array
    {
        if (!empty($medicine['timing_names'])) {
            return [$this->whenFromTimings($medicine['timing_names']), $actualFrequency];
        }

        $doseInterval = strtolower(trim((string) $medicine['dose_interval']));

        return $this->scheduleFromInterval($doseInterval, $actualFrequency);
    }

    /** @return string[] */
    private function whenFromTimings(string $timingNames): array
    {
        $whenCode = [];
        foreach (array_map('trim', explode(',', $timingNames)) as $timingName) {
            $t = strtolower($timingName);
            if (stripos($t, 'morning') !== false) {
                $whenCode[] = 'MORN';
            } elseif (stripos($t, 'afternoon') !== false) {
                $whenCode[] = 'AFT';
            } elseif (stripos($t, 'evening') !== false) {
                $whenCode[] = 'EVE';
            } elseif (stripos($t, 'night') !== false || stripos($t, 'bed') !== false) {
                $whenCode[] = 'NIGHT';
            } elseif (stripos($t, 'noon') !== false) {
                $whenCode[] = 'NOON';
            }
        }

        return empty($whenCode) ? ['MORN'] : $whenCode;
    }

    /**
     * Fallback when no timings were selected. Legacy matches loose substrings on purpose (e.g. "3", "pc", "ac"),
     * and when nothing matches it keeps ['MORN'] with the timing-derived frequency.
     *
     * @return array{0: string[], 1: int}
     */
    private function scheduleFromInterval(string $interval, int $defaultFrequency): array
    {
        if ($this->has($interval, 'three', 'thrice', '3')) {
            return $this->mealAwareSchedule($interval, 3, ['MORN', 'AFT', 'NIGHT'], true);
        }
        if ($this->has($interval, 'twice', '2')) {
            return $this->mealAwareSchedule($interval, 2, ['MORN', 'NIGHT'], true);
        }
        if ($this->has($interval, 'four', '4')) {
            // Legacy has no "before meal" case for four-times-a-day.
            return $this->mealAwareSchedule($interval, 4, ['MORN', 'NOON', 'EVE', 'NIGHT'], false);
        }

        $single = [
            [['morning'], 'MORN'],
            [['afternoon'], 'AFT'],
            [['evening'], 'EVE'],
            [['night', 'bedtime'], 'NIGHT'],
            [['after meal', 'pc'], 'PC'],
            [['before meal', 'ac'], 'AC'],
        ];
        foreach ($single as [$needles, $code]) {
            if ($this->has($interval, ...$needles)) {
                return [[$code], 1];
            }
        }

        return [['MORN'], $defaultFrequency];
    }

    /**
     * @param string[] $defaultWhen
     * @return array{0: string[], 1: int}
     */
    private function mealAwareSchedule(string $interval, int $frequency, array $defaultWhen, bool $hasBeforeMeal): array
    {
        if ($this->has($interval, 'after meal', 'pc')) {
            return [['PC'], $frequency];
        }
        if ($hasBeforeMeal && $this->has($interval, 'before meal', 'ac')) {
            return [['AC'], $frequency];
        }

        return [$defaultWhen, $frequency];
    }

    private function has(string $haystack, string ...$needles): bool
    {
        foreach ($needles as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $whenCode
     * @return array<string, mixed>
     */
    private function dosageInstruction(array $medicine, string $dosageText, int $frequency, array $whenCode): array
    {
        return [
            'sequence' => 1,
            'text' => $dosageText,
            'timing' => [
                'repeat' => [
                    'frequency' => $frequency,
                    'period' => 1,
                    'periodUnit' => 'd',
                    'when' => $whenCode,
                ],
            ],
            'route' => [
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '26643006',
                    'display' => 'Oral route',
                ]],
            ],
            'doseAndRate' => [
                [
                    'doseQuantity' => [
                        'value' => $this->doseQuantity($medicine) * $frequency,
                        'unit' => 'tablet',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => '{tbl}',
                    ],
                ],
            ],
        ];
    }

    /**
     * SNOMED-coded reason from the medical condition, else the prescription findings as text.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function reasonCode(array $medicine, array $prescription): ?array
    {
        if (!empty($medicine['medical_condition_id']) && !empty($medicine['snomed_code'])) {
            error_log('[ABDM][OPD][PRESCRIPTION] Added reasonCode with SNOMED: ' . $medicine['snomed_code'] . ' - ' . $medicine['condition_name']);

            return [[
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => $medicine['snomed_code'],
                    'display' => !empty($medicine['snomed_display']) ? $medicine['snomed_display'] : $medicine['condition_name'],
                ]],
            ]];
        }
        if (!empty($prescription['findings'])) {
            error_log('[ABDM][OPD][PRESCRIPTION] Added reasonCode as text from findings');

            return [['text' => trim($prescription['findings'])]];
        }

        return null;
    }

    /**
     * Total quantity = dose x frequency x days; only when both dose and duration are set and the dose starts
     * with a number.
     *
     * @return array<string, mixed>|null
     */
    private function dispenseRequest(array $medicine, int $frequency, string $now): ?array
    {
        error_log('[ABDM][OPD][PRESCRIPTION] Checking dispenseRequest conditions: dose_quantity=' . ($medicine['dose_quantity'] ?? 'NULL') . ', duration_days=' . ($medicine['duration_days'] ?? 'NULL'));

        if (empty($medicine['dose_quantity']) || empty($medicine['duration_days'])) {
            error_log('[ABDM][OPD][PRESCRIPTION] Skipping dispenseRequest - missing dose_quantity or duration_days');

            return null;
        }

        error_log('[ABDM][OPD][PRESCRIPTION] Conditions passed, attempting regex match on: "' . trim($medicine['dose_quantity']) . '"');
        if (!preg_match('/^(\d+(?:\.\d+)?)/i', trim($medicine['dose_quantity']), $matches)) {
            error_log('[ABDM][OPD][PRESCRIPTION] Regex FAILED to match dose_quantity');

            return null;
        }

        $doseValue = (float) $matches[1];
        error_log('[ABDM][OPD][PRESCRIPTION] Regex matched! Extracted dose value: ' . $doseValue);
        $totalQuantity = $doseValue * $frequency * (int) $medicine['duration_days'];
        error_log('[ABDM][OPD][PRESCRIPTION] CALC: dose=' . $doseValue . ' × freq=' . $frequency . ' × days=' . $medicine['duration_days'] . ' = TOTAL:' . $totalQuantity . ' tablets');

        $dispense = [
            'validityPeriod' => [
                'start' => gmdate('Y-m-d', strtotime($now)),
                'end' => gmdate('Y-m-d', strtotime($now . ' +' . $medicine['duration_days'] . ' days')),
            ],
            'quantity' => [
                'value' => $totalQuantity,
                'unit' => 'tablets',
                'system' => 'http://unitsofmeasure.org',
                'code' => '{tbl}',
            ],
            'expectedSupplyDuration' => [
                'value' => (int) $medicine['duration_days'],
                'unit' => 'days',
                'system' => 'http://unitsofmeasure.org',
                'code' => 'd',
            ],
        ];
        error_log('[ABDM][OPD][PRESCRIPTION] dispenseRequest.quantity.value: ' . $totalQuantity);
        error_log('[ABDM][OPD][PRESCRIPTION] dispenseRequest.expectedSupplyDuration.value: ' . $medicine['duration_days']);

        return $dispense;
    }

    /**
     * @param array<string, mixed> $request
     * @param string[] $whenCode
     */
    private function logSummary(
        OpdBundleContext $context,
        array $request,
        array $medicine,
        array $prescription,
        string $dosageText,
        int $actualFrequency,
        array $whenCode,
        int $frequency,
    ): void {
        $practitionerId = $context->practitionerId;
        $instruction = $request['dosageInstruction'][0];

        error_log('[ABDM][OPD][PRESCRIPTION] Medicine: ' . $medicine['medicine_name']);
        error_log('[ABDM][OPD][PRESCRIPTION] Dosage: ' . $dosageText);
        error_log('[ABDM][OPD][PRESCRIPTION] Timing count: ' . $actualFrequency . ' (from: ' . ($medicine['timing_names'] ?? 'none') . ')');
        error_log('[ABDM][OPD][PRESCRIPTION] Interval: ' . $medicine['dose_interval'] . ' → when=' . json_encode($whenCode) . ', freq=' . $frequency);
        error_log('[ABDM][OPD][PRESCRIPTION] Has Practitioner: ' . ($practitionerId ? 'Yes (' . $practitionerId . ')' : 'No'));
        error_log('[ABDM][OPD][PRESCRIPTION] Findings: ' . (isset($prescription['findings']) && $prescription['findings'] !== '' ? $prescription['findings'] : 'EMPTY'));
        error_log('[ABDM][OPD][PRESCRIPTION] Timing JSON: ' . json_encode($instruction['timing']));

        if (isset($instruction['doseAndRate'][0]['doseQuantity']['value'])) {
            error_log('[ABDM][OPD][PRESCRIPTION] doseAndRate.doseQuantity.value: ' . $instruction['doseAndRate'][0]['doseQuantity']['value']);
        }
        if (isset($request['dispenseRequest']['quantity']['value'])) {
            error_log('[ABDM][OPD][PRESCRIPTION] Final dispenseRequest in resource: ' . $request['dispenseRequest']['quantity']['value'] . ' tablets');
        }
    }
}
