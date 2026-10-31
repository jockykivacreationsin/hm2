<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for the ABDM panel write endpoints. The panels (app.js / Blade scripts) read
 * `{success:false, error}` from a normal 200 response and show it inline, so a failed
 * validation answers in that shape - with the first message - instead of a 422 payload.
 */
abstract class AbdmJsonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = (string) $validator->errors()->first();

        throw new HttpResponseException(response()->json([
            'success' => false,
            'error'   => $message,
            'message' => $message,
        ]));
    }
}
