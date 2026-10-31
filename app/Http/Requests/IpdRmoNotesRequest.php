<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Save IPD RMO notes (ipd.rmo-notes.save).
 */
class IpdRmoNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rmo_notes' => ['nullable', 'string'],
        ];
    }
}
