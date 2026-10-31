<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/**
 * Edit Bill modal. Same fields as the Add Bill modal, but every field is optional: whatever the form
 * does not send keeps the value already stored (the controller falls back to the existing bill).
 */
class UpdateBillingRequest extends StoreBillingRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_billing_edit');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        $rules['charge_department_id'] = ['sometimes', 'integer', 'exists:charge_departments,id'];
        $rules['patient_name']         = ['sometimes', 'string', 'max:255'];
        $rules['bill_title']           = ['sometimes', 'string', 'max:255'];
        $rules['charge_id_value']      = ['sometimes', 'array', 'min:1'];

        return $rules;
    }
}
