<?php

namespace App\Http\Requests;

/**
 * Add / Edit DCD charge modals. Line items arrive as parallel arrays (charge_id_value[], amount_value[], ...)
 * and split payments as sp_mode[] / sp_amount[]. A single-line payload (charge_department_id + amount) is also accepted.
 */
class DcdChargeRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'charge_date_time'            => ['nullable', 'string', 'max:30'],
            'charge_title'                => ['nullable', 'string', 'max:255'],
            'charge_department_id'        => ['nullable', 'integer'],
            'charge_id'                   => ['nullable', 'integer'],
            'amount'                      => ['nullable', 'numeric'],
            'quantity'                    => ['nullable', 'numeric'],
            'discount_percentage'         => ['nullable', 'numeric'],

            'charge_department_id_value'   => ['nullable', 'array'],
            'charge_department_id_value.*' => ['nullable', 'integer'],
            'charge_id_value'              => ['nullable', 'array'],
            'charge_id_value.*'            => ['nullable', 'integer'],
            'quantity_value'               => ['nullable', 'array'],
            'quantity_value.*'             => ['nullable', 'numeric'],
            'amount_value'                 => ['nullable', 'array'],
            'amount_value.*'               => ['nullable', 'numeric'],
            'discount_percentage_value'    => ['nullable', 'array'],
            'discount_percentage_value.*'  => ['nullable', 'numeric'],
            'discount_amount_value'        => ['nullable', 'array'],
            'discount_amount_value.*'      => ['nullable', 'numeric'],
            'total_value'                  => ['nullable', 'array'],
            'total_value.*'                => ['nullable', 'numeric'],

            'addon_discount_mode'         => ['nullable', 'string', 'max:20'],
            'addon_discount_value'        => ['nullable', 'numeric'],
            'addon_discount_amount'       => ['nullable', 'numeric'],
            'dcd_charges_amount'          => ['nullable', 'numeric'],
            'dcd_charges_discount'        => ['nullable', 'numeric'],
            'dcd_charges_addon_discount'  => ['nullable', 'numeric'],
            'dcd_charges_total'           => ['nullable', 'numeric'],
            'paid_amount'                 => ['nullable', 'numeric'],
            'payment_mode'                => ['nullable', 'string', 'max:100'],
            'ih'                          => ['nullable', 'string', 'max:10'],

            'sp_mode'                     => ['nullable', 'array'],
            'sp_mode.*'                   => ['nullable', 'string', 'max:100'],
            'sp_amount'                   => ['nullable', 'array'],
            'sp_amount.*'                 => ['nullable', 'numeric'],
        ];
    }
}
