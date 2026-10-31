<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / Update pharmacy product category modal (store when the route has no {id}, update otherwise). */
class PharmaProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission(
            $this->route('id') ? 'b_pharma_category_edit' : 'b_pharma_category_new'
        );
    }

    public function rules(): array
    {
        return [
            'category_name' => [
                'required', 'string', 'max:255',
                Rule::unique('pharma_product_categories', 'category_name')->ignore($this->route('id')),
            ],
        ];
    }
}
