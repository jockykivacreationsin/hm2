<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update IPD charge (ipd.charges.store / ipd.charges.update).
 * Charge rows are posted as parallel "<column>_value[]" arrays; split payments as sp_mode[] / sp_amount[].
 */
class IpdChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'charge_date_time'            => ['nullable', 'string', 'max:50'],
            'charge_title'                => ['nullable', 'string', 'max:255'],
            'charge_department_id'        => ['nullable', 'integer'],
            'charge_id'                   => ['nullable', 'integer'],
            'quantity'                    => ['nullable', 'numeric'],
            'amount'                      => ['nullable', 'numeric'],
            'discount_percentage'         => ['nullable', 'numeric'],
            'charge_department_id_value'  => ['nullable', 'array'],
            'charge_id_value'             => ['nullable', 'array'],
            'quantity_value'              => ['nullable', 'array'],
            'amount_value'                => ['nullable', 'array'],
            'discount_percentage_value'   => ['nullable', 'array'],
            'discount_amount_value'       => ['nullable', 'array'],
            'total_value'                 => ['nullable', 'array'],
            'addon_discount_mode'         => ['nullable', 'string', 'max:20'],
            'addon_discount_value'        => ['nullable', 'numeric'],
            'ipd_charges_amount'          => ['nullable', 'numeric'],
            'ipd_charges_discount'        => ['nullable', 'numeric'],
            'ipd_charges_addon_discount'  => ['nullable', 'numeric'],
            'ipd_charges_total'           => ['nullable', 'numeric'],
            'paid_amount'                 => ['nullable', 'numeric', 'min:0'],
            'payment_mode'                => ['nullable', 'string', 'max:50'],
            'ih'                          => ['nullable', 'string', 'max:3'],
            'sp_mode'                     => ['nullable', 'array'],
            'sp_amount'                   => ['nullable', 'array'],
        ];
    }
}
