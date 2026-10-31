<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update IPD consultation (ipd.consultations.store / ipd.consultations.update).
 * consultation_date_time arrives as "d-m-Y h:i a" from the flatpickr input.
 */
class IpdConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consultation_date_time' => ['required', 'string', 'max:50'],
            'doctor_id'              => ['required', 'integer'],
            'remarks'                => ['nullable', 'string'],
        ];
    }
}
