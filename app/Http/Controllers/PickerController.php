<?php

namespace App\Http\Controllers;

use App\Models\StaffMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * select2 sources shared by several modules (the `pickers.*` route names are authorised for all of them in
 * config/route_permissions.php). Every action answers `[{id, text}]` for the search term `q`.
 */
class PickerController extends Controller
{
    /** Active staff members (consultants / doctors / nurses). */
    public function doctors(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $rows = StaffMember::where('active', 'Yes')
            ->when($q !== '', fn ($query) => $query->where('staff_name', 'like', "%{$q}%"))
            ->orderBy('staff_name')
            ->get(['id', 'staff_name']);

        return response()->json($rows->map(fn ($r) => ['id' => $r->id, 'text' => $r->staff_name]));
    }

    /** Calendar months (report period filters). */
    public function months(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $rows = DB::table('months')
            ->when($q !== '', fn ($query) => $query->where('month_name', 'like', "%{$q}%"))
            ->orderBy('id')
            ->get();

        return response()->json($rows->map(fn ($r) => ['id' => $r->id, 'text' => $r->month_name]));
    }
}
