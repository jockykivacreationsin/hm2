<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sale Bills "reset invoice number" box: renumbers the visible charges of a date range from a start value.
 * Dates arrive as d-m-Y from the date pickers (Y-m-d is accepted as well).
 */
class ResetInvoiceNumberTwoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date'         => ['required', 'string', 'max:20'],
            'end_date'           => ['required', 'string', 'max:20'],
            'invoice_number_two' => ['required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date'         => 'start date',
            'end_date'           => 'end date',
            'invoice_number_two' => 'start value',
        ];
    }
}
