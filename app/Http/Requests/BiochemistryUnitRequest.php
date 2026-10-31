<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update biochemistry unit modal (Master > Biochemistry Units).
 */
class BiochemistryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_name' => ['required', 'string', 'max:255'],
            'ucum_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
