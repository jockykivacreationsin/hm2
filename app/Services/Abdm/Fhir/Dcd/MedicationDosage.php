<?php

namespace App\Services\Abdm\Fhir\Dcd;

/**
 * Dosage facts derived from one medicine row (admission or discharge): how often per day, the FHIR `when` codes and the
 * human readable instruction. Needs the row keys timing_names, dose_quantity, meal_relation and duration_days.
 */
final class MedicationDosage
{
    /** @param list<string> $whenCodes */
    private function __construct(
        public readonly int $frequency,
        public readonly array $whenCodes,
        public readonly string $text,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        [$timingCount, $whenCodes] = self::parseTimings($row['timing_names'] ?? null);

        $frequency = $timingCount > 0 ? $timingCount : 1;
        if (empty($whenCodes)) {
            $whenCodes = ['MORN'];
        }

        return new self($frequency, $whenCodes, self::instruction($row, $frequency));
    }

    /**
     * Counts the selected timings and maps their names to FHIR `when` codes.
     * Legacy quirk: a timing that matches no keyword still counts towards the frequency but adds no code.
     *
     * @return array{0: int, 1: list<string>}
     */
    private static function parseTimings(mixed $timingNames): array
    {
        $whenCodes = [];
        if (empty($timingNames)) {
            return [0, $whenCodes];
        }

        $timings = array_filter(array_map('trim', explode(',', $timingNames)));
        foreach ($timings as $timingName) {
            $t = strtolower($timingName);
            if (stripos($t, 'morning') !== false) {
                $whenCodes[] = 'MORN';
            } elseif (stripos($t, 'afternoon') !== false) {
                $whenCodes[] = 'AFT';
            } elseif (stripos($t, 'evening') !== false) {
                $whenCodes[] = 'EVE';
            } elseif (stripos($t, 'night') !== false || stripos($t, 'bed') !== false) {
                $whenCodes[] = 'NIGHT';
            } elseif (stripos($t, 'noon') !== false) {
                $whenCodes[] = 'NOON';
            }
        }

        return [count($timings), $whenCodes];
    }

    /** @param array<string, mixed> $row */
    private static function instruction(array $row, int $frequency): string
    {
        $doseQty = !empty($row['dose_quantity']) ? (float) $row['dose_quantity'] : 1;
        if ($doseQty == floor($doseQty)) {
            $doseQty = (int) $doseQty;
        }

        $tabletWord = $doseQty == 1 ? 'tablet' : 'tablets';
        $frequencyText = $frequency == 1 ? 'once' : ($frequency == 2 ? 'twice' : $frequency . ' times');

        $text = "Take {$doseQty} {$tabletWord} {$frequencyText} daily";
        if (!empty($row['timing_names'])) {
            $text .= ' - ' . strtolower($row['timing_names']);
        }
        if (!empty($row['meal_relation'])) {
            $text .= ' ' . strtolower($row['meal_relation']);
        }
        if (!empty($row['duration_days'])) {
            $text .= " for {$row['duration_days']} days";
        }

        return $text;
    }
}
