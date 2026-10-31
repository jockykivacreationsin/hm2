<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Inline edit of the discharge date/time on the discharged list (ipd.discharged.update-date).
 * Failures keep the {success: false, message} shape the list's JS reads.
 */
class UpdateIpdDischargeDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discharge_id'        => ['required', 'integer', 'min:1'],
            'discharge_date_time' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                if (strtotime((string) $value) === false) {
                    $fail('Invalid date format');
                }
            }],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
