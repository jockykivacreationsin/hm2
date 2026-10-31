<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;
use Generator;

/**
 * Builds the MedicationRequest entries for one admission from every medication source the app has:
 *   - ipd_prescriptions + ipd_medicines (doctor's prescription lines)
 *   - ipd_treatments + ipd_treatment_detail (treatment-chart lines)
 *   - ipd_discharge_treatment_advised (once discharged)
 *
 * Shared by the DischargeSummary and Prescription bundles. MedicationRequest.requester is REQUIRED (1..1) by
 * the NRCeS profile (its absence made both bundles fail to render in the ABHA app), so it is always the
 * context's author reference.
 */
final class MedicationEntries
{
    private const LOG = '[ABDM][IPD][PRESCRIPTION] ';

    private const TIMING_CODES = [
        'morning' => 'MORN',
        'afternoon' => 'AFT',
        'evening' => 'EVE',
    ];

    /**
     * Adds the MedicationRequests to the context's entries.
     *
     * @return list<array{reference:string}> the section entry references
     */
    public function build(IpdContext $ctx): array
    {
        $sectionEntries = [];
        foreach ($this->lines($ctx->ipdId) as $line) {
            $reference = $this->addRequest($ctx, $line);
            if ($reference !== null) {
                $sectionEntries[] = $reference;
            }
        }

        return $sectionEntries;
    }

    /** @return Generator<int, MedicationLine> lazily, so queries and id generation interleave as they always did */
    private function lines(int $ipdId): Generator
    {
        yield from $this->prescriptionLines($ipdId);
        yield from $this->treatmentChartLines($ipdId);
        // ipd_discharge_medication_given is deliberately not exported: it holds free text, not the normalised structure.
        yield from $this->dischargeAdvisedLines($ipdId);
    }

    private function prescriptionLines(int $ipdId): Generator
    {
        $prescriptions = FhirQuery::rows("SELECT id, findings FROM ipd_prescriptions WHERE ipd_id = ?", 'i', [$ipdId]);

        foreach ($prescriptions as $prescription) {
            $medicines = FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                m.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                m.duration_days,
                m.medical_condition_id,
                mc.condition_name,
                mc.snomed_code,
                mc.snomed_display,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_medicines m
            INNER JOIN pharma_products pp ON m.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON m.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON m.meal_relation_id = mr.id
            LEFT JOIN ipd_medicine_timing_selections mts ON m.id = mts.ipd_medicine_id
            LEFT JOIN medicine_timing_of_day t ON mts.timing_id = t.id
            LEFT JOIN medical_conditions mc ON m.medical_condition_id = mc.id
            WHERE m.ipd_prescription_id = ?
            GROUP BY m.id, pp.product_name, m.dose_quantity, ft.display_text, mr.relation_name, m.duration_days, m.medical_condition_id, mc.condition_name, mc.snomed_code, mc.snomed_display
            ORDER BY m.id", 'i', [$prescription['id']]);

            foreach ($medicines as $medicine) {
                error_log(self::LOG . 'Medicine: ' . $medicine['medicine_name'] .
                    ' | medical_condition_id: ' . ($medicine['medical_condition_id'] ?? 'NULL') .
                    ' | condition_name: ' . ($medicine['condition_name'] ?? 'NULL') .
                    ' | snomed_code: ' . ($medicine['snomed_code'] ?? 'NULL'));

                // The prescription's findings are general notes, not a medicine-specific reason, so they are not used.
                yield new MedicationLine(
                    $medicine['medicine_name'],
                    $medicine['dose_quantity'],
                    $medicine['timing_names'] ?? '',
                    $medicine['meal_relation'] ?? '',
                    $medicine['duration_days'],
                    'Admission Prescription',
                    $medicine['medical_condition_id'],
                    $medicine['condition_name'] ?? null,
                    $medicine['snomed_code'] ?? null,
                    $medicine['snomed_display'] ?? null,
                );
            }
        }
    }

