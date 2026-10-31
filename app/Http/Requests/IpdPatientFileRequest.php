<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save IPD patient file / admission examination (ipd.patient-file.save).
 */
class IpdPatientFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relative_name'                     => ['nullable', 'string', 'max:255'],
            'relationship'                      => ['nullable', 'string', 'max:255'],
            'witness_name'                      => ['nullable', 'string', 'max:255'],
            'admission_type'                    => ['nullable', 'string', 'max:255'],
            'patient_condition'                 => ['nullable', 'string', 'max:255'],
            'general_condition'                 => ['nullable', 'string', 'max:255'],
            'source_of_history'                 => ['nullable', 'string', 'max:255'],
            'chief_complaints'                  => ['nullable', 'string'],
            'history_of_present_illness'        => ['nullable', 'string'],
            'past_medical_history'              => ['nullable', 'string'],
            'pain_score'                        => ['nullable', 'string', 'max:255'],
            'pain_location'                     => ['nullable', 'string', 'max:255'],
            'intervention'                      => ['nullable', 'string'],
            'family_ho'                         => ['nullable', 'string'],
            'current_medication'                => ['nullable', 'string'],
            'allergies'                         => ['nullable', 'string'],
            'ho_blood_transfusion'              => ['nullable', 'string'],
            'substance_abuse'                   => ['nullable'],
            'nutritional_status'                => ['nullable', 'string', 'max:255'],
            'general_examination'               => ['nullable'],
            'systemic_examination'              => ['nullable', 'string'],
            'cns_system_mental_status'          => ['nullable', 'string', 'max:255'],
            'cns_system_sensory_examination'    => ['nullable', 'string', 'max:255'],
            'cns_system_motor_examination_tone' => ['nullable', 'string', 'max:255'],
            'cns_system_reflexes'               => ['nullable', 'string', 'max:255'],
            'cns_system_power'                  => ['nullable', 'string', 'max:255'],
            'provisional_diagnosis'             => ['nullable', 'string'],
            'diet_plan'                         => ['nullable', 'string', 'max:255'],
        ];
    }
}
