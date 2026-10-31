<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DeniesWithJson;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Add Ward Category modal (beds/ward-categories). */
class StoreWardCategoryRequest extends FormRequest
{
    use DeniesWithJson;

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_ward_categories_add');
    }

    public function rules(): array
    {
        return [
            'ward_category' => ['required', 'string', 'max:255'],
        ];
    }
}
