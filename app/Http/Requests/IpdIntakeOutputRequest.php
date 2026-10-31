<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update IPD intake-output sheet (ipd.intake-output.store / ipd.intake-output.update).
 * The reading rows are posted as parallel "<column>_value[]" arrays (or as an "entries" list).
 */
class IpdIntakeOutputRequest extends FormRequest
{
    private const ROW_FIELDS = [
        'intake_output_time_value', 'iv_fluids_value', 'oral_value', 'urine_value', 'stool_value',
        'vomiting_value', 'rt_asp_value', 'drain_value',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'intake_output_date' => ['required', 'date'],
            'bed_id'             => ['nullable', 'integer'],
            'remarks'            => ['nullable', 'string'],
            'entries'            => ['nullable', 'array'],
            'entries.*'          => ['array'],
        ];
        foreach (self::ROW_FIELDS as $field) {
            $rules[$field] = ['nullable', 'array'];
        }

        return $rules;
    }
}
