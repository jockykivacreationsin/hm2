<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Add / Update Staff modal (legacy ajax.php save_staff_member / update_staff_member).
 * Field names are the legacy form field names. Dates arrive as d-m-Y from the flatpickr inputs.
 */
class StaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id') ?: ($this->filled('staff_member_id') && (int) $this->input('staff_member_id') > 0 ? (int) $this->input('staff_member_id') : null);

        return app(PermissionService::class)->hasPermission($id ? 'b_staff_edit' : 'b_staff_new');
    }

    public function rules(): array
    {
        $id = $this->route('id') ?: ($this->filled('staff_member_id') && (int) $this->input('staff_member_id') > 0 ? (int) $this->input('staff_member_id') : null);

        // Staff log in with e-mail OR mobile number, so those must identify a single active member.
        $unique = fn (string $column) => Rule::unique('staff_members', $column)->where('active', 'Yes')->ignore($id);

        return [
            'joining_date'        => ['nullable', 'date_format:d-m-Y'],
            'staff_name'          => ['required', 'string', 'max:255'],
            'mobile_number'       => ['required', 'digits_between:6,15', $unique('mobile_number')],
            'department_id'       => ['required', 'integer', 'exists:departments,id'],
            'designation_id'      => ['required', 'integer', 'exists:designations,id'],
            'specialities'        => ['nullable', 'array'],
            'specialities.*'      => ['nullable', 'integer', 'exists:specialities,id'],
            'gender'              => ['required', 'in:Male,Female'],
            'dob'                 => ['required', 'date_format:d-m-Y'],
            'email'               => ['nullable', 'email', 'max:255', $unique('email')],
            'residential_address' => ['required', 'string', 'max:255'],
            'permanent_address'   => ['nullable', 'string', 'max:255'],
            'qualification'       => ['nullable', 'string', 'max:255'],
            'experience_years'    => ['required', 'integer', 'min:0', 'max:80'],
            'aadhaar_number'      => ['nullable', 'regex:/^\d{12}$/'],
            'user_role_id'        => ['required', 'integer', 'exists:user_roles,id'],
            // Required when adding; on edit a blank password keeps the current one.
            'password'            => [$id ? 'nullable' : 'required', Password::defaults()],
            'photo'               => ['nullable', 'string', 'max:255', 'regex:/^[\w.\-]+$/'],
            'current_salary'               => ['nullable', 'integer', 'min:0'],
            'medical_registration_number'  => ['nullable', 'string', 'max:50'],
            'registration_council'         => ['nullable', 'string', 'max:150'],
            'hpr_id'                       => ['nullable', 'string', 'max:50'],
            'hpr_address'                  => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'staff_name'                  => 'name',
            'mobile_number'               => 'mobile number',
            'department_id'               => 'department',
            'designation_id'              => 'designation',
            'residential_address'         => 'residential address',
            'permanent_address'           => 'permanent address',
            'experience_years'            => 'experience',
            'aadhaar_number'              => 'Aadhaar number',
            'user_role_id'                => 'role',
            'dob'                         => 'DOB',
            'current_salary'              => 'salary',
            'medical_registration_number' => 'registration number',
            'registration_council'        => 'registration council',
            'hpr_id'                      => 'HPR ID',
            'hpr_address'                 => 'HPR address',
        ];
    }
}
