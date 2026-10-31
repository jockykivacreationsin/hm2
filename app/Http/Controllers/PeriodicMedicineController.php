<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientPeriodicMedicineRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PatientPeriodicMedicine;
use App\Models\PatientPeriodicMedicineDetail;
use App\Models\Patient;
use App\Models\PatientCategory;
use App\Models\Disease;
use App\Models\PharmaProduct;
use App\Models\MedicineFrequencyType;
use App\Services\PermissionService;
use App\Support\CurrentUser;

class PeriodicMedicineController extends Controller
{
    public function index(PermissionService $ps)
    {
        $canModule = $ps->hasPermission('m_patient_periodic_medicines') || $ps->hasPermission('m_master');
        $canInner = $ps->hasPermission('im_periodic_medicine_patients');
        $canAccess = $canModule && $canInner;

        if (!$canAccess) {
            return view('periodic-medicine.index', [
                'canAccess'      => false,
                'records'        => collect(),
                'patientRecords' => collect(),
            ]);
        }

        $maxIds = PatientPeriodicMedicine::groupBy('patient_id')
            ->selectRaw('MAX(id) as max_id')
            ->pluck('max_id');

        $rawRecords = PatientPeriodicMedicine::has('patient')
            ->with('patient')
            ->whereIn('id', $maxIds)
            ->orderByDesc('id')
            ->get();

        $diseaseMap = Disease::pluck('disease_name', 'id');

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
                'record_id'          => $r->id,
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

        return view('periodic-medicine.index', [
            'canAccess'      => true,
            'records'        => $patientRecords,
            'patientRecords' => $patientRecords,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $rawSearch = $request->input('search.value');
        if ($rawSearch === null || $rawSearch === '') {
            $rawSearch = $request->input('search', '');
        }
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        $patientId = $request->input('patient_id');

        $query = PatientPeriodicMedicine::with(['patient', 'medicines'])
            ->when($patientId, fn($q) => $q->where('patient_id', $patientId))
            ->when($search, fn($q) => $q->whereHas('patient',
                fn($sq) => $sq->where('patient_name', 'LIKE', "%{$search}%")));

        $total   = $query->count();
        $records = $query->orderByDesc('medicine_date_time')
            ->skip($request->integer('start'))
            ->take($request->integer('length', 25))
            ->get();

        $data = $records->map(fn($r) => [
            'id'                  => $r->id,
            'patient_name'        => e($r->patient?->patient_name ?? ''),
            'uhid'                => $r->patient?->uhid ?? '',
            'medicine_date_time'  => $r->medicine_date_time,
            'next_medicine_date'  => $r->next_medicine_date,
            'delivery'            => $r->delivery,
            'medicines_count'     => $r->medicines->count(),
            'remarks'             => e($r->remarks),
        ]);

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'patient_id'          => 'required|integer|exists:patients,id',
            'medicine_date_time'  => 'required|date',
            'next_medicine_date'  => 'nullable|date',
            'disease_ids'         => 'nullable|string',
            'delivery'            => 'nullable|string|max:100',
            'remarks'             => 'nullable|string',
            'medicines'           => 'nullable|array',
            'medicines.*.medicine_name'  => 'required|string|max:255',
            'medicines.*.dose_quantity'  => 'nullable|string|max:100',
            'medicines.*.dose_interval'  => 'nullable|string|max:100',
            'medicines.*.remarks'        => 'nullable|string',
        ]);

        $pm = PatientPeriodicMedicine::create([
            'patient_id'         => $v['patient_id'],
            'medicine_date_time' => $v['medicine_date_time'],
            'next_medicine_date' => $v['next_medicine_date'] ?? null,
            'disease_ids'        => $v['disease_ids'] ?? '',
            'delivery'           => $v['delivery'] ?? '',
            'remarks'            => $v['remarks'] ?? '',
        ]);

        foreach ($v['medicines'] ?? [] as $med) {
            PatientPeriodicMedicineDetail::create([
                'pm_id'         => $pm->id,
                'medicine_name' => $med['medicine_name'],
                'dose_quantity' => $med['dose_quantity'] ?? null,
                'dose_interval' => $med['dose_interval'] ?? null,
                'remarks'       => $med['remarks'] ?? null,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Periodic medicine record saved.', 'id' => $pm->id]);
    }

    public function show(int $id): JsonResponse
    {
        $pm = PatientPeriodicMedicine::with(['patient', 'medicines'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $pm]);
    }

    public function destroy(int $id): JsonResponse
    {
        PatientPeriodicMedicineDetail::where('pm_id', $id)->delete();
        PatientPeriodicMedicine::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }

    // ─── Medicine Due Days ────────────────────────────────────

    public function dueDays(Request $request, PermissionService $ps)
    {
        $canModule = $ps->hasPermission('m_patient_periodic_medicines') || $ps->hasPermission('m_master');
        $canInner = $ps->hasPermission('im_patients_medicine_due_days');
        $canAccess = $canModule && $canInner;

        if (!$canAccess) {
            return view('periodic-medicine.due-days', [
                'canAccess' => false,
            ]);
        }

        return view('periodic-medicine.due-days', [
            'canAccess' => true,
        ]);
    }

    public function dueDaysData(Request $request): JsonResponse
    {
        $dueDays = (int) $request->input('due_days', 10);
        $targetDate = \Carbon\Carbon::today()->addDays($dueDays)->format('Y-m-d');

        $rawSearch = $request->input('search.value');
        if ($rawSearch === null || $rawSearch === '') {
            $rawSearch = $request->input('search', '');
        }
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);

        // Subquery for latest record per patient: MAX(id)
        $maxIds = PatientPeriodicMedicine::groupBy('patient_id')
            ->selectRaw('MAX(id) as max_id')
            ->pluck('max_id');

        $query = PatientPeriodicMedicine::has('patient')
            ->with('patient')
            ->whereIn('id', $maxIds)
            ->whereNotNull('next_medicine_date')
            ->where('next_medicine_date', '!=', '')
            ->where('next_medicine_date', '<=', $targetDate);

        if ($search !== '') {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('patient_name', 'LIKE', "%{$search}%")
                  ->orWhere('mobile_number', 'LIKE', "%{$search}%");
            });
        }

        $totalRecords = $query->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);

