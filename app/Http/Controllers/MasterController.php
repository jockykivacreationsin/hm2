<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\BiochemistrySubTestRequest;
use App\Http\Requests\BiochemistryUnitRequest;
use App\Http\Requests\DiseaseRequest;
use App\Http\Requests\TpaProviderRequest;
use App\Models\TpaProvider;
use App\Models\PharmaProduct;
use App\Models\PharmaProductCategory;
use App\Models\ChargeCategory;
use App\Models\ChargeDepartment;
use App\Models\Charge;
use App\Models\BiochemistrySubTest;
use App\Models\BiochemistryUnit;
use App\Models\Disease;
use App\Models\Floor;
use App\Models\FloorArea;
use App\Models\HrsDepartment;
use App\Models\Designation;
use App\Http\Requests\CategoryChargeRequest;
use App\Http\Requests\ChargeAmountRequest;
use App\Http\Requests\ChargeCategoryRequest;
use App\Http\Requests\PharmaCategoryProductRequest;
use App\Http\Requests\PharmaProductCategoryRequest;
use App\Services\ChargeCatalogueService;
use App\Services\PermissionService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MasterController extends Controller
{
    // ─── TPA ────────────────────────────────────────────────

    public function tpa(PermissionService $ps)
    {
        $canAccess = $ps->hasPermission('m_master') && $ps->hasPermission('im_tpa');
        $canNew    = $ps->hasPermission('b_tpa_add');
        $canEdit   = $ps->hasPermission('b_tpa_edit');
        $canDelete = $ps->hasPermission('b_tpa_delete');

        $tpaProviders = $canAccess ? TpaProvider::orderBy('id')->get() : collect();

        return view('master.tpa', compact(
            'canAccess',
            'canNew',
            'canEdit',
            'canDelete',
            'tpaProviders'
        ));
    }

    public function getTpa(int $id): JsonResponse
    {
        $tpa = TpaProvider::find($id);
        return response()->json($tpa ?: (object)[]);
    }

    public function storeTpa(TpaProviderRequest $request): JsonResponse
    {
        $tpa = TpaProvider::create($request->validated());
        return response()->json([
            'success'         => true,
            'message'         => 'Saved Successfully.',
            'tpa_provider_id' => $tpa->id,
            'data'            => $tpa,
        ]);
    }

    public function updateTpa(TpaProviderRequest $request, int $id): JsonResponse
    {
        $tpa = TpaProvider::findOrFail($id);
        $tpa->update($request->validated());

        return response()->json([
            'success'         => true,
            'message'         => 'Saved Successfully.',
            'tpa_provider_id' => $tpa->id,
            'data'            => $tpa,
        ]);
    }

    public function destroyTpa(Request $request, int $id): JsonResponse
    {
        $ps = app(PermissionService::class);
        abort_unless($ps->hasPermission('m_master') || $ps->hasPermission('b_tpa_delete'), 403);

        $tpaId = $id;
        $tpa = TpaProvider::find($tpaId);

        if (!$tpa) {
            return response()->json(['success' => false, 'result' => 'Not Found', 'message' => 'TPA not found.'], 404);
        }

        $inUse = Schema::hasTable('ipds') && DB::table('ipds')->where('tpa_provider_id', $tpaId)->exists();
        if ($inUse) {
            return response()->json([
                'success' => false,
                'result'  => 'In Use',
                'message' => 'In use unable to delete',
            ]);
        }

        $tpa->delete();

        return response()->json([
            'success' => true,
            'result'  => 'OK',
            'message' => 'Deleted Successfully',
        ]);
    }

    // ─── Pharmacy ───────────────────────────────────────────

    public function pharmacy(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_pharmacy'), 403);
        $categories = PharmaProductCategory::orderBy('category_name')->get();
        $products   = PharmaProduct::with('category')->orderBy('product_name')->paginate(100);
        return view('master.pharmacy', compact('categories', 'products'));
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $v = $request->validate([
            'product_name' => 'required|string|max:255',
            'category_id'  => 'required|integer|exists:pharma_product_categories,id',
            'is_active'    => 'nullable|boolean',
        ]);
        $v['is_active'] ??= 1;
        $v['created_on'] = now()->toDateTimeString();
        $v['updated_on'] = now()->toDateTimeString();
        $product = PharmaProduct::create($v);
        return response()->json(['success' => true, 'message' => 'Product added.', 'data' => $product]);
    }

    public function destroyProduct(int $id): JsonResponse
    {
        PharmaProduct::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    public function pharmacyCategories(PermissionService $ps)
    {
        $canView = $ps->hasPermission('m_pharmacy');
        $canNew = $ps->hasPermission('b_pharma_category_new');
        $canList = $ps->hasPermission('b_pharma_category_product_list');
        $canEdit = $ps->hasPermission('b_pharma_category_edit');

        if (!$canView) {
            return view('master.pharmacy-categories', [
                'canView'    => false,
                'categories' => collect(),
                'canNew'     => false,
                'canList'    => false,
                'canEdit'    => false,
            ]);
        }

        $categories = PharmaProductCategory::all();

        return view('master.pharmacy-categories', [
            'canView'    => true,
            'categories' => $categories,
            'canNew'     => $canNew,
            'canList'    => $canList,
            'canEdit'    => $canEdit,
        ]);
    }

    public function storePharmacyCategory(PharmaProductCategoryRequest $request): JsonResponse
    {
        $cat = PharmaProductCategory::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Category added.', 'data' => $cat, 'pharma_product_category_id' => $cat->id]);
    }

    public function showPharmacyCategory(int $id): JsonResponse
    {
        return response()->json(PharmaProductCategory::find($id) ?: (object) []);
    }

    public function updatePharmacyCategory(PharmaProductCategoryRequest $request, int $id): JsonResponse
    {
        $cat = PharmaProductCategory::findOrFail($id);
        $cat->update($request->validated());
        return response()->json(['success' => true, 'message' => 'Category updated.', 'data' => $cat, 'pharma_product_category_id' => $cat->id]);
    }

    /** select2 source: pharmacy product categories (`q`). */
    public function pharmacyCategoryOptions(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');
        $rows = PharmaProductCategory::query()
            ->when($search !== '', fn ($q) => $q->where('category_name', 'like', "%{$search}%"))
            ->orderBy('category_name')
            ->limit(50)
            ->get(['id', 'category_name']);

        return response()->json($rows->map(fn ($r) => ['id' => $r->id, 'text' => $r->category_name]));
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        $products = PharmaProduct::with('category')
            ->where('is_active', 1)
            ->where('product_name', 'LIKE', "%{$q}%")
            ->orderBy('product_name')->limit(50)
            ->get(['id', 'product_name', 'category_id']);
        return response()->json([
            'results' => $products->map(fn($p) => [
                'id'       => $p->id,
                'text'     => $p->product_name,
                'category' => $p->category?->category_name,
            ]),
        ]);
    }

    // ─── Charge Categories ──────────────────────────────────

    public function chargeCategories(Request $request, PermissionService $ps)
    {
        $canView = $ps->hasPermission('im_charges_categories');
        $canNew = $ps->hasPermission('b_charges_categories_new');
        $canEdit = $ps->hasPermission('b_charges_categories_edit');
        $canCharges = $ps->hasPermission('b_charges_categories_charges');

        if (!$canView) {
            return view('master.charge-categories', [
                'canView'              => false,
                'charge_department_id' => 0,
                'charge_department'    => '',
                'categories'           => collect(),
                'canNew'               => false,
                'canEdit'              => false,
                'canCharges'           => false,
            ]);
        }

        $charge_department_id = (int) $request->input('id', 0);
        $charge_department = '';

        if ($charge_department_id > 0) {
            $dept = ChargeDepartment::find($charge_department_id);
            if ($dept) {
                $charge_department = $dept->department_name;
            }
        }

        $categories = collect();
        if ($charge_department_id > 0 && $charge_department !== '') {
            $categories = ChargeCategory::where('charge_department_id', $charge_department_id)->get();
        }

        return view('master.charge-categories', [
            'canView'              => true,
            'charge_department_id' => $charge_department_id,
            'charge_department'    => $charge_department,
            'categories'           => $categories,
            'canNew'               => $canNew,
            'canEdit'              => $canEdit,
            'canCharges'           => $canCharges,
        ]);
    }

    public function getChargeCategory(int $id): JsonResponse
    {
        $cat = ChargeCategory::with('department')->findOrFail($id);
        return response()->json([
            'id'                   => $cat->id,
            'charge_category'      => $cat->charge_category,
            'charge_department_id' => $cat->charge_department_id,
            'department_name'      => $cat->department?->department_name ?? '',
        ]);
    }

    public function storeChargeCategory(ChargeCategoryRequest $request): JsonResponse
    {
        $cat = ChargeCategory::create($request->validated());
        return response()->json(['success' => true, 'message' => 'Category added.', 'data' => $cat, 'charge_category_id' => $cat->id]);
    }

    public function updateChargeCategory(ChargeCategoryRequest $request, int $id): JsonResponse
    {
        $cat = ChargeCategory::findOrFail($id);
        $cat->update($request->validated());
        return response()->json(['success' => true, 'message' => 'Category updated.', 'data' => $cat, 'charge_category_id' => $cat->id]);
    }

    /** Charges within a specific charge category */
    public function chargeCategoryCharges(Request $request, int $id, PermissionService $ps)
    {
        $canView = $ps->hasPermission('im_charges');
        $canNew = $ps->hasPermission('b_charges_new');
        $canEdit = $ps->hasPermission('b_charges_edit');
        $canDelete = $ps->hasPermission('b_charges_delete');

        if (!$canView) {
            return view('master.charge-category-charges', [
                'canView'              => false,
                'charge_category_id'   => $id,
                'charge_department_id' => 0,
                'charge_category'      => '',
                'charges'              => collect(),
                'patient_categories'   => [],
                'all_charges_map'      => [],
                'canNew'               => false,
                'canEdit'              => false,
                'canDelete'            => false,
            ]);
        }

        $charge_category_id = $id;
        $charge_department_id = (int) $request->input('cd_id', 0);
        $charge_category = '';

        if ($charge_category_id > 0) {
            $cat = ChargeCategory::find($charge_category_id);
            if ($cat) {
                $charge_category = $cat->charge_category;
                if ($charge_department_id === 0) {
                    $charge_department_id = (int) $cat->charge_department_id;
                }
            }
        }

        $charges = collect();
        $patient_categories = [];
        $all_charges_map = [];

        if ($charge_category_id > 0 && $charge_category !== '') {
            $charges = DB::table('charges')
                ->join('charge_departments', 'charge_departments.id', '=', 'charges.charge_department_id')
                ->join('charge_categories', 'charge_categories.id', '=', 'charges.charge_category_id')
                ->where('charges.charge_category_id', $charge_category_id)
                ->orderBy('charges.charge_category_id')
                ->orderBy('charges.charge_name')
                ->select('charges.*', 'charge_departments.department_name', 'charge_categories.charge_category')
                ->get();

            $allChargesResult = DB::table('patient_categories_charges as pcc')
                ->join('charges as c', 'c.id', '=', 'pcc.charge_id')
                ->where('c.charge_category_id', $charge_category_id)
                ->select('pcc.charge_id', 'pcc.patient_category_id', 'pcc.amount')
                ->get();

            foreach ($allChargesResult as $cr) {
                $cid = (int) $cr->charge_id;
                $pcid = (int) $cr->patient_category_id;
                if (!isset($all_charges_map[$cid])) {
                    $all_charges_map[$cid] = [];
                }
                $all_charges_map[$cid][$pcid] = (float) $cr->amount;
            }

            $pcResult = DB::table('patient_categories')->orderBy('id')->get(['id', 'patient_category_name']);
            foreach ($pcResult as $pcr) {
                $patient_categories[] = [
                    'id'   => (int) $pcr->id,
                    'name' => $pcr->patient_category_name,
                ];
            }
        }

        return view('master.charge-category-charges', [
            'canView'              => true,
            'charge_category_id'   => $charge_category_id,
            'charge_department_id' => $charge_department_id,
            'charge_category'      => $charge_category,
            'charges'              => $charges,
            'patient_categories'   => $patient_categories,
            'all_charges_map'      => $all_charges_map,
            'canNew'               => $canNew,
            'canEdit'              => $canEdit,
            'canDelete'            => $canDelete,
        ]);
    }

    /** One charge with its department and category names (edit modal of the category's charge list). */
    public function showCategoryCharge(int $id): JsonResponse
    {
        $row = DB::table('charges')
            ->join('charge_departments', 'charge_departments.id', '=', 'charges.charge_department_id')
            ->join('charge_categories', 'charge_categories.id', '=', 'charges.charge_category_id')
            ->where('charges.id', $id)
            ->select('charges.*', 'charge_departments.department_name', 'charge_categories.charge_category')
            ->first();

        return response()->json($row ?: (object) []);
    }

    public function storeCategoryCharge(CategoryChargeRequest $request, int $categoryId, ChargeCatalogueService $charges): JsonResponse
    {
        $category = ChargeCategory::findOrFail($categoryId);
        $charge = $charges->createInCategory(
            $category,
            $request->input('charge_name'),
            (string) $request->input('charge_code', ''),
            (array) $request->input('patient_category_id', []),
            (array) $request->input('amount', [])
        );
        return response()->json(['success' => true, 'message' => 'Charge added.', 'data' => $charge, 'charge_id' => $charge->id]);
    }

    public function updateCategoryCharge(CategoryChargeRequest $request, int $id): JsonResponse
    {
        $charge = Charge::findOrFail($id);
        $charge->update($request->validated());
        return response()->json(['success' => true, 'message' => 'Charge updated.', 'charge_id' => $charge->id]);
    }

    /** Inline edit of the amount one patient category pays for a charge. */
    public function updateCategoryChargeAmount(ChargeAmountRequest $request, int $id, ChargeCatalogueService $charges): JsonResponse
    {
        $charge = Charge::findOrFail($id);
        $charges->setAmount($charge->id, (int) $request->input('patient_category_id'), (float) $request->input('amount'));
        return response()->json(['success' => true, 'result' => 'OK']);
    }

    /** Deletes an unused charge; a charge already billed answers 200 with result "In Use" (the list script reads `result`). */
    public function destroyCategoryCharge(int $chargeId, ChargeCatalogueService $charges): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_charges_delete'), 403);
        $charge = Charge::findOrFail($chargeId);

        if (!$charges->deleteUnused($charge)) {
            return response()->json(['success' => false, 'result' => 'In Use', 'message' => 'In use unable to delete']);
        }
        return response()->json(['success' => true, 'result' => 'OK', 'message' => 'Charge deleted.']);
    }


    // ─── Pharmacy category products ─────────────────────────────

    public function pharmacyCategoryProducts(Request $request, int $id, PermissionService $ps)
    {
        $canView = $ps->hasPermission('mi_pharmacy_products');
        $canNew = $ps->hasPermission('b_pharma_category_product_new');
        $canEdit = $ps->hasPermission('b_pharma_category_product_edit');
        $canDelete = $ps->hasPermission('b_pharma_category_product_delete');

        $ppc_id = (int) ($request->input('id') ?: $id);

        if (!$canView) {
            return view('master.pharmacy-category-products', [
                'canView'       => false,
                'category_id'   => $ppc_id,
                'category_name' => '',
                'products'      => collect(),
                'canNew'        => false,
                'canEdit'       => false,
                'canDelete'     => false,
            ]);
        }

        $category_name = '';

        if ($ppc_id > 0) {
            $cat = PharmaProductCategory::find($ppc_id);
            if ($cat) {
                $category_name = $cat->category_name;
            }
        }

        $products = collect();
        if ($ppc_id > 0) {
            $products = PharmaProduct::where('category_id', $ppc_id)->get();
        }

        return view('master.pharmacy-category-products', [
            'canView'       => true,
            'category_id'   => $ppc_id,
            'category_name' => $category_name,
            'products'      => $products,
            'canNew'        => $canNew,
            'canEdit'       => $canEdit,
            'canDelete'     => $canDelete,
        ]);
    }

    /** One product with its category name (edit modal of the category's product list). */
    public function showCategoryProduct(int $id): JsonResponse
    {
        $row = DB::table('pharma_products')
            ->leftJoin('pharma_product_categories', 'pharma_product_categories.id', '=', 'pharma_products.category_id')
            ->where('pharma_products.id', $id)
            ->select('pharma_products.*', 'pharma_product_categories.category_name')
            ->first();

        return response()->json($row ?: (object) []);
    }

    public function storeCategoryProduct(PharmaCategoryProductRequest $request, int $categoryId): JsonResponse
    {
        $category = PharmaProductCategory::findOrFail($categoryId);
        $product = PharmaProduct::create([
            'category_id'  => $category->id,
            'product_name' => $request->input('product_name'),
        ]);
        return response()->json(['success' => true, 'message' => 'Product added.', 'data' => $product, 'pharma_product_id' => $product->id]);
    }

    public function updateCategoryProduct(PharmaCategoryProductRequest $request, int $id): JsonResponse
    {
        $product = PharmaProduct::findOrFail($id);
        $product->update($request->safe()->only(['category_id', 'product_name']));
        return response()->json(['success' => true, 'message' => 'Product updated.', 'pharma_product_id' => $product->id]);
    }

    public function destroyCategoryProduct(int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_pharma_category_product_delete'), 403);
        PharmaProduct::findOrFail($id)->delete();
        return response()->json(['success' => true, 'result' => 'OK', 'message' => 'Product deleted.']);
    }

    // ─── Biochemistry Units ───────────────────────────────────

    public function biochemistryUnits(PermissionService $ps)
    {
        $canAccess = $ps->hasPermission('m_master') && $ps->hasPermission('im_bunits');
        $canAdd    = $ps->hasPermission('b_bunits_add');
        $canEdit   = $ps->hasPermission('b_bunits_edit');
        $canDelete = $ps->hasPermission('b_bunits_delete');

        $units = $canAccess ? BiochemistryUnit::orderBy('id')->get() : collect();

        return view('master.biochemistry-units', compact(
            'canAccess',
            'canAdd',
            'canEdit',
            'canDelete',
            'units',
            'ps'
        ));
    }

    public function getUnit(int $id): JsonResponse
    {
        $unit = BiochemistryUnit::find($id);
        if (!$unit) {
            return response()->json(['error' => 'Invalid ID'], 404);
        }
        return response()->json([
            'id'        => $unit->id,
            'unit_name' => $unit->unit_name,
            'ucum_code' => $unit->ucum_code,
        ]);
    }

    public function storeUnit(BiochemistryUnitRequest $request): JsonResponse
    {
        $unit = BiochemistryUnit::create($request->validated());
        return response()->json([
            'success' => true,
            'unit_id' => $unit->id,
            'message' => 'Unit saved successfully.',
            'data'    => $unit,
        ]);
    }

    public function updateUnit(BiochemistryUnitRequest $request, int $id): JsonResponse
    {
        $unit = BiochemistryUnit::findOrFail($id);
        $unit->update($request->validated());
        return response()->json([
            'success' => true,
            'unit_id' => $unit->id,
            'message' => 'Unit updated successfully.',
            'data'    => $unit,
        ]);
    }

    public function destroyUnit(int $id): JsonResponse
    {
        $unitId = $id;
        $inUse = Schema::hasTable('biochemistry_sub_tests') && DB::table('biochemistry_sub_tests')->where('unit_id', $unitId)->exists();
        if ($inUse) {
            return response()->json([
                'result'  => 'In Use',
                'success' => false,
                'message' => 'Cannot delete: Unit is in use by test parameters.',
            ]);
        }
        $unit = BiochemistryUnit::find($unitId);
        if ($unit) {
            $unit->delete();
        }
        return response()->json([
            'result'  => 'OK',
            'success' => true,
            'message' => 'Unit deleted.',
        ]);
    }

    /** select2 source for the unit pickers: a plain `[{id, text}]` array (select2 `processResults` wraps it). */
    public function bunitOptions(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', $request->input('term', ''));
        $units = BiochemistryUnit::when($q !== '', fn($query) => $query->where('unit_name', 'like', "%{$q}%"))
            ->orderBy('id')
            ->get(['id', 'unit_name']);
        return response()->json($units->map(fn($u) => ['id' => $u->id, 'text' => $u->unit_name]));
    }

    // ─── Biochemistry Tests (Main Categories) ──────────────────

    public function biochemistryTests(Request $request, PermissionService $ps, SettingsService $settings)
    {
        $canAccess = $ps->hasPermission('m_master');
        $pathologyDeptId = $settings->getIdField('pathology_department_id', 0);
        if ($pathologyDeptId <= 0) {
            $pathologyDeptId = ChargeDepartment::where('department', 'like', '%pathology%')->value('id') ?? 3;
        }
        $charges = $canAccess ? Charge::where('charge_department_id', $pathologyDeptId)->orderBy('charge_name')->get() : collect();
        return view('master.biochemistry-tests', compact('charges', 'canAccess', 'ps'));
    }

    // ─── Biochemistry Sub Tests ───────────────────────────────

    public function biochemistrySubTests(Request $request, PermissionService $ps)
    {
        $canAccess = $ps->hasPermission('m_master');
        $charge_id = (int) $request->input('id', 0);
        $charge = $charge_id > 0 ? Charge::find($charge_id) : null;
        $subTests = $canAccess
            ? BiochemistrySubTest::with('unit')
                ->when($charge_id > 0, fn($q) => $q->where('main_category_id', $charge_id))
                ->orderBy('id')
                ->get()
            : collect();
        $units = $canAccess ? BiochemistryUnit::orderBy('id')->get() : collect();
        return view('master.biochemistry-sub-tests', compact('charge', 'charge_id', 'subTests', 'units', 'canAccess', 'ps'));
    }

    public function getSubTest(int $id): JsonResponse
    {
        $sub = BiochemistrySubTest::with('unit')->find($id);
        if (!$sub) {
            return response()->json(['error' => 'Invalid ID'], 404);
        }
        return response()->json([
            'id'               => $sub->id,
            'main_category_id' => $sub->main_category_id,
            'sub_test_name'    => $sub->sub_test_name,
            'reference_range'  => $sub->reference_range,
            'unit_id'          => $sub->unit_id,
            'unit_name'        => $sub->unit?->unit_name ?? '',
        ]);
    }

    public function storeBiochemistrySubTest(BiochemistrySubTestRequest $request): JsonResponse
    {
        $v = $request->validated();
        $unitId = $v['unit_id'] ?? $v['bunit_id'] ?? null;
        $mainCatId = $v['main_category_id'] ?? $v['charge_id'] ?? 0;

        $sub = BiochemistrySubTest::create([
            'sub_test_name'    => $v['sub_test_name'],
            'reference_range'  => $v['reference_range'] ?? '',
            'unit_id'          => (int) ($unitId ?? 0),
            'main_category_id' => (int) $mainCatId,
        ]);
        return response()->json([
            'success' => true,
            'bst_id'  => $sub->id,
            'id'      => $sub->id,
            'message' => 'Sub test saved.',
            'data'    => $sub,
        ]);
    }

    public function updateBiochemistrySubTest(BiochemistrySubTestRequest $request, int $id): JsonResponse
    {
        $v = $request->validated();
        $unitId = $v['unit_id'] ?? $v['bunit_id'] ?? null;
        $sub = BiochemistrySubTest::findOrFail($id);
        $payload = [
            'sub_test_name'   => $v['sub_test_name'],
            'reference_range' => $v['reference_range'] ?? '',
        ];
        if ($unitId !== null) {
            $payload['unit_id'] = (int) $unitId;
        }
        if (isset($v['main_category_id'])) {
            $payload['main_category_id'] = (int) $v['main_category_id'];
        }
        $sub->update($payload);
        return response()->json([
            'success' => true,
            'bst_id'  => $sub->id,
            'id'      => $sub->id,
            'message' => 'Sub test updated.',
            'data'    => $sub,
        ]);
    }

    public function destroyBiochemistrySubTest(int $id): JsonResponse
    {
        $subId = $id;
        $sub = BiochemistrySubTest::find($subId);
        if ($sub) {
            $sub->delete();
        }
        return response()->json([
            'success' => true,
            'id'      => $subId,
            'message' => 'Sub test deleted.',
        ]);
    }

    // Compatibility method for previous route
    public function biochemistry(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_master'), 403);
        $units    = BiochemistryUnit::orderBy('unit_name')->get();
        $subTests = BiochemistrySubTest::with('unit')->orderBy('sub_test_name')->get();
        return view('master.biochemistry', compact('units', 'subTests'));
    }

    // ─── Diseases ───────────────────────────────────────────

    public function diseases(Request $request, PermissionService $ps)
    {
        // Reached from both the Master and the Patient Medicines menus (legacy diseases.php).
        $canModule = $ps->hasPermission('m_master') || $ps->hasPermission('m_patient_periodic_medicines');
        $canInner = $ps->hasPermission('im_diseases');
        $canAccess = $canModule && $canInner;

        if (!$canAccess) {
            return view('master.diseases', [
                'canAccess'          => false,
                'diseases'           => collect(),
                'canAdd'             => false,
                'canEdit'            => false,
                'canDiseasePatients' => false,
            ]);
        }

        $canAdd = $ps->hasPermission('b_diseases_add');
        $canEdit = $ps->hasPermission('b_diseases_edit');
        $canDiseasePatients = $ps->hasPermission('b_disease_patients');

        $perPage = 50;
        $diseases = Disease::orderBy('disease_name', 'asc')->paginate($perPage);

        return view('master.diseases', compact('diseases', 'canAccess', 'canAdd', 'canEdit', 'canDiseasePatients'));
    }

    public function getDisease(int $id): JsonResponse
    {
        return response()->json(Disease::findOrFail($id));
    }

    public function storeDisease(DiseaseRequest $request): JsonResponse
    {
        $disease = Disease::create($request->validated());
        return response()->json([
            'success'    => true,
            'disease_id' => $disease->id,
            'message'    => 'Disease added successfully.',
            'data'       => $disease,
        ]);
    }

    public function updateDisease(DiseaseRequest $request, int $id): JsonResponse
    {
        $disease = Disease::findOrFail($id);
        $disease->update($request->validated());
        return response()->json([
            'success'    => true,
            'disease_id' => $disease->id,
            'message'    => 'Disease updated successfully.',
            'data'       => $disease,
        ]);
    }

    public function destroyDisease(int $id): JsonResponse
    {
        Disease::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Disease deleted successfully.']);
    }

    // ─── Floor Areas ────────────────────────────────────────

    public function floorAreas(Request $request, PermissionService $ps)
    {
        $canView = $ps->hasPermission('im_floor_areas');
        if (!$canView) {
            return view('beds.floor-areas', [
                'canView'       => false,
                'floors'        => collect(),
                'floorAreas'    => collect(),
                'currentFloor'  => null,
                'floor_id'      => 0,
                'canAdd'        => false,
                'canEdit'       => false,
            ]);
        }

        $canAdd = $ps->hasPermission('b_floor_areas_add');
        $canEdit = $ps->hasPermission('b_floor_areas_edit');
        $floor_id = (int) $request->input('id', 0);
        $currentFloor = $floor_id > 0 ? Floor::find($floor_id) : null;
        $floors = Floor::orderBy('sort_order')->get();
        if ($floor_id > 0) {
            $floorAreas = FloorArea::with('floor')->where('floor_id', $floor_id)->get();
        } else {
            $floorAreas = FloorArea::with('floor')->orderBy('floor_id')->get();
        }
        return view('beds.floor-areas', compact('floors', 'floorAreas', 'currentFloor', 'floor_id', 'canView', 'canAdd', 'canEdit'));
    }

    public function getFloorArea(int $id): JsonResponse
    {
        $area = FloorArea::with('floor')->findOrFail($id);
        return response()->json([
            'id'       => $area->id,
            'floor_id' => $area->floor_id,
            'area'     => $area->area,
            'floor'    => $area->floor?->floor ?? '',
        ]);
    }

    public function storeFloorArea(\App\Http\Requests\StoreFloorAreaRequest $request): JsonResponse
    {
        $area = FloorArea::create($request->validated());
        return response()->json([
            'success'       => true,
            'floor_area_id' => $area->id,
            'message'       => 'Saved Successfully.',
            'data'          => $area,
        ]);
    }

    public function updateFloorArea(\App\Http\Requests\UpdateFloorAreaRequest $request, int $id): JsonResponse
    {
        $area = FloorArea::findOrFail($id);
        $area->update($request->validated());
        return response()->json([
            'success'       => true,
            'floor_area_id' => $area->id,
            'message'       => 'Saved Successfully.',
            'data'          => $area,
        ]);
    }

    public function destroyFloorArea(int $id): JsonResponse
    {
        FloorArea::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ─── HR Departments ─────────────────────────────────────

    public function hrDepartments(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_staff'), 403);
        $departments  = HrsDepartment::orderBy('department')->get();
        $designations = Designation::orderBy('designation')->get();
        return view('master.hr-departments', compact('departments', 'designations'));
    }

    public function storeHrDepartment(Request $request): JsonResponse
    {
        $v = $request->validate(['department' => 'required|string|max:255|unique:departments,department']);
        $dept = HrsDepartment::create($v);
        return response()->json(['success' => true, 'message' => 'Department added.', 'data' => $dept]);
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        $v = $request->validate(['designation' => 'required|string|max:255|unique:designations,designation']);
        $d = Designation::create($v);
        return response()->json(['success' => true, 'message' => 'Designation added.', 'data' => $d]);
    }

    // ─── Disease Patients ─────────────────────────────────────────

    public function diseasePatients(int $diseaseId, \App\Services\PermissionService $ps)
    {
        $canModule = $ps->hasPermission('m_master') || $ps->hasPermission('m_patient_periodic_medicines');
        $canInner = $ps->hasPermission('im_disease_patients');
        $canAccess = $canModule && $canInner;

        $disease = \App\Models\Disease::find($diseaseId);
        if (!$canAccess || !$disease) {
            return view('master.disease-patients', [
                'canAccess'      => false,
                'disease'        => $disease ?? new \App\Models\Disease(['disease_name' => '']),
                'patientRecords' => collect(),
                'records'        => collect(),
            ]);
        }

        $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';

        // Latest record per patient linked to this disease
        $maxIds = \App\Models\PatientPeriodicMedicine::groupBy('patient_id')
            ->selectRaw('MAX(id) as max_id')
            ->pluck('max_id');

        $query = \App\Models\PatientPeriodicMedicine::with('patient')
            ->whereIn('id', $maxIds);

        if ($isSqlite) {
            $query->whereRaw("(',' || disease_ids || ',') LIKE ?", ["%,{$diseaseId},%"]);
        } else {
            $query->whereRaw('FIND_IN_SET(?, disease_ids)', [$diseaseId]);
        }

        $rawRecords = $query->orderByDesc('id')->get();
        $diseaseMap = \App\Models\Disease::pluck('disease_name', 'id');

        $patientRecords = $rawRecords->map(function ($r) use ($diseaseMap) {
            $days = $r->next_medicine_date
                ? (int) \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($r->next_medicine_date)->startOfDay(), false)
                : 0;

            $days_class = '';
            $due_days = '';

            if ($days > 10) {
                $due_days = "{$days} Left";
            } elseif ($days >= 5 && $days <= 10) {
                $days_class = 'days_priority_normal';
                $due_days = "{$days} Left";
            } elseif ($days >= 1 && $days <= 4) {
                $days_class = 'days_priority_high';
                $due_days = "{$days} Left";
            } else {
                $days_class = 'days_priority_urgent';
                $abs_days = abs($days);
                $due_days = "{$abs_days} Over";
            }

            $ids = array_filter(explode(',', (string) $r->disease_ids));
            sort($ids, SORT_NUMERIC);
            $disease_names = collect($ids)->map(fn($id) => $diseaseMap[(int)$id] ?? null)->filter()->implode(', ');

            return [
                'ppm_id'             => $r->id,
                'patient_id'         => $r->patient_id,
                'patient_name'       => $r->patient?->patient_name ?? '',
                'mobile_number'      => $r->patient?->mobile_number ?? '',
                'address'            => $r->patient?->address ?? '',
                'age'                => $r->patient?->age ?? '',
                'days_class'         => $days_class,
                'due_days'           => $due_days,
                'disease_names'      => $disease_names,
                'medicine_date_time' => $r->medicine_date_time ? \Carbon\Carbon::parse($r->medicine_date_time)->format('d-m-Y') : '',
                'next_medicine_date' => $r->next_medicine_date ? \Carbon\Carbon::parse($r->next_medicine_date)->format('d-m-Y') : '',
                'delivery'           => $r->delivery ?? '',
            ];
        });

        return view('master.disease-patients', [
            'canAccess'      => true,
            'disease'        => $disease,
            'patientRecords' => $patientRecords,
            'records'        => $patientRecords,
        ]);
    }

}
