<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve an IPD discharge (ipd.discharge.approve). The route id is the IPD admission id.
 * Treatment-advised rows are posted as parallel "<column>_value[]" arrays plus "timing_ids_<n>[]".
 */
class ApproveIpdDischargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_doctor_id'         => ['nullable', 'integer'],
            'diagnosis'                  => ['nullable', 'string'],
            'condition_on_discharge'     => ['nullable', 'string', 'max:255'],
            'discharge_date_time'        => ['nullable', 'string', 'max:50'],
            'follow_up_detail'           => ['nullable', 'string'],
            'urgent_care_condition'      => ['nullable', 'string'],
            'note'                       => ['nullable', 'string'],
            'pharma_product_id_value'    => ['nullable', 'array'],
            'dose_quantity_value'        => ['nullable', 'array'],
            'frequency_type_id_value'    => ['nullable', 'array'],
            'meal_relation_id_value'     => ['nullable', 'array'],
            'duration_days_value'        => ['nullable', 'array'],
            'medical_condition_id_value' => ['nullable', 'array'],
        ];
    }
}
