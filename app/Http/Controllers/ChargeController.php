<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Models\Charge;
use App\Models\ChargeDepartment;
use App\Models\ChargeCategory;
use App\Models\PatientCategory;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

class ChargeController extends Controller
{
    public function index(PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('m_charges'), 403);

        $departments = ChargeDepartment::orderBy('sort_order')->get();
        $categories  = ChargeCategory::all();
        $charges     = Charge::with(['chargeDepartment', 'chargeCategory'])->orderBy('charge_name')->get();
        $patientCategories = PatientCategory::orderBy('id')->get();
        $loggedInPatientCategoryId = (int) session('patient_category_id', session('logged_in_patient_category_id', 1));

        return view('charges.index', compact('departments', 'categories', 'charges', 'patientCategories', 'loggedInPatientCategoryId'));
    }

    public function data(Request $request): JsonResponse
    {
        $deptId      = $request->input('filter_department') ?? $request->input('department_id', '');
        $catId       = $request->input('filter_charge_category') ?? $request->input('category_id', '');
        $search      = $request->input('search.value', '');
        $catIdFilter = (int) session('patient_category_id', session('logged_in_patient_category_id', 1));

        $query = Charge::with(['chargeDepartment', 'chargeCategory'])
            ->when($deptId, fn($q) => $q->where('charge_department_id', $deptId))
            ->when($catId, fn($q) => $q->where('charge_category_id', $catId))
            ->when($search, fn($q) => $q->where(function($sq) use ($search) {
                $sq->where('charge_name', 'LIKE', "%{$search}%")
                   ->orWhere('charge_code', 'LIKE', "%{$search}%");
            }));

        $total   = $query->count();
        $charges = $query->orderBy('charge_name')
            ->skip($request->input('start', 0))
            ->take($request->input('length', 100))
            ->get();

        // Get prices for the current patient category
        $chargeIds = $charges->pluck('id');
        $prices = DB::table('patient_categories_charges')
            ->whereIn('charge_id', $chargeIds)
            ->where('patient_category_id', $catIdFilter)
            ->pluck('amount', 'charge_id');

        $data = $charges->map(function($c) use ($prices) {
            $amt = $prices[$c->id] ?? 0;
            return [
                'action'            => '<a href="javascript:void(0)" onclick="editChargeModal(' . $c->id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a> <a href="javascript:void(0)" onclick="deleteCharge(' . $c->id . ')" class="text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>',
                'id'                => $c->id,
                'charge_code'       => '<input type="text" class="form-control form-control-sm" value="' . e($c->charge_code) . '" onchange="updateChargeCode(' . $c->id . ', this.value)">',
                'charge_name'       => e($c->charge_name),
                'department_name'   => e($c->chargeDepartment?->department_name ?? ''),
                'charge_category'   => e($c->chargeCategory?->charge_category ?? ''),
                'amount'            => number_format((float) $amt, 2),
            ];
        });

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $charge = Charge::with(['chargeDepartment', 'chargeCategory'])->findOrFail($id);
        $patientCategoryId = (int) $request->input('patient_category_id', session('patient_category_id', 1));

        $price = DB::table('patient_categories_charges')
            ->where('charge_id', $id)
            ->where('patient_category_id', $patientCategoryId)
            ->value('amount') ?? 0;

        $allPrices = DB::table('patient_categories_charges')
            ->where('charge_id', $id)
            ->pluck('amount', 'patient_category_id');

        return response()->json([
            'id'                   => $charge->id,
            'charge_code'          => $charge->charge_code,
            'charge_name'          => $charge->charge_name,
            'charge_department_id' => $charge->charge_department_id,
            'department_name'      => $charge->chargeDepartment?->department_name ?? '',
            'charge_category_id'   => $charge->charge_category_id,
            'charge_category'      => $charge->chargeCategory?->charge_category ?? '',
            'amount'               => (float) $price,
            'amounts'              => $allPrices,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'charge_code'          => 'nullable|string|max:50',
            'charge_name'          => 'required|string|max:255',
            'charge_department_id' => 'required|integer|exists:charge_departments,id',
            'charge_category_id'   => 'nullable|integer',
        ]);

        $validated['charge_code'] = $validated['charge_code'] ?? '';
        $validated['charge_category_id'] = $validated['charge_category_id'] ?? 0;

        DB::beginTransaction();
        try {
            $charge = Charge::create($validated);

            // Handle patient category amounts
            $catIds = $request->input('patient_category_id', []);
            $amounts = $request->input('amount', []);
            if (!empty($catIds) && is_array($catIds)) {
                for ($i = 0; $i < count($catIds); $i++) {
                    DB::table('patient_categories_charges')->insert([
                        'patient_category_id' => $catIds[$i],
                        'charge_id'           => $charge->id,
                        'amount'              => (float) ($amounts[$i] ?? 0),
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Charge added.', 'data' => $charge]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ChargeController@store failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $charge = Charge::findOrFail($id);

        $validated = $request->validate([
            'charge_code'          => 'nullable|string|max:50',
            'charge_name'          => 'required|string|max:255',
            'charge_department_id' => 'required|integer|exists:charge_departments,id',
            'charge_category_id'   => 'nullable|integer',
        ]);

        $validated['charge_code'] = $validated['charge_code'] ?? '';
        $validated['charge_category_id'] = $validated['charge_category_id'] ?? 0;

        DB::beginTransaction();
        try {
            $charge->update($validated);

            // Update patient category amounts if provided
            $catIds = $request->input('patient_category_id', []);
            $amounts = $request->input('amount', []);
            if (!empty($catIds) && is_array($catIds)) {
                for ($i = 0; $i < count($catIds); $i++) {
                    DB::table('patient_categories_charges')
                        ->updateOrInsert(
                            ['patient_category_id' => $catIds[$i], 'charge_id' => $id],
                            ['amount' => (float) ($amounts[$i] ?? 0)]
                        );
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Charge updated.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ChargeController@update failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function updateCode(Request $request): JsonResponse
    {
        $id = (int) $request->input('charge_id');
        $code = $request->input('charge_code', '');
        Charge::where('id', $id)->update(['charge_code' => $code]);
        return response()->json(['success' => true]);
    }

    public function categoriesByDepartment(Request $request, $deptId): JsonResponse
    {
        $query = ChargeCategory::query();
        if ($deptId) {
            $query->where('charge_department_id', $deptId);
        }
        $categories = $query->orderBy('charge_category')->get(['id', 'charge_category', 'charge_department_id']);
        return response()->json($categories);
    }

    public function destroy(int $id): JsonResponse
    {
        Charge::findOrFail($id)->delete();
        DB::table('patient_categories_charges')->where('charge_id', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Charge deleted.']);
    }

    // ---------- Departments ----------

    public function departments(Request $request, PermissionService $ps)
    {
        $canView = $ps->hasPermission('m_charges') && $ps->hasPermission('im_charges_departments');
        $canListCategories = $ps->hasPermission('b_departments_categories_list');

        if (!$canView) {
            return view('charges.departments', [
                'canView'           => false,
                'departments'       => collect(),
                'canListCategories' => false,
            ]);
        }

        $departments = ChargeDepartment::orderBy('sort_order')->get();
        return view('charges.departments', compact('departments', 'canView', 'canListCategories'));
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_name' => 'required|string|max:255',
            'sort_order'      => 'nullable|integer',
        ]);
        $dept = ChargeDepartment::create($validated);
        return response()->json(['success' => true, 'data' => $dept]);
    }

    // ---------- Autocomplete for billing ----------

    public function autocomplete(Request $request): JsonResponse
    {
        $search = $request->input('q', '');
        $deptId = $request->input('dept_id', '');

        $charges = Charge::when($deptId, fn($q) => $q->where('charge_department_id', $deptId))
            ->when($search, fn($q) => $q->where('charge_name', 'LIKE', "%{$search}%"))
            ->orderBy('charge_name')
            ->limit(50)
            ->get(['id', 'charge_name']);

        $data = $charges->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->charge_name,
        ]);

        return response()->json(['results' => $data]);
    }
}
