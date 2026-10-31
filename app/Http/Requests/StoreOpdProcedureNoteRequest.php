<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Procedure note (Summernote HTML) of one OPD procedure service; one note per service, saved as upsert. */
class StoreOpdProcedureNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The service id comes from the URL (/opd/procedure-note/{svcId}); older callers posted it as opd_service_id.
        if ($this->route('svcId') !== null) {
            $this->merge(['opd_service_id' => $this->route('svcId')]);
        }
    }

    public function rules(): array
    {
        return [
            'opd_service_id' => ['required', 'integer', 'min:1'],
            'note'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'opd_service_id.required' => 'Invalid procedure',
            'opd_service_id.integer'  => 'Invalid procedure',
            'opd_service_id.min'      => 'Invalid procedure',
        ];
    }
}
