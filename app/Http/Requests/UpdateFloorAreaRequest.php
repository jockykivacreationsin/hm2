<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/** Update Floor Area modal (beds/floor-areas). */
class UpdateFloorAreaRequest extends StoreFloorAreaRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_floor_areas_edit');
    }
}
