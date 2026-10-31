<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by the Add and Edit Patient modals (patients page and the OPD Add Patient modal).
 * ABHA number / address carry UNIQUE keys, so a clash must be a validation error rather than a 500.
 */
abstract class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The patient being edited (null on add); excluded from the ABHA uniqueness checks. */
    protected function ignoredPatientId(): ?int
    {
        return null;
    }

    /**
     * Clean-up of the optional fields that were actually submitted (a field the form did not send is left
     * out, so an update never wipes a value it did not mention).
     * - abha_number / abha_address are UNIQUE: blank must be NULL, never '', or the second patient without
     *   an ABHA would collide with the first.
     * - email is trimmed so a pasted " name@host " validates instead of being rejected for its stray spaces.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['abha_number', 'abha_address'] as $column) {
            if ($this->exists($column)) {
                $value = $this->input($column);
                $value = is_string($value) ? trim($value) : $value;
                $clean[$column] = ($value === '' || $value === null) ? null : $value;
            }
        }
        if ($this->exists('email')) {
            $email = $this->input('email');
            $clean['email'] = is_string($email) ? trim($email) : $email;
        }

        $this->merge($clean);
    }

    public function rules(): array
    {
        $unique = fn (string $column) => Rule::unique('patients', $column)->ignore($this->ignoredPatientId());

        $dobRule = function (string $attribute, $value, \Closure $fail) {
            $date = self::parseDob((string) $value);
            if (!$date) {
                $fail('Date of birth must be a valid date (DD-MM-YYYY).');
            } elseif ($date->isFuture()) {
                $fail('Date of birth cannot be in the future.');
            }
        };

        return [
            'patient_name'  => 'required|string|max:255',
            'gender'        => 'required|in:Male,Female,Other',
            'mobile_number' => 'nullable|string|max:15',
            'email'         => 'nullable|email|max:255',
            'dob'           => ['nullable', $dobRule],
            // ISO date captured by the ABDM identity verification (the OPD Add Patient modal posts it).
            'date_of_birth' => ['nullable', $dobRule],
            'blood_group'   => 'nullable|string|max:10',
            'address'       => 'nullable|string',
            'abha_number'   => ['nullable', 'string', 'max:50', $unique('abha_number')],
            'abha_address'  => ['nullable', 'string', 'max:255', $unique('abha_address')],
            // Optional extras of the OPD Add Patient modal.
            'guardian_name'           => 'nullable|string|max:255',
            'age'                     => 'nullable|integer|min:0|max:150',
            'alternate_mobile_number' => 'nullable|string|max:50',
            'esm_name'                => 'nullable|string|max:255',
            'card_number'             => 'nullable|string|max:100',
            'service_number'          => 'nullable|string|max:255',
            'claim_id'                => 'nullable|string|max:255',
            'aadhaar_number'          => 'nullable|string|max:50',
            // A direct upload is checked here; a bare filename of an already-saved photo is resolved by the controller.
            'photo'                   => $this->hasFile('photo') ? ['image', 'mimes:jpg,jpeg,png,gif', 'max:4096'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'abha_number.unique'  => 'Another patient already has this ABHA number.',
            'abha_address.unique' => 'Another patient already has this ABHA address.',
        ];
    }

    /** The date pickers submit DD-MM-YYYY; ISO YYYY-MM-DD is also accepted. Never guess the order. */
    public static function parseDob(string $value): ?Carbon
    {
        $value = trim($value);
        foreach (['d-m-Y', 'Y-m-d'] as $format) {
            if (!Carbon::hasFormat($value, $format)) {
                continue;
            }
            $date = Carbon::createFromFormat('!' . $format, $value);
            // createFromFormat rolls 31-02-2000 over to 02-03-2000: only accept a date that round-trips.
            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}
