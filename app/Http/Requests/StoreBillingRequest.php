<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DeniesWithJson;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add Bill modal on the Billing page (stand-alone bill with its own line items and split payments).
 * Field names are the form's. Dates arrive as "d-m-Y h:i a" from the datetime picker.
 */
class StoreBillingRequest extends FormRequest
{
    use DeniesWithJson;

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_billing_new');
    }

    public function rules(): array
    {
        return [
            'billing_date_time'           => ['nullable', 'string', 'max:50'],
            'charge_department_id'        => ['required', 'integer', 'exists:charge_departments,id'],
            'patient_name'                => ['required', 'string', 'max:255'],
            'bill_title'                  => ['required', 'string', 'max:255'],
            'bill_charges_amount'         => ['nullable', 'numeric', 'min:0'],
            'bill_charges_discount'       => ['nullable', 'numeric', 'min:0'],
            'addon_discount_mode'         => ['nullable', 'in:Percentage,Amount'],
            'addon_discount_value'        => ['nullable', 'numeric', 'min:0'],
            'bill_charges_addon_discount' => ['nullable', 'numeric', 'min:0'],
            'bill_charges_total'          => ['nullable', 'numeric', 'min:0'],
            'paid_amount'                 => ['nullable', 'numeric', 'min:0'],
            'payment_mode'                => ['nullable', 'string', 'max:50'],
            'ih'                          => ['nullable'],
            'charge_id_value'             => ['required', 'array', 'min:1'],
            'charge_id_value.*'           => ['integer'],
            'quantity_value'              => ['nullable', 'array'],
            'quantity_value.*'            => ['nullable', 'numeric', 'min:0'],
            'amount_value'                => ['nullable', 'array'],
            'amount_value.*'              => ['nullable', 'numeric', 'min:0'],
            'discount_percentage_value'   => ['nullable', 'array'],
            'discount_percentage_value.*' => ['nullable', 'numeric', 'min:0'],
            'discount_amount_value'       => ['nullable', 'array'],
            'discount_amount_value.*'     => ['nullable', 'numeric', 'min:0'],
            'total_value'                 => ['nullable', 'array'],
            'total_value.*'               => ['nullable', 'numeric', 'min:0'],
            'sp_mode'                     => ['nullable', 'array'],
            'sp_mode.*'                   => ['required', 'string', 'max:50'],
            'sp_amount'                   => ['nullable', 'array'],
            'sp_amount.*'                 => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'charge_department_id' => 'department',
            'patient_name'         => 'patient name',
            'bill_title'           => 'bill title',
            'charge_id_value'      => 'bill charges',
        ];
    }
}
