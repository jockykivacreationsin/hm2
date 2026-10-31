<?php

namespace App\Http\Requests;

use App\Services\PermissionService;

/** Update Ward Category modal (beds/ward-categories). */
class UpdateWardCategoryRequest extends StoreWardCategoryRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_ward_categories_edit');
    }
}
