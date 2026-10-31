<?php

namespace App\Http\Requests;

/** "Shift To IPD" modal on the DCD overview. */
class ShiftDcdToIpdRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'opd_id'              => ['nullable', 'integer', 'exists:opds,id'],
            'patient_id'          => ['nullable', 'integer', 'exists:patients,id'],
            'admission_date_time' => ['nullable', 'string', 'max:30'],
            'tpa_provider_id'     => ['nullable', 'integer', 'exists:tpa_providers,id'],
            'tpa_number'          => ['nullable', 'string', 'max:100'],
            'tpa_credit_limit'    => ['nullable', 'numeric', 'min:0'],
            'bed_id'              => ['nullable', 'integer', 'exists:beds,id'],
        ];
    }
}
