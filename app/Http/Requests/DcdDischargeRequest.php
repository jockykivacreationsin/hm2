<?php

namespace App\Http\Requests;

/**
 * Submit-discharge form on dcd/discharge (legacy dcd-discharge.php -> ajax.php create_dcd_discharge).
 *
 * discharge_date_time is a string: the date picker sends "d-m-Y h:i a" (e.g. 20-10-2026 07:59 pm), which the `date`
 * rule does not accept; the controller parses it with Carbon.
 */
class DcdDischargeRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'discharge_date_time'                => ['required', 'string', 'max:50'],
            'condition_on_discharge'             => ['required', 'string', 'max:255'],
            'diagnosis'                          => ['nullable', 'string'],
            'follow_up_detail'                   => ['nullable', 'string'],
            'note'                               => ['nullable', 'string'],
            'urgent_care_condition'              => ['nullable', 'string'],
            'discharged_to'                      => ['nullable', 'string', 'max:255'],
            'approved_doctor_id'                 => ['nullable', 'integer', 'exists:staff_members,id'],
            'dcd_discharge_investigation_name'   => ['nullable', 'array'],
            'dcd_discharge_investigation_name.*' => ['nullable', 'string'],
            'dcd_discharge_investigation_value'  => ['nullable', 'array'],
            'dcd_discharge_investigation_value.*' => ['nullable', 'string'],
            'dcd_discharge_procedure'            => ['nullable', 'array'],
            'dcd_discharge_procedure.*'          => ['nullable', 'string'],
            'medicine'                           => ['nullable', 'array'],
            'medicine.*'                         => ['nullable', 'string'],
            'dose_quantity'                      => ['nullable', 'array'],
            'dose_quantity.*'                    => ['nullable', 'string'],
            'frequency'                          => ['nullable', 'array'],
            'frequency.*'                        => ['nullable', 'string'],
            'timing'                             => ['nullable', 'array'],
            'timing.*'                           => ['nullable', 'string'],
            'meal'                               => ['nullable', 'array'],
            'meal.*'                             => ['nullable', 'string'],
            'duration_days'                      => ['nullable', 'array'],
            'duration_days.*'                    => ['nullable', 'string'],
        ];
    }
}
