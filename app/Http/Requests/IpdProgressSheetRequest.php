<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save IPD progress sheet (ipd.progress-sheet.save).
 */
class IpdProgressSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'progress_sheet' => ['nullable', 'string'],
        ];
    }
}