    private function treatmentChartLines(int $ipdId): Generator
    {
        $treatments = FhirQuery::rows("SELECT id FROM ipd_treatments WHERE ipd_id = ?", 'i', [$ipdId]);

        foreach ($treatments as $treatment) {
            $details = FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                d.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                d.duration_days,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_treatment_detail d
            INNER JOIN pharma_products pp ON d.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON d.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON d.meal_relation_id = mr.id
            LEFT JOIN ipd_treatment_timing_selections tts ON d.id = tts.treatment_detail_id
            LEFT JOIN medicine_timing_of_day t ON tts.timing_id = t.id
            WHERE d.ipd_treatment_id = ?
            GROUP BY d.id, pp.product_name, d.dose_quantity, ft.display_text, mr.relation_name, d.duration_days
            ORDER BY d.id", 'i', [$treatment['id']]);

            foreach ($details as $detail) {
                yield new MedicationLine(
                    $detail['medicine_name'],
                    $detail['dose_quantity'],
                    $detail['timing_names'] ?? '',
                    $detail['meal_relation'] ?? '',
                    $detail['duration_days'],
                    'Treatment Chart',
                );
            }
        }
    }

    private function dischargeAdvisedLines(int $ipdId): Generator
    {
        $advised = FhirQuery::rows("SELECT 
                pp.product_name as medicine_name,
                ta.dose_quantity,
                ft.display_text as dose_interval,
                mr.relation_name as meal_relation,
                ta.duration_days,
                ta.medical_condition_id,
                mc.condition_name,
                GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ', ') as timing_names
            FROM ipd_discharge_treatment_advised ta
            INNER JOIN ipd_discharge ON ipd_discharge.id = ta.discharge_id
            INNER JOIN pharma_products pp ON ta.pharma_product_id = pp.id
            LEFT JOIN medicine_frequency_types ft ON ta.frequency_type_id = ft.id
            LEFT JOIN medicine_meal_relation mr ON ta.meal_relation_id = mr.id
            LEFT JOIN medical_conditions mc ON ta.medical_condition_id = mc.id
            LEFT JOIN ipd_discharge_treatment_timing_selections dts ON ta.id = dts.treatment_advised_id
            LEFT JOIN medicine_timing_of_day t ON dts.timing_id = t.id
            WHERE ipd_discharge.ipd_id = ?
            GROUP BY ta.id, pp.product_name, ta.dose_quantity, ft.display_text, mr.relation_name, ta.duration_days, ta.medical_condition_id, mc.condition_name
            ORDER BY ta.id", 'i', [$ipdId]);

        foreach ($advised as $row) {
            yield new MedicationLine(
                $row['medicine_name'],
                $row['dose_quantity'],
                $row['timing_names'] ?? '',
                $row['meal_relation'] ?? '',
                $row['duration_days'],
                'Discharge Treatment Advised',
                $row['medical_condition_id'] ?? null,
                $row['condition_name'] ?? null,
            );
        }
    }

    /** @return array{reference:string}|null null when the medicine has no name */
    private function addRequest(IpdContext $ctx, MedicationLine $line): ?array
    {
        $name = trim($line->medicineName);
        if ($name === '') {
            return null;
        }

        error_log(self::LOG . 'Processing medicine: ' . $name);

        [$frequency, $whenCodes] = $this->frequencyAndWhen($line->timingNames);
        $dosageText = $this->dosageText($line, $frequency);
        error_log(self::LOG . 'Dosage text: ' . $dosageText);

        $request = [
            'resourceType' => 'MedicationRequest',
            'id' => abdm_generate_uuid(),
            'status' => 'active',
            'intent' => 'order',
            'subject' => $ctx->patientRef(),
            'encounter' => $ctx->encounterRef(),
            'authoredOn' => $ctx->now,
            'requester' => ['reference' => $ctx->authorRef()],
            'medicationCodeableConcept' => ['text' => $name],
            'dosageInstruction' => [$this->dosageInstruction($dosageText, $frequency, $whenCodes)],
        ];

        // The source note lets the ABHA app group the medicines by prescription type.
        if (!empty($line->source)) {
            $request['note'] = [['text' => 'Source: ' . $line->source]];
        }

        $reason = $this->reasonCode($line);
        if ($reason !== null) {
            $request['reasonCode'] = $reason;
        }

        $dispense = $this->dispenseRequest($line, $frequency);
        if ($dispense !== null) {
            $request['dispenseRequest'] = $dispense;
        }

        return $ctx->entries->add($request);
    }

