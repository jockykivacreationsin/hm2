<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Add / Update Charge Category modal (store when the route has no {id}, update otherwise). */
class ChargeCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission(
            $this->route('id') ? 'b_charges_categories_edit' : 'b_charges_categories_new'
        );
    }

    public function rules(): array
    {
        return [
            'charge_category'      => ['required', 'string', 'max:255'],
            'charge_department_id' => ['required', 'integer', 'exists:charge_departments,id'],
        ];
    }
}
