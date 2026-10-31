<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Submit an IPD discharge for approval (ipd.discharge.store).
 */
class IpdDischargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharge_date_time'                => ['required', 'string', 'max:50'],
            'condition_on_discharge'             => ['required', 'string', 'max:255'],
            'diagnosis'                          => ['nullable', 'string'],
            'follow_up_detail'                   => ['nullable', 'string'],
            'note'                               => ['nullable', 'string'],
            'urgent_care_condition'              => ['nullable', 'string'],
            'approved_doctor_id'                 => ['nullable', 'integer'],
            'ipd_discharge_investigation_name'   => ['nullable', 'array'],
            'ipd_discharge_investigation_value'  => ['nullable', 'array'],
            'ipd_discharge_procedure'            => ['nullable', 'array'],
            'medicine'                           => ['nullable', 'array'],
            'dose_quantity'                      => ['nullable', 'array'],
            'frequency'                          => ['nullable', 'array'],
            'timing'                             => ['nullable', 'array'],
            'meal'                               => ['nullable', 'array'],
            'duration_days'                      => ['nullable', 'array'],
        ];
    }
}
