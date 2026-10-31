<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** "Add Doctor" on the OPD overview: attaches an extra consultant to the visit. */
class AddOpdDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The visit id comes from the URL (/opd/{id}/doctors); older callers posted it as opd_id.
        if ($this->route('id') !== null) {
            $this->merge(['opd_id' => $this->route('id')]);
        }
    }

    public function rules(): array
    {
        return [
            'opd_id'    => ['required', 'integer', 'min:1', 'exists:opds,id'],
            'doctor_id' => ['required', 'integer', 'min:1', 'exists:staff_members,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'opd_id.required'    => 'Invalid doctor or OPD ID',
            'doctor_id.required' => 'Invalid doctor or OPD ID',
        ];
    }
}
