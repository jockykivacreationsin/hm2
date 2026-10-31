<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / edit an OPD invoice. The charges page posts parallel `*_value[]` arrays (one entry per charge line,
 * grouped into one invoice per charge department) plus the split-payment rows (sp_mode[] / sp_amount[]).
 */
class SaveOpdChargeRequest extends FormRequest
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
            'charge_department_id_value'  => ['nullable', 'array'],
            'charge_department_id_value.*' => ['integer'],
            'charge_id_value'             => ['nullable', 'array'],
            'charge_id_value.*'           => ['nullable', 'integer'],
            'quantity_value'              => ['nullable', 'array'],
            'quantity_value.*'            => ['nullable', 'numeric'],
            'amount_value'                => ['nullable', 'array'],
            'amount_value.*'              => ['nullable', 'numeric'],
            'discount_percentage_value'   => ['nullable', 'array'],
            'discount_percentage_value.*' => ['nullable', 'numeric'],
            'discount_amount_value'       => ['nullable', 'array'],
            'discount_amount_value.*'     => ['nullable', 'numeric'],
            'total_value'                 => ['nullable', 'array'],
            'total_value.*'               => ['nullable', 'numeric'],
            'charge_id'                   => ['nullable', 'integer'],
            'quantity'                    => ['nullable', 'integer'],
            'amount'                      => ['nullable', 'numeric'],
            'discount_percentage'         => ['nullable', 'numeric'],
            'discount'                    => ['nullable', 'numeric'],
            'total'                       => ['nullable', 'numeric'],
            'opd_charges_amount'          => ['nullable', 'numeric'],
            'opd_charges_discount'        => ['nullable', 'numeric'],
            'opd_charges_total'           => ['nullable', 'numeric'],
            'addon_discount_mode'         => ['nullable', 'string', 'max:50'],
            'addon_discount_value'        => ['nullable', 'numeric'],
            'opd_charges_addon_discount'  => ['nullable', 'numeric'],
            'paid_amount'                 => ['nullable', 'numeric', 'min:0'],
            'payment_mode'                => ['nullable', 'string', 'max:255'],
            'ih'                          => ['nullable', 'string', 'max:10'],
            'sp_mode'                     => ['nullable', 'array'],
            'sp_mode.*'                   => ['nullable', 'string', 'max:50'],
            'sp_amount'                   => ['nullable', 'array'],
            'sp_amount.*'                 => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
