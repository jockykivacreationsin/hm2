<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\AuditLogger;
use App\Models\Dcd;
use App\Models\Patient;
use App\Models\DcdPrescription;
use App\Models\DcdMedicine;
use App\Models\DcdMedicineTimingSelection;
use App\Models\PharmaProduct;
use App\Models\MedicineFrequencyType;
use App\Models\MedicineMealRelation;
use App\Models\MedicineTimingOfDay;
use App\Models\MedicalCondition;
use App\Models\StaffMember;
use App\Models\DcdService;
use App\Models\DcdServiceDetail;
use App\Models\DcdLabInvestigationNote;
use App\Models\DcdProcedureNote;
use App\Models\DcdPatientFile;
use App\Models\DcdDischarge;
use App\Models\BillingCharge;
use App\Models\BillingChargeDetail;
use App\Models\BillingChargePayment;
use App\Models\ChargeDepartment;
use App\Models\Bed;
use App\Models\Opd;
use App\Models\Ipd;
use App\Models\AbdmCareContext;
use App\Models\AbdmHiuConsentRequest;
use App\Http\Requests\ApproveDcdDischargeRequest;
use App\Http\Requests\DcdChargeRequest;
use App\Http\Requests\DcdCollectPaymentsRequest;
use App\Http\Requests\DcdDischargeRequest;
use App\Http\Requests\DcdPatientFileRequest;
use App\Http\Requests\DcdPrescriptionRequest;
use App\Http\Requests\DcdProcedureNoteRequest;
use App\Http\Requests\ShiftDcdToIpdRequest;
use App\Http\Requests\StoreDcdRequest;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\CurrentUser;
use App\Support\Uhid;
use App\Support\Uploads;

class DcdController extends Controller
{
    public function index(Request $request, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_dcd'), 403);
        $canNew = $ps->hasPermission('b_new_dcd');
        $opdIdParam = (int) $request->query('opd_id', 0);
        $doctors = \App\Models\StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);

