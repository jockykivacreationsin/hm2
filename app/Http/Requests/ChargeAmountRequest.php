<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Inline amount edit of one patient category on the category's charge list. */
class ChargeAmountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_charges_edit');
    }

    public function rules(): array
    {
        return [
            'patient_category_id' => ['required', 'integer', 'exists:patient_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0'],
        ];
    }
}
