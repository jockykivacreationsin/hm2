<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update "Patient Medicines" issue modal (Periodic Medicine > Issue List).
 * `disease_ids` arrives as an array from the multi-select; the medicine rows come as parallel `*_value[]` arrays.
 */
class PatientPeriodicMedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'                    => ['required', 'integer', 'min:1'],
            'patient_periodic_medicine_id'  => ['nullable', 'integer', 'min:1'],
            'id'                            => ['nullable', 'integer', 'min:1'],
            'medicine_date_time'            => ['nullable', 'string', 'max:50'],
            'days'                          => ['nullable', 'integer', 'min:0'],
            'delivery'                      => ['nullable', 'string', 'max:255'],
            'remarks'                       => ['nullable', 'string'],
            'disease_ids'                   => ['nullable'],
            'disease_ids.*'                 => ['nullable', 'integer'],
            'medicine_name_value'           => ['nullable', 'array'],
            'medicine_name_value.*'         => ['nullable', 'string', 'max:255'],
            'dose_value'                    => ['nullable', 'array'],
            'dose_value.*'                  => ['nullable', 'string', 'max:255'],
            'dose_interval_value'           => ['nullable', 'array'],
            'dose_interval_value.*'         => ['nullable', 'string', 'max:255'],
            'remarks_value'                 => ['nullable', 'array'],
            'remarks_value.*'               => ['nullable', 'string'],
        ];
    }
}
