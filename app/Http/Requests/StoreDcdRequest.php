<?php

namespace App\Http\Requests;

/** Create DCD modal (dcd/index): admits an OPD patient to day care. */
class StoreDcdRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'opd_id'                => ['required', 'integer', 'exists:opds,id'],
            'patient_id'            => ['nullable', 'integer', 'exists:patients,id'],
            'consultant_doctor_id'  => ['required', 'integer', 'exists:staff_members,id'],
            'dcd_date_time'         => ['required'],
            'bed_id'                => ['required', 'integer', 'min:1', 'exists:beds,id'],
            'mobile_number'         => ['nullable', 'string', 'max:50'],
            'address'               => ['nullable', 'string', 'max:255'],
            'previous_medical_note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['bed_id.min' => 'Please choose a bed.'];
    }
}
