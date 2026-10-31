<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DeniesWithJson;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/** Add Floor Area modal (beds/floor-areas). The area name may arrive as `area` or `floor_area`. */
class StoreFloorAreaRequest extends FormRequest
{
    use DeniesWithJson;

    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_floor_areas_add');
    }

    protected function prepareForValidation(): void
    {
        if (!filled($this->input('area')) && filled($this->input('floor_area'))) {
            $this->merge(['area' => $this->input('floor_area')]);
        }
    }

    public function rules(): array
    {
        return [
            'floor_id' => ['required', 'integer', 'exists:floors,id'],
            'area'     => ['required', 'string', 'max:100'],
        ];
    }
}
