<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Position of one table row after a drag-sort: `tr_id` = row id, `i` = its new 1-based position. */
class UpdateSortOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // module permission is checked per table in SortOrderController
    }

    public function rules(): array
    {
        return [
            'tr_id' => ['required', 'integer', 'min:1'],
            'i'     => ['required', 'integer', 'min:0'],
        ];
    }
}
