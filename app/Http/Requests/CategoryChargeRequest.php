<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update Charge modal of a charge category.
 * Store (POST master/charge-categories/{id}/charges): charge name + an amount per patient category.
 * Update (PUT master/charges/{id}): charge name, department and category (amounts are edited inline, see ChargeAmountRequest).
 */
class CategoryChargeRequest extends FormRequest
{
    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission($this->isUpdate() ? 'b_charges_edit' : 'b_charges_new');
    }

    public function rules(): array
    {
        if ($this->isUpdate()) {
            return [
                'charge_name'          => ['required', 'string', 'max:255'],
                'charge_department_id' => ['required', 'integer', 'exists:charge_departments,id'],
                'charge_category_id'   => ['required', 'integer', 'exists:charge_categories,id'],
            ];
        }

        return [
            'charge_name'           => ['required', 'string', 'max:255'],
            'charge_code'           => ['nullable', 'string', 'max:50'],
            'patient_category_id'   => ['nullable', 'array'],
            'patient_category_id.*' => ['integer', 'exists:patient_categories,id'],
            'amount'                => ['nullable', 'array'],
            'amount.*'              => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
