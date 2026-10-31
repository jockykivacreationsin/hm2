<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/** Copy Bed modal (beds index): same fields as a new bed, guarded by the copy button permission. */
class CopyBedRequest extends StoreBedRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_beds_copy');
    }
}
