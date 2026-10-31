<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update IPD vital monitoring sheet (ipd.vital-monitoring.store / ipd.vital-monitoring.update).
 * The reading rows are posted as parallel "<column>_value[]" arrays (or as an "entries" list).
 */
class IpdVitalMonitoringRequest extends FormRequest
{
    private const ROW_FIELDS = [
        'vital_monitoring_time_value', 'bp_value', 'pulse_value', 'temperature_value', 'respiration_value',
        'spo2_value', 'fbs_value', 'rbs_value', 'intake_value', 'output_value',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'vital_monitoring_date' => ['required', 'date'],
            'bed_id'                => ['nullable', 'integer'],
            'remarks'               => ['nullable', 'string'],
            'entries'               => ['nullable', 'array'],
            'entries.*'             => ['array'],
        ];
        foreach (self::ROW_FIELDS as $field) {
            $rules[$field] = ['nullable', 'array'];
        }

        return $rules;
    }
}
