<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Bed;
use App\Models\Floor;
use App\Models\FloorArea;
use App\Models\WardCategory;
use App\Models\BedType;
use App\Models\Ipd;
use App\Models\Dcd;
use App\Http\Requests\CopyBedRequest;
use App\Http\Requests\StoreBedRequest;
use App\Http\Requests\StoreFloorRequest;
use App\Http\Requests\StoreWardCategoryRequest;
use App\Http\Requests\UpdateBedRequest;
use App\Http\Requests\UpdateFloorRequest;
use App\Http\Requests\UpdateWardCategoryRequest;
use App\Services\PermissionService;

class BedController extends Controller
{
    public function index(Request $request, PermissionService $ps)
    {
        $canView = $ps->hasPermission('m_beds') && $ps->hasPermission('im_beds');
        $canAdd  = $ps->hasPermission('b_beds_add');
        $canEdit = $ps->hasPermission('b_beds_edit');
        $canCopy = $ps->hasPermission('b_beds_copy');

        if (!$canView) {
            return view('beds.index', [
                'canView'        => false,
                'beds'           => collect(),
                'floors'         => collect(),
                'floorAreas'     => collect(),
                'wardCategories' => collect(),
                'bedTypes'       => collect(),
                'canAdd'         => false,
                'canEdit'        => false,
                'canCopy'        => false,
            ]);
        }

        $floors         = Floor::orderBy('sort_order')->get();
        $floorAreas     = FloorArea::orderBy('area')->get();
        $wardCategories = WardCategory::orderBy('ward_category')->get();
        $bedTypes       = BedType::orderBy('bed_type')->get();
        $beds           = Bed::with(['floor', 'floorArea', 'wardCategory', 'bedType'])
            ->orderBy('floor_id')
            ->orderBy('floor_area_id')
            ->orderBy('ward_category_id')
            ->orderBy('bed_type_id')
            ->orderBy('id')
            ->paginate(50);

        return view('beds.index', compact('floors', 'floorAreas', 'wardCategories', 'bedTypes', 'beds', 'canView', 'canAdd', 'canEdit', 'canCopy'));
    }

    // ─── Beds UI (visual map) ─────────────────────────────

    public function bedsUi(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('b_beds_ui'), 403);

        $floors = Floor::whereHas('beds')
            ->with(['areas' => function ($q) {
                $q->whereHas('beds')->with(['beds' => function ($bq) {
                    $bq->with(['wardCategory', 'bedType'])->orderBy('ward_category_id')->orderBy('bed_type_id')->orderBy('sort_order');
                }]);
            }])
            ->withCount('beds')
            ->orderBy('sort_order')
            ->get();

        $activeIpds = Ipd::whereNotIn('status', ['Discharged'])
            ->with('patient')
            ->get()
            ->keyBy('bed_id');

        $activeDcds = Dcd::whereNotIn('status', ['Discharged'])
            ->with('patient')
            ->get()
            ->keyBy('bed_id');

