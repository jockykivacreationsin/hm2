<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/** Update Floor modal (beds/floors). */
class UpdateFloorRequest extends StoreFloorRequest
{
    public function authorize(): bool
    {
        $ps = app(PermissionService::class);

        return $ps->hasPermission('b_floors_edit') || $ps->hasPermission('m_beds');
    }
}
