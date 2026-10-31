<?php

namespace App\Http\Requests;

/** Consent Requests page: new consent request for a patient picked by ABHA address. */
class StoreHiuConsentRequest extends HiuConsentRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['abha_address' => trim((string) $this->input('abha_address', ''))]);
    }

    public function rules(): array
    {
        return ['abha_address' => ['required', 'string', 'max:255']] + parent::rules();
    }

    public function messages(): array
    {
        return ['abha_address.required' => "Patient's ABHA Address is required"] + parent::messages();
    }
}
