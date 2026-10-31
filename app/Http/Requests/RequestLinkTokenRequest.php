<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Request an ABDM link token for a patient (HIP-initiated linking, step 1). */
class RequestLinkTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ];
    }

    /** The Link-to-ABDM modal reads {success, error}, so answer in that shape (legacy: "Invalid patient"). */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['success' => false, 'error' => 'Invalid patient']));
    }
}
