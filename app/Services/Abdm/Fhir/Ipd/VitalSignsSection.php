<?php

namespace App\Services\Abdm\Fhir\Ipd;

use App\Services\Abdm\Fhir\FhirQuery;

/** The discharge summary's Physical Examination section: every recorded vital sign of the stay as Observations. */
final class VitalSignsSection
{
    /** detail column => [LOINC code, LOINC display, unit] */
    private const VITALS = [
        'pulse' => ['8867-4', 'Heart rate', '/min'],
        'temperature' => ['8310-5', 'Body temperature', 'degF'],
        'respiration' => ['9279-1', 'Respiratory rate', '/min'],
        'spo2' => ['2708-6', 'Oxygen saturation', '%'],
    ];

    public function __construct(private readonly ObservationFactory $observations = new ObservationFactory())
    {
    }

    public function collect(IpdContext $ctx, SummaryDraft $draft): void
    {
        $refs = [];

        $vitalDays = FhirQuery::rows("SELECT id, vital_monitoring_date FROM ipd_vital_monitoring WHERE ipd_id = ?", 'i', [$ctx->ipdId]);
        foreach ($vitalDays as $vitalDay) {
            $details = FhirQuery::rows("SELECT * FROM ipd_vital_monitoring_detail WHERE ipd_vital_monitoring_id = ?", 'i', [$vitalDay['id']]);

            foreach ($details as $detail) {
                $effective = FhirTime::utc($vitalDay['vital_monitoring_date'] . ' ' . ($detail['vital_monitoring_time'] ?? '00:00:00'));
                array_push($refs, ...$this->singleValueVitals($ctx, $detail, $effective));

                $bloodPressure = $this->bloodPressure($ctx, $detail, $effective);
                if ($bloodPressure !== null) {
                    $refs[] = $bloodPressure;
                }
            }
        }

        if (!empty($refs)) {
            $draft->section('Physical Examination', '425044008', 'Physical exam section', $refs);
        }
    }

    /** @return list<array{reference:string}> */
    private function singleValueVitals(IpdContext $ctx, array $detail, string $effective): array
    {
        $refs = [];

        foreach (self::VITALS as $field => [$loincCode, $loincDisplay, $unit]) {
            $raw = trim((string) ($detail[$field] ?? ''));
            if ($raw === '' || !is_numeric($raw)) {
                continue;
            }
            $refs[] = $ctx->entries->add(
                $this->observations->vitalSign($ctx, $loincCode, $loincDisplay, $unit, (float) $raw, $effective)
            );
        }

        return $refs;
    }

    /** @return array{reference:string}|null null unless the reading looks like "120/80" */
    private function bloodPressure(IpdContext $ctx, array $detail, string $effective): ?array
    {
        $text = trim((string) ($detail['bp'] ?? ''));
        if (!preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', $text, $m)) {
            return null;
        }

        return $ctx->entries->add($this->observations->bloodPressure($ctx, (float) $m[1], (float) $m[2], $effective));
    }
}
