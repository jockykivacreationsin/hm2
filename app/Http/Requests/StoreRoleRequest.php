<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Add Role modal on the roles screen. */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'max:100', 'unique:user_roles,role'],
        ];
    }
}