    /**
     * Frequency is the number of selected timings of the day (at least 1); unknown timings add no `when` code.
     *
     * @return array{0:int, 1:list<string>} [frequency, FHIR `when` codes]
     */
    private function frequencyAndWhen(string $timingNames): array
    {
        $timingCount = 0;
        $whenCodes = [];
        if (!empty($timingNames)) {
            $timings = array_filter(array_map('trim', explode(',', $timingNames)));
            $timingCount = count($timings);

            foreach ($timings as $timingName) {
                $code = $this->whenCode(strtolower($timingName));
                if ($code !== null) {
                    $whenCodes[] = $code;
                }
            }
        }

        return [$timingCount > 0 ? $timingCount : 1, empty($whenCodes) ? ['MORN'] : $whenCodes];
    }

    private function whenCode(string $timing): ?string
    {
        foreach (self::TIMING_CODES as $needle => $code) {
            if (stripos($timing, $needle) !== false) {
                return $code;
            }
        }
        if (stripos($timing, 'night') !== false || stripos($timing, 'bed') !== false) {
            return 'NIGHT';
        }

        return stripos($timing, 'noon') !== false ? 'NOON' : null;
    }

    private function dosageText(MedicationLine $line, int $frequency): string
    {
        $doseQty = !empty($line->doseQuantity) ? (float) $line->doseQuantity : 1;
        if ($doseQty == floor($doseQty)) {
            $doseQty = (int) $doseQty;
        }

        $tabletWord = $doseQty == 1 ? 'tablet' : 'tablets';
        $frequencyText = match ($frequency) {
            1 => 'once',
            2 => 'twice',
            default => $frequency . ' times',
        };

        $text = "Take {$doseQty} {$tabletWord} {$frequencyText} daily";
        if (!empty($line->timingNames)) {
            $text .= ' - ' . strtolower($line->timingNames);
        }
        if (!empty($line->mealRelation)) {
            $text .= ' ' . strtolower($line->mealRelation);
        }
        if (!empty($line->durationDays)) {
            $text .= " for {$line->durationDays} days";
        }

        return $text;
    }

    /** @param list<string> $whenCodes */
    private function dosageInstruction(string $text, int $frequency, array $whenCodes): array
    {
        return [
            'text' => $text,
            'timing' => [
                'repeat' => [
                    'frequency' => $frequency,
                    'period' => 1,
                    'periodUnit' => 'd',
                    'when' => $whenCodes,
                ],
            ],
            'route' => [
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '26643006',
                    'display' => 'Oral route',
                ]],
            ],
            // Legacy quirk: doseQuantity.value is the daily frequency, not the per-intake dose.
            'doseAndRate' => [[
                'doseQuantity' => ['value' => $frequency, 'unit' => 'tablets'],
            ]],
        ];
    }

    /** SNOMED-coded when the medical condition has a code, otherwise the condition name as plain text. */
    private function reasonCode(MedicationLine $line): ?array
    {
        if ($line->medicalConditionId && !empty($line->snomedCode)) {
            error_log(self::LOG . 'Added reasonCode with SNOMED: ' . $line->snomedCode . ' - ' . $line->conditionName);

            return [[
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => (string) $line->snomedCode,
                    'display' => !empty($line->snomedDisplay) ? $line->snomedDisplay : $line->conditionName,
                ]],
                'text' => $line->conditionName,
            ]];
        }
        if (!empty($line->conditionName)) {
            error_log(self::LOG . 'Added reasonCode as text from condition_name: ' . $line->conditionName);

            return [['text' => trim($line->conditionName)]];
        }

        return null;
    }

    /** Only for a plain numeric dose and a numeric duration: dose x frequency x days tablets. */
    private function dispenseRequest(MedicationLine $line, int $frequency): ?array
    {
        $dose = $line->doseQuantity;
        $days = $line->durationDays;
        if (empty($dose) || empty($days) || !is_numeric($dose) || !is_numeric($days)) {
            return null;
        }
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*$/', $dose, $matches)) {
            return null;
        }

        $totalQuantity = (float) $matches[1] * $frequency * (int) $days;
        error_log(self::LOG . 'dispenseRequest.quantity.value: ' . $totalQuantity);

        return [
            'quantity' => ['value' => $totalQuantity, 'unit' => 'tablets'],
            'expectedSupplyDuration' => [
                'value' => (int) $days,
                'unit' => 'days',
                'system' => 'http://unitsofmeasure.org',
                'code' => 'd',
            ],
        ];
    }
}
