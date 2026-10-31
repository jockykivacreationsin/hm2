<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/** Update Bed modal (beds index). */
class UpdateBedRequest extends StoreBedRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_beds_edit');
    }
}
