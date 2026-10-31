<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update biochemistry sub test modal (Master > Biochemistry Tests > Sub Tests).
 * The unit arrives as `bunit_id` from the select2 picker; `unit_id` is accepted as an alias.
 */
class BiochemistrySubTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_test_name'    => ['required', 'string', 'max:255'],
            'reference_range'  => ['nullable', 'string', 'max:255'],
            'unit_id'          => ['nullable', 'integer', 'exists:bunits,id'],
            'bunit_id'         => ['nullable', 'integer', 'exists:bunits,id'],
            'main_category_id' => ['nullable', 'integer'],
            'charge_id'        => ['nullable', 'integer'],
        ];
    }
}
