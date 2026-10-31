<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Save Changes" on the OPD overview (also used by the DCD overview, which edits the OPD it was created from).
 * Every field is optional: a field that is not posted keeps its stored value.
 */
class UpdateOpdOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultant_doctor_id' => ['nullable', 'integer'],
            'height'       => ['nullable', 'string', 'max:255'],
            'weight'       => ['nullable', 'string', 'max:255'],
            'bp'           => ['nullable', 'string', 'max:255'],
            'bp_systolic'  => ['nullable', 'integer', 'between:0,250'],
            'bp_diastolic' => ['nullable', 'integer', 'between:0,200'],
            'pulse'        => ['nullable', 'string', 'max:255'],
            'temperature'  => ['nullable', 'string', 'max:255'],
            'respiration'  => ['nullable', 'string', 'max:255'],
            'spo2'         => ['nullable', 'string', 'max:255'],
            // Summernote HTML, stored as-is.
            'symptoms'     => ['nullable', 'string'],
            'remarks'      => ['nullable', 'string'],
        ];
    }
}
