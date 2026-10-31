<?php

namespace App\Http\Requests;

/** DCD patient file form (history, examination and provisional diagnosis). */
class DcdPatientFileRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'chief_complaints'             => ['nullable', 'string'],
            'past_medical_history'         => ['nullable', 'string'],
            'mlc'                          => ['nullable', 'string', 'max:10'],
            'mlc_number'                   => ['nullable', 'string', 'max:50'],
            'referred_from_other_hospital' => ['nullable', 'string', 'max:10'],
            'referred_from_hospital_name'  => ['nullable', 'string', 'max:255'],
            'mode'                         => ['nullable', 'string', 'max:50'],
            'history_given_by'             => ['nullable', 'string', 'max:255'],
            'identification_marks'         => ['nullable', 'string', 'max:255'],
            'emergency_arrival_time'       => ['nullable', 'string', 'max:20'],
            'allergies'                    => ['nullable', 'string', 'max:255'],
            'gcs'                          => ['nullable', 'string', 'max:50'],
            'pallor'                       => ['nullable', 'string', 'max:50'],
            'pain_score'                   => ['nullable', 'string', 'max:50'],
            'cns'                          => ['nullable', 'string', 'max:100'],
            'rs'                           => ['nullable', 'string', 'max:100'],
            'cvs'                          => ['nullable', 'string', 'max:100'],
            'per_abdomen'                  => ['nullable', 'string', 'max:100'],
            'female_menstural_history'     => ['nullable', 'string', 'max:255'],
            'provisional_diagnosis'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
