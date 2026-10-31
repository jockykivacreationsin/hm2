<?php

namespace App\Http\Requests;

/**
 * Discharge approval form (dcd/view-discharge-approval). Every field is optional: whatever the approver
 * edits overrides the submitted discharge, and the treatment-advised rows arrive as parallel arrays
 * plus one timing_ids_<row>[] list per row.
 */
class ApproveDcdDischargeRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'discharge_id'               => ['nullable', 'integer'],
            'discharge_date_time'        => ['nullable', 'string', 'max:30'],
            'diagnosis'                  => ['nullable', 'string'],
            'condition_on_discharge'     => ['nullable', 'string'],
            'follow_up_detail'           => ['nullable', 'string'],
            'urgent_care_condition'      => ['nullable', 'string'],
            'note'                       => ['nullable', 'string'],
            'discharged_to'              => ['nullable', 'string', 'max:255'],
            'approved_doctor_id'         => ['nullable', 'integer', 'exists:staff_members,id'],

            'pharma_product_id_value'    => ['nullable', 'array'],
            'dose_quantity_value'        => ['nullable', 'array'],
            'frequency_type_id_value'    => ['nullable', 'array'],
            'meal_relation_id_value'     => ['nullable', 'array'],
            'duration_days_value'        => ['nullable', 'array'],
            'medical_condition_id_value' => ['nullable', 'array'],
        ];
    }
}
