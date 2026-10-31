<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DeniesWithJson;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Add Floor modal (beds/floors). */
class StoreFloorRequest extends FormRequest
{
    use DeniesWithJson;

    public function authorize(): bool
    {
        $ps = app(PermissionService::class);

        return $ps->hasPermission('b_floors_add') || $ps->hasPermission('m_beds');
    }

    public function rules(): array
    {
        return [
            'floor'      => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