        if ($length > 0) {
            $records = $query->orderByDesc('id')->skip($start)->take($length)->get();
        } else {
            $records = $query->orderByDesc('id')->get();
        }

        $diseases = Disease::pluck('disease_name', 'id');

        $data = $records->map(function ($row) use ($diseases) {
            $id = $row->id;
            $days = $row->next_medicine_date
                ? (int) \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($row->next_medicine_date)->startOfDay(), false)
                : 0;

            $dueDaysHtml = '';
            if ($days >= 5 && $days <= 10) {
                $dueDaysHtml = "<span class='days_priority_normal'>{$days} Left</span>";
            } elseif ($days >= 1 && $days <= 4) {
                $dueDaysHtml = "<span class='days_priority_high'>{$days} Left</span>";
            } elseif ($days < 1) {
                $absDays = abs($days);
                $dueDaysHtml = "<span class='days_priority_urgent'>{$absDays} Over</span>";
            } else {
                $dueDaysHtml = "{$days} Left";
            }

            $ids = array_filter(explode(',', (string) $row->disease_ids));
            sort($ids, SORT_NUMERIC);
            $diseaseNames = collect($ids)->map(fn($did) => $diseases[(int)$did] ?? null)->filter()->implode(', ');

            $medDate = $row->medicine_date_time ? \Carbon\Carbon::parse($row->medicine_date_time)->format('d-m-Y') : '';
            $nextDate = $row->next_medicine_date ? \Carbon\Carbon::parse($row->next_medicine_date)->format('d-m-Y') : '';

            return [
                'DT_RowId'           => 'row_' . $id,
                'action'             => '<a href="javascript:void(0)" onclick="issueMedicineDetailModal(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Medicine Detail"><i class="far fa-pills"></i></a>',
                'id'                 => $row->patient_id,
                'due_days'           => $dueDaysHtml,
                'patient_name'       => $row->patient?->patient_name ?? '',
                'mobile_number'      => $row->patient?->mobile_number ?? '',
                'address'            => $row->patient?->address ?? '',
                'age'                => $row->patient?->age ?? '',
                'diseases'           => $diseaseNames,
                'medicine_date_time' => $medDate,
                'next_medicine_date' => $nextDate,
                'delivery'           => $row->delivery ?? '',
            ];
        });

        return response()->json([
            'draw'                 => (int) $request->input('draw', 1),
            'recordsTotal'         => $totalRecords,
            'recordsFiltered'      => $totalRecords,
            'iTotalRecords'        => $totalRecords,
            'iTotalDisplayRecords' => $totalRecords,
            'data'                 => $data,
            'aaData'               => $data,
        ]);
    }

    // ─── Issue List (daily/monthly/periodic) ──────────────────────

    public function issueList(Request $request, PermissionService $ps)
    {
        $canModule = $ps->hasPermission('m_patient_periodic_medicines') || $ps->hasPermission('m_master');
        $canInner = $ps->hasPermission('im_patient_medicine_issue');
        $canAccess = $canModule && $canInner;

        if (!$canAccess) {
            return view('periodic-medicine.issue-list', [
                'canAccess'         => false,
                'canAdd'            => false,
                'diseases'          => collect(),
                'patientCategories' => collect(),
                'bloodGroups'       => collect(),
            ]);
        }

        $canAdd = $ps->hasPermission('b_add');
        $diseases = Disease::orderBy('id')->get();
        $patientCategories = PatientCategory::orderBy('id')->get();
        $bloodGroups = \Illuminate\Support\Facades\Schema::hasTable('blood_groups')
            ? \Illuminate\Support\Facades\DB::table('blood_groups')->get()
            : collect();

        return view('periodic-medicine.issue-list', [
            'canAccess'         => true,
            'canAdd'            => $canAdd,
            'diseases'          => $diseases,
            'patientCategories' => $patientCategories,
            'bloodGroups'       => $bloodGroups,
        ]);
    }

    public function searchPatientMedicineIssue(Request $request): JsonResponse
    {
        $reportPeriod = $request->input('report_period', $request->input('period', 'Daily'));
        $searchDateStr = $request->input('search_date', $request->input('date', now()->format('d-m-Y')));
        $fromStr = $request->input('from_date', now()->format('d-m-Y'));
        $toStr = $request->input('to_date', now()->format('d-m-Y'));

        try {
            $searchDate = \Carbon\Carbon::parse($searchDateStr)->format('Y-m-d');
        } catch (\Throwable) {
            $searchDate = now()->format('Y-m-d');
        }

        try {
            $fromDate = \Carbon\Carbon::parse($fromStr)->format('Y-m-d');
        } catch (\Throwable) {
            $fromDate = now()->format('Y-m-d');
        }

        try {
            $toDate = \Carbon\Carbon::parse($toStr)->format('Y-m-d');
        } catch (\Throwable) {
            $toDate = now()->format('Y-m-d');
        }

        $query = PatientPeriodicMedicine::with(['patient', 'medicines']);
        $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';

        if ($reportPeriod === 'Daily') {
            if ($isSqlite) {
                $query->whereRaw("date(medicine_date_time) = ?", [$searchDate]);
            } else {
                $query->whereDate('medicine_date_time', $searchDate);
            }
        } elseif ($reportPeriod === 'Monthly') {
            $searchMonth = (int) $request->input('search_month', now()->month);
            $year = (int) $request->input('year', now()->year);
            if ($isSqlite) {
                $query->whereRaw("CAST(strftime('%m', medicine_date_time) AS INTEGER) = ? AND CAST(strftime('%Y', medicine_date_time) AS INTEGER) = ?", [$searchMonth, $year]);
            } else {
                $query->whereMonth('medicine_date_time', $searchMonth)
                      ->whereYear('medicine_date_time', $year);
            }
        } elseif ($reportPeriod === 'Periodic') {
            if ($isSqlite) {
                $query->whereRaw("date(medicine_date_time) BETWEEN ? AND ?", [$fromDate, $toDate]);
            } else {
                $query->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(medicine_date_time)'), [$fromDate, $toDate]);
            }
        }

        $rawSearch = $request->input('search.value');
        if ($rawSearch === null || $rawSearch === '') {
            $rawSearch = $request->input('search');
        }
        $searchValue = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($searchValue !== '') {
            $query->whereHas('patient', function ($q) use ($searchValue) {
                $q->where('patient_name', 'like', "%{$searchValue}%")
                  ->orWhere('mobile_number', 'like', "%{$searchValue}%");
            });
        }

        $totalRecords = $query->count();
        $filteredRecords = $totalRecords;

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);

        if ($length > 0) {
            $records = $query->orderByDesc('id')->skip($start)->take($length)->get();
        } else {
            $records = $query->orderByDesc('id')->get();
        }

        $diseases = Disease::pluck('disease_name', 'id');

        $data = $records->map(function ($row) use ($diseases) {
            $id = $row->id;
            $ids = array_filter(explode(',', (string) $row->disease_ids));
            sort($ids, SORT_NUMERIC);
            $diseaseNames = collect($ids)->map(fn($did) => $diseases[(int)$did] ?? null)->filter()->implode(', ');

            $medDate = $row->medicine_date_time ? \Carbon\Carbon::parse($row->medicine_date_time)->format('d-m-Y h:i A') : '';
            $nextDate = $row->next_medicine_date ? \Carbon\Carbon::parse($row->next_medicine_date)->format('d-m-Y') : '';

            return [
                'DT_RowId'           => 'row_' . $id,
                'action'             => '<a href="javascript:void(0)" onclick="issueMedicineDetailModal(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Medicine Detail"><i class="far fa-pills"></i></a> '
                                      . '<a href="javascript:void(0)" onclick="editPatientMedicineModal(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a> '
                                      . '<a href="javascript:void(0)" onclick="deletePatientIssuedMedicine(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>',
                'medicine_date_time' => $medDate,
                'next_medicine_date' => $nextDate,
                'patient_name'       => $row->patient?->patient_name ?? '',
                'mobile_number'      => $row->patient?->mobile_number ?? '',
                'address'            => $row->patient?->address ?? '',
                'diseases'           => $diseaseNames,
                'delivery'           => $row->delivery ?? '',
                'remarks'            => $row->remarks ?? '',
                'id'                 => $row->id,
                'patient_id'         => $row->patient_id,
                'medicines'          => $row->medicines->map(fn($m) => [
                    'medicine_name' => $m->medicine_name,
                    'dose_quantity' => $m->dose_quantity,
                    'dose_interval' => $m->dose_interval,
                ])->all(),
            ];
        });

        return response()->json([
            'draw'                 => (int) $request->input('draw', 1),
            'recordsTotal'         => $totalRecords,
            'recordsFiltered'      => $filteredRecords,
            'data'                 => $data,
            'iTotalRecords'        => $totalRecords,
            'iTotalDisplayRecords' => $filteredRecords,
            'aaData'               => $data,
            'success'              => true,
        ]);
    }

    public function issueListData(Request $request): JsonResponse
    {
        return $this->searchPatientMedicineIssue($request);
    }

    public function getPatientMedicineRecord(Request $request): JsonResponse
    {
        $patientId = (int) $request->input('patient_id');
        if ($patientId <= 0) {
            return response()->json([
                'pm_id'                  => 0,
                'previous_date'          => '',
                'next_date'              => '',
                'previous_medicine_list' => '<tr><td colspan="4" style="text-align: center;">Invalid Patient ID</td></tr>',
            ]);
        }

        $row = PatientPeriodicMedicine::where('patient_id', $patientId)->orderByDesc('id')->first();
        if (!$row) {
            return response()->json([
                'pm_id'                  => 0,
                'previous_date'          => '',
                'next_date'              => '',
                'previous_medicine_list' => '<tr><td colspan="4" style="text-align: center;">No Previous Medicine Record</td></tr>',
            ]);
        }

        $pmId = $row->id;
        $prevDate = $row->medicine_date_time ? \Carbon\Carbon::parse($row->medicine_date_time)->format('d-m-Y') : '';
        $nextDate = $row->next_medicine_date ? \Carbon\Carbon::parse($row->next_medicine_date)->format('d-m-Y') : '';

        $details = PatientPeriodicMedicineDetail::where('pm_id', $pmId)->get();
        $listHtml = '';
        foreach ($details as $m) {
            $mId = (int) $m->id;
            $medName = e($m->medicine_name ?? '');
            $doseQty = e($m->dose_quantity ?? '');
            $doseInt = e($m->dose_interval ?? '');

            $listHtml .= '<tr id="' . $mId . '">'
                . '<td class="tr-sm-action-buttons">'
                . '<a href="javascript:void(0)" onclick="addPrevMedicine(' . $mId . ')">+</a>'
                . '</td>'
                . '<td>' . $medName . '</td>'
                . '<td>' . $doseQty . '</td>'
                . '<td>' . $doseInt . '</td>'
                . '</tr>';
        }

        if ($listHtml === '') {
            $listHtml = '<tr><td colspan="4" style="text-align: center;">No Previous Medicine Record</td></tr>';
        }

        return response()->json([
            'pm_id'                  => $pmId,
            'previous_date'          => $prevDate,
            'next_date'              => $nextDate,
            'previous_medicine_list' => $listHtml,
        ]);
    }

    public function getPatientIssueMedicines(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['row' => null, 'table_html' => '', 'nor' => 0, 'previous_medicine_list' => '', 'previous_date' => '', 'next_date' => '']);
        }

        $record = PatientPeriodicMedicine::with('patient')->find($id);
        if (!$record) {
            return response()->json(['row' => null, 'table_html' => '', 'nor' => 0, 'previous_medicine_list' => '', 'previous_date' => '', 'next_date' => '']);
        }

        $medDateTime = $record->medicine_date_time ? \Carbon\Carbon::parse($record->medicine_date_time) : null;
        $nextMedDate = $record->next_medicine_date ? \Carbon\Carbon::parse($record->next_medicine_date) : null;
        $days = ($medDateTime && $nextMedDate) ? (int) $medDateTime->diffInDays($nextMedDate) : 0;

        $rowData = [
            'id'                 => $record->id,
            'patient_id'         => $record->patient_id,
            'patient_name'       => $record->patient?->patient_name ?? '',
            'medicine_date_time' => $medDateTime ? $medDateTime->format('d-m-Y h:i a') : '',
            'next_medicine_date' => $nextMedDate ? $nextMedDate->format('d-m-Y') : '',
            'days'               => $days,
            'delivery'           => $record->delivery ?? '',
            'remarks'            => $record->remarks ?? '',
            'disease_ids'        => $record->disease_ids ?? '',
        ];

        $details = PatientPeriodicMedicineDetail::where('pm_id', $id)->get();
        $tableHtml = '';
        $i = 0;
        foreach ($details as $t) {
            $i++;
            $medName = e($t->medicine_name ?? '');
            $doseQty = e($t->dose_quantity ?? '');
            $doseInt = e($t->dose_interval ?? '');
            $remarks = e($t->remarks ?? '');

            $tableHtml .= '<tr id="n_' . $i . '">'
                . '<td><a href="javascript:void(0)" onclick="removeEPatientMedicine(\'n_' . $i . '\')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>'
                . '<td>' . $medName . '<input type="hidden" name="medicine_name_value[]" value="' . $medName . '"></td>'
                . '<td>' . $doseQty . '<input type="hidden" name="dose_value[]" value="' . $doseQty . '"></td>'
                . '<td>' . $doseInt . '<input type="hidden" name="dose_interval_value[]" value="' . $doseInt . '"></td>'
                . '<td>' . $remarks . '<input type="hidden" name="remarks_value[]" value="' . $remarks . '"></td>'
                . '</tr>';
        }

        $prev = PatientPeriodicMedicine::where('patient_id', $record->patient_id)
            ->where('id', '<', $id)
            ->orderByDesc('id')
            ->first();

        $prevDate = '';
        $nextDate = '';
        $prevList = '';

        if ($prev) {
            $prevDate = $prev->medicine_date_time ? \Carbon\Carbon::parse($prev->medicine_date_time)->format('d-m-Y') : '';
            $nextDate = $prev->next_medicine_date ? \Carbon\Carbon::parse($prev->next_medicine_date)->format('d-m-Y') : '';

            $prevDetails = PatientPeriodicMedicineDetail::where('pm_id', $prev->id)->get();
            foreach ($prevDetails as $pm) {
                $pmId = (int) $pm->id;
                $pmName = e($pm->medicine_name ?? '');
                $pmDose = e($pm->dose_quantity ?? '');
                $pmInt = e($pm->dose_interval ?? '');

                $prevList .= '<tr id="' . $pmId . '">'
                    . '<td class="tr-sm-action-buttons">'
                    . '<a href="javascript:void(0)" onclick="addEPrevMedicine(' . $pmId . ')">+</a>'
                    . '</td>'
                    . '<td>' . $pmName . '</td>'
                    . '<td>' . $pmDose . '</td>'
                    . '<td>' . $pmInt . '</td>'
                    . '</tr>';
            }
        }

        return response()->json([
            'row'                    => $rowData,
            'table_html'             => $tableHtml,
            'nor'                    => $i,
            'previous_medicine_list' => $prevList,
            'previous_date'          => $prevDate,
            'next_date'              => $nextDate,
        ]);
    }

    public function storePatientMedicine(PatientPeriodicMedicineRequest $request): JsonResponse
    {
        $patientId = (int) $request->input('patient_id');
        $medDateStr = $request->input('medicine_date_time');
        try {
            $medicineDateTime = $medDateStr ? \Carbon\Carbon::parse($medDateStr)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $medicineDateTime = now()->format('Y-m-d H:i:s');
        }

        $days = (int) $request->input('days', 0);
        $nextMedicineDate = \Carbon\Carbon::parse($medicineDateTime)->addDays($days)->format('Y-m-d H:i:s');

        $diseaseIds = $request->input('disease_ids');
        if (is_array($diseaseIds)) {
            $diseaseIdsStr = implode(',', $diseaseIds);
        } else {
            $diseaseIdsStr = (string) $diseaseIds;
        }

        $delivery = (string) $request->input('delivery', '');
        $remarks = (string) $request->input('remarks', '');

        $pm = PatientPeriodicMedicine::create([
            'patient_id'         => $patientId,
            'medicine_date_time' => $medicineDateTime,
            'next_medicine_date' => $nextMedicineDate,
            'disease_ids'        => $diseaseIdsStr,
            'delivery'           => $delivery,
            'remarks'            => $remarks,
        ]);

        $medNames = $request->input('medicine_name_value', []);
        $doses = $request->input('dose_value', []);
        $intervals = $request->input('dose_interval_value', []);
        $remList = $request->input('remarks_value', []);

        if (is_array($medNames)) {
            for ($i = 0; $i < count($medNames); $i++) {
                if (!empty($medNames[$i])) {
                    PatientPeriodicMedicineDetail::create([
                        'pm_id'         => $pm->id,
                        'medicine_name' => $medNames[$i],
                        'dose_quantity' => $doses[$i] ?? null,
                        'dose_interval' => $intervals[$i] ?? null,
                        'remarks'       => $remList[$i] ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'pm_id'   => $pm->id,
            'success' => true,
            'message' => 'Saved Successfully.',
        ]);
    }

    public function updatePatientMedicine(PatientPeriodicMedicineRequest $request): JsonResponse
    {
        $id = (int) ($request->input('patient_periodic_medicine_id') ?: $request->input('id'));
        $pm = PatientPeriodicMedicine::findOrFail($id);

        $patientId = (int) $request->input('patient_id');
        $medDateStr = $request->input('medicine_date_time');
        try {
            $medicineDateTime = $medDateStr ? \Carbon\Carbon::parse($medDateStr)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $medicineDateTime = now()->format('Y-m-d H:i:s');
        }

        $days = (int) $request->input('days', 0);
        $nextMedicineDate = \Carbon\Carbon::parse($medicineDateTime)->addDays($days)->format('Y-m-d H:i:s');

        $diseaseIds = $request->input('disease_ids');
        if (is_array($diseaseIds)) {
            $diseaseIdsStr = implode(',', $diseaseIds);
        } else {
            $diseaseIdsStr = (string) $diseaseIds;
        }

        $delivery = (string) $request->input('delivery', '');
        $remarks = (string) $request->input('remarks', '');

        $pm->update([
            'patient_id'         => $patientId,
            'medicine_date_time' => $medicineDateTime,
            'next_medicine_date' => $nextMedicineDate,
            'disease_ids'        => $diseaseIdsStr,
            'delivery'           => $delivery,
            'remarks'            => $remarks,
        ]);

        PatientPeriodicMedicineDetail::where('pm_id', $id)->delete();

        $medNames = $request->input('medicine_name_value', []);
        $doses = $request->input('dose_value', []);
        $intervals = $request->input('dose_interval_value', []);
        $remList = $request->input('remarks_value', []);

        if (is_array($medNames)) {
            for ($i = 0; $i < count($medNames); $i++) {
                if (!empty($medNames[$i])) {
                    PatientPeriodicMedicineDetail::create([
                        'pm_id'         => $id,
                        'medicine_name' => $medNames[$i],
                        'dose_quantity' => $doses[$i] ?? null,
                        'dose_interval' => $intervals[$i] ?? null,
                        'remarks'       => $remList[$i] ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'pm_id'   => $id,
            'success' => true,
            'message' => 'Updated Successfully.',
        ]);
    }

    public function deletePatientIssuedMedicine(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if ($id > 0) {
            PatientPeriodicMedicineDetail::where('pm_id', $id)->delete();
            PatientPeriodicMedicine::where('id', $id)->delete();
        }
        return response()->json(['result' => 'Deleted', 'status' => 'Success', 'success' => true]);
    }


    // ─── Per-patient issued list ─────────────────────────────

    public function issuedList(int $patientId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_patient_periodic_medicines') || $ps->hasPermission('m_master'), 403);
        $patient = Patient::findOrFail($patientId);
        $records = PatientPeriodicMedicine::with('medicines')
            ->where('patient_id', $patientId)
            ->orderByDesc('id')->get();
        $diseases = \App\Models\Disease::pluck('disease_name', 'id');
        return view('periodic-medicine.issued-list', compact('patient', 'records', 'diseases'));
    }

    public function getIssueMedicineDetail(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['medicine_detail' => '', 'details' => []]);
        }

        $items = PatientPeriodicMedicineDetail::where('pm_id', $id)->get();
        $html = '';
        foreach ($items as $item) {
            $html .= '<tr>'
                . '<td>' . e($item->medicine_name ?? '') . '</td>'
                . '<td>' . e($item->dose_quantity ?? '') . '</td>'
                . '<td>' . e($item->dose_interval ?? '') . '</td>'
                . '<td>' . e($item->remarks ?? '') . '</td>'
                . '</tr>';
        }

        return response()->json([
            'medicine_detail' => $html,
            'details'         => $items,
        ]);
    }

    public function savePatient(Request $request): JsonResponse
    {
        $categoryId = (int) ($request->input('patient_category_id') ?: session('patient_category_id', 1));
        $name = (string) $request->input('patient_name', '');
        $guardian = (string) $request->input('guardian_name', '');
        $gender = (string) $request->input('gender', '');
        $age = (int) $request->input('age', 0);
        $bg = (string) $request->input('blood_group', '');
        $mobile = (string) $request->input('mobile_number', '');
        $altMobile = (string) $request->input('alternate_mobile_number', '');
        $photo = (string) $request->input('photo', '');
        $address = (string) $request->input('address', '');

        $patient = Patient::create([
            'patient_category_id'     => $categoryId,
            'patient_name'            => $name,
            'guardian_name'           => $guardian,
            'gender'                  => $gender,
            'age'                     => $age,
            'blood_group'             => $bg,
            'mobile_number'           => $mobile,
            'alternate_mobile_number' => $altMobile,
            'photo'                   => $photo,
            'address'                 => $address,
            'esm_name'                => '',
            'card_number'             => '',
            'service_number'          => '',
            'claim_id'                => '',
            'status'                  => 'New',
            'created_on'              => now()->toDateTimeString(),
            'user_id'                 => CurrentUser::id(),
        ]);

        return response()->json([
            'patient_id' => $patient->id,
            'success'    => true,
            'message'    => 'Patient Added Successfully.',
        ]);
    }

    public function dropdownPatients(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $query = Patient::where('status', '!=', 'Admitted');
        if ($q !== '') {
            $uhidId = \App\Support\Uhid::parse($q);   // a typed UHID finds that patient
            $query->where(function ($sq) use ($q, $uhidId) {
                $sq->where('id', 'like', "%{$q}%")
                   ->orWhere('patient_name', 'like', "%{$q}%")
                   ->orWhere('mobile_number', 'like', "%{$q}%")
                   ->when($uhidId !== null, fn ($u) => $u->orWhere('id', $uhidId));
            })->orderBy('patient_name');
        } else {
            $query->orderByDesc('id');
        }
        $patients = $query->limit(50)->get(['id', 'patient_name', 'mobile_number']);
        return response()->json($patients->map(fn($p) => [
            'id'   => $p->id,
            'text' => trim("{$p->patient_name} {$p->mobile_number} {$p->id}"),
        ]));
    }

    public function dropdownPharmaProduct(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $query = PharmaProduct::query();
        if ($q !== '') {
            $query->where('product_name', 'like', "%{$q}%");
        }
        $products = $query->limit(50)->get(['id', 'product_name']);
        return response()->json($products->map(fn($p) => [
            'id'   => $p->id,
            'text' => $p->product_name,
        ]));
    }

    public function dropdownMedicines(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $query = PharmaProduct::query();
        if ($q !== '') {
            $query->where('product_name', 'like', "%{$q}%");
        }
        $medicines = $query->limit(30)->get(['product_name']);
        return response()->json($medicines->map(fn($m) => [
            'id'   => $m->product_name,
            'text' => $m->product_name,
        ]));
    }

    public function dropdownDoseInterval(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        if (\Illuminate\Support\Facades\Schema::hasTable('medicine_frequency_types')) {
            $query = \Illuminate\Support\Facades\DB::table('medicine_frequency_types');
            if ($q !== '') {
                $query->where('display_text', 'like', "%{$q}%");
            }
            $rows = $query->orderBy('sort_order')->limit(50)->get();
            return response()->json($rows->map(fn($r) => [
                'id'   => $r->display_text ?? $r->id,
                'text' => $r->display_text ?? $r->frequency_type,
            ]));
        } else {
            $query = \Illuminate\Support\Facades\DB::table('dose_intervals');
            if ($q !== '') {
                $query->where('dose_interval', 'like', "%{$q}%");
            }
            $rows = $query->limit(50)->get();
            return response()->json($rows->map(fn($r) => [
                'id'   => $r->dose_interval ?? $r->id,
                'text' => $r->dose_interval,
            ]));
        }
    }
}

