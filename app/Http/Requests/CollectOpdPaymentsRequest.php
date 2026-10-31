<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** "Collect Payments" on the OPD charges page: one split-payment list spread over the ticked invoices (cb). */
class CollectOpdPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date_time' => ['nullable', 'string', 'max:50'],
            'cb'                => ['required', 'array', 'min:1'],
            'cb.*'              => ['integer'],
            'sp_mode'           => ['required', 'array', 'min:1'],
            'sp_mode.*'         => ['required', 'string', 'max:50'],
            'sp_amount'         => ['required', 'array'],
            'sp_amount.*'       => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
