<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Role permission checkboxes (`permissions[]`). Unknown keys are dropped by the controller;
 * an empty/absent list revokes every permission of the role.
 */
class SaveRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'string', 'max:100'],
        ];
    }
}
