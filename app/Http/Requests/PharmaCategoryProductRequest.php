<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update product modal of a pharmacy category.
 * Store (POST master/pharmacy-categories/{id}/products): the category comes from the URL.
 * Update (PUT master/pharmacy-category-products/{id}): the product can be moved to another category.
 */
class PharmaCategoryProductRequest extends FormRequest
{
    private function isUpdate(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission(
            $this->isUpdate() ? 'b_pharma_category_product_edit' : 'b_pharma_category_product_new'
        );
    }

    protected function prepareForValidation(): void
    {
        // The edit modal names its category select `pharma_product_category_id`.
        if (!$this->has('category_id') && $this->has('pharma_product_category_id')) {
            $this->merge(['category_id' => $this->input('pharma_product_category_id')]);
        }
    }

    public function rules(): array
    {
        $rules = ['product_name' => ['required', 'string', 'max:255']];

        if ($this->isUpdate()) {
            $rules['category_id'] = ['required', 'integer', 'exists:pharma_product_categories,id'];
        }

        return $rules;
    }
}
