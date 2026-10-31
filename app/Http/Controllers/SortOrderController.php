<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSortOrderRequest;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Drag-and-drop row ordering of the master tables that carry a `sort_order` column. */
class SortOrderController extends Controller
{
    /** Sortable table => module permission needed to reorder it. Anything else is rejected. */
    public const SORTABLE_TABLES = [
        'charge_departments' => 'm_charges',
        'floors'             => 'm_beds',
        'beds'               => 'm_beds',
        'ward_categories'    => 'm_beds',
        'bed_types'          => 'm_beds',
    ];

    public function update(UpdateSortOrderRequest $request, string $table, PermissionService $ps): JsonResponse
    {
        abort_unless(isset(self::SORTABLE_TABLES[$table]), 404);
        abort_unless($ps->hasPermission(self::SORTABLE_TABLES[$table]), 403);

        DB::table($table)->where('id', $request->integer('tr_id'))->update(['sort_order' => $request->integer('i')]);

        return response()->json(['success' => true, 'message' => 'Order updated']);
    }
}