        return view('dcd.index', compact('canNew', 'opdIdParam', 'doctors', 'ps'));
    }

    public function data(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = $request->integer('start', 0);
        $length = $request->integer('length', 50);
        $search = trim((string) $request->input('search.value', ''));
        $catId  = (int) session('patient_category_id', 0);

        // Filter status: defaults to 'New Admission'
        $status = (string) $request->input('filter_dcd_status', $request->input('status', 'New Admission'));

        // Ordering: 0=actions, 1=date, 2=dcd_no, 3=name, 4=gender, 5=age, 6=phone, 7=bed
        $columns = [
            'dcds.id',
            'dcds.dcd_date_time',
            'dcds.id',
            'patients.patient_name',
            'patients.gender',
            'patients.age',
            'patients.mobile_number',
            'beds.bed_name',
        ];
        $orderColIdx = $request->integer('order.0.column', 2);
        $orderBy = $columns[$orderColIdx] ?? 'dcds.id';
        $orderDir = strtolower($request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('dcds')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->leftJoin('beds', 'beds.id', '=', 'dcds.bed_id')
            ->leftJoin('floors', 'floors.id', '=', 'beds.floor_id')
            ->leftJoin('bed_types', 'bed_types.id', '=', 'beds.bed_type_id')
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->when($catId > 0, fn ($q) => $q->where('patients.patient_category_id', $catId))
            ->when($status !== 'All' && !empty($status), function ($q) use ($status) {
                if ($status === 'New Admission') {
                    $q->whereIn('dcds.status', ['New Admission', 'Admitted']);
                } else {
                    $q->where('dcds.status', $status);
                }
            })
            ->when($search !== '', function ($q) use ($search) {
                $like = "%{$search}%";
                $uhidId = Uhid::parse($search);   // a typed UHID finds that patient's day-care visits
                $q->where(function ($sq) use ($like, $uhidId) {
                    $sq->where('patients.patient_name', 'LIKE', $like)
                       ->orWhere('patients.mobile_number', 'LIKE', $like)
                       ->orWhere('patients.gender', 'LIKE', $like)
                       ->orWhere('patients.age', 'LIKE', $like)
                       ->when($uhidId !== null, fn ($u) => $u->orWhere('patients.id', $uhidId))
                       ->orWhere(DB::raw("CONCAT(COALESCE(floors.floor,''), '-', COALESCE(bed_types.bed_type,''), '-', COALESCE(ward_categories.ward_category,''), '-', COALESCE(beds.bed_name,''))"), 'LIKE', $like);
                });
            });

        $totalRecords = DB::table('dcds')->count();
        $filteredRecords = (clone $query)->count();

        $rows = $query->orderBy($orderBy, $orderDir)
            ->skip($start)
            ->take($length)
            ->get([
                'dcds.id',
                'dcds.dcd_date_time',
                'patients.patient_name',
                'patients.gender',
                'patients.age',
                'patients.mobile_number',
                'floors.floor',
                'bed_types.bed_type',
                'ward_categories.ward_category',
                'beds.bed_name',
            ]);

        $data = [];
        foreach ($rows as $row) {
            $dcdId = (int) $row->id;
            $dcdDateTime = $row->dcd_date_time ? date('d-m-Y h:i A', strtotime($row->dcd_date_time)) : '';
            $patientName = htmlspecialchars($row->patient_name ?? '', ENT_QUOTES, 'UTF-8');
            $gender = htmlspecialchars($row->gender ?? '', ENT_QUOTES, 'UTF-8');
            $age = htmlspecialchars($row->age ?? '', ENT_QUOTES, 'UTF-8');
            $mobile = htmlspecialchars($row->mobile_number ?? '', ENT_QUOTES, 'UTF-8');
            $bedParts = array_filter([$row->floor, $row->bed_type, $row->ward_category, $row->bed_name], fn ($p) => $p !== null && $p !== '');
            $bed = htmlspecialchars(implode('-', $bedParts), ENT_QUOTES, 'UTF-8');

            $actions = '<div class="tr-sm-action-buttons center">'
                . '<a href="' . route('dcd.show', $dcdId) . '" data-bs-toggle="tooltip" title="DCD Dashboard"><i class="fas fa-list"></i></a>'
                . '</div>';

            $data[] = [
                $actions,
                $dcdDateTime,
                $dcdId,
                $patientName,
                $gender,
                $age,
                $mobile,
                $bed,
            ];
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = StoreDcdRequest::validatedFrom($request);

        $opd = \App\Models\Opd::findOrFail($v['opd_id']);
        $patientId = $v['patient_id'] ?? $opd->patient_id;

        $parsed = strtotime($v['dcd_date_time']);
        $dcdDateTime = $parsed ? date('Y-m-d H:i:s', $parsed) : now()->toDateTimeString();

        DB::beginTransaction();
        try {
            $dcd = Dcd::create([
                'dcd_date_time'         => $dcdDateTime,
                'opd_id'                => $opd->id,
                'patient_id'            => $patientId,
                'consultant_doctor_id'  => $v['consultant_doctor_id'],
                'bed_id'                => $v['bed_id'],
                // NOT NULL column; Laravel turns an empty textarea into null, so store '' (as IpdController does).
                'previous_medical_note' => $v['previous_medical_note'] ?? '',
                'status'                => 'New Admission',
                'created_on'            => now()->toDateTimeString(),
                'user_id'               => CurrentUser::id(),
            ]);

            // Update patient
            $patientUpdate = ['status' => 'Admitted'];
            if (!empty($v['mobile_number'])) {
                $patientUpdate['mobile_number'] = $v['mobile_number'];
            }
            if (!empty($v['address'])) {
                $patientUpdate['address'] = $v['address'];
            }
            \App\Models\Patient::where('id', $patientId)->update($patientUpdate);

            // Allot bed
            Bed::where('id', $v['bed_id'])->update(['status' => 'Alloted']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Day Care patient admitted.',
                'dcd_id'  => $dcd->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /** Patient summary for the Create DCD modal's "OPD ID" lookup. */
    public function getOpdIdPatient(Request $request, ?int $opdId = null): JsonResponse
    {
        $opdId = $opdId ?? (int) $request->input('opd_id');
        $patientId = "";
        $mobileNumber = "";
        $address = "";
        $opdExist = "No";
        $html = "";
        $nor = 0;
        $catId = (int) session('patient_category_id', 0);

        // Check if patient is already admitted from this OPD in IPD
        $activeIpd = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipds.opd_id', $opdId)
            ->where('patients.status', 'Admitted')
            ->exists();
        if ($activeIpd) {
            $opdExist = "Yes";
        }

        $row = DB::table('patients')
            ->join('opds', 'opds.patient_id', '=', 'patients.id')
            ->where('opds.id', $opdId)
            ->when($catId > 0, fn ($q) => $q->where('patients.patient_category_id', $catId))
            ->select('patients.*')
            ->first();

        if ($row) {
            $nor = 1;
            $patientId = $row->id;
            $address = $row->address ?? '';
            $mobileNumber = $row->mobile_number ?? '';
            $photo = Uploads::url(Uploads::PATIENTS, $row->photo);

            $html = '<div class="ipd-pdwi-box">'
                . '<div class="box-left f1-mr10">'
                . '<p class="ipd-pd-name">' . e($row->patient_name) . ' (' . $row->id . ')</p>'
                . '<p class="ipd-pd-l2 mt-10">'
                . '<span><i class="fas fa-venus-mars"></i>' . e($row->gender) . '</span>'
                . '<span><i class="fas fa-tint"></i>' . e($row->blood_group) . '</span>'
                . '<span><i class="fas fa-hourglass-half"></i>' . e($row->age) . '</span>'
                . '</p>'
                . '</div>'
                . '<div class="ipd-pimg">'
                . '<img class="w100" src="' . $photo . '">'
                . '</div>'
                . '</div>';
        }

        return response()->json([
            'nor'           => $nor,
            'opd_exist'     => $opdExist,
            'patient_id'    => $patientId,
            'mobile_number' => $mobileNumber,
            'address'       => $address,
            'html'          => $html,
        ]);
    }

    public function show(int $id, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_overview'), 403);
        $dcd = Dcd::with([
            'patient',
            'doctor.designation',
            'bed.floor',
            'bed.bedType',
            'bed.wardCategory',
            'opd',
            'discharge'
        ])->findOrFail($id);

        $patient = $dcd->patient;

        app(AuditLogger::class)->viewDcd($id, $patient?->id, $patient?->patient_name);

        $opd = $dcd->opd;

        // Vitals: Always from OPD (no DCD-specific vitals table)
        $height      = $opd?->height ?? '';
        $weight      = $opd?->weight ?? '';
        $bp          = $opd?->bp ?? '';
        $bpSystolic  = '';
        $bpDiastolic = '';
        if (strpos($bp, '/') !== false) {
            $parts = explode('/', $bp);
            $bpSystolic = $parts[0] ?? '';
            $bpDiastolic = $parts[1] ?? '';
        }

        $pulse       = $opd?->pulse ?? '';
        $temperature = $opd?->temperature ?? '';
        $respiration = $opd?->respiration ?? '';
        $spo2        = $opd?->spo2 ?? '';

        // DCD patient file (symptoms/findings)
        $dcdPatientFile = \App\Models\DcdPatientFile::where('dcd_id', $id)->first();

        // Symptoms: DCD first, fallback to OPD
        $dcdSymptomsRaw = $dcdPatientFile?->chief_complaints;
        if (!empty($dcdSymptomsRaw) && trim(strip_tags($dcdSymptomsRaw)) !== '') {
            $symptoms = $dcdSymptomsRaw;
        } else {
            $symptoms = $opd?->symptoms ?? '';
        }

        // Findings: DCD physical exam fields first, fallback to OPD remarks
        $dcdFindingsParts = array_filter([
            !empty($dcdPatientFile?->gcs) ? 'GCS: ' . $dcdPatientFile->gcs : '',
            !empty($dcdPatientFile?->pallor) ? 'Pallor: ' . $dcdPatientFile->pallor : '',
            !empty($dcdPatientFile?->pain_score) ? 'Pain Score: ' . $dcdPatientFile->pain_score : '',
            !empty($dcdPatientFile?->cns) ? 'CNS: ' . $dcdPatientFile->cns : '',
            !empty($dcdPatientFile?->rs) ? 'RS: ' . $dcdPatientFile->rs : '',
            !empty($dcdPatientFile?->cvs) ? 'CVS: ' . $dcdPatientFile->cvs : '',
            !empty($dcdPatientFile?->per_abdomen) ? 'Per Abdomen: ' . $dcdPatientFile->per_abdomen : '',
        ]);

        if (!empty($dcdFindingsParts)) {
            $remarks = '<p>' . implode('</p><p>', $dcdFindingsParts) . '</p>';
        } else {
            $remarks = $opd?->remarks ?? '';
        }

        // ABDM N/M badge & statuses (M = total available HI types, including Invoice when billing data exists)
        // Newest row first: the first row seen per HI type is its current status (legacy ORDER BY id DESC).
        $careContextService = app(\App\Services\Abdm\CareContextService::class);
        $careContexts = AbdmCareContext::where('dcd_id', $id)->orderByDesc('id')->select('hi_type', 'status', 'created_on')->get();
        $abdmStatuses = [];
        foreach ($careContexts as $cc) {
            if (!isset($abdmStatuses[$cc->hi_type])) {
                $abdmStatuses[$cc->hi_type] = $cc->status;
            }
        }
        $abdmAvailableTypes = $careContextService->availableHiTypes('dcd', $id);
        $abdmAvailableCount = count($abdmAvailableTypes) ?: 3; // fallback to 3 for badge denominator
        $abdmLinkedCount    = count(array_filter($abdmStatuses, fn($s) => $s === 'linked'));
        // Nothing left to link: the overview then drops its "Link to ABDM" button (the badge stays).
        $abdmFullyLinked = !empty($dcd->patient?->abha_address)
            && $careContextService->isFullyLinked('dcd', $id);

        if ($abdmLinkedCount >= $abdmAvailableCount && $abdmLinkedCount > 0) {
            $abdmBadge = '<span class="badge bg-success" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="All ' . $abdmAvailableCount . ' DCD care contexts are linked on ABDM">ABDM Linked</span>';
        } elseif ($abdmLinkedCount > 0) {
            $abdmBadge = '<span class="badge bg-warning text-dark" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $abdmLinkedCount . ' of ' . $abdmAvailableCount . ' DCD care contexts linked on ABDM">ABDM ' . $abdmLinkedCount . '/' . $abdmAvailableCount . '</span>';
        } else {
            $abdmBadge = '<span class="badge bg-secondary" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="This admission has not been linked as a care context on ABDM yet">ABDM Not Linked</span>';
        }

        // Billing summary per department
        $depts = \App\Models\ChargeDepartment::where('active', 'Yes')->orderBy('sort_order')->get();
        $dcdBillingSummary = [];
        foreach ($depts as $dept) {
            $row = DB::table('billing_charges')
                ->selectRaw('SUM(total) as total, SUM(paid_amount) as paid_amount')
                ->where('module', 'DCD')->where('module_id', $id)
                ->where('charge_department_id', $dept->id)->first();
            $t = (float)($row->total ?? 0);
            $p = (float)($row->paid_amount ?? 0);
            $pp = $t > 0 ? round(($p / $t) * 100) : 0;
            $dcdBillingSummary[] = [
                'name'    => $dept->department_name,
                'icon'    => $dept->icon ?? 'fas fa-file-invoice-dollar',
                'total'   => (int)$t,
                'paid'    => (int)$p,
                'percent' => $pp,
            ];
        }

        // Latest prescription and medicines
        $latestPrescription = DcdPrescription::where('dcd_id', $id)->orderByDesc('id')->first();
        $latestMedicines = [];
        if ($latestPrescription) {
            if (DB::getDriverName() === 'sqlite') {
                $selectRaw = "dm.id, pp.product_name, dm.dose_quantity, ft.display_text as frequency_display, mr.relation_name as meal_name, dm.duration_days, GROUP_CONCAT(t.timing_name, ' + ') as timing_names";
            } else {
                $selectRaw = "dm.id, pp.product_name, dm.dose_quantity, ft.display_text as frequency_display, mr.relation_name as meal_name, dm.duration_days, GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ' + ') as timing_names";
            }

            $latestMedicines = DB::table('dcd_medicines as dm')
                ->join('pharma_products as pp', 'dm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'dm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'dm.meal_relation_id', '=', 'mr.id')
                ->leftJoin('dcd_medicine_timing_selections as dmts', 'dm.id', '=', 'dmts.dcd_medicine_id')
                ->leftJoin('medicine_timing_of_day as t', 'dmts.timing_id', '=', 't.id')
                ->where('dm.dcd_prescription_id', $latestPrescription->id)
                ->groupByRaw('dm.id, pp.product_name, dm.dose_quantity, ft.display_text, mr.relation_name, dm.duration_days')
                ->orderBy('dm.id')
                ->selectRaw($selectRaw)
                ->get();
        }

        // All prescriptions list
        $prescriptions = DcdPrescription::with('doctor')
            ->where('dcd_id', $id)
            ->orderByDesc('id')
            ->get();

        // TPA Providers for Shift to IPD modal
        $tpaProviders = \App\Models\TpaProvider::orderBy('tpa_name')->get();

        return view('dcd.overview', compact(
            'dcd', 'height', 'weight', 'bp', 'bpSystolic', 'bpDiastolic',
            'pulse', 'temperature', 'respiration', 'spo2',
            'symptoms', 'remarks', 'dcdPatientFile',
            'abdmLinkedCount', 'abdmFullyLinked', 'abdmBadge', 'dcdBillingSummary',
            'latestPrescription', 'latestMedicines', 'prescriptions',
            'tpaProviders', 'ps'
        ));
    }

    /** Vitals / symptoms / remarks edited on the DCD overview are stored on the underlying OPD visit. */
    public function updateOverview(Request $request, int $id): JsonResponse
    {
        $dcd = Dcd::findOrFail($id);

        return app(OpdController::class)->updateOverview($request, (int) $dcd->opd_id);
    }

    // ─── Prescriptions ─────────────────────────────────────

    public function prescriptions(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_prescription') || $ps->hasPermission('im_ipd_prescription'), 403);
        $dcd = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $prescriptions = DcdPrescription::with(['doctor', 'medicines.product'])
            ->where('dcd_id', $dcdId)->orderByDesc('prescription_date_time')->get();

        $pharmaProducts = PharmaProduct::with(['doseUnit', 'route'])
            ->where('is_active', 1)
            ->orderBy('product_name')
            ->get();
        $frequencies = MedicineFrequencyType::where('is_active', 1)->orderBy('sort_order')->get();
        $meals = MedicineMealRelation::where('is_active', 1)->orderBy('sort_order')->get();
        $timings = MedicineTimingOfDay::where('is_active', 1)->orderBy('sort_order')->get();
        $medicalConditions = MedicalCondition::where('is_active', 1)->orderBy('condition_name')->get();

        // Doctor and test pickers are rendered into the page (like the OPD / IPD prescription pages) instead of being
        // fetched over AJAX on every open: the lists are small, and the Edit modal restores a saved prescription's
        // doctor and tests with .val([...]), which needs the options to exist. Same sources as the pickers.* /
        // lab-results.dropdown.* endpoints.
        $labs = app(\App\Services\LabResultService::class);
        $doctors = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);
        $pathologyTests = $labs->testOptions('pathology');
        $radiologyTests = $labs->testOptions('radiology');
        $procedureTests = $labs->testOptions('procedure');

        $newAllowed = $ps->hasPermission('b_dcd_new_prescription') || $ps->hasPermission('b_ipd_new_prescription');
        $editAllowed = $ps->hasPermission('b_dcd_edit_prescription') || $ps->hasPermission('b_ipd_edit_prescription');
        $deleteAllowed = $ps->hasPermission('b_dcd_delete_prescription') || $ps->hasPermission('b_ipd_delete_prescription');

        return view('dcd.prescriptions', compact(
            'dcd', 'prescriptions', 'pharmaProducts', 'frequencies',
            'meals', 'timings', 'medicalConditions', 'ps',
            'newAllowed', 'editAllowed', 'deleteAllowed',
            'doctors', 'pathologyTests', 'radiologyTests', 'procedureTests'
        ));
    }

    public function storePrescription(Request $request, ?int $dcdId = null): JsonResponse
    {
        $dcdId = $dcdId ?: (int) $request->input('dcd_id');
        $ps = app(PermissionService::class);
        abort_unless($ps->hasPermission('im_dcd_prescription') || $ps->hasPermission('b_dcd_new_prescription') || $ps->hasPermission('b_ipd_new_prescription'), 403);

        DcdPrescriptionRequest::validatedFrom($request);

        $rawDateTime = $request->input('prescription_date_time');
        $parsedDateTime = date('Y-m-d H:i:s', strtotime($rawDateTime));

        $pathologyTests = is_array($request->input('pathology_tests'))
            ? implode(',', array_filter($request->input('pathology_tests')))
            : ($request->input('pathology_tests') ?? '');

        $radiologyTests = is_array($request->input('radiology_tests'))
            ? implode(',', array_filter($request->input('radiology_tests')))
            : ($request->input('radiology_tests') ?? '');

        $procedures = is_array($request->input('procedures'))
            ? implode(',', array_filter($request->input('procedures')))
            : ($request->input('procedures') ?? '');

        $rx = DcdPrescription::create([
            'prescription_date_time' => $parsedDateTime,
            'dcd_id'                 => $dcdId,
            'doctor_id'              => (int) $request->input('doctor_id'),
            'pathology_tests'        => $pathologyTests,
            'radiology_tests'        => $radiologyTests,
            'procedures'             => $procedures,
            'findings'               => $request->input('findings') ?? '',
        ]);

        $this->savePrescriptionMedicines($request, $rx->id);

        return response()->json([
            'success'             => true,
            'message'             => 'Prescription saved.',
            'dcd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
    }

    public function getPrescription(int $rxId): JsonResponse
    {
        $rx = DcdPrescription::with([
            'doctor',
            'medicines.product.doseUnit',
            'medicines.frequency',
            'medicines.mealRelation',
            'medicines.condition',
            'medicines.timings.timing'
        ])->findOrFail($rxId);

        $tableHtml = '';
        $nor = 0;
        foreach ($rx->medicines as $med) {
            $nor++;
            $medName = e($med->product?->product_name ?? 'Medicine #'.$med->pharma_product_id);
            $doseQty = e($med->dose_quantity ?? '1');
            $unitName = e($med->product?->doseUnit?->unit_name ?? '');
            $doseDisplay = trim($doseQty . ($unitName ? ' ' . $unitName : ''));
            $freqText = e($med->frequency?->display_text ?? 'Not Specified');
            $mealText = e($med->mealRelation?->relation_name ?? 'Not Specified');
            $durationDays = e($med->duration_days ?? '1');
            $condText = e($med->condition?->condition_name ?? '');

            $timingNames = [];
            $timingHidden = '';
            foreach ($med->timings as $t) {
                if ($t->timing && $t->timing->timing_name) {
                    $timingNames[] = e($t->timing->timing_name);
                }
                $timingHidden .= '<input type="hidden" name="timing_ids_'.$nor.'[]" value="'.$t->timing_id.'">';
            }
            $timingText = count($timingNames) > 0 ? implode(' + ', $timingNames) : 'Not Specified';

            $tableHtml .= '<tr id="e_dcd_pmr_'.$nor.'">
                <td><a href="javascript:void(0)" onclick="removeEDCDPrescriptionMedicine('.$nor.')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>
                <td>'.$medName.'<input type="hidden" name="pharma_product_id_value[]" value="'.$med->pharma_product_id.'"></td>
                <td>'.$doseDisplay.'<input type="hidden" name="dose_quantity_value[]" value="'.$med->dose_quantity.'"></td>
                <td>'.$freqText.'<input type="hidden" name="frequency_type_id_value[]" value="'.$med->frequency_type_id.'"></td>
                <td>'.$timingText.$timingHidden.'</td>
                <td>'.$mealText.'<input type="hidden" name="meal_relation_id_value[]" value="'.($med->meal_relation_id ?? '').'"></td>
                <td>'.$durationDays.' days<input type="hidden" name="duration_days_value[]" value="'.$med->duration_days.'"></td>
                <td>'.$condText.'<input type="hidden" name="medical_condition_id_value[]" value="'.($med->medical_condition_id ?? '').'"></td>
            </tr>';
        }

        $row = [
            'id' => $rx->id,
            'prescription_date_time' => \Carbon\Carbon::parse($rx->prescription_date_time)->format('d-m-Y h:i a'),
            'doctor_id' => $rx->doctor_id,
            'staff_name' => $rx->doctor?->staff_name ?? '',
            'pathology_tests' => $rx->pathology_tests ?? '',
            'radiology_tests' => $rx->radiology_tests ?? '',
            'procedures' => $rx->procedures ?? '',
            'findings' => $rx->findings ?? '',
        ];

        return response()->json([
            'success'    => true,
            'data'       => $rx,
            'row'        => $row,
            'table_html' => $tableHtml,
            'nor'        => $nor,
        ]);
    }

    public function updatePrescription(Request $request, ?int $rxId = null): JsonResponse
    {
        $rxId = $rxId ?: (int) $request->input('dcd_prescription_id');
        $ps = app(PermissionService::class);
        abort_unless($ps->hasPermission('im_dcd_prescription') || $ps->hasPermission('b_dcd_edit_prescription') || $ps->hasPermission('b_ipd_edit_prescription'), 403);

        $rx = DcdPrescription::findOrFail($rxId);
        DcdPrescriptionRequest::validatedFrom($request);

        $rawDateTime = $request->input('prescription_date_time');
        $parsedDateTime = date('Y-m-d H:i:s', strtotime($rawDateTime));

        $pathologyTests = is_array($request->input('pathology_tests'))
            ? implode(',', array_filter($request->input('pathology_tests')))
            : ($request->input('pathology_tests') ?? '');

        $radiologyTests = is_array($request->input('radiology_tests'))
            ? implode(',', array_filter($request->input('radiology_tests')))
            : ($request->input('radiology_tests') ?? '');

        $procedures = is_array($request->input('procedures'))
            ? implode(',', array_filter($request->input('procedures')))
            : ($request->input('procedures') ?? '');

        $rx->update([
            'prescription_date_time' => $parsedDateTime,
            'doctor_id'              => (int) $request->input('doctor_id'),
            'pathology_tests'        => $pathologyTests,
            'radiology_tests'        => $radiologyTests,
            'procedures'             => $procedures,
            'findings'               => $request->input('findings') ?? '',
        ]);

        $medIds = DcdMedicine::where('dcd_prescription_id', $rxId)->pluck('id');
        if ($medIds->isNotEmpty()) {
            DcdMedicineTimingSelection::whereIn('dcd_medicine_id', $medIds)->delete();
            DcdMedicine::whereIn('id', $medIds)->delete();
        }

        $this->savePrescriptionMedicines($request, $rxId);

        return response()->json([
            'success'             => true,
            'message'             => 'Prescription updated.',
            'dcd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
    }

    public function destroyPrescription(int $id): JsonResponse
    {
        $ps = app(PermissionService::class);
        abort_unless($ps->hasPermission('im_dcd_prescription') || $ps->hasPermission('b_dcd_delete_prescription') || $ps->hasPermission('b_ipd_delete_prescription'), 403);

        $rx = DcdPrescription::findOrFail($id);
        $medIds = DcdMedicine::where('dcd_prescription_id', $id)->pluck('id');
        if ($medIds->isNotEmpty()) {
            DcdMedicineTimingSelection::whereIn('dcd_medicine_id', $medIds)->delete();
            DcdMedicine::whereIn('id', $medIds)->delete();
        }
        $rx->delete();
        return response()->json(['success' => true, 'result' => 'OK', 'message' => 'Prescription deleted.']);
    }

    private function savePrescriptionMedicines(Request $request, int $prescriptionId): void
    {
        if ($request->has('pharma_product_id_value') && is_array($request->input('pharma_product_id_value'))) {
            $productIds = $request->input('pharma_product_id_value');
            $doseQuantities = $request->input('dose_quantity_value', []);
            $frequencyIds = $request->input('frequency_type_id_value', []);
            $mealIds = $request->input('meal_relation_id_value', []);
            $durationDays = $request->input('duration_days_value', []);
            $conditionIds = $request->input('medical_condition_id_value', []);

            for ($i = 0; $i < count($productIds); $i++) {
                $pId = (int) $productIds[$i];
                if ($pId <= 0) continue;

                $rowNum = $i + 1;
                $med = DcdMedicine::create([
                    'dcd_prescription_id'  => $prescriptionId,
                    'pharma_product_id'    => $pId,
                    'dose_quantity'        => !empty($doseQuantities[$i]) ? (float) $doseQuantities[$i] : 1.0,
                    'frequency_type_id'    => !empty($frequencyIds[$i]) ? (int) $frequencyIds[$i] : 1,
                    'meal_relation_id'     => (!empty($mealIds[$i]) && $mealIds[$i] !== '') ? (int) $mealIds[$i] : null,
                    'duration_days'        => !empty($durationDays[$i]) ? (int) $durationDays[$i] : 1,
                    'medical_condition_id' => (!empty($conditionIds[$i]) && $conditionIds[$i] !== '') ? (int) $conditionIds[$i] : null,
                    'created_on'           => now()->toDateTimeString(),
                    'updated_on'           => now()->toDateTimeString(),
                ]);

                $timingKey = 'timing_ids_' . $rowNum;
                $timingIds = $request->input($timingKey, []);
                if (is_array($timingIds)) {
                    foreach ($timingIds as $timingId) {
                        if (!empty($timingId)) {
                            DcdMedicineTimingSelection::create([
                                'dcd_medicine_id' => $med->id,
                                'timing_id'       => (int) $timingId,
                                'created_on'      => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        } elseif ($request->has('medicines') && is_array($request->input('medicines'))) {
            foreach ($request->input('medicines') as $m) {
                $pId = (int) ($m['pharma_product_id'] ?? 0);
                if ($pId <= 0) continue;

                $med = DcdMedicine::create([
                    'dcd_prescription_id'  => $prescriptionId,
                    'pharma_product_id'    => $pId,
                    'dose_quantity'        => !empty($m['dose_quantity']) ? (float) $m['dose_quantity'] : 1.0,
                    'frequency_type_id'    => !empty($m['frequency_type_id']) ? (int) $m['frequency_type_id'] : 1,
                    'meal_relation_id'     => !empty($m['meal_relation_id']) ? (int) $m['meal_relation_id'] : null,
                    'duration_days'        => !empty($m['duration_days']) ? (int) $m['duration_days'] : 1,
                    'medical_condition_id' => !empty($m['medical_condition_id']) ? (int) $m['medical_condition_id'] : null,
                    'created_on'           => now()->toDateTimeString(),
                    'updated_on'           => now()->toDateTimeString(),
                ]);

                if (!empty($m['timing_ids']) && is_array($m['timing_ids'])) {
                    foreach ($m['timing_ids'] as $timingId) {
                        DcdMedicineTimingSelection::create([
                            'dcd_medicine_id' => $med->id,
                            'timing_id'       => (int) $timingId,
                            'created_on'      => now()->toDateTimeString(),
                        ]);
                    }
                }
            }
        }
    }

    // --- Lab Investigations ---

    public function labInvestigations(int $dcdId, PermissionService $ps, \App\Services\LabResultService $labs)
    {
        abort_unless($ps->hasPermission('im_dcd_lab_investigations'), 403);
        $dcd = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $services = $labs->investigations('dcd', $dcdId);
        return view('dcd.lab-investigations', compact('dcd', 'services', 'ps'));
    }

    /** Lab conclusion only (the result-entry page saves it together with the values). */
    public function storeLabNote(Request $request, int $serviceId, PermissionService $ps, \App\Services\LabResultService $labs): JsonResponse
    {
        abort_unless($ps->hasPermission('im_dcd_lab_investigations'), 403);
        $v = $request->validate(['note' => 'nullable|string|max:65000']);
        $labs->service('dcd', $serviceId);
        $labs->saveConclusion('dcd', $serviceId, $v['note'] ?? '', (int) CurrentUser::id());
        return response()->json(['success' => true]);
    }

    // --- Charges ---

    public function charges(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_charges'), 403);
        $dcd = Dcd::with(['patient.patientCategory', 'doctor'])->findOrFail($dcdId);
        $patient_category_id = (int) ($dcd->patient?->patient_category_id ?? 0);
        $charges = BillingCharge::with(['chargeDepartment', 'payments', 'details.charge'])
            ->where('module', 'DCD')
            ->where('module_id', $dcdId)
            ->orderByDesc('id')
            ->get();
        $chargeDepts = ChargeDepartment::where('active', 'Yes')->orderBy('department_name')->get();
        $prescriptions = DcdPrescription::where('dcd_id', $dcdId)->orderByDesc('id')->get();

        return view('dcd.charges', compact('dcd', 'charges', 'chargeDepts', 'prescriptions', 'patient_category_id', 'ps'));
    }

    public function destroyCharge(int $id): JsonResponse
    {
        BillingCharge::forModule('DCD')->findOrFail($id);
        DB::beginTransaction();
        try {
            BillingChargeDetail::where('module_charge_id', $id)->delete();
            BillingChargePayment::where('billing_charge_id', $id)->delete();
            $svc = DcdService::where('dcd_charge_id', $id)->first();
            if ($svc) {
                DcdServiceDetail::where('dcd_service_id', $svc->id)->delete();
                $svc->delete();
            }
            BillingCharge::where('id', $id)->delete();
            DB::commit();
            return response()->json(['success' => true, 'result' => 'OK', 'message' => 'Deleted Successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function storeCharge(Request $request, ?int $dcdId = null): JsonResponse
    {
        DcdChargeRequest::validatedFrom($request);
        $dcdId = $dcdId ?: (int) $request->input('dcd_id');
        $charge_date_time_raw = $request->input('charge_date_time');
        try {
            $charge_date_time = $charge_date_time_raw ? \Carbon\Carbon::parse($charge_date_time_raw)->toDateTimeString() : now()->toDateTimeString();
        } catch (\Exception $e) {
            $charge_date_time = now()->toDateTimeString();
        }

        $deptIdArray = $request->input('charge_department_id_value', []);
        if (empty($deptIdArray) && $request->filled('charge_department_id')) {
            $singleDept = (int) $request->input('charge_department_id');
            $singleAmount = (float) $request->input('amount', 0);
            $singleQty = (int) $request->input('quantity', 1);
            $singleDisc = (float) $request->input('discount_percentage', 0);
            $singleDiscAmt = ($singleAmount * $singleQty * $singleDisc) / 100;
            $singleTotal = ($singleAmount * $singleQty) - $singleDiscAmt;

            $deptIdArray = [$singleDept];
            $chargeIdArray = [$request->input('charge_id', 0)];
            $qtyArray = [$singleQty];
            $amountArray = [$singleAmount];
            $discPctArray = [$singleDisc];
            $discAmtArray = [$singleDiscAmt];
            $totalArray = [$singleTotal];
        } else {
            $chargeIdArray = $request->input('charge_id_value', []);
            $qtyArray = $request->input('quantity_value', []);
            $amountArray = $request->input('amount_value', []);
            $discPctArray = $request->input('discount_percentage_value', []);
            $discAmtArray = $request->input('discount_amount_value', []);
            $totalArray = $request->input('total_value', []);
        }

        if (empty($deptIdArray)) {
            return response()->json(['success' => false, 'message' => 'Please enter some data.'], 422);
        }

        $groups = [];
        for ($i = 0; $i < count($deptIdArray); $i++) {
            $deptId = (int) $deptIdArray[$i];
            if (!isset($groups[$deptId])) {
                $groups[$deptId] = [];
            }
            $groups[$deptId][] = $i;
        }

        $addon_discount_mode = $request->input('addon_discount_mode', 'Percentage');
        $addon_discount_value = (float) $request->input('addon_discount_value', 0);
        $addon_discount_amount_total = (float) ($request->input('dcd_charges_addon_discount') ?? $request->input('addon_discount_amount') ?? 0);
        $paid_amount_total = (float) $request->input('paid_amount', 0);
        $payment_mode = (string) $request->input('payment_mode', '');
        $ih = $request->input('ih') === 'Yes' ? 'Yes' : '';

        $sp_mode_array = $request->input('sp_mode', []);
        $sp_amount_array = $request->input('sp_amount', []);

        $invoice_number_two = 0;
        if ($ih === 'Yes') {
            $invoice_number_two = (int) (BillingCharge::max('invoice_number_two') ?? 0) + 1;
        }

        $group_data = [];
        $overall_subtotal = 0.0;
        foreach ($groups as $deptId => $indexes) {
            $g_amount = 0.0;
            $g_discount = 0.0;
            foreach ($indexes as $idx) {
                $qty = (float) ($qtyArray[$idx] ?? 1);
                $g_amount += (float) ($amountArray[$idx] ?? 0) * $qty;
                $g_discount += (float) ($discAmtArray[$idx] ?? 0) * $qty;
            }
            $g_subtotal = $g_amount - $g_discount;
            $group_data[$deptId] = [
                'amount' => $g_amount,
                'discount' => $g_discount,
                'subtotal' => $g_subtotal,
            ];
            $overall_subtotal += $g_subtotal;
        }

        $dept_ids = array_keys($group_data);
        $dept_count = count($dept_ids);
        $first_dcd_charge_id = 0;
        $created_dcd_charge_ids = [];

        $running_addon_discount = 0.0;
        $remaining_paid_pool = $paid_amount_total;
        $group_addon = [];
        $group_total = [];
        $group_paid = [];

        for ($gi = 0; $gi < $dept_count; $gi++) {
            $deptId = $dept_ids[$gi];
            $is_last_group = ($gi === $dept_count - 1);
            $g = $group_data[$deptId];
            $ratio = ($overall_subtotal > 0) ? ($g['subtotal'] / $overall_subtotal) : (1 / max(1, $dept_count));

            if ($is_last_group) {
                $g_addon_discount_amount = round($addon_discount_amount_total - $running_addon_discount, 2);
            } else {
                $g_addon_discount_amount = round($addon_discount_amount_total * $ratio, 2);
                $running_addon_discount += $g_addon_discount_amount;
            }
            $g_total = round($g['subtotal'] - $g_addon_discount_amount, 2);

            $g_paid_amount = round(min($g_total, $remaining_paid_pool), 2);
            if ($g_paid_amount < 0) { $g_paid_amount = 0; }
            $remaining_paid_pool = round($remaining_paid_pool - $g_paid_amount, 2);

            $group_addon[$deptId] = $g_addon_discount_amount;
            $group_total[$deptId] = $g_total;
            $group_paid[$deptId] = $g_paid_amount;
        }

        $sp_remaining = [];
        for ($i = 0; $i < count($sp_mode_array); $i++) {
            $sp_remaining[$i] = (float) $sp_amount_array[$i];
        }
        $sp_pointer = 0;
        $group_payments = [];
        foreach ($dept_ids as $deptId) {
            $need = $group_paid[$deptId];
            $payments = [];
            while ($need > 0.004 && $sp_pointer < count($sp_mode_array)) {
                $avail = $sp_remaining[$sp_pointer];
                if ($avail <= 0.004) { $sp_pointer++; continue; }
                $take = round(min($need, $avail), 2);
                if ($take > 0) {
                    $payments[] = ['mode' => $sp_mode_array[$sp_pointer], 'amount' => $take];
                }
                $sp_remaining[$sp_pointer] = round($avail - $take, 2);
                $need = round($need - $take, 2);
                if ($sp_remaining[$sp_pointer] <= 0.004) { $sp_pointer++; }
            }
            if (empty($payments) && $group_paid[$deptId] > 0 && !empty($payment_mode)) {
                $payments[] = ['mode' => $payment_mode, 'amount' => $group_paid[$deptId]];
            }
            $group_payments[$deptId] = $payments;
        }

        DB::beginTransaction();
        try {
            for ($gi = 0; $gi < $dept_count; $gi++) {
                $charge_dept_id = $dept_ids[$gi];
                $g = $group_data[$charge_dept_id];
                $g_amount = $g['amount'];
                $g_discount = $g['discount'];
                $g_addon_discount_amount = $group_addon[$charge_dept_id];
                $g_total = $group_total[$charge_dept_id];
                $g_paid_amount = $group_paid[$charge_dept_id];

                $deptObj = ChargeDepartment::find($charge_dept_id);
                $department_name = $deptObj?->department_name ?? 'Charge';
                $is_service = ($deptObj?->services ?? 'No') === 'Yes';

                $invoice_number = (int) (BillingCharge::where('charge_department_id', $charge_dept_id)->max('invoice_number') ?? 0) + 1;

                $bc = BillingCharge::create([
                    'invoice_number'        => $invoice_number,
                    'invoice_number_two'    => $invoice_number_two,
                    'charge_date_time'      => $charge_date_time,
                    'module'                => 'DCD',
                    'module_id'             => $dcdId,
                    'charge_title'          => $department_name,
                    'charge_department_id'  => $charge_dept_id,
                    'amount'                => $g_amount,
                    'discount'              => $g_discount,
                    'addon_discount_mode'   => $addon_discount_mode,
                    'addon_discount_value'  => $addon_discount_value,
                    'addon_discount_amount' => $g_addon_discount_amount,
                    'total'                 => $g_total,
                    'paid_amount'           => $g_paid_amount,
                    'payment_mode'          => $payment_mode ?: (!empty($group_payments[$charge_dept_id]) ? implode(', ', array_unique(array_column($group_payments[$charge_dept_id], 'mode'))) : ''),
                    'is_hidden'             => $ih,
                    'created_on'            => now()->toDateTimeString(),
                    'user_id'               => CurrentUser::id(),
                ]);

                $bc->update(['charge_title' => $department_name . ' ' . $bc->id]);
                $created_dcd_charge_ids[] = $bc->id;
                if ($first_dcd_charge_id === 0) {
                    $first_dcd_charge_id = $bc->id;
                }

                foreach ($group_payments[$charge_dept_id] as $pm) {
                    if ($pm['amount'] > 0) {
                        BillingChargePayment::create([
                            'billing_charge_id' => $bc->id,
                            'payment_date_time' => $charge_date_time,
                            'payment_mode'      => $pm['mode'],
                            'amount'            => $pm['amount'],
                        ]);
                    }
                }

                $dcd_service_id = 0;
                if ($is_service) {
                    $svc = DcdService::create([
                        'service_date_time'    => $charge_date_time,
                        'service_title'        => $department_name . ' ' . $bc->id,
                        'dcd_id'               => $dcdId,
                        'dcd_charge_id'        => $bc->id,
                        'charge_department_id' => $charge_dept_id,
                        'status'               => 'New',
                        'created_on'           => now()->toDateTimeString(),
                        'user_id'              => CurrentUser::id(),
                    ]);
                    $dcd_service_id = $svc->id;
                }

                foreach ($groups[$charge_dept_id] as $idx) {
                    $cId = (int) ($chargeIdArray[$idx] ?? 0);
                    $qty = (float) ($qtyArray[$idx] ?? 1);
                    $amt = (float) ($amountArray[$idx] ?? 0);
                    $dPct = (float) ($discPctArray[$idx] ?? 0);
                    $dAmt = (float) ($discAmtArray[$idx] ?? 0);
                    $tot = (float) ($totalArray[$idx] ?? 0);

                    $bcd = BillingChargeDetail::create([
                        'module_charge_id'    => $bc->id,
                        'charge_id'           => $cId,
                        'quantity'            => $qty,
                        'amount'              => $amt,
                        'discount_percentage' => $dPct,
                        'discount_amount'     => $dAmt,
                        'total'               => $tot,
                    ]);

                    if ($dcd_service_id > 0) {
                        DcdServiceDetail::create([
                            'dcd_service_id'        => $dcd_service_id,
                            'dcd_charge_detail_id'  => $bcd->id,
                            'charge_id'             => $cId,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success'        => true,
                'message'        => 'Charge saved.',
                'dcd_charge_id'  => $first_dcd_charge_id,
                'dcd_charge_ids' => $created_dcd_charge_ids,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function getCharge(int $id): JsonResponse
    {
        $charge = BillingCharge::forModule('DCD')->with(['chargeDepartment', 'details.charge'])->find($id);
        if (!$charge) {
            return response()->json(['error' => 'Invalid ID'], 404);
        }
        $table_html = '';
        $i = 0;
        foreach ($charge->details as $detail) {
            $i++;
            $cName = e($detail->charge?->charge_name ?? '');
            $table_html .= '<tr id="chr_' . $i . '"><td><a href="javascript:void(0)" onclick="removeEDCDChargeDetail(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>' . $cName . '<input type="hidden" name="charge_id_value[]" value="' . $detail->charge_id . '"></td><td>' . $detail->amount . '<input type="hidden" name="amount_value[]" value="' . $detail->amount . '"></td><td>' . $detail->discount_percentage . '<input type="hidden" name="discount_percentage_value[]" value="' . $detail->discount_percentage . '"></td><td>' . $detail->discount_amount . '<input type="hidden" name="discount_amount_value[]" value="' . $detail->discount_amount . '"></td><td>' . $detail->quantity . '<input type="hidden" name="quantity_value[]" value="' . $detail->quantity . '"></td><td>' . $detail->total . '<input type="hidden" name="total_value[]" value="' . $detail->total . '"></td></tr>';
        }

        $edit = 'Yes';
        $setting = \App\Models\IdFieldSetting::first();
        $pathologyId = $setting?->pathology_department_id ?? 3;
        $radiologyId = $setting?->radiology_department_id ?? 4;
        $svc = DcdService::where('dcd_charge_id', $id)
            ->where(function($q) use ($pathologyId, $radiologyId) {
                $q->where('charge_department_id', $pathologyId)
                  ->orWhere('charge_department_id', $radiologyId);
            })
            ->first();
        if ($svc && $svc->status !== 'New') {
            $edit = 'No';
        }

        return response()->json([
            'row' => [
                'id'                   => $charge->id,
                'charge_date_time'     => \Carbon\Carbon::parse($charge->charge_date_time)->format('d-m-Y h:i a'),
                'charge_title'         => $charge->charge_title,
                'charge_department_id' => $charge->charge_department_id,
                'department_name'      => $charge->chargeDepartment?->department_name ?? '',
                'addon_discount_mode'  => $charge->addon_discount_mode,
                'addon_discount_value' => $charge->addon_discount_value,
                'paid_amount'          => $charge->paid_amount,
                'payment_mode'         => $charge->payment_mode,
            ],
            'table_html' => $table_html,
            'nor'        => $i,
            'edit'       => $edit,
        ]);
    }

    public function getSplitPayments(int $id): JsonResponse
    {
        BillingCharge::forModule('DCD')->findOrFail($id);
        $payments = BillingChargePayment::where('billing_charge_id', $id)->get();
        $html = '';
        $i = 0;
        $total = 0.0;
        foreach ($payments as $p) {
            $i++;
            $mode = e($p->payment_mode);
            $amount = (float) $p->amount;
            $total += $amount;
            $html .= "<tr id='e_sp_{$i}'><td><a href='javascript:void(0)' onclick='delESplitPayment({$i});'>x</a></td><td>{$mode}<input name='sp_mode[]' type='hidden' value='{$mode}'></td><td>{$amount}<input id='e_sp_amount_{$i}' name='sp_amount[]' type='hidden' value='{$amount}'></td></tr>";
        }
        return response()->json(['html' => $html, 'nor' => $i, 'total' => number_format($total, 2, '.', '')]);
    }

    public function updateCharge(Request $request, ?int $id = null): JsonResponse
    {
        $id = $id ?: (int) ($request->input('dcd_charge_id') ?: $request->input('id'));
        $charge = BillingCharge::forModule('DCD')->findOrFail($id);
        DcdChargeRequest::validatedFrom($request);
        $charge_date_time_raw = $request->input('charge_date_time');
        try {
            $charge_date_time = $charge_date_time_raw ? \Carbon\Carbon::parse($charge_date_time_raw)->toDateTimeString() : $charge->charge_date_time;
        } catch (\Exception $e) {
            $charge_date_time = $charge->charge_date_time;
        }

        $charge_dept_id = (int) ($request->input('charge_department_id') ?: $charge->charge_department_id);
        $deptObj = ChargeDepartment::find($charge_dept_id);
        $is_service = ($deptObj?->services ?? 'No') === 'Yes';

        DB::beginTransaction();
        try {
            BillingChargePayment::where('billing_charge_id', $id)->delete();
            $spModes = $request->input('sp_mode', []);
            $spAmounts = $request->input('sp_amount', []);
            for ($i = 0; $i < count($spModes); $i++) {
                $amt = (float) ($spAmounts[$i] ?? 0);
                if ($amt > 0) {
                    BillingChargePayment::create([
                        'billing_charge_id' => $id,
                        'payment_date_time' => $charge_date_time,
                        'payment_mode'      => $spModes[$i],
                        'amount'            => $amt,
                    ]);
                }
            }

            $charge->update([
                'charge_date_time'      => $charge_date_time,
                'charge_title'          => $request->input('charge_title', $charge->charge_title),
                'charge_department_id'  => $charge_dept_id,
                'amount'                => (float) $request->input('dcd_charges_amount', $charge->amount),
                'discount'              => (float) $request->input('dcd_charges_discount', $charge->discount),
                'addon_discount_mode'   => $request->input('addon_discount_mode', $charge->addon_discount_mode),
                'addon_discount_value'  => (float) $request->input('addon_discount_value', $charge->addon_discount_value),
                'addon_discount_amount' => (float) $request->input('dcd_charges_addon_discount', $charge->addon_discount_amount),
                'total'                 => (float) $request->input('dcd_charges_total', $charge->total),
                'paid_amount'           => (float) $request->input('paid_amount', $charge->paid_amount),
                'payment_mode'          => (string) $request->input('payment_mode', $charge->payment_mode),
                'user_id'               => CurrentUser::id(),
            ]);

            BillingChargeDetail::where('module_charge_id', $id)->delete();

            $oldSvc = DcdService::where('dcd_charge_id', $id)->first();
            if ($oldSvc) {
                DcdServiceDetail::where('dcd_service_id', $oldSvc->id)->delete();
                $oldSvc->delete();
            }

            $newSvcId = 0;
            if ($is_service) {
                $newSvc = DcdService::create([
                    'service_date_time'    => $charge_date_time,
                    'service_title'        => $charge->charge_title,
                    'dcd_id'               => $charge->module_id,
                    'dcd_charge_id'        => $id,
                    'charge_department_id' => $charge_dept_id,
                    'status'               => 'New',
                    'created_on'           => now()->toDateTimeString(),
                    'user_id'              => CurrentUser::id(),
                ]);
                $newSvcId = $newSvc->id;
            }

            $chargeIds = $request->input('charge_id_value', []);
            $quantities = $request->input('quantity_value', []);
            $amounts = $request->input('amount_value', []);
            $discPcts = $request->input('discount_percentage_value', []);
            $discAmts = $request->input('discount_amount_value', []);
            $totals = $request->input('total_value', []);

            for ($i = 0; $i < count($chargeIds); $i++) {
                $bcd = BillingChargeDetail::create([
                    'module_charge_id'    => $id,
                    'charge_id'           => (int) $chargeIds[$i],
                    'quantity'            => (float) ($quantities[$i] ?? 1),
                    'amount'              => (float) ($amounts[$i] ?? 0),
                    'discount_percentage' => (float) ($discPcts[$i] ?? 0),
                    'discount_amount'     => (float) ($discAmts[$i] ?? 0),
                    'total'               => (float) ($totals[$i] ?? 0),
                ]);
                if ($newSvcId > 0) {
                    DcdServiceDetail::create([
                        'dcd_service_id'       => $newSvcId,
                        'dcd_charge_detail_id' => $bcd->id,
                        'charge_id'            => (int) $chargeIds[$i],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'dcd_charge_id' => $id, 'result' => 'OK', 'message' => 'Saved Successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function collectPayments(Request $request, ?int $dcdId = null): JsonResponse
    {
        DcdCollectPaymentsRequest::validatedFrom($request);
        $raw_dt = $request->input('payment_date_time');
        try {
            $payment_date_time = $raw_dt ? \Carbon\Carbon::parse($raw_dt)->toDateTimeString() : now()->toDateTimeString();
        } catch (\Exception $e) {
            $payment_date_time = now()->toDateTimeString();
        }

        $cb = $request->input('cb', []);
        $spModes = $request->input('sp_mode', []);
        $spAmounts = $request->input('sp_amount', []);

        $payments = [];
        for ($i = 0; $i < count($spModes); $i++) {
            $payments[] = [
                'amount' => (float) ($spAmounts[$i] ?? 0),
                'payment_mode' => $spModes[$i],
            ];
        }

        $billing_charges = BillingCharge::whereIn('id', $cb)->get();
        $used_amounts = [
            'Cash' => 0.0,
            'UPI' => 0.0,
            'Debit Card' => 0.0,
            'Credit Card' => 0.0,
        ];

        DB::beginTransaction();
        try {
            foreach ($billing_charges as $charge) {
                $total_bal = (float) $charge->total - (float) $charge->paid_amount;
                $payment_modes_used = [];

                foreach ($payments as $payment) {
                    $amount = $payment['amount'];
                    $mode = $payment['payment_mode'];
                    $used = $used_amounts[$mode] ?? 0.0;
                    $remaining_amount = $amount - $used;

                    if ($remaining_amount > 0 && $total_bal > 0) {
                        $paid = min($total_bal, $remaining_amount);
                        BillingChargePayment::create([
                            'billing_charge_id' => $charge->id,
                            'payment_date_time' => $payment_date_time,
                            'amount'            => $paid,
                            'payment_mode'      => $mode,
                        ]);
                        $total_bal -= $paid;
                        $used_amounts[$mode] = ($used_amounts[$mode] ?? 0.0) + $paid;

                        if (!in_array($mode, $payment_modes_used)) {
                            $payment_modes_used[] = $mode;
                        }
                    }
                }

                if (!empty($payment_modes_used)) {
                    $existing_modes = array_filter(array_map('trim', explode(',', (string) $charge->payment_mode)));
                    $merged_modes = array_unique(array_merge($existing_modes, $payment_modes_used));
                    $new_paid = (float) $charge->total - max(0, $total_bal);
                    $charge->update([
                        'paid_amount'  => $new_paid,
                        'payment_mode' => implode(', ', $merged_modes),
                    ]);
                }
            }

            DB::commit();
            return response()->json(['result' => 'OK', 'success' => true, 'message' => 'Payments collected.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['result' => 'ERROR', 'success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    // ─── Discharge ─────────────────────────────────────────

    /**
     * Discharge form (legacy dcd-discharge.php): patient overview, symptoms / findings / vitals of the OPD visit, the
     * consultant doctor, investigations and procedures pre-filled from this stay, the treatment given (latest
     * prescription), follow-up and a note. Submitting it sends the discharge for approval.
     */
    public function dischargeForm(int $id, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('b_dcd_discharge_patient'), 403);
        $dcd = Dcd::with(['patient', 'opd', 'doctor.designation', 'bed.floor', 'bed.bedType', 'bed.wardCategory'])->findOrFail($id);

        // Investigations: what the discharge already saved, else filled from this DCD's lab / radiology tests with
        // their results and notes (same as the IPD discharge).
        $investigations = collect();
        $savedDischargeId = DB::table('dcd_discharge')->where('dcd_id', $id)->value('id');
        if ($savedDischargeId) {
            $investigations = DB::table('dcd_discharge_investigations')->where('discharge_id', $savedDischargeId)->orderBy('id')->get(['investigation', 'result']);
        }
        if ($investigations->isEmpty()) {
            $investigations = $this->dischargeInvestigations($id);
        }

        // Procedures: billed DCD charges of a department with category 'Procedure'.
        $procedures = DB::table('billing_charges')
            ->join('charge_departments', 'charge_departments.id', '=', 'billing_charges.charge_department_id')
            ->where('charge_departments.category', 'Procedure')
            ->where('billing_charges.module', 'DCD')->where('billing_charges.module_id', $id)
            ->orderBy('billing_charges.id')->pluck('billing_charges.charge_title');

        // Treatment given: the medicines of the latest prescription that has any.
        $medicines = collect();
        $latestPrescriptionId = DB::table('dcd_prescriptions')
            ->join('dcd_medicines', 'dcd_medicines.dcd_prescription_id', '=', 'dcd_prescriptions.id')
            ->where('dcd_prescriptions.dcd_id', $id)
            ->orderByDesc('dcd_prescriptions.id')->value('dcd_prescriptions.id');
        if ($latestPrescriptionId) {
            $timingExpr = DB::getDriverName() === 'sqlite'
                ? "GROUP_CONCAT(t.timing_name, ' + ')"
                : "GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ' + ')";
            $medicines = DB::table('dcd_medicines as dm')
                ->join('pharma_products as pp', 'dm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'dm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'dm.meal_relation_id', '=', 'mr.id')
                ->leftJoin('dcd_medicine_timing_selections as dmts', 'dm.id', '=', 'dmts.dcd_medicine_id')
                ->leftJoin('medicine_timing_of_day as t', 'dmts.timing_id', '=', 't.id')
                ->where('dm.dcd_prescription_id', $latestPrescriptionId)
                ->groupBy('dm.id', 'pp.product_name', 'dm.dose_quantity', 'ft.display_text', 'mr.relation_name', 'dm.duration_days')
                ->orderBy('dm.id')
                ->selectRaw("dm.id, pp.product_name, dm.dose_quantity, ft.display_text as frequency_display, mr.relation_name as meal_name, dm.duration_days, {$timingExpr} as timing_names")
                ->get();
        }

        return view('dcd.discharge', [
            'dcd'                 => $dcd,
            'admissionFormatted'  => $dcd->dcd_date_time ? date('d-m-Y h:i A', strtotime($dcd->dcd_date_time)) : '',
            'currentFormatted'    => date('d-m-Y h:i a'),
            'investigations'      => $investigations,
            'procedures'          => $procedures,
            'medicines'           => $medicines,
            'ps'                  => $ps,
        ]);
    }

    /**
     * Investigations to pre-fill on the discharge form, like IpdController::getDischargeInvestigations: one entry per
     * test charged on a lab / radiology service of the DCD. The result is the saved sub-test values ("Hb: 14, WBC: 7800"),
     * or else the radiology / lab note of the service. Repeated tests keep the first non-empty result.
     *
     * @return \Illuminate\Support\Collection<int, object{investigation: string, charge_id: int, result: string}>
     */
    private function dischargeInvestigations(int $dcdId): \Illuminate\Support\Collection
    {
        $settings = \App\Models\IdFieldSetting::first();
        $pathologyDeptId = $settings?->pathology_department_id ?? 3;
        $radiologyDeptId = $settings?->radiology_department_id ?? 4;

        $services = DB::table('dcd_services')
            ->join('charge_departments', 'charge_departments.id', '=', 'dcd_services.charge_department_id')
            ->where('dcd_services.dcd_id', $dcdId)
            ->where(function ($q) use ($pathologyDeptId, $radiologyDeptId) {
                $q->whereIn('charge_departments.category', ['Lab', 'Radiology'])
                    ->orWhereIn('charge_departments.id', [$pathologyDeptId, $radiologyDeptId]);
            })
            ->orderBy('dcd_services.id')
            ->get(['dcd_services.id as service_id', 'dcd_services.service_title', 'charge_departments.department_name', 'charge_departments.category']);
        if ($services->isEmpty()) {
            return collect();
        }
        $serviceIds = $services->pluck('service_id')->all();

        $details = DB::table('dcd_service_detail')
            ->join('charges', 'charges.id', '=', 'dcd_service_detail.charge_id')
            ->whereIn('dcd_service_detail.dcd_service_id', $serviceIds)
            ->orderBy('dcd_service_detail.id')
            ->get(['dcd_service_detail.id as detail_id', 'dcd_service_detail.dcd_service_id', 'charges.id as charge_id', 'charges.charge_name'])
            ->groupBy('dcd_service_id');

        $results = DB::table('dcd_lab_test_results as r')
            ->join('biochemistry_sub_tests as st', 'st.id', '=', 'r.test_id')
            ->where('r.dcd_id', $dcdId)
            ->orderBy('r.id')
            ->get(['r.dcd_service_detail_id', 'r.test_value', 'st.sub_test_name'])
            ->groupBy('dcd_service_detail_id');

        $note = fn ($table, $ids) => DB::table($table)->whereIn('dcd_service_id', $ids)->pluck('note', 'dcd_service_id')
            ->map(fn ($n) => trim(strip_tags(html_entity_decode((string) $n, ENT_QUOTES, 'UTF-8'))));
        $labNotes = $note('dcd_lab_investigation_notes', $serviceIds);
        $radNotes = $note('dcd_radiology_notes', $serviceIds);

        $investigations = collect();
        $index = [];   // lower-cased name => position in $investigations
        $add = function (string $name, int $chargeId, string $result) use ($investigations, &$index) {
            $key = strtolower($name);
            if (!isset($index[$key])) {
                $index[$key] = $investigations->count();
                $investigations->push((object) ['investigation' => $name, 'charge_id' => $chargeId, 'result' => $result]);
            } elseif ($result !== '' && $investigations[$index[$key]]->result === '') {
                $investigations[$index[$key]]->result = $result;
            }
        };

        foreach ($services as $svc) {
            $isRad = stripos((string) $svc->department_name, 'radiology') !== false || strtolower((string) $svc->category) === 'radiology';
            $radNote = (string) ($radNotes[$svc->service_id] ?? '');
            $labNote = (string) ($labNotes[$svc->service_id] ?? '');
            $serviceDetails = $details->get($svc->service_id, collect());

            // A service without test lines is listed under its own title, with the note as its result.
            if ($serviceDetails->isEmpty()) {
                $add($svc->service_title ?: ($svc->department_name ?: 'Investigation'), 0, $isRad ? $radNote : $labNote);
                continue;
            }

            foreach ($serviceDetails as $detail) {
                $parts = [];
                foreach ($results->get($detail->detail_id, collect()) as $r) {
                    $value = trim((string) $r->test_value);
                    if ($value !== '') {
                        $parts[] = $r->sub_test_name . ': ' . $value;
                    }
                }
                $result = $parts ? implode(', ', $parts) : (($isRad && $radNote !== '') ? $radNote : $labNote);
                $add($detail->charge_name, (int) $detail->charge_id, $result);
            }
        }

        return $investigations;
    }

    /**
     * Submit the discharge for approval (legacy ajax.php create_dcd_discharge): stores the discharge with approved =
     * 'No' plus its investigations, procedures and medication given, and marks the DCD 'Discharged'. The bed and the
     * patient are only released when the discharge is approved (approveDischarge).
     */
    public function discharge(Request $request, int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_dcd_discharge_patient'), 403);
        $dcd = Dcd::findOrFail($id);
        if ($dcd->status === 'Discharged') {
            return response()->json(['success' => false, 'message' => 'Discharge already submitted for approval.'], 422);
        }
        $v = DcdDischargeRequest::validatedFrom($request);

        try {
            $dischargeAt = \Carbon\Carbon::parse($v['discharge_date_time'])->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $dischargeAt = now()->format('Y-m-d H:i:s');
        }

        DB::beginTransaction();
        try {
            // The columns are NOT NULL: blanks are stored as '' (an empty form field reaches PHP as null).
            $discharge = DcdDischarge::updateOrCreate(['dcd_id' => $id], [
                'discharge_date_time'    => $dischargeAt,
                'diagnosis'              => $v['diagnosis'] ?? '',
                'condition_on_discharge' => $v['condition_on_discharge'] ?? '',
                'follow_up_detail'       => $v['follow_up_detail'] ?? '',
                'note'                   => $v['note'] ?? '',
                'urgent_care_condition'  => $v['urgent_care_condition'] ?? '',
                'discharged_to'          => $v['discharged_to'] ?? '',
                'approved'               => 'No',
                'approved_doctor_id'     => $v['approved_doctor_id'] ?? null,
            ]);

            $names = (array) ($v['dcd_discharge_investigation_name'] ?? []);
            $results = (array) ($v['dcd_discharge_investigation_value'] ?? []);
            DB::table('dcd_discharge_investigations')->where('discharge_id', $discharge->id)->delete();
            foreach ($names as $i => $name) {
                if (trim((string) $name) !== '') {
                    DB::table('dcd_discharge_investigations')->insert([
                        'discharge_id' => $discharge->id, 'investigation' => $name, 'result' => (string) ($results[$i] ?? ''),
                    ]);
                }
            }

            DB::table('dcd_discharge_procedures')->where('discharge_id', $discharge->id)->delete();
            foreach ((array) ($v['dcd_discharge_procedure'] ?? []) as $procedure) {
                if (trim((string) $procedure) !== '') {
                    DB::table('dcd_discharge_procedures')->insert(['discharge_id' => $discharge->id, 'procedure_detail' => $procedure]);
                }
            }

            $meds = (array) ($v['medicine'] ?? []);
            DB::table('dcd_discharge_medication_given')->where('discharge_id', $discharge->id)->delete();
            foreach ($meds as $i => $medicine) {
                if (trim((string) $medicine) === '') {
                    continue;
                }
                DB::table('dcd_discharge_medication_given')->insert([
                    'discharge_id'      => $discharge->id,
                    'medicine_name'     => $medicine,
                    'dose_quantity'     => (string) ($v['dose_quantity'][$i] ?? ''),
                    'frequency_display' => (string) ($v['frequency'][$i] ?? ''),
                    'timing_display'    => (string) ($v['timing'][$i] ?? ''),
                    'meal_relation'     => (string) ($v['meal'][$i] ?? ''),
                    'duration_days'     => (int) ($v['duration_days'][$i] ?? 0),
                ]);
            }

            $dcd->update(['status' => 'Discharged']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return response()->json(['success' => false, 'message' => 'Could not submit the discharge. Please try again.'], 500);
        }

        return response()->json(['success' => true, 'discharge_id' => $discharge->id, 'message' => 'Saved Successfully.']);
    }

    public function approveDischarge(Request $request, int $id, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('b_dcd_discharge_approve_and_discharge'), 403);
        ApproveDcdDischargeRequest::validatedFrom($request);
        $dcd = Dcd::find($id);
        $discharge = DcdDischarge::where('dcd_id', $id)->first();
        if (!$discharge) {
            $discharge = DcdDischarge::find($id);
            if ($discharge && !$dcd) {
                $dcd = Dcd::find($discharge->dcd_id);
            }
        }
        abort_unless($dcd && $discharge, 404);

        $updateData = [
            'approved'           => 'Yes',
            'approved_doctor_id' => $request->integer('approved_doctor_id') ?: (CurrentUser::id() ?: 1),
        ];

        if ($request->filled('diagnosis')) {
            $updateData['diagnosis'] = $request->input('diagnosis');
        }
        if ($request->filled('condition_on_discharge')) {
            $updateData['condition_on_discharge'] = $request->input('condition_on_discharge');
        }
        if ($request->filled('discharge_date_time')) {
            $rawDt = $request->input('discharge_date_time');
            try {
                $updateData['discharge_date_time'] = \Carbon\Carbon::parse($rawDt)->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $updateData['discharge_date_time'] = $rawDt;
            }
        }
        if ($request->has('follow_up_detail')) {
            $updateData['follow_up_detail'] = $request->input('follow_up_detail');
        }
        if ($request->has('urgent_care_condition')) {
            $updateData['urgent_care_condition'] = $request->input('urgent_care_condition');
        }
        if ($request->has('note')) {
            $updateData['note'] = $request->input('note');
        }
        if ($request->has('discharged_to')) {
            $updateData['discharged_to'] = $request->input('discharged_to');
        }

        $discharge->update($updateData);

        if ($request->has('pharma_product_id_value') && Schema::hasTable('dcd_discharge_treatment_advised')) {
            $productIds = (array) $request->input('pharma_product_id_value', []);
            $doses = (array) $request->input('dose_quantity_value', []);
            $freqs = (array) $request->input('frequency_type_id_value', []);
            $meals = (array) $request->input('meal_relation_id_value', []);
            $durations = (array) $request->input('duration_days_value', []);
            $conditions = (array) $request->input('medical_condition_id_value', []);

            for ($i = 0; $i < count($productIds); $i++) {
                if (empty($productIds[$i])) continue;
                $taId = DB::table('dcd_discharge_treatment_advised')->insertGetId([
                    'discharge_id'         => $discharge->id,
                    'pharma_product_id'    => (int) $productIds[$i],
                    'dose_quantity'        => $doses[$i] ?? '',
                    'frequency_type_id'    => (int) ($freqs[$i] ?? 0),
                    'meal_relation_id'     => (int) ($meals[$i] ?? 0),
                    'duration_days'        => (int) ($durations[$i] ?? 0),
                    'medical_condition_id' => !empty($conditions[$i]) ? (int) $conditions[$i] : null,
                ]);

                $timingKey = 'timing_ids_' . ($i + 1);
                if ($request->has($timingKey) && Schema::hasTable('dcd_discharge_treatment_timing_selections')) {
                    $timings = (array) $request->input($timingKey);
                    foreach ($timings as $tId) {
                        DB::table('dcd_discharge_treatment_timing_selections')->insert([
                            'treatment_advised_id' => $taId,
                            'timing_id'            => (int) $tId,
                        ]);
                    }
                }
            }
        }

        $dcd->update(['status' => 'Discharged']);
        if ($dcd->bed_id) Bed::where('id', $dcd->bed_id)->update(['status' => 'Available']);
        if ($dcd->patient_id) Patient::where('id', $dcd->patient_id)->update(['status' => 'Discharged']);

        return response()->json([
            'success'      => true,
            'discharge_id' => $discharge->id,
            'message'      => 'Discharge approved.',
        ]);
    }

    // ─── Patient File ──────────────────────────────────────

    public function patientFile(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_file'), 403);
        $dcd  = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $file = DcdPatientFile::where('dcd_id', $dcdId)->first();
        return view('dcd.patient-file', compact('dcd', 'file', 'ps'));
    }

    public function getPatientFileJson(int $dcdId): JsonResponse
    {
        $file = DcdPatientFile::where('dcd_id', $dcdId)->first();
        return response()->json($file);
    }

    public function saveDcdPatientFile(Request $request, int $dcdId): JsonResponse
    {
        $ps = app(PermissionService::class);
        abort_unless($ps->hasPermission('im_dcd_file') || $ps->hasPermission('b_dcd_file_save'), 403);
        $v = DcdPatientFileRequest::validatedFrom($request);
        foreach (['chief_complaints', 'past_medical_history'] as $fld) {
            if (!empty($v[$fld])) {
                $v[$fld] = preg_replace('/<\/?span[^>]*>/i', '', $v[$fld]);
            }
        }
        // Every form field is optional, but its column is NOT NULL: an empty field is stored as '' (as the legacy app
        // did on non-strict MySQL), not as the NULL that Laravel makes of an empty string.
        foreach (array_keys((new DcdPatientFileRequest)->rules()) as $field) {
            $v[$field] = $v[$field] ?? '';
        }
        $v['dcd_id']    = $dcdId;
        $v['user_id']   = CurrentUser::id();
        $v['updated_on']= now()->toDateTimeString();
        DcdPatientFile::updateOrCreate(['dcd_id' => $dcdId], $v);
        return response()->json(['success' => true, 'dcd_id' => $dcdId, 'message' => 'Patient file saved.']);
    }

    // ─── Procedures ────────────────────────────────────────

    public function procedures(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_procedures'), 403);
        $dcd = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $services = DcdService::with(['details.charge', 'procedureNote', 'chargeDepartment'])
            ->where('dcd_id', $dcdId)
            ->whereHas('chargeDepartment', fn($q) => $q->whereRaw("LOWER(category) = ?", ['procedure']))
            ->orderBy('id')->get();
        return view('dcd.procedures', compact('dcd', 'services', 'ps'));
    }

    public function getProcedureNote(int $serviceId): JsonResponse
    {
        $note = DcdProcedureNote::where('dcd_service_id', $serviceId)->value('note');
        return response()->json([
            'success' => true,
            'note'    => $note ?? '',
        ]);
    }

    public function storeProcedureNote(Request $request, ?int $serviceId = null): JsonResponse
    {
        DcdProcedureNoteRequest::validatedFrom($request);
        $svcId = $serviceId ?: (int) $request->input('dcd_service_id');
        if ($svcId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid procedure'], 422);
        }
        $note = (string) $request->input('note', '');
        $userId = CurrentUser::id() ?: 1;

        DcdProcedureNote::updateOrCreate(
            ['dcd_service_id' => $svcId],
            [
                'note'       => $note,
                'updated_on' => now()->toDateTimeString(),
                'user_id'    => $userId,
            ]
        );
        return response()->json(['success' => true, 'message' => 'Procedure note saved.']);
    }

    // ─── Medications ───────────────────────────────────────

    public function medications(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_medication'), 403);
        $dcd = Dcd::with(['patient', 'doctor'])->findOrFail($dcdId);
        $prescriptions = DcdPrescription::with([
            'doctor',
            'medicines.product',
            'medicines.frequency',
            'medicines.mealRelation',
            'medicines.timings.timing',
        ])
        ->where('dcd_id', $dcdId)
        ->has('medicines')
        ->orderByDesc('id')
        ->get();

        return view('dcd.medications', compact('dcd', 'prescriptions', 'ps'));
    }

    // ─── Visits ────────────────────────────────────────────

    public function visits(int $dcdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_dcd_history') && $ps->hasPermission('b_dcd_view_history_detail'), 403);
        $dcd = Dcd::with(['patient', 'doctor', 'opd'])->findOrFail($dcdId);
        $patientId = (int) ($dcd->patient_id ?? $dcd->opd?->patient_id ?? 0);
        $opdId = (int) ($dcd->opd_id ?? 0);

        if ($patientId === 0 && $opdId > 0) {
            $opd = Opd::find($opdId);
            $patientId = $opd ? (int) $opd->patient_id : 0;
        }

        // All OTHER OPD visits for this patient (excluding the current admission's OPD)
        $otherOpds = Opd::with('doctor')
            ->where('patient_id', $patientId)
            ->where('id', '!=', $opdId)
            ->orderBy('id', 'asc')
            ->get();

        $visits = [];
        foreach ($otherOpds as $otherOpd) {
            $opdFormattedDate = $otherOpd->opd_date_time 
                ? \Carbon\Carbon::parse($otherOpd->opd_date_time)->format('d-m-Y h:i A') 
                : '';
            $visits[] = [
                'id' => $otherOpd->id,
                'type' => 'OPD',
                'type_display' => 'OPD ' . $otherOpd->id,
                'date_time' => $opdFormattedDate,
                'doctor' => $otherOpd->doctor?->staff_name ?? '',
                'symptoms' => trim(strip_tags((string)$otherOpd->symptoms)),
                'remarks' => trim(strip_tags((string)$otherOpd->remarks)),
            ];

            $linkedIpd = Ipd::with('doctor')->where('opd_id', $otherOpd->id)->first();
            if ($linkedIpd) {
                $ipdFormattedDate = $linkedIpd->admission_date_time 
                    ? \Carbon\Carbon::parse($linkedIpd->admission_date_time)->format('d-m-Y h:i A') 
                    : '';
                $visits[] = [
                    'id' => $linkedIpd->id,
                    'type' => 'IPD',
                    'type_display' => '<b>IPD</b> OPD ' . $otherOpd->id,
                    'date_time' => $ipdFormattedDate,
                    'doctor' => $linkedIpd->doctor?->staff_name ?? ($otherOpd->doctor?->staff_name ?? ''),
                    'symptoms' => trim(strip_tags((string)$otherOpd->symptoms)),
                    'remarks' => trim(strip_tags((string)$otherOpd->remarks)),
                ];
            }

            $linkedDcd = Dcd::with('doctor')->where('opd_id', $otherOpd->id)->first();
            if ($linkedDcd) {
                $dcdFormattedDate = $linkedDcd->dcd_date_time 
                    ? \Carbon\Carbon::parse($linkedDcd->dcd_date_time)->format('d-m-Y h:i A') 
                    : '';
                $visits[] = [
                    'id' => $linkedDcd->id,
                    'type' => 'DCD',
                    'type_display' => '<b>DCD</b> OPD ' . $otherOpd->id,
                    'date_time' => $dcdFormattedDate,
                    'doctor' => $linkedDcd->doctor?->staff_name ?? ($otherOpd->doctor?->staff_name ?? ''),
                    'symptoms' => trim(strip_tags((string)$otherOpd->symptoms)),
                    'remarks' => trim(strip_tags((string)$otherOpd->remarks)),
                ];
            }
        }

        $history = $otherOpds;

        // Pre-resolve ABDM initial button state & text for SSR parity
        $abdmInitialBtnText = 'Checking ABDM...';
        $abdmInitialState = 'checking';
        if ($dcd->patient) {
            if (empty($dcd->patient->abha_address) || empty($dcd->patient->abha_number)) {
                $abdmInitialBtnText = 'Abha Details Not Provided';
                $abdmInitialState = 'no-abha';
            } else {
                $latestReq = AbdmHiuConsentRequest::where('abha_address', $dcd->patient->abha_address)->orderByDesc('id')->first();
                if ($latestReq) {
                    if ($latestReq->latest_status === 'REQUESTED') {
                        $abdmInitialBtnText = 'Patient Approval Pending';
                        $abdmInitialState = 'pending';
                    } elseif ($latestReq->latest_status === 'GRANTED') {
                        $abdmInitialBtnText = 'Fetch Records';
                        $abdmInitialState = 'granted';
                    } elseif (in_array($latestReq->latest_status, ['DENIED', 'EXPIRED', 'REVOKED', 'UNKNOWN'], true)) {
                        $abdmInitialBtnText = 'Request Records From Abha';
                        $abdmInitialState = 'requestable';
                    } else {
                        $abdmInitialBtnText = 'ABDM: ' . $latestReq->latest_status;
                        $abdmInitialState = 'other';
                    }
                } else {
                    $abdmInitialBtnText = 'Request Records From Abha';
                    $abdmInitialState = 'requestable';
                }
            }
        }

        return view('dcd.visits', compact('dcd', 'patientId', 'visits', 'history', 'abdmInitialBtnText', 'abdmInitialState', 'ps'));
    }

    // ─── Discharged List ────────────────────────────────────────

    public function discharged(PermissionService $ps)
    {
        $canView = ($ps->hasPermission('m_discharge') || $ps->hasPermission('m_dcd')) && $ps->hasPermission('im_dcd_discharge_patients');
        return view('dcd.discharged', compact('canView', 'ps'));
    }

    public function dischargedData(Request $request, ?PermissionService $ps = null): JsonResponse
    {
        $ps = $ps ?: app(PermissionService::class);
        $draw = (int) $request->input('draw', 1);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 50);
        if ($length < 0) {
            $length = 1000;
        }
        $searchValue = (string) $request->input('search.value', '');

        $orderColumnIndex = (int) $request->input('order.0.column', 7);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $columns = [
            0 => 'dcd_discharge.id',
            1 => 'patients.patient_name',
            2 => 'patients.mobile_number',
            3 => 'dcd_discharge.diagnosis',
            4 => 'dcds.opd_id',
            5 => 'dcds.id',
            6 => 'dcds.dcd_date_time',
            7 => 'dcd_discharge.discharge_date_time',
        ];

        $orderColumn = $columns[$orderColumnIndex] ?? 'dcd_discharge.discharge_date_time';

        $loggedInPatientCategoryId = (int) session('patient_category_id', session('logged_in_patient_category_id', 1));

        $baseQuery = DB::table('dcd_discharge')
            ->join('dcds', 'dcds.id', '=', 'dcd_discharge.dcd_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where('dcd_discharge.approved', 'Yes');

        if ($loggedInPatientCategoryId > 0) {
            $baseQuery->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $totalRecords = $baseQuery->count();

        $query = clone $baseQuery;

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('patients.patient_name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('patients.mobile_number', 'LIKE', "%{$searchValue}%")
                  ->orWhere('dcd_discharge.diagnosis', 'LIKE', "%{$searchValue}%");
            });
        }

        $filteredRecords = $searchValue !== '' ? $query->count() : $totalRecords;

        $rows = $query->select([
                'dcd_discharge.id as discharge_id',
                'dcd_discharge.diagnosis',
                'dcd_discharge.discharge_date_time',
                'patients.patient_name',
                'patients.mobile_number',
                'dcds.id as dcd_id',
                'dcds.opd_id',
                'dcds.dcd_date_time',
            ])
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $canView = $ps->hasPermission('b_dcd_discharge_patient_view');

        $data = [];
        foreach ($rows as $row) {
            $dischargeId = (int) $row->discharge_id;
            $dcdId = (int) $row->dcd_id;
            $patientName = htmlspecialchars($row->patient_name ?? '', ENT_QUOTES, 'UTF-8');
            $mobileNumber = htmlspecialchars($row->mobile_number ?? '', ENT_QUOTES, 'UTF-8');
            $diagnosis = htmlspecialchars($row->diagnosis ?? '', ENT_QUOTES, 'UTF-8');
            $opdId = (int) ($row->opd_id ?? 0);

            $dcdDateTime = '';
            if (!empty($row->dcd_date_time)) {
                try {
                    $dcdDateTime = \Carbon\Carbon::parse($row->dcd_date_time)->format('d-m-Y h:i A');
                } catch (\Exception $e) {
                    $dcdDateTime = (string) $row->dcd_date_time;
                }
            }

            $dischargeDateTime = '';
            if (!empty($row->discharge_date_time)) {
                try {
                    $dischargeDateTime = \Carbon\Carbon::parse($row->discharge_date_time)->format('d-m-Y h:i A');
                } catch (\Exception $e) {
                    $dischargeDateTime = (string) $row->discharge_date_time;
                }
            }

            $actions = '<div class="tr-sm-action-buttons center">';
            $actions .= '<a href="' . route('dcd.show', $dcdId) . '" data-bs-toggle="tooltip" title="Dashboard"><i class="fas fa-border-outer"></i></a> ';
            if ($canView) {
                $actions .= '<a href="' . route('dcd.view-discharged', $dischargeId) . '" data-bs-toggle="tooltip" title="View"><i class="fas fa-list"></i></a>';
            }
            $actions .= '</div>';

            $data[] = [
                $actions,
                $patientName,
                $mobileNumber,
                $diagnosis,
                $opdId,
                $dcdId,
                $dcdDateTime,
                $dischargeDateTime,
            ];
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function viewDischarged(int $dcdId, PermissionService $ps)
    {
        $canView = $ps->hasPermission('b_dcd_discharge_patient_view');
        if (!$canView) {
            return view('dcd.view-discharged', [
                'canView'                    => false,
                'dcd'                        => null,
                'discharge'                  => null,
                'patient'                    => null,
                'doctor'                     => null,
                'dischargeDateTimeFormatted' => '',
                'dcdDateTimeFormatted'       => '',
                'investigations'             => collect(),
                'procedures'                 => collect(),
                'medicationsGiven'           => collect(),
                'treatmentAdvised'           => collect(),
                'ps'                         => $ps,
            ]);
        }

        $discharge = DcdDischarge::find($dcdId);
        if ($discharge) {
            $dcd = Dcd::with(['patient', 'doctor.designation', 'bed.wardCategory', 'bed.floor', 'bed.bedType', 'discharge', 'opd'])->find($discharge->dcd_id);
        } else {
            $dcd = Dcd::with(['patient', 'doctor.designation', 'bed.wardCategory', 'bed.floor', 'bed.bedType', 'discharge', 'opd'])->find($dcdId);
            $discharge = $dcd ? DcdDischarge::where('dcd_id', $dcd->id)->first() : null;
        }

        abort_unless($dcd, 404);

        if (!$discharge) {
            $discharge = new DcdDischarge();
            $discharge->id = 0;
            $discharge->dcd_id = $dcd->id;
            $discharge->approved = 'No';
        }

        $patient = $dcd->patient;
        $doctor  = $dcd->doctor;

        $dischargeDateTimeFormatted = '';
        if (!empty($discharge->discharge_date_time)) {
            try {
                $dischargeDateTimeFormatted = \Carbon\Carbon::parse($discharge->discharge_date_time)->format('d-m-Y h:i A');
            } catch (\Exception $e) {
                $dischargeDateTimeFormatted = (string) $discharge->discharge_date_time;
            }
        }

        $dcdDateTimeFormatted = '';
        if (!empty($dcd->dcd_date_time)) {
            try {
                $dcdDateTimeFormatted = \Carbon\Carbon::parse($dcd->dcd_date_time)->format('d-m-Y h:i A');
            } catch (\Exception $e) {
                $dcdDateTimeFormatted = (string) $dcd->dcd_date_time;
            }
        }

        $investigations = Schema::hasTable('dcd_discharge_investigations')
            ? DB::table('dcd_discharge_investigations')->where('discharge_id', $discharge->id)->get()
            : collect();

        $procedures = Schema::hasTable('dcd_discharge_procedures')
            ? DB::table('dcd_discharge_procedures')->where('discharge_id', $discharge->id)->get()
            : collect();

        $medicationsGiven = Schema::hasTable('dcd_discharge_medication_given')
            ? DB::table('dcd_discharge_medication_given')->where('discharge_id', $discharge->id)->get()
            : collect();

        $treatmentAdvised = collect();
        if (Schema::hasTable('dcd_discharge_treatment_advised')) {
            $taRecords = DB::table('dcd_discharge_treatment_advised as m')
                ->leftJoin('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
                ->where('m.discharge_id', $discharge->id)
                ->select([
                    'm.id',
                    'pp.product_name as medicine_name',
                    'm.dose_quantity',
                    'ft.display_text as frequency_display',
                    'mr.relation_name as meal_relation',
                    'm.duration_days',
                ])
                ->get();

            $treatmentAdvised = $taRecords->map(function ($row) {
                $timings = [];
                if (Schema::hasTable('dcd_discharge_treatment_timing_selections') && Schema::hasTable('medicine_timing_of_day')) {
                    $timings = DB::table('dcd_discharge_treatment_timing_selections as mts')
                        ->join('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
                        ->where('mts.treatment_advised_id', $row->id)
                        ->orderBy('t.sort_order')
                        ->pluck('t.timing_name')
                        ->toArray();
                }
                $row->timing_display = implode(', ', $timings);
                return $row;
            });
        }

        return view('dcd.view-discharged', compact(
            'canView',
            'dcd',
            'discharge',
            'patient',
            'doctor',
            'dischargeDateTimeFormatted',
            'dcdDateTimeFormatted',
            'investigations',
            'procedures',
            'medicationsGiven',
            'treatmentAdvised',
            'ps'
        ));
    }

    public function viewDischargeApproval(int $id, PermissionService $ps)
    {
        $canView = $ps->hasPermission('im_dcd_discharge_approve') || 
                   $ps->hasPermission('b_dcd_discharge_approval_view');

        if (!$canView) {
            return view('dcd.view-discharge-approval', [
                'canView'                    => false,
                'discharge'                  => null,
                'dcd'                        => null,
                'patient'                    => null,
                'doctor'                     => null,
                'dischargeDateTimeFormatted' => '',
                'dcdDateTimeFormatted'       => '',
                'investigations'             => collect(),
                'procedures'                 => collect(),
                'medicationsGiven'           => collect(),
                'pharmaProducts'             => collect(),
                'frequencyTypes'             => collect(),
                'mealRelations'              => collect(),
                'timingList'                 => collect(),
                'medicalConditions'          => collect(),
                'approve'                    => false,
                'ps'                         => $ps,
            ]);
        }

        $discharge = DcdDischarge::find($id);
        if ($discharge) {
            $dcd = Dcd::with(['patient', 'doctor.designation', 'bed.wardCategory', 'bed.floor', 'bed.bedType', 'discharge', 'opd'])->find($discharge->dcd_id);
        } else {
            $dcd = Dcd::with(['patient', 'doctor.designation', 'bed.wardCategory', 'bed.floor', 'bed.bedType', 'discharge', 'opd'])->find($id);
            $discharge = $dcd ? DcdDischarge::where('dcd_id', $dcd->id)->first() : null;
        }

        abort_unless($dcd, 404);

        if (!$discharge) {
            $discharge = new DcdDischarge();
            $discharge->id = 0;
            $discharge->dcd_id = $dcd->id;
            $discharge->approved = 'No';
        }

        $approve = $ps->hasPermission('b_dcd_discharge_approve_and_discharge');
        $patient = $dcd->patient;
        $doctor  = $dcd->doctor;

        $dischargeDateTimeFormatted = '';
        if (!empty($discharge->discharge_date_time)) {
            try {
                $dischargeDateTimeFormatted = \Carbon\Carbon::parse($discharge->discharge_date_time)->format('d-m-Y h:i A');
            } catch (\Exception $e) {
                $dischargeDateTimeFormatted = (string) $discharge->discharge_date_time;
            }
        }

        $dcdDateTimeFormatted = '';
        if (!empty($dcd->dcd_date_time)) {
            try {
                $dcdDateTimeFormatted = \Carbon\Carbon::parse($dcd->dcd_date_time)->format('d-m-Y h:i A');
            } catch (\Exception $e) {
                $dcdDateTimeFormatted = (string) $dcd->dcd_date_time;
            }
        }

        $investigations = Schema::hasTable('dcd_discharge_investigations')
            ? DB::table('dcd_discharge_investigations')->where('discharge_id', $discharge->id)->get()
            : collect();
        $procedures = Schema::hasTable('dcd_discharge_procedures')
            ? DB::table('dcd_discharge_procedures')->where('discharge_id', $discharge->id)->get()
            : collect();
        $medicationsGiven = Schema::hasTable('dcd_discharge_medication_given')
            ? DB::table('dcd_discharge_medication_given')->where('discharge_id', $discharge->id)->get()
            : collect();
        $pharmaProducts = Schema::hasTable('pharma_products')
            ? DB::table('pharma_products')->orderBy('product_name')->get(['id', 'product_name'])
            : collect();
        $frequencyTypes = Schema::hasTable('medicine_frequency_types')
            ? DB::table('medicine_frequency_types')->orderBy('sort_order')->get(['id', 'display_text'])
            : collect();
        $mealRelations = Schema::hasTable('medicine_meal_relation')
            ? DB::table('medicine_meal_relation')->orderBy('id')->get(['id', 'relation_name'])
            : collect();
        $timingList = Schema::hasTable('medicine_timing_of_day')
            ? DB::table('medicine_timing_of_day')->orderBy('sort_order')->get(['id', 'timing_name'])
            : collect();
        $medicalConditions = Schema::hasTable('medical_conditions')
            ? DB::table('medical_conditions')->where('is_active', 1)->orderBy('condition_name')->get(['id', 'condition_name'])
            : collect();

        return view('dcd.view-discharge-approval', compact(
            'canView',
            'dcd',
            'discharge',
            'patient',
            'doctor',
            'dischargeDateTimeFormatted',
            'dcdDateTimeFormatted',
            'investigations',
            'procedures',
            'medicationsGiven',
            'pharmaProducts',
            'frequencyTypes',
            'mealRelations',
            'timingList',
            'medicalConditions',
            'approve',
            'ps'
        ));
    }

    public function dischargeApprovals(Request $request, PermissionService $ps)
    {
        $canView = ($ps->hasPermission('m_discharge') || $ps->hasPermission('m_dcd')) && $ps->hasPermission('im_dcd_discharge_approval');
        $canApprovalView = $ps->hasPermission('b_dcd_discharge_approval_view');

        if (!$canView) {
            return view('dcd.discharge-approvals', [
                'canView'         => false,
                'records'         => collect(),
                'canApprovalView' => false,
                'page'            => 1,
                'totalPages'      => 0,
                'totalRecords'    => 0,
            ]);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $loggedInPatientCategoryId = (int) session('patient_category_id', session('logged_in_patient_category_id', 1));

        $countQuery = DB::table('dcd_discharge')
            ->join('dcds', 'dcds.id', '=', 'dcd_discharge.dcd_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where(function ($q) {
                $q->whereNull('dcd_discharge.approved')
                  ->orWhere(function ($sq) {
                      $sq->where('dcd_discharge.approved', '!=', 'Yes')
                         ->where('dcd_discharge.approved', '!=', '1')
                         ->where('dcd_discharge.approved', '!=', 1);
                  });
            });

        if ($loggedInPatientCategoryId > 0) {
            $countQuery->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $totalRecords = $countQuery->count();
        $totalPages = (int) ceil($totalRecords / $perPage);

        $query = DB::table('dcd_discharge')
            ->join('dcds', 'dcds.id', '=', 'dcd_discharge.dcd_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where(function ($q) {
                $q->whereNull('dcd_discharge.approved')
                  ->orWhere(function ($sq) {
                      $sq->where('dcd_discharge.approved', '!=', 'Yes')
                         ->where('dcd_discharge.approved', '!=', '1')
                         ->where('dcd_discharge.approved', '!=', 1);
                  });
            });

        if ($loggedInPatientCategoryId > 0) {
            $query->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $records = $query->select([
                'dcd_discharge.id',
                'dcd_discharge.dcd_id',
                'patients.patient_name',
                'dcds.opd_id',
                'dcds.dcd_date_time',
            ])
            ->orderBy('dcd_discharge.id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        foreach ($records as $row) {
            if (!empty($row->dcd_date_time)) {
                try {
                    $row->dcd_date_time_formatted = \Carbon\Carbon::parse($row->dcd_date_time)->format('d-m-Y h:i A');
                } catch (\Exception $e) {
                    $row->dcd_date_time_formatted = $row->dcd_date_time;
                }
            } else {
                $row->dcd_date_time_formatted = '';
            }
        }

        return view('dcd.discharge-approvals', compact(
            'canView',
            'records',
            'canApprovalView',
            'page',
            'totalPages',
            'totalRecords'
        ));
    }

    // ─── DCD to IPD conversion ───────────────────────────────

    public function shiftToIpd(Request $request, int $dcdId): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_dcd_to_ipd'), 403);
        $v = $request->validate([
            'bed_id'               => 'required|integer|exists:beds,id',
            'consultant_doctor_id' => 'required|integer|exists:staff_members,id',
            'admission_date_time'  => 'required|date',
        ]);

        $dcd = Dcd::findOrFail($dcdId);

        DB::beginTransaction();
        try {
            // Create IPD from DCD
            $ipd = \App\Models\Ipd::create([
                'opd_id'               => $dcd->opd_id,
                'patient_id'           => $dcd->patient_id,
                'consultant_doctor_id' => $v['consultant_doctor_id'],
                'admission_date_time'  => $v['admission_date_time'],
                'bed_id'               => $v['bed_id'],
                'previous_medical_note'=> 'Shifted from DCD #' . $dcd->dcd_ref,
                'status'               => \App\Models\Ipd::NEW_ADMISSION,
                'created_on'           => now()->toDateTimeString(),
                'user_id'              => CurrentUser::id(),
            ]);

            // Allot bed
            Bed::where('id', $v['bed_id'])->update(['status' => 'Alloted']);

            // Record initial bed
            \App\Models\IpdPatientBed::create([
                'allot_date_time' => $v['admission_date_time'],
                'ipd_id'          => $ipd->id,
                'bed_id'          => $v['bed_id'],
                'remarks'         => 'Shifted from DCD',
                'user_id'         => CurrentUser::id(),
            ]);

            // Mark DCD as discharged (shifted)
            $dcd->update(['status' => 'Discharged']);
            if ($dcd->bed_id) Bed::where('id', $dcd->bed_id)->update(['status' => 'Available']);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Shifted to IPD successfully.', 'ipd_id' => $ipd->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /** Data the "Shift To IPD" modal pre-fills: the DCD row plus its current bed name. */
    public function ipdAdmissionPrefill(int $id): JsonResponse
    {
        $row = DB::table('dcds')
            ->join('beds', 'beds.id', '=', 'dcds.bed_id')
            ->where('dcds.id', $id)
            ->select('dcds.*', 'beds.bed_name')
            ->first();
        abort_unless($row, 404);

        return response()->json($row);
    }

    /** Modal-driven shift: admits the DCD patient to IPD (TPA details, optional bed change). */
    public function shiftToIpdLegacy(Request $request, ?int $id = null): JsonResponse
    {
        ShiftDcdToIpdRequest::validatedFrom($request);
        $dcdId = $id ?? (int) $request->input('dcd_id');
        $dcd = Dcd::findOrFail($dcdId);

        $prevBedId = (int) $dcd->bed_id;
        $consultantDoctorId = (int) $dcd->consultant_doctor_id;
        $previousMedicalNote = $dcd->previous_medical_note;

        $opdId = $request->filled('opd_id') ? (int) $request->input('opd_id') : $dcd->opd_id;
        $patientId = $request->filled('patient_id') ? (int) $request->input('patient_id') : $dcd->patient_id;
        $admissionDateTimeStr = $request->input('admission_date_time');
        $admissionDateTime = $admissionDateTimeStr ? date('Y-m-d H:i:s', strtotime($admissionDateTimeStr)) : now()->toDateTimeString();

        $tpaProviderId = $request->filled('tpa_provider_id') ? (int) $request->input('tpa_provider_id') : null;
        $tpaNumber = $request->input('tpa_number', '');
        $tpaCreditLimit = $request->filled('tpa_credit_limit') ? (int) $request->input('tpa_credit_limit') : 0;
        $bedId = (int) $request->input('bed_id', $prevBedId);

        DB::beginTransaction();
        try {
            $ipd = \App\Models\Ipd::create([
                'admission_date_time'  => $admissionDateTime,
                'opd_id'               => $opdId,
                'dcd_id'               => $dcdId,
                'patient_id'           => $patientId,
                'tpa_provider_id'      => $tpaProviderId,
                'tpa_number'           => $tpaNumber,
                'tpa_credit_limit'     => $tpaCreditLimit,
                'consultant_doctor_id' => $consultantDoctorId,
                'bed_id'               => $bedId,
                'previous_medical_note'=> $previousMedicalNote,
                'status'               => 'New Admission',
                'created_on'           => now()->toDateTimeString(),
                'user_id'              => CurrentUser::id(),
            ]);

            \App\Models\Patient::where('id', $patientId)->update(['status' => 'Admitted']);
            $dcd->update(['status' => 'TO IPD']);

            if ($prevBedId && $prevBedId !== $bedId) {
                Bed::where('id', $prevBedId)->update(['status' => 'Available']);
                Bed::where('id', $bedId)->update(['status' => 'Alloted']);
            } elseif ($bedId) {
                Bed::where('id', $bedId)->update(['status' => 'Alloted']);
            }

            DB::table('ipd_doctors')->insert([
                'doctor_id'  => $consultantDoctorId,
                'ipd_id'     => $ipd->id,
                'created_on' => now()->toDateTimeString(),
                'user_id'    => CurrentUser::id(),
            ]);

            DB::table('ipd_patient_beds')->insert([
                'allot_date_time' => now()->toDateTimeString(),
                'ipd_id'          => $ipd->id,
                'bed_id'          => $bedId,
                'remarks'         => '',
                'user_id'         => (string) CurrentUser::id(),
            ]);

            DB::commit();
            return response()->json(['ipd_id' => $ipd->id, 'success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }
}

