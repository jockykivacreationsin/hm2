<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Add / edit an OPD prescription: header, the ordered tests/procedures (charge ids) and its medicine rows. */
class SaveOpdPrescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prescription_date_time' => ['required'],
            'doctor_id'              => ['required', 'integer', 'exists:staff_members,id'],
            'findings'               => ['nullable', 'string'],
            'follow_up_detail'       => ['nullable', 'string'],
            'pathology_tests'        => ['nullable'],
            'radiology_tests'        => ['nullable'],
            'procedures'             => ['nullable'],
            'pharma_product_id_value'   => ['nullable', 'array'],
            'dose_quantity_value'       => ['nullable', 'array'],
            'frequency_type_id_value'   => ['nullable', 'array'],
            'meal_relation_id_value'    => ['nullable', 'array'],
            'duration_days_value'       => ['nullable', 'array'],
            'medical_condition_id_value' => ['nullable', 'array'],
            'medicines'              => ['nullable', 'array'],
        ];
    }
}
