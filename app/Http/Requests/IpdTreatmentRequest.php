<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update IPD treatment (ipd.treatments.store / ipd.treatments.update).
 * Medicine rows are posted as parallel "<column>_value[]" arrays plus "timing_ids_<n>[]" per row
 * (or as a "details" list).
 */
class IpdTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'treatment_date_time'          => ['required', 'string', 'max:50'],
            'doctor_id'                    => ['required', 'integer'],
            'bed_id'                       => ['nullable', 'integer'],
            'note'                         => ['nullable', 'string'],
            'remarks'                      => ['nullable', 'string'],
            'pharma_product_id_value'      => ['nullable', 'array'],
            'dose_quantity_value'          => ['nullable', 'array'],
            'frequency_type_id_value'      => ['nullable', 'array'],
            'meal_relation_id_value'       => ['nullable', 'array'],
            'duration_days_value'          => ['nullable', 'array'],
            'medical_condition_id_value'   => ['nullable', 'array'],
            'details'                      => ['nullable', 'array'],
        ];
    }
}
