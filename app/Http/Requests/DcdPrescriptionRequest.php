<?php

namespace App\Http\Requests;

/**
 * Add / Edit DCD prescription modals. Medicine rows arrive as parallel arrays (pharma_product_id_value[], ...)
 * plus one timing_ids_<row>[] list per row, or as a nested medicines[] array.
 */
class DcdPrescriptionRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'prescription_date_time'   => ['required'],
            'doctor_id'                => ['required', 'integer', 'exists:staff_members,id'],
            'findings'                 => ['nullable', 'string'],
            'pharma_product_id_value'  => ['nullable', 'array'],
            'dose_quantity_value'      => ['nullable', 'array'],
            'frequency_type_id_value'  => ['nullable', 'array'],
            'meal_relation_id_value'   => ['nullable', 'array'],
            'duration_days_value'      => ['nullable', 'array'],
            'medical_condition_id_value' => ['nullable', 'array'],
            'medicines'                => ['nullable', 'array'],
        ];
    }
}