        return view('beds.ui', compact('floors', 'activeIpds', 'activeDcds'));
    }

    public function show(int $id): JsonResponse
    {
        $bed = Bed::with(['floor', 'floorArea', 'wardCategory', 'bedType'])->findOrFail($id);
        return response()->json([
            'id'               => $bed->id,
            'bed_name'         => $bed->bed_name,
            'floor_id'         => $bed->floor_id,
            'floor'            => $bed->floor?->floor ?? '',
            'floor_area_id'    => $bed->floor_area_id,
            'area'             => $bed->floorArea?->area ?? '',
            'ward_category_id' => $bed->ward_category_id,
            'ward_category'    => $bed->wardCategory?->ward_category ?? '',
            'bed_type_id'      => $bed->bed_type_id,
            'bed_type'         => $bed->bedType?->bed_type ?? '',
            'status'           => $bed->status,
            'sort_order'       => $bed->sort_order,
        ]);
    }

    public function copyBed(CopyBedRequest $request): JsonResponse
    {
        $v = $request->safe()->except('sort_order');
        $v['status']     = 'Available';
        $v['sort_order'] = 0;
        $bed = Bed::create($v);
        return response()->json([
            'success'  => true,
            'bed_id'   => $bed->id,
            'message'  => 'Saved Successfully.',
            'data'     => $bed,
        ]);
    }

    public function emptyBed(Request $request): JsonResponse
    {
        $id = (int) ($request->input('id') ?? $request->input('bed_id'));
        $bed = Bed::findOrFail($id);
        $bed->update(['status' => 'Available']);
        return response()->json(['success' => true, 'message' => 'Bed made available.', 'bed_id' => $id]);
    }

    public function floorAreasByFloor(int $floorId): JsonResponse
    {
        $areas = FloorArea::where('floor_id', $floorId)->orderBy('area')->get(['id', 'area']);
        return response()->json($areas);
    }

    // ─── Select2 sources for the bed modals ({id, text}[], filtered by ?q=) ───

    public function floorOptions(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');
        $floors = Floor::when($search !== '', fn ($q) => $q->where('floor', 'like', "%{$search}%"))
            ->orderBy('floor')
            ->get()
            ->map(fn ($f) => ['id' => $f->id, 'text' => $f->floor]);

        return response()->json($floors);
    }

    public function floorAreaOptions(Request $request): JsonResponse
    {
        $floorId = (int) $request->input('floor_id', 0);
        $search  = (string) $request->input('q', '');
        $areas   = FloorArea::when($floorId > 0, fn ($q) => $q->where('floor_id', $floorId))
            ->when($search !== '', fn ($q) => $q->where('area', 'like', "%{$search}%"))
            ->orderBy('area')
            ->get()
            ->map(fn ($a) => ['id' => $a->id, 'text' => $a->area]);

        return response()->json($areas);
    }

    public function wardCategoryOptions(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');
        $wards  = WardCategory::when($search !== '', fn ($q) => $q->where('ward_category', 'like', "%{$search}%"))
            ->orderBy('ward_category')
            ->get()
            ->map(fn ($w) => ['id' => $w->id, 'text' => $w->ward_category]);

        return response()->json($wards);
    }

    public function bedTypeOptions(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');
        $types  = BedType::when($search !== '', fn ($q) => $q->where('bed_type', 'like', "%{$search}%"))
            ->orderBy('bed_type')
            ->get()
            ->map(fn ($b) => ['id' => $b->id, 'text' => $b->bed_type]);

        return response()->json($types);
    }

    public function data(Request $request): JsonResponse
    {
        $beds = Bed::with(['floor', 'floorArea', 'wardCategory', 'bedType'])->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $beds]);
    }

    // ─── Available beds for select2 ──────────────────────

    public function available(Request $request): JsonResponse
    {
        $beds = Bed::with(['wardCategory'])
            ->where('status', 'Available')
            ->orderBy('bed_name')
            ->get();

        return response()->json([
            'results' => $beds->map(fn($b) => [
                'id'   => $b->id,
                'text' => $b->bed_name . ' — ' . ($b->wardCategory?->ward_category ?? ''),
            ]),
        ]);
    }

    public function store(StoreBedRequest $request): JsonResponse
    {
        $v = $request->validated();
        $v['status']     = 'Available';
        $v['sort_order'] = $v['sort_order'] ?? 0;
        $bed = Bed::create($v);
        return response()->json([
            'success'  => true,
            'bed_id'   => $bed->id,
            'message'  => 'Saved Successfully.',
            'data'     => $bed,
        ]);
    }

    public function update(UpdateBedRequest $request, int $id): JsonResponse
    {
        $bed = Bed::findOrFail($id);
        $bed->update($request->validated());
        return response()->json([
            'success'  => true,
            'bed_id'   => $bed->id,
            'message'  => 'Saved Successfully.',
            'data'     => $bed,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        Bed::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Bed deleted.']);
    }

    // ─── Floors ──────────────────────────────────────────

    public function floors(PermissionService $ps)
    {
        $hasBeds = $ps->hasPermission('m_beds');
        $hasFloors = $ps->hasPermission('im_floors');
        $canView = $hasBeds && $hasFloors;

        if (!$canView) {
            return view('beds.floors', [
                'canView'       => false,
                'floors'        => collect(),
                'canAdd'        => false,
                'canEdit'       => false,
                'canFloorAreas' => false,
            ]);
        }

        $canAdd = $ps->hasPermission('b_floors_add');
        $canEdit = $ps->hasPermission('b_floors_edit');
        $canFloorAreas = $ps->hasPermission('b_floors_areas');
        $floors = Floor::orderBy('sort_order')->get();

        return view('beds.floors', compact('floors', 'canView', 'canAdd', 'canEdit', 'canFloorAreas'));
    }

    public function getFloor(int $id): JsonResponse
    {
        $floor = Floor::findOrFail($id);

        return response()->json([
            'id'         => $floor->id,
            'floor'      => $floor->floor,
            'sort_order' => $floor->sort_order ?? 0,
        ]);
    }

    public function storeFloor(StoreFloorRequest $request): JsonResponse
    {
        $v = $request->validated();
        $v['sort_order'] = $v['sort_order'] ?? 0;

        $floor = Floor::create($v);

        return response()->json([
            'success'  => true,
            'floor_id' => $floor->id,
            'message'  => 'Saved Successfully.',
            'data'     => $floor,
        ]);
    }

    public function updateFloor(UpdateFloorRequest $request, int $id): JsonResponse
    {
        $floor = Floor::findOrFail($id);
        $floor->update($request->validated());

        return response()->json([
            'success'  => true,
            'floor_id' => $floor->id,
            'message'  => 'Saved Successfully.',
        ]);
    }

    // ─── Ward Categories ─────────────────────────────────

    public function wardCategories(PermissionService $ps)
    {
        $canView = $ps->hasPermission('m_beds') && $ps->hasPermission('im_ward_categories');
        $canAdd = $ps->hasPermission('b_ward_categories_add');
        $canEdit = $ps->hasPermission('b_ward_categories_edit');

        if (!$canView) {
            return view('beds.ward-categories', [
                'canView'        => false,
                'wardCategories' => collect(),
                'canAdd'         => false,
                'canEdit'        => false,
            ]);
        }

        $wardCategories = WardCategory::orderBy('id')->get();
        return view('beds.ward-categories', compact('wardCategories', 'canView', 'canAdd', 'canEdit'));
    }

    public function getWardCategory(int $id): JsonResponse
    {
        $wc = WardCategory::findOrFail($id);
        return response()->json([
            'id'            => $wc->id,
            'ward_category' => $wc->ward_category,
        ]);
    }

    public function storeWardCategory(StoreWardCategoryRequest $request): JsonResponse
    {
        $wc = WardCategory::create($request->validated());
        return response()->json([
            'success'          => true,
            'ward_category_id' => $wc->id,
            'message'          => 'Saved Successfully.',
            'data'             => $wc,
        ]);
    }

    public function updateWardCategory(UpdateWardCategoryRequest $request, int $id): JsonResponse
    {
        $wc = WardCategory::findOrFail($id);
        $wc->update($request->validated());
        return response()->json([
            'success'          => true,
            'ward_category_id' => $wc->id,
            'message'          => 'Saved Successfully.',
            'data'             => $wc,
        ]);
    }
}
