<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DeniesWithJson;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Add Bed modal (beds index). */
class StoreBedRequest extends FormRequest
{
    use DeniesWithJson;

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_beds_add');
    }

    public function rules(): array
    {
        return [
            'bed_name'         => ['required', 'string', 'max:100'],
            'floor_id'         => ['required', 'integer', 'exists:floors,id'],
            'floor_area_id'    => ['required', 'integer', 'exists:floor_areas,id'],
            'ward_category_id' => ['required', 'integer', 'exists:ward_categories,id'],
            'bed_type_id'      => ['required', 'integer', 'exists:bed_types,id'],
            'sort_order'       => ['nullable', 'integer'],
        ];
    }
}
