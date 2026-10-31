<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Manual health-information push retry for an OPD / IPD / DCD encounter and a known consent. */
class RetryHealthInfoPushRequest extends AbdmJsonRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['consent_id' => trim((string) $this->input('consent_id', ''))]);
    }

    public function rules(): array
    {
        return [
            'opd_id'     => ['required_without_all:ipd_id,dcd_id'],
            'consent_id' => ['required', 'string', Rule::exists('abdm_consents', 'consent_id')],
        ];
    }

    public function messages(): array
    {
        return [
            'opd_id.required_without_all' => 'Provide opd_id, ipd_id, or dcd_id.',
            'consent_id.required'         => 'Missing consent_id',
            'consent_id.exists'           => 'Consent not found',
        ];
    }
}
