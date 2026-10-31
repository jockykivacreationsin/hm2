<?php

namespace App\Http\Requests;

/** "Collect" modal on the DCD charges page: pays the ticked invoices (cb[]) from the split payments. */
class DcdCollectPaymentsRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'payment_date_time' => ['nullable', 'string', 'max:30'],
            'cb'                => ['nullable', 'array'],
            'cb.*'              => ['integer'],
            'sp_mode'           => ['nullable', 'array'],
            'sp_mode.*'         => ['nullable', 'string', 'max:100'],
            'sp_amount'         => ['nullable', 'array'],
            'sp_amount.*'       => ['nullable', 'numeric'],
        ];
    }
}
