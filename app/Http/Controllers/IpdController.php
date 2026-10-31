<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Ipd;
use App\Models\Opd;
use App\Models\Patient;
use App\Models\StaffMember;
use App\Models\TpaProvider;
use App\Services\SettingsService;
use App\Services\IpdOverviewService;
use App\Http\Requests\UpdateIpdOverviewRequest;
use App\Http\Requests\AddIpdDoctorRequest;
use App\Http\Requests\ApproveIpdDischargeRequest;
use App\Http\Requests\IpdChargeRequest;
use App\Http\Requests\IpdConsultationRequest;
use App\Http\Requests\IpdDischargeRequest;
use App\Http\Requests\IpdIntakeOutputRequest;
use App\Http\Requests\IpdPatientFileRequest;
use App\Http\Requests\IpdPrescriptionRequest;
use App\Http\Requests\IpdProcedureNoteRequest;
use App\Http\Requests\IpdProgressSheetRequest;
use App\Http\Requests\IpdRmoNotesRequest;
use App\Http\Requests\IpdTreatmentRequest;
use App\Http\Requests\IpdVitalMonitoringRequest;
use App\Http\Requests\UpdateIpdDischargeDateRequest;
use Illuminate\Support\Carbon;
use App\Models\IpdPrescription;
use App\Models\IpdMedicine;
use App\Models\IpdService;
use App\Models\IpdServiceDetail;
use App\Models\IpdLabInvestigationNote;
use App\Models\IpdProcedureNote;
use App\Models\IpdConsultation;
use App\Models\IpdVitalMonitoring;
use App\Models\IpdVitalMonitoringDetail;
use App\Models\IpdIntakeOutput;
use App\Models\IpdIntakeOutputDetail;
use App\Models\IpdTreatment;
use App\Models\IpdTreatmentDetail;
use App\Models\IpdPatientBed;
use App\Models\IpdDischarge;
use App\Models\IpdPatientFile;
use App\Models\BillingCharge;
use App\Models\BillingChargePayment;
use App\Models\BillingChargeDetail;
use App\Models\Bed;
use App\Models\AbdmCareContext;
use App\Models\PharmaProduct;
use App\Models\MedicineFrequencyType;
use App\Models\MedicineMealRelation;
use App\Models\MedicineTimingOfDay;
use App\Models\MedicalCondition;
use App\Models\IpdMedicineTimingSelection;
use App\Models\IdFieldSetting;
use App\Models\Charge;
use App\Models\ChargeDepartment;
use App\Models\ChargeCategory;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Support\CurrentUser;
use App\Support\Uhid;
use App\Support\Uploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IpdController extends Controller
{
    // ─────────────────────────────────────────────
    // IPD Register List
    // ─────────────────────────────────────────────

    public function index(Request $request, PermissionService $ps, SettingsService $settings)
    {
        abort_unless($ps->hasPermission('m_ipd'), 403);

        // Consultant list: every active member of the configured doctor department (legacy dropdown_doctors).
        $doctorDeptId = $settings->getIdField('doctor_department_id');
        $consultants  = $doctorDeptId > 0
            ? StaffMember::where('department_id', $doctorDeptId)->where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name'])
            : collect();

        return view('ipd.index', [
            'canNew'       => $ps->hasPermission('b_new_ipd'),
            'canPatientFile' => $ps->hasPermission('im_ipd_patient_file'),
            'consultants'  => $consultants,
            'tpaProviders' => TpaProvider::orderBy('tpa_name')->get(['id', 'tpa_name']),
            // IPD Register?opd_id=N (the "Admit" shortcut on an OPD) opens the New Admission modal pre-filled.
            'opdIdParam'   => max(0, (int) $request->query('opd_id', 0)),
            'now'          => now()->format('d-m-Y h:i A'),
        ]);
    }

    /**
     * Legacy get_ipd_list_datatable. Columns: 0 actions, 1 admission date, 2 IPD no, 3 name, 4 gender, 5 age,
     * 6 phone, 7 bed. "Total" ignores every filter, "filtered" honours the status filter and the search.
     */
    public function data(Request $request): JsonResponse
    {
        $status = (string) $request->input('filter_ipd_status', 'New Admission');
        $search = trim((string) $request->input('search.value', ''));
        $catId  = (int) session('patient_category_id', 0);

        $columns = ['ipds.id', 'ipds.admission_date_time', 'ipds.id', 'patients.patient_name', 'patients.gender',
                    'patients.age', 'patients.mobile_number', 'beds.bed_name'];
        $orderBy = $columns[$request->integer('order.0.column', 2)] ?? 'ipds.id';
        $dir     = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

        $query = DB::table('ipds')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->leftJoin('beds', 'beds.id', '=', 'ipds.bed_id')
            ->leftJoin('floors', 'floors.id', '=', 'beds.floor_id')
            ->leftJoin('bed_types', 'bed_types.id', '=', 'beds.bed_type_id')
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->when($catId > 0, fn ($q) => $q->where('patients.patient_category_id', $catId))
            ->when($status === 'New Admission', fn ($q) => $q->whereIn('ipds.status', Ipd::ADMITTED_STATUSES))
            ->when(in_array($status, ['Discharge Approval', 'Discharged'], true), fn ($q) => $q->where('ipds.status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($sq) use ($search) {
                $like = "%{$search}%";
                $uhidId = Uhid::parse($search);   // a typed UHID finds that patient's admissions
                $sq->where('patients.patient_name', 'LIKE', $like)
                   ->orWhere('patients.mobile_number', 'LIKE', $like)
                   ->orWhere('patients.gender', 'LIKE', $like)
                   ->orWhere('patients.age', 'LIKE', $like)
                   ->orWhere('floors.floor', 'LIKE', $like)
                   ->orWhere('bed_types.bed_type', 'LIKE', $like)
                   ->orWhere('ward_categories.ward_category', 'LIKE', $like)
                   ->orWhere('beds.bed_name', 'LIKE', $like)
                   ->when($uhidId !== null, fn ($u) => $u->orWhere('patients.id', $uhidId));
            }));

        $filtered = (clone $query)->count();

        $rows = $query->orderBy($orderBy, $dir)
            ->skip($request->integer('start'))
            ->take($request->integer('length', 50))
            ->get(['ipds.id', 'ipds.admission_date_time', 'patients.patient_name', 'patients.gender', 'patients.age',
                   'patients.mobile_number', 'floors.floor', 'bed_types.bed_type', 'ward_categories.ward_category', 'beds.bed_name']);

        $data = $rows->map(fn ($r) => [
            'id'                  => $r->id,
            'admission_date_time' => $r->admission_date_time ? date('d-m-Y h:i A', strtotime($r->admission_date_time)) : '',
            'patient_name'        => e($r->patient_name),
            'gender'              => e($r->gender),
            'age'                 => e($r->age),
            'mobile_number'       => e($r->mobile_number),
            // Legacy label: floor-bed type-ward category-bed name.
            'bed'                 => e(implode('-', array_filter([$r->floor, $r->bed_type, $r->ward_category, $r->bed_name], fn ($p) => $p !== null && $p !== ''))),
        ]);

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => Ipd::count(),
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    /**
     * Legacy getopdidpatient: the New Admission form starts from an OPD number. Reports whether the OPD's
     * patient (within the logged-in user's category) exists and whether that OPD is already admitted.
     */
    public function opdPatient(int $opdId): JsonResponse
    {
        $catId = (int) session('patient_category_id', 0);

        $opd = Opd::with('patient')->find($opdId);
        $patient = $opd?->patient;
        if (!$patient || ($catId > 0 && (int) $patient->patient_category_id !== $catId)) {
            return response()->json(['found' => false, 'already_admitted' => false]);
        }

        return response()->json([
            'found'            => true,
            'already_admitted' => $this->opdHasActiveIpd($opdId),
            'patient' => [
                'id'            => $patient->id,
                'name'          => $patient->patient_name,
                'gender'        => $patient->gender,
                'blood_group'   => $patient->blood_group,
                'age'           => $patient->age,
                'mobile_number' => $patient->mobile_number,
                'address'       => $patient->address,
                'photo'         => Uploads::url(Uploads::PATIENTS, $patient->photo),
            ],
        ]);
    }

    /** Legacy getbedsui as used by the "Choose Bed" button: every floor / area / bed, free beds selectable. */
    public function bedPicker(): JsonResponse
    {
        $floors = DB::table('floors')->orderBy('sort_order')->get();
        $areas  = DB::table('floor_areas')->get()->groupBy('floor_id');
        $beds   = DB::table('beds')
            ->join('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->join('bed_types', 'bed_types.id', '=', 'beds.bed_type_id')
            ->orderBy('ward_categories.id')->orderBy('bed_types.id')->orderBy('beds.sort_order')
            ->get(['beds.id', 'beds.bed_name', 'beds.floor_id', 'beds.floor_area_id', 'beds.status', 'ward_categories.ward_category', 'bed_types.image'])
            ->groupBy('floor_area_id');

        return response()->json(['html' => view('ipd.partials.bed-picker', compact('floors', 'areas', 'beds'))->render()]);
    }

    private function opdHasActiveIpd(int $opdId): bool
    {
        return Ipd::where('opd_id', $opdId)->where('status', '!=', 'Discharged')->exists();
    }

    private function parseAdmissionDateTime(string $value): ?Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'd-m-Y h:i A',
            'd-m-Y g:i A',
            'd-m-Y h:i a',
            'd-m-Y g:i a',
            'd-m-Y H:i',
            'd-m-Y G:i',
            'd-m-Y H:i:s',
            'd/m/Y h:i A',
            'd/m/Y g:i A',
            'd/m/Y h:i a',
            'd/m/Y g:i a',
            'd/m/Y H:i',
            'd/m/Y G:i',
            'd/m/Y H:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d h:i A',
            'Y-m-d g:i A',
        ];

        foreach ($formats as $format) {
            if (Carbon::hasFormat($value, $format)) {
                try {
                    return Carbon::createFromFormat($format, $value);
                } catch (\Throwable) {
                    // continue to next format
                }
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Legacy save_ipd: admit the OPD's patient, allot the bed, record the consultant and the first bed stay. */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'opd_id'               => 'required|integer|exists:opds,id',
            'mobile_number'        => 'required|string|max:50',
            'address'              => 'required|string|max:255',
            'consultant_doctor_id' => 'required|integer|exists:staff_members,id',
            'admission_date_time'  => ['required', function (string $attribute, $value, \Closure $fail) {
                if (!$this->parseAdmissionDateTime((string) $value)) {
                    $fail('Admission date must be a valid date and time.');
                }
            }],
            'bed_id'               => 'required|integer|min:1|exists:beds,id',
            'tpa_provider_id'      => 'nullable|integer|exists:tpa_providers,id',
            'tpa_number'           => 'nullable|string|max:255',
            'tpa_credit_limit'     => 'nullable|numeric|min:0',
            'previous_medical_note'=> 'nullable|string',
        ], [
            'bed_id.min'      => 'Please choose a bed.',
            'bed_id.required' => 'Please choose a bed.',
        ]);

        $opd     = Opd::with('patient')->findOrFail($v['opd_id']);
        $patient = $opd->patient;
        $catId   = (int) session('patient_category_id', 0);
        if (!$patient || ($catId > 0 && (int) $patient->patient_category_id !== $catId)) {
            return response()->json(['success' => false, 'message' => 'Please check the entered OPD ID.'], 422);
        }
        if ($this->opdHasActiveIpd($opd->id)) {
            return response()->json(['success' => false, 'message' => 'IPD already exists for this OPD ID.'], 422);
        }

        $admittedAt = $this->parseAdmissionDateTime($v['admission_date_time'])->toDateTimeString();
        $nowStamp   = now()->toDateTimeString();
        $userId     = (int) CurrentUser::id();

        try {
            $ipd = DB::transaction(function () use ($v, $opd, $patient, $admittedAt, $nowStamp, $userId) {
                // Lock the bed row so two admissions can't take the same bed.
                $bed = Bed::whereKey($v['bed_id'])->lockForUpdate()->first();
                if (!$bed || $bed->status !== 'Available') {
                    throw new \DomainException('The selected bed is not available. Please choose another bed.');
                }

                // The table has NOT NULL columns without defaults: same values as the legacy INSERT.
                $ipd = Ipd::create([
                    'admission_date_time'   => $admittedAt,
                    'opd_id'                => $opd->id,
                    'dcd_id'                => 0,
                    'patient_id'            => $patient->id,
                    'tpa_provider_id'       => !empty($v['tpa_provider_id']) ? (int) $v['tpa_provider_id'] : null,
                    'tpa_number'            => $v['tpa_number'] ?? '',
                    'tpa_credit_limit'      => (int) ($v['tpa_credit_limit'] ?? 0),
                    'consultant_doctor_id'  => $v['consultant_doctor_id'],
                    'bed_id'                => $bed->id,
                    'previous_medical_note' => $v['previous_medical_note'] ?? '',
                    'status'                => Ipd::NEW_ADMISSION,
                    'created_on'            => $nowStamp,
                    'user_id'               => $userId,
                ]);

                // The form lets staff correct the patient's contact details; the patient is now an inpatient.
                $patient->forceFill([
                    'mobile_number' => $v['mobile_number'],
                    'address'       => $v['address'],
                    'status'        => 'Admitted',
                ])->save();

                $bed->update(['status' => 'Alloted']);

                DB::table('ipd_doctors')->insert([
                    'doctor_id'  => $v['consultant_doctor_id'],
                    'ipd_id'     => $ipd->id,
                    'created_on' => $nowStamp,
                    'user_id'    => $userId,
                ]);

                IpdPatientBed::create([
                    'allot_date_time' => $nowStamp,
                    'left_date_time'  => null,
                    'ipd_id'          => $ipd->id,
                    'bed_id'          => $bed->id,
                    'remarks'         => '',
                    'user_id'         => $userId,
                ]);

                return $ipd;
            });
        } catch (\DomainException $e) {
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Patient admitted.', 'ipd_id' => $ipd->id]);
    }

    // ─────────────────────────────────────────────
    // Overview
    // ─────────────────────────────────────────────

    public function show(int $id, PermissionService $ps, IpdOverviewService $overviewService, SettingsService $settings)
    {
        abort_unless($ps->hasPermission('im_ipd_overview'), 403);
        $ipd = Ipd::with([
            'patient', 'doctor', 'bed.floor', 'bed.wardCategory', 'bed.bedType',
            'tpaProvider', 'patientFile', 'opd', 'discharge',
        ])->findOrFail($id);

        app(AuditLogger::class)->viewIpd($id, $ipd->patient?->id, $ipd->patient?->patient_name);


        // ABDM N/M badge: count how many of the available HI types are linked
        $careContextService = app(\App\Services\Abdm\CareContextService::class);
        $careContexts    = AbdmCareContext::where('ipd_id', $id)
            ->select('hi_type', 'status', 'created_on')->get();
        $abdmStatuses = [];
        foreach ($careContexts as $cc) {
            if (!isset($abdmStatuses[$cc->hi_type])) {
                $abdmStatuses[$cc->hi_type] = $cc->status;
            }
        }
        $abdmAvailableTypes = $careContextService->availableHiTypes('ipd', $id);
        $abdmAvailableCount = count($abdmAvailableTypes) ?: 3;
        $abdmLinkedCount    = count(array_filter($abdmStatuses, fn($s) => $s === 'linked'));
        // A pending row only counts while ABDM's callback can still arrive (same window as the link modal).
        $pendingCutoff   = now('UTC')->subSeconds(config('abdm.care_context_pending_timeout', 600))->toDateTimeString();
        $abdmPendingAny  = $careContexts->where('status', 'pending')->where('created_on', '>', $pendingCutoff)->count() > 0;
        // Nothing left to link: the overview then drops its "Link to ABDM" button (the badge stays).
        $abdmFullyLinked = !empty($ipd->patient?->abha_address)
            && $careContextService->isFullyLinked('ipd', $id);

        if ($abdmLinkedCount >= $abdmAvailableCount && $abdmLinkedCount > 0) {
            $abdmBadge = '<span class="badge bg-success" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="All ' . $abdmAvailableCount . ' IPD care contexts are linked on ABDM">ABDM Linked</span>';
        } elseif ($abdmLinkedCount > 0) {
            $abdmBadge = '<span class="badge bg-warning text-dark" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $abdmLinkedCount . ' of ' . $abdmAvailableCount . ' IPD care contexts linked on ABDM">ABDM ' . $abdmLinkedCount . '/' . $abdmAvailableCount . '</span>';
        } elseif ($abdmPendingAny) {
            $abdmBadge = '<span class="badge bg-warning text-dark" style="margin-left:6px;">ABDM Pending</span>';
        } else {
            $abdmBadge = '<span class="badge bg-secondary" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="This admission has not been linked as a care context on ABDM yet">ABDM Not Linked</span>';
        }
        // Keep $careContext for backward compat with existing partial
        $careContext = AbdmCareContext::where('ipd_id', $id)->latest('id')->first();

        // Vitals, symptoms/findings, billing boxes, credit chart, medication, prescriptions, bed history
        $overview = $overviewService->build($ipd);

        // IPD consultant doctors (from ipd_doctors table)
        $ipdDoctors = DB::table('ipd_doctors')
            ->join('staff_members', 'staff_members.id', '=', 'ipd_doctors.doctor_id')
            ->join('designations', 'designations.id', '=', 'staff_members.designation_id')
            ->where('ipd_doctors.ipd_id', $id)
            ->select('ipd_doctors.id as ipd_doctor_id', 'staff_members.staff_name', 'staff_members.photo',
                'designations.designation', 'staff_members.mobile_number')
            ->get();

        // "Add Doctor" dropdown: active staff of the configured doctor department (legacy dropdown_doctors).
        $doctorDeptId = $settings->getIdField('doctor_department_id');
        $allDoctors   = $doctorDeptId > 0
            ? StaffMember::where('department_id', $doctorDeptId)->where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name'])
            : collect();

        return view('ipd.overview', compact(
            'ipd', 'careContext', 'careContexts', 'abdmLinkedCount', 'abdmPendingAny', 'abdmFullyLinked', 'abdmBadge',
            'overview', 'ipdDoctors', 'allDoctors', 'ps'
        ));
    }

    /** Overview "Save Changes": vitals, symptoms and findings are saved on the linked OPD row (legacy updateopdoverview). */
    public function updateOverview(UpdateIpdOverviewRequest $request, int $id): JsonResponse
    {
        $ipd = Ipd::findOrFail($id);
        Opd::whereKey($ipd->opd_id)->update($request->opdColumns());

        return response()->json(['success' => true, 'message' => 'Updated successfully.']);
    }

    // ─────────────────────────────────────────────
    // Prescriptions
    // ─────────────────────────────────────────────

    public function prescriptions(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_prescription'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);
        $prescriptions = IpdPrescription::with(['doctor', 'medicines.product', 'medicines.timings.timing', 'medicines.frequency', 'medicines.mealRelation', 'medicines.condition'])
            ->where('ipd_id', $ipdId)->orderByDesc('prescription_date_time')->get();

        $doctors = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);
        $medicines = PharmaProduct::with(['doseUnit', 'route'])->where('is_active', 1)->orderBy('product_name')->get();
        $frequencies = MedicineFrequencyType::where('is_active', 1)->orderBy('sort_order')->get();
        $meals = MedicineMealRelation::where('is_active', 1)->orderBy('sort_order')->get();
        $timings = MedicineTimingOfDay::where('is_active', 1)->orderBy('sort_order')->get();
        $conditions = MedicalCondition::where('is_active', 1)->orderBy('condition_name')->get();

        $pathologyDeptId = IdFieldSetting::first()?->pathology_department_id ?? 3;
        $pathologyCharges = Charge::where('charge_department_id', $pathologyDeptId)->orderBy('charge_name')->get(['id', 'charge_name']);

        $radiologyDeptId = IdFieldSetting::first()?->radiology_department_id ?? 4;
        $radiologyCharges = Charge::where('charge_department_id', $radiologyDeptId)->orderBy('charge_name')->get(['id', 'charge_name']);

        $procedureCharges = Charge::whereHas('department', fn($q) => $q->whereRaw('LOWER(category) = ?', ['procedure']))
            ->orderBy('charge_name')->get(['id', 'charge_name']);

        return view('ipd.prescriptions', compact(
            'ipd', 'prescriptions', 'doctors', 'medicines', 'frequencies',
            'meals', 'timings', 'conditions', 'pathologyCharges',
            'radiologyCharges', 'procedureCharges', 'ps'
        ));
    }

    public function storePrescription(IpdPrescriptionRequest $request, int $ipdId): JsonResponse
    {
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

        $rx = IpdPrescription::create([
            'prescription_date_time' => $parsedDateTime,
            'ipd_id'                 => $ipdId,
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
            'ipd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
    }

    public function destroyPrescription(int $id): JsonResponse
    {
        $rx = IpdPrescription::findOrFail($id);
        $medIds = IpdMedicine::where('ipd_prescription_id', $id)->pluck('id');
        if ($medIds->isNotEmpty()) {
            IpdMedicineTimingSelection::whereIn('ipd_medicine_id', $medIds)->delete();
            IpdMedicine::whereIn('id', $medIds)->delete();
        }
        $rx->delete();
        return response()->json(['success' => true, 'result' => 'OK', 'message' => 'Prescription deleted.']);
    }

    public function copyPrescription(IpdPrescriptionRequest $request, int $ipdId): JsonResponse
    {
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

        $rx = IpdPrescription::create([
            'prescription_date_time' => $parsedDateTime,
            'ipd_id'                 => $ipdId,
            'doctor_id'              => (int) $request->input('doctor_id'),
            'pathology_tests'        => $pathologyTests,
            'radiology_tests'        => $radiologyTests,
            'procedures'             => $procedures,
            'findings'               => $request->input('findings') ?? '',
        ]);

        $this->savePrescriptionMedicines($request, $rx->id);

        return response()->json([
            'success'             => true,
            'ipd_prescription_id' => $rx->id,
        ]);
    }

    public function getPrescription(int $rxId): JsonResponse
    {
        $rx = IpdPrescription::with([
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
            $doseDisplay = trim($doseQty . ' ' . $unitName);
            $freqText = e($med->frequency?->display_text ?? 'Not Specified');
            $mealText = e($med->mealRelation?->relation_name ?? 'Not Specified');
            $durationDays = e($med->duration_days ?? '1');
            $condText = e($med->condition?->condition_name ?? 'Not Specified');

            $timingNames = [];
            $timingHidden = '';
            foreach ($med->timings as $t) {
                if ($t->timing && $t->timing->timing_name) {
                    $timingNames[] = e($t->timing->timing_name);
                }
                $timingHidden .= '<input type="hidden" name="timing_ids_'.$nor.'[]" value="'.$t->timing_id.'">';
            }
            $timingText = count($timingNames) > 0 ? implode(' + ', $timingNames) : 'Not Specified';

            $tableHtml .= '<tr id="e_ipd_pmr_'.$nor.'" class="epmr_'.$nor.'">
                <td><a href="javascript:void(0)" onclick="removeEIPDPrescriptionMedicine('.$nor.')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>
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

    public function updatePrescription(IpdPrescriptionRequest $request, int $rxId): JsonResponse
    {
        $rx = IpdPrescription::findOrFail($rxId);

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

        $medIds = IpdMedicine::where('ipd_prescription_id', $rxId)->pluck('id');
        if ($medIds->isNotEmpty()) {
            IpdMedicineTimingSelection::whereIn('ipd_medicine_id', $medIds)->delete();
            IpdMedicine::whereIn('id', $medIds)->delete();
        }

        $this->savePrescriptionMedicines($request, $rxId);

        return response()->json([
            'success'             => true,
            'message'             => 'Prescription updated.',
            'ipd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
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
                $med = IpdMedicine::create([
                    'ipd_prescription_id'  => $prescriptionId,
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
                            IpdMedicineTimingSelection::create([
                                'ipd_medicine_id' => $med->id,
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

                $med = IpdMedicine::create([
                    'ipd_prescription_id'  => $prescriptionId,
                    'pharma_product_id'    => $pId,
                    'dose_quantity'        => isset($m['dose_quantity']) ? (float) $m['dose_quantity'] : 1.0,
                    'frequency_type_id'    => isset($m['frequency_type_id']) ? (int) $m['frequency_type_id'] : 1,
                    'meal_relation_id'     => !empty($m['meal_relation_id']) ? (int) $m['meal_relation_id'] : null,
                    'duration_days'        => isset($m['duration_days']) ? (int) $m['duration_days'] : 1,
                    'medical_condition_id' => !empty($m['medical_condition_id']) ? (int) $m['medical_condition_id'] : null,
                    'created_on'           => now()->toDateTimeString(),
                    'updated_on'           => now()->toDateTimeString(),
                ]);
                if (!empty($m['timing_ids']) && is_array($m['timing_ids'])) {
                    foreach ($m['timing_ids'] as $timingId) {
                        if (!empty($timingId)) {
                            IpdMedicineTimingSelection::create([
                                'ipd_medicine_id' => $med->id,
                                'timing_id'       => (int) $timingId,
                                'created_on'      => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    // ─────────────────────────────────────────────
    // Charges / Services
    // ─────────────────────────────────────────────

    public function charges(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_charges'), 403);
        $ipd = Ipd::with(['patient.patientCategory', 'doctor'])->findOrFail($ipdId);
        $patient_category_id = (int) ($ipd->patient?->patient_category_id ?? 0);
        $charges = BillingCharge::with(['chargeDepartment', 'payments', 'details.charge'])
            ->where('module', 'IPD')
            ->where('module_id', $ipdId)
            ->orderByDesc('id')
            ->get();
        $chargeDepts = ChargeDepartment::where('active', 'Yes')->orderBy('department_name')->get();
        $prescriptions = IpdPrescription::where('ipd_id', $ipdId)->orderByDesc('id')->get();

        return view('ipd.charges', compact('ipd', 'charges', 'chargeDepts', 'prescriptions', 'patient_category_id', 'ps'));
    }

    public function destroyCharge(int $id): JsonResponse
    {
        BillingCharge::forModule('IPD')->findOrFail($id);
        DB::beginTransaction();
        try {
            BillingChargeDetail::where('module_charge_id', $id)->delete();
            BillingChargePayment::where('billing_charge_id', $id)->delete();
            $svc = IpdService::where('ipd_charge_id', $id)->first();
            if ($svc) {
                IpdServiceDetail::where('ipd_service_id', $svc->id)->delete();
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

    public function storeCharge(IpdChargeRequest $request, int $ipdId): JsonResponse
    {
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
        $addon_discount_amount_total = (float) $request->input('ipd_charges_addon_discount', 0);
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
        $first_ipd_charge_id = 0;
        $created_ipd_charge_ids = [];

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
                    'module'                => 'IPD',
                    'module_id'             => $ipdId,
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
                $created_ipd_charge_ids[] = $bc->id;
                if ($first_ipd_charge_id === 0) {
                    $first_ipd_charge_id = $bc->id;
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

                $ipd_service_id = 0;
                if ($is_service) {
                    $svc = IpdService::create([
                        'service_date_time'    => $charge_date_time,
                        'service_title'        => $department_name . ' ' . $bc->id,
                        'ipd_id'               => $ipdId,
                        'ipd_charge_id'        => $bc->id,
                        'charge_department_id' => $charge_dept_id,
                        'status'               => 'New',
                        'created_on'           => now()->toDateTimeString(),
                        'user_id'              => CurrentUser::id(),
                    ]);
                    $ipd_service_id = $svc->id;
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

                    if ($ipd_service_id > 0) {
                        IpdServiceDetail::create([
                            'ipd_service_id'        => $ipd_service_id,
                            'ipd_charge_detail_id'  => $bcd->id,
                            'charge_id'             => $cId,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success'         => true,
                'message'         => 'Charge saved.',
                'ipd_charge_id'   => $first_ipd_charge_id,
                'ipd_charge_ids'  => $created_ipd_charge_ids,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function getCharge(int $id): JsonResponse
    {
        $charge = BillingCharge::forModule('IPD')->with(['chargeDepartment', 'details.charge'])->findOrFail($id);
        $table_html = '';
        $i = 0;
        foreach ($charge->details as $detail) {
            $i++;
            $cName = e($detail->charge?->charge_name ?? '');
            $table_html .= '<tr id="chr_' . $i . '"><td><a href="javascript:void(0)" onclick="removeEIPDChargeDetail(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>' . $cName . '<input type="hidden" name="charge_id_value[]" value="' . $detail->charge_id . '"></td><td>' . $detail->amount . '<input type="hidden" name="amount_value[]" value="' . $detail->amount . '"></td><td>' . $detail->discount_percentage . '<input type="hidden" name="discount_percentage_value[]" value="' . $detail->discount_percentage . '"></td><td>' . $detail->discount_amount . '<input type="hidden" name="discount_amount_value[]" value="' . $detail->discount_amount . '"></td><td>' . $detail->quantity . '<input type="hidden" name="quantity_value[]" value="' . $detail->quantity . '"></td><td>' . $detail->total . '<input type="hidden" name="total_value[]" value="' . $detail->total . '"></td></tr>';
        }

        $edit = 'Yes';
        $svc = IpdService::where('ipd_charge_id', $id)->first();
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
        BillingCharge::forModule('IPD')->findOrFail($id);
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

    public function updateCharge(IpdChargeRequest $request, int $id): JsonResponse
    {
        $charge = BillingCharge::forModule('IPD')->findOrFail($id);
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
                'amount'                => (float) $request->input('ipd_charges_amount', $charge->amount),
                'discount'              => (float) $request->input('ipd_charges_discount', $charge->discount),
                'addon_discount_mode'   => $request->input('addon_discount_mode', $charge->addon_discount_mode),
                'addon_discount_value'  => (float) $request->input('addon_discount_value', $charge->addon_discount_value),
                'addon_discount_amount' => (float) $request->input('ipd_charges_addon_discount', $charge->addon_discount_amount),
                'total'                 => (float) $request->input('ipd_charges_total', $charge->total),
                'paid_amount'           => (float) $request->input('paid_amount', $charge->paid_amount),
                'payment_mode'          => (string) $request->input('payment_mode', $charge->payment_mode),
                'user_id'               => CurrentUser::id(),
            ]);

            BillingChargeDetail::where('module_charge_id', $id)->delete();

            $oldSvc = IpdService::where('ipd_charge_id', $id)->first();
            if ($oldSvc) {
                IpdServiceDetail::where('ipd_service_id', $oldSvc->id)->delete();
                $oldSvc->delete();
            }

            $newSvcId = 0;
            if ($is_service) {
                $newSvc = IpdService::create([
                    'service_date_time'    => $charge_date_time,
                    'service_title'        => $charge->charge_title,
                    'ipd_id'               => $charge->module_id,
                    'ipd_charge_id'        => $id,
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
                    IpdServiceDetail::create([
                        'ipd_service_id'       => $newSvcId,
                        'ipd_charge_detail_id' => $bcd->id,
                        'charge_id'            => (int) $chargeIds[$i],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'ipd_charge_id' => $id, 'message' => 'Saved Successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function collectPayments(Request $request, int $ipdId): JsonResponse
    {
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
                    $modeStr = implode(', ', $payment_modes_used);
                    $charge->update([
                        'paid_amount'  => $charge->total,
                        'payment_mode' => $charge->payment_mode ? $charge->payment_mode . ', ' . $modeStr : $modeStr,
                    ]);
                }
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Saved Successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function getChargeAmount(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $patientCategoryId = (int) $request->input('patient_category_id');
        $amount = DB::table('patient_categories_charges')
            ->where('charge_id', $id)
            ->where('patient_category_id', $patientCategoryId)
            ->value('amount') ?? 0;
        return response()->json(['amount' => (float) $amount]);
    }

    public function dropdownAdCharges(Request $request): JsonResponse
    {
        $deptId = (int) $request->input('charge_department_id');
        $search = $request->input('q', '');
        $query = Charge::query();
        if ($deptId > 0) {
            $query->where('charge_department_id', $deptId);
        }
        if ($search !== '') {
            $query->where('charge_name', 'LIKE', "%{$search}%");
        }
        $charges = $query->orderBy('charge_name')->limit(50)->get(['id', 'charge_name']);
        $data = $charges->map(fn($c) => ['id' => $c->id, 'text' => $c->charge_name]);
        return response()->json($data);
    }

    public function dropdownDepartments(Request $request): JsonResponse
    {
        $search = $request->input('q', '');
        $query = ChargeDepartment::where('active', 'Yes');
        if ($search !== '') {
            $query->where('department_name', 'LIKE', "%{$search}%");
        }
        $depts = $query->orderBy('department_name')->get(['id', 'department_name']);
        $data = $depts->map(fn($d) => ['id' => $d->id, 'text' => $d->department_name]);
        return response()->json($data);
    }

    public function getPrescriptionDetails(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $rx = IpdPrescription::find($id);
        $html = '<option></option>';
        if ($rx) {
            if (!empty($rx->pathology_tests)) {
                $html .= '<option value="' . e($rx->pathology_tests) . '">Biochemistry</option>';
            }
            if (!empty($rx->radiology_tests)) {
                $html .= '<option value="' . e($rx->radiology_tests) . '">Radiology</option>';
            }
            if (!empty($rx->procedures)) {
                $html .= '<option value="' . e($rx->procedures) . '">Procedures</option>';
            }
        }
        return response()->json(['html' => $html]);
    }

    /**
     * The charges of one prescription test group, each with its "add to billing" button. Items already in billing
     * (saved on this admission's bills, or queued in the modal's table: `exclude[]`) are not offered - see
     * ChargeLookupService::prescriptionChargesHtml().
     */
    public function getPrescriptionTD(Request $request, \App\Services\ChargeLookupService $lookup): JsonResponse
    {
        return response()->json(['html' => $lookup->prescriptionChargesHtml(
            (string) $request->input('type', ''),
            (string) $request->input('values', ''),
            'IPD',
            (int) $request->input('prescription_id', 0),
            (array) $request->input('exclude', [])
        )]);
    }

    public function getChargeForPrescription(Request $request): JsonResponse
    {
        $chargeId          = (int) $request->input('charge_id');
        $patientCategoryId = (int) $request->input('patient_category_id');

        $row = DB::table('charges as c')
            ->leftJoin('charge_departments as d', 'd.id', '=', 'c.charge_department_id')
            ->leftJoin('patient_categories_charges as pcc', function ($join) use ($chargeId, $patientCategoryId) {
                $join->on('pcc.charge_id', '=', 'c.id')
                     ->where('pcc.patient_category_id', '=', $patientCategoryId);
            })
            ->where('c.id', $chargeId)
            ->select('c.id', 'c.charge_name', 'c.charge_department_id', 'c.charge_category_id', 'd.department_name', 'pcc.amount')
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Charge not found']);
        }

        return response()->json([
            'success'              => true,
            'charge_id'            => (int) $row->id,
            'charge_name'          => $row->charge_name ?? '',
            'charge_department_id' => (int) $row->charge_department_id,
            'department_name'      => $row->department_name ?? '',
            'charge_category_id'   => (int) ($row->charge_category_id ?? 0),
            'amount'               => (float) ($row->amount ?? 0),
        ]);
    }

    public function getDepartmentName(Request $request): JsonResponse
    {
        $deptId = (int) ($request->input('department_id') ?: $request->input('charge_department_id'));
        $dept = ChargeDepartment::find($deptId);
        return response()->json([
            'success' => (bool) $dept,
            'department_name' => $dept?->department_name ?? '',
        ]);
    }

    public function getCategoryName(Request $request): JsonResponse
    {
        $catId = (int) ($request->input('category_id') ?: $request->input('charge_category_id'));
        $cat = ChargeCategory::find($catId);
        return response()->json([
            'success' => (bool) $cat,
            'category_name' => $cat?->charge_category ?? '',
            'charge_category' => $cat?->charge_category ?? '',
        ]);
    }

    public function getChargeName(Request $request): JsonResponse
    {
        $chargeId = (int) ($request->input('charge_id') ?: $request->input('id'));
        $charge = Charge::find($chargeId);
        return response()->json([
            'success' => (bool) $charge,
            'charge_name' => $charge?->charge_name ?? '',
        ]);
    }

    // ─────────────────────────────────────────────
    // Consultations
    // ─────────────────────────────────────────────

    public function consultations(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_consultations'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);
        $consultations = IpdConsultation::with('doctor')->where('ipd_id', $ipdId)->orderByDesc('id')->get();
        $doctors = \App\Models\StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);

        return view('ipd.consultations', compact('ipd', 'consultations', 'doctors', 'ps'));
    }

    public function storeConsultation(IpdConsultationRequest $request, int $ipdId): JsonResponse
    {
        $id = $ipdId;
        $rawDt = $request->input('consultation_date_time');
        try {
            $consultation_date_time = $rawDt ? \Carbon\Carbon::parse($rawDt)->toDateTimeString() : now()->toDateTimeString();
        } catch (\Exception $e) {
            $consultation_date_time = now()->toDateTimeString();
        }

        $c = IpdConsultation::create([
            'consultation_date_time' => $consultation_date_time,
            'ipd_id'                 => $id,
            'doctor_id'              => (int) $request->input('doctor_id'),
            'remarks'                => (string) $request->input('remarks', ''),
            'user_id'                => CurrentUser::id() ?: 1,
        ]);

        return response()->json([
            'success'             => true,
            'message'             => 'Saved Successfully.',
            'ipd_consultation_id' => $c->id,
            'data'                => $c,
        ]);
    }

    public function getConsultation(int $id): JsonResponse
    {
        $c = IpdConsultation::with('doctor')->findOrFail($id);
        return response()->json([
            'id'                     => $c->id,
            'consultation_date_time' => \Carbon\Carbon::parse($c->consultation_date_time)->format('d-m-Y h:i a'),
            'ipd_id'                 => $c->ipd_id,
            'doctor_id'              => $c->doctor_id,
            'staff_name'             => $c->doctor?->staff_name ?? '',
            'remarks'                => $c->remarks ?? '',
        ]);
    }

    public function updateConsultation(IpdConsultationRequest $request, int $id): JsonResponse
    {
        $c = IpdConsultation::findOrFail($id);

        $rawDt = $request->input('consultation_date_time');
        try {
            $consultation_date_time = $rawDt ? \Carbon\Carbon::parse($rawDt)->toDateTimeString() : $c->consultation_date_time;
        } catch (\Exception $e) {
            $consultation_date_time = $c->consultation_date_time;
        }

        $c->update([
            'consultation_date_time' => $consultation_date_time,
            'doctor_id'              => (int) ($request->input('doctor_id') ?: $c->doctor_id),
            'remarks'                => (string) $request->input('remarks', $c->remarks),
            'user_id'                => CurrentUser::id() ?: $c->user_id,
        ]);

        return response()->json([
            'success'             => true,
            'message'             => 'Saved Successfully.',
            'ipd_consultation_id' => $c->id,
            'data'                => $c,
        ]);
    }

    public function destroyConsultation(int $id): JsonResponse
    {
        IpdConsultation::where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'result'  => 'OK',
            'message' => 'Deleted Successfully.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Vital Monitoring
    // ─────────────────────────────────────────────

    public function vitalMonitoring(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_vital_monitoring'), 403);
        $ipd = Ipd::with(['patient', 'bed.wardCategory'])->findOrFail($ipdId);

        $new = $ps->hasPermission('b_ipd_new_vital_monitoring');
        $edit = $ps->hasPermission('b_ipd_edit_vital_monitoring');
        $delete = $ps->hasPermission('b_ipd_delete_vital_monitoring');

        $vitals = DB::table('ipd_vital_monitoring as vm')
            ->leftJoin('staff_members as sm', 'sm.id', '=', 'vm.user_id')
            ->join('ipds', 'ipds.id', '=', 'vm.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', 'ipds.bed_id')
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('vm.ipd_id', $ipdId)
            ->select(
                'vm.id',
                'vm.ipd_id',
                'vm.bed_id',
                'vm.vital_monitoring_date',
                'vm.remarks',
                'vm.user_id',
                'sm.staff_name as added_by',
                'beds.bed_name',
                'ward_categories.ward_category'
            )
            ->orderByDesc('vm.id')
            ->get()
            ->map(function ($row) {
                $row->vital_monitoring_date = $row->vital_monitoring_date ? \Carbon\Carbon::parse($row->vital_monitoring_date)->format('d-m-Y') : '';
                return $row;
            });

        $vmIds = $vitals->pluck('id')->toArray();
        $detailsByVm = [];
        if (!empty($vmIds)) {
            $details = DB::table('ipd_vital_monitoring_detail')
                ->whereIn('ipd_vital_monitoring_id', $vmIds)
                ->orderBy('id')
                ->get()
                ->map(function ($d) {
                    $d->vital_monitoring_time = $d->vital_monitoring_time ? \Carbon\Carbon::parse($d->vital_monitoring_time)->format('h:i A') : '';
                    return $d;
                });

            foreach ($details as $d) {
                $detailsByVm[$d->ipd_vital_monitoring_id][] = $d;
            }
        }

        foreach ($vitals as $vm) {
            $vm->details = $detailsByVm[$vm->id] ?? [];
        }

        return view('ipd.vital-monitoring', compact('ipd', 'vitals', 'ps', 'new', 'edit', 'delete'));
    }

    public function destroyVitalMonitoring(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        DB::table('ipd_vital_monitoring_detail')->where('ipd_vital_monitoring_id', $id)->delete();
        DB::table('ipd_vital_monitoring')->where('id', $id)->delete();
        return response()->json(['result' => 'OK', 'success' => true, 'message' => 'Vitals deleted.']);
    }

    public function getVitalMonitoring(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        if ($id <= 0) {
            return response()->json(['error' => 'Invalid ID'], 400);
        }

        $row = DB::table('ipd_vital_monitoring')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $row->vital_monitoring_date = $row->vital_monitoring_date ? \Carbon\Carbon::parse($row->vital_monitoring_date)->format('d-m-Y') : '';

        $details = DB::table('ipd_vital_monitoring_detail')
            ->where('ipd_vital_monitoring_id', $id)
            ->orderBy('id')
            ->get();

        $tableHtml = '';
        $i = 0;
        foreach ($details as $trow) {
            $i++;
            $timeFormatted = $trow->vital_monitoring_time ? \Carbon\Carbon::parse($trow->vital_monitoring_time)->format('h:i A') : '';
            $tableHtml .= '<tr id="epmr_' . $i . '">';
            $tableHtml .= '<td><a href="javascript:void(0)" onclick="removeEVMD(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>';
            $tableHtml .= '<td>' . e($timeFormatted) . '<input type="hidden" name="vital_monitoring_time_value[]" value="' . e($timeFormatted) . '"></td>';
            $tableHtml .= '<td>' . e($trow->bp) . '<input type="hidden" name="bp_value[]" value="' . e($trow->bp) . '"></td>';
            $tableHtml .= '<td>' . e($trow->pulse) . '<input type="hidden" name="pulse_value[]" value="' . e($trow->pulse) . '"></td>';
            $tableHtml .= '<td>' . e($trow->temperature) . '<input type="hidden" name="temperature_value[]" value="' . e($trow->temperature) . '"></td>';
            $tableHtml .= '<td>' . e($trow->respiration) . '<input type="hidden" name="respiration_value[]" value="' . e($trow->respiration) . '"></td>';
            $tableHtml .= '<td>' . e($trow->spo2) . '<input type="hidden" name="spo2_value[]" value="' . e($trow->spo2) . '"></td>';
            $tableHtml .= '<td>' . e($trow->fbs) . '<input type="hidden" name="fbs_value[]" value="' . e($trow->fbs) . '"></td>';
            $tableHtml .= '<td>' . e($trow->rbs) . '<input type="hidden" name="rbs_value[]" value="' . e($trow->rbs) . '"></td>';
            $tableHtml .= '<td>' . e($trow->intake) . '<input type="hidden" name="intake_value[]" value="' . e($trow->intake) . '"></td>';
            $tableHtml .= '<td>' . e($trow->output) . '<input type="hidden" name="output_value[]" value="' . e($trow->output) . '"></td>';
            $tableHtml .= '</tr>';
        }

        return response()->json([
            'row'        => $row,
            'table_html' => $tableHtml,
            'nor'        => $i,
        ]);
    }

    public function updateVitalMonitoring(IpdVitalMonitoringRequest $request, int $id): JsonResponse
    {
        $vmId = $id;
        $vm = DB::table('ipd_vital_monitoring')->where('id', $vmId)->first();
        if (!$vm) {
            return response()->json(['success' => false, 'error' => 'Record not found.'], 404);
        }

        $date = $request->input('vital_monitoring_date');
        $parsedDate = $date ? date('Y-m-d', strtotime($date)) : $vm->vital_monitoring_date;
        $remarks = (string) ($request->input('remarks') ?? $vm->remarks);

        DB::table('ipd_vital_monitoring')->where('id', $vmId)->update([
            'vital_monitoring_date' => $parsedDate,
            'remarks'               => $remarks,
        ]);

        DB::table('ipd_vital_monitoring_detail')->where('ipd_vital_monitoring_id', $vmId)->delete();

        if ($request->has('vital_monitoring_time_value') && is_array($request->input('vital_monitoring_time_value'))) {
            $times = $request->input('vital_monitoring_time_value');
            $bp = $request->input('bp_value', []);
            $pulse = $request->input('pulse_value', []);
            $temp = $request->input('temperature_value', []);
            $resp = $request->input('respiration_value', []);
            $spo2 = $request->input('spo2_value', []);
            $fbs = $request->input('fbs_value', []);
            $rbs = $request->input('rbs_value', []);
            $intake = $request->input('intake_value', []);
            $output = $request->input('output_value', []);

            for ($i = 0; $i < count($times); $i++) {
                $t = $times[$i];
                if (!$t) continue;
                DB::table('ipd_vital_monitoring_detail')->insert([
                    'ipd_vital_monitoring_id' => $vmId,
                    'vital_monitoring_time'   => date('H:i:s', strtotime($t)),
                    'bp'                      => $bp[$i] ?? '',
                    'pulse'                   => $pulse[$i] ?? '',
                    'temperature'             => $temp[$i] ?? '',
                    'respiration'             => $resp[$i] ?? '',
                    'spo2'                    => $spo2[$i] ?? '',
                    'fbs'                     => $fbs[$i] ?? '',
                    'rbs'                     => $rbs[$i] ?? '',
                    'intake'                  => $intake[$i] ?? '',
                    'output'                  => $output[$i] ?? '',
                ]);
            }
        }

        return response()->json([
            'ipd_vital_monitoring_id' => $vmId,
            'success'                 => true,
            'message'                 => 'Vitals updated.',
        ]);
    }

    public function storeVitalMonitoring(IpdVitalMonitoringRequest $request, int $ipdId): JsonResponse
    {
        $ipd = Ipd::findOrFail($ipdId);

        $date = $request->input('vital_monitoring_date');
        $parsedDate = $date ? date('Y-m-d', strtotime($date)) : now()->toDateString();
        $bedId = (int) ($request->input('bed_id') ?: $ipd->bed_id);
        $remarks = (string) ($request->input('remarks') ?? '');
        $userId = CurrentUser::id() ?? 1;

        $vmId = DB::table('ipd_vital_monitoring')->insertGetId([
            'vital_monitoring_date' => $parsedDate,
            'ipd_id'                => $ipdId,
            'bed_id'                => $bedId,
            'remarks'               => $remarks,
            'user_id'               => $userId,
        ]);

        if ($request->has('vital_monitoring_time_value') && is_array($request->input('vital_monitoring_time_value'))) {
            $times = $request->input('vital_monitoring_time_value');
            $bp = $request->input('bp_value', []);
            $pulse = $request->input('pulse_value', []);
            $temp = $request->input('temperature_value', []);
            $resp = $request->input('respiration_value', []);
            $spo2 = $request->input('spo2_value', []);
            $fbs = $request->input('fbs_value', []);
            $rbs = $request->input('rbs_value', []);
            $intake = $request->input('intake_value', []);
            $output = $request->input('output_value', []);

            for ($i = 0; $i < count($times); $i++) {
                $t = $times[$i];
                if (!$t) continue;
                DB::table('ipd_vital_monitoring_detail')->insert([
                    'ipd_vital_monitoring_id' => $vmId,
                    'vital_monitoring_time'   => date('H:i:s', strtotime($t)),
                    'bp'                      => $bp[$i] ?? '',
                    'pulse'                   => $pulse[$i] ?? '',
                    'temperature'             => $temp[$i] ?? '',
                    'respiration'             => $resp[$i] ?? '',
                    'spo2'                    => $spo2[$i] ?? '',
                    'fbs'                     => $fbs[$i] ?? '',
                    'rbs'                     => $rbs[$i] ?? '',
                    'intake'                  => $intake[$i] ?? '',
                    'output'                  => $output[$i] ?? '',
                ]);
            }
        } elseif ($request->has('entries') && is_array($request->input('entries'))) {
            foreach ($request->input('entries') as $entry) {
                if (empty($entry['vital_monitoring_time'])) continue;
                DB::table('ipd_vital_monitoring_detail')->insert([
                    'ipd_vital_monitoring_id' => $vmId,
                    'vital_monitoring_time'   => date('H:i:s', strtotime($entry['vital_monitoring_time'])),
                    'bp'                      => $entry['bp'] ?? '',
                    'pulse'                   => $entry['pulse'] ?? '',
                    'temperature'             => $entry['temperature'] ?? '',
                    'respiration'             => $entry['respiration'] ?? '',
                    'spo2'                    => $entry['spo2'] ?? '',
                    'fbs'                     => $entry['fbs'] ?? '',
                    'rbs'                     => $entry['rbs'] ?? '',
                    'intake'                  => $entry['intake'] ?? '',
                    'output'                  => $entry['output'] ?? '',
                ]);
            }
        }

        return response()->json([
            'ipd_vital_monitoring_id' => $vmId,
            'success'                 => true,
            'message'                 => 'Vitals saved.',
        ]);
    }

    public function storeVital(IpdVitalMonitoringRequest $request, int $ipdId): JsonResponse
    {
        return $this->storeVitalMonitoring($request, $ipdId);
    }

    // ─────────────────────────────────────────────
    // Intake / Output
    // ─────────────────────────────────────────────

    public function intakeOutput(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_intake_output'), 403);
        $ipd = Ipd::with(['patient', 'bed.wardCategory'])->findOrFail($ipdId);

        $new = $ps->hasPermission('b_ipd_new_intake_output');
        $edit = $ps->hasPermission('b_ipd_edit_intake_output');
        $delete = $ps->hasPermission('b_ipd_delete_intake_output');

        $records = DB::table('ipd_intake_output as io')
            ->leftJoin('staff_members as sm', 'sm.id', '=', 'io.user_id')
            ->join('ipds', 'ipds.id', '=', 'io.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', 'ipds.bed_id')
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('io.ipd_id', $ipdId)
            ->select(
                'io.id',
                'io.ipd_id',
                'io.bed_id',
                'io.intake_output_date',
                'io.remarks',
                'io.user_id',
                'sm.staff_name as added_by',
                'beds.bed_name',
                'ward_categories.ward_category'
            )
            ->orderByDesc('io.id')
            ->get()
            ->map(function ($row) {
                $row->intake_output_date = $row->intake_output_date ? \Carbon\Carbon::parse($row->intake_output_date)->format('d-m-Y') : '';
                return $row;
            });

        $ioIds = $records->pluck('id')->toArray();
        $detailsByIo = [];
        if (!empty($ioIds)) {
            $details = DB::table('ipd_intake_output_detail')
                ->whereIn('ipd_intake_output_id', $ioIds)
                ->orderBy('id')
                ->get()
                ->map(function ($d) {
                    $d->intake_output_time = $d->intake_output_time ? \Carbon\Carbon::parse($d->intake_output_time)->format('h:i A') : '';
                    return $d;
                });

            foreach ($details as $d) {
                $detailsByIo[$d->ipd_intake_output_id][] = $d;
            }
        }

        foreach ($records as $r) {
            $r->details = $detailsByIo[$r->id] ?? [];
        }

        return view('ipd.intake-output', compact('ipd', 'records', 'ps', 'new', 'edit', 'delete'));
    }

    public function destroyIntakeOutput(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        DB::table('ipd_intake_output_detail')->where('ipd_intake_output_id', $id)->delete();
        DB::table('ipd_intake_output')->where('id', $id)->delete();
        return response()->json(['result' => 'OK', 'success' => true, 'message' => 'Intake/output deleted.']);
    }

    public function getIntakeOutput(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        if ($id <= 0) {
            return response()->json(['error' => 'Invalid ID'], 400);
        }

        $row = DB::table('ipd_intake_output')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['error' => 'Record not found'], 404);
        }

        $row->intake_output_date = $row->intake_output_date ? \Carbon\Carbon::parse($row->intake_output_date)->format('d-m-Y') : '';

        $details = DB::table('ipd_intake_output_detail')
            ->where('ipd_intake_output_id', $id)
            ->orderBy('id')
            ->get();

        $tableHtml = '';
        $i = 0;
        foreach ($details as $trow) {
            $i++;
            $timeFormatted = $trow->intake_output_time ? \Carbon\Carbon::parse($trow->intake_output_time)->format('h:i A') : '';
            $tableHtml .= '<tr id="epmr_' . $i . '">';
            $tableHtml .= '<td><a href="javascript:void(0)" onclick="removeEIntakeOutputDetail(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>';
            $tableHtml .= '<td>' . e($timeFormatted) . '<input type="hidden" name="intake_output_time_value[]" value="' . e($timeFormatted) . '"></td>';
            $tableHtml .= '<td>' . e($trow->iv_fluids) . '<input type="hidden" name="iv_fluids_value[]" value="' . e($trow->iv_fluids) . '"></td>';
            $tableHtml .= '<td>' . e($trow->oral) . '<input type="hidden" name="oral_value[]" value="' . e($trow->oral) . '"></td>';
            $tableHtml .= '<td>' . e($trow->urine) . '<input type="hidden" name="urine_value[]" value="' . e($trow->urine) . '"></td>';
            $tableHtml .= '<td>' . e($trow->stool) . '<input type="hidden" name="stool_value[]" value="' . e($trow->stool) . '"></td>';
            $tableHtml .= '<td>' . e($trow->vomiting) . '<input type="hidden" name="vomiting_value[]" value="' . e($trow->vomiting) . '"></td>';
            $tableHtml .= '<td>' . e($trow->rt_asp) . '<input type="hidden" name="rt_asp_value[]" value="' . e($trow->rt_asp) . '"></td>';
            $tableHtml .= '<td>' . e($trow->drain) . '<input type="hidden" name="drain_value[]" value="' . e($trow->drain) . '"></td>';
            $tableHtml .= '</tr>';
        }

        return response()->json([
            'row'        => $row,
            'table_html' => $tableHtml,
            'nor'        => $i,
        ]);
    }

    public function updateIntakeOutput(IpdIntakeOutputRequest $request, int $id): JsonResponse
    {
        $ioId = $id;
        $io = DB::table('ipd_intake_output')->where('id', $ioId)->first();
        if (!$io) {
            return response()->json(['success' => false, 'error' => 'Record not found.'], 404);
        }

        $date = $request->input('intake_output_date');
        $parsedDate = $date ? date('Y-m-d', strtotime($date)) : $io->intake_output_date;
        $remarks = (string) ($request->input('remarks') ?? $io->remarks);

        DB::table('ipd_intake_output')->where('id', $ioId)->update([
            'intake_output_date' => $parsedDate,
            'remarks'            => $remarks,
        ]);

        DB::table('ipd_intake_output_detail')->where('ipd_intake_output_id', $ioId)->delete();

        if ($request->has('intake_output_time_value') && is_array($request->input('intake_output_time_value'))) {
            $times = $request->input('intake_output_time_value');
            $ivFluids = $request->input('iv_fluids_value', []);
            $oral = $request->input('oral_value', []);
            $urine = $request->input('urine_value', []);
            $stool = $request->input('stool_value', []);
            $vomiting = $request->input('vomiting_value', []);
            $rtAsp = $request->input('rt_asp_value', []);
            $drain = $request->input('drain_value', []);

            for ($i = 0; $i < count($times); $i++) {
                $t = $times[$i];
                if (!$t) continue;
                DB::table('ipd_intake_output_detail')->insert([
                    'ipd_intake_output_id' => $ioId,
                    'intake_output_time'   => date('H:i:s', strtotime($t)),
                    'iv_fluids'            => $ivFluids[$i] ?? '',
                    'oral'                 => $oral[$i] ?? '',
                    'urine'                => $urine[$i] ?? '',
                    'stool'                => $stool[$i] ?? '',
                    'vomiting'             => $vomiting[$i] ?? '',
                    'rt_asp'               => $rtAsp[$i] ?? '',
                    'drain'                => $drain[$i] ?? '',
                ]);
            }
        } elseif ($request->has('entries') && is_array($request->input('entries'))) {
            foreach ($request->input('entries') as $entry) {
                if (empty($entry['intake_output_time'])) continue;
                DB::table('ipd_intake_output_detail')->insert([
                    'ipd_intake_output_id' => $ioId,
                    'intake_output_time'   => date('H:i:s', strtotime($entry['intake_output_time'])),
                    'iv_fluids'            => $entry['iv_fluids'] ?? '',
                    'oral'                 => $entry['oral'] ?? '',
                    'urine'                => $entry['urine'] ?? '',
                    'stool'                => $entry['stool'] ?? '',
                    'vomiting'             => $entry['vomiting'] ?? '',
                    'rt_asp'               => $entry['rt_asp'] ?? '',
                    'drain'                => $entry['drain'] ?? '',
                ]);
            }
        }

        return response()->json([
            'ipd_intake_output_id' => $ioId,
            'success'              => true,
            'message'              => 'Intake/output updated.',
        ]);
    }

    public function storeIntakeOutput(IpdIntakeOutputRequest $request, int $ipdId): JsonResponse
    {
        $ipd = Ipd::findOrFail($ipdId);

        $date = $request->input('intake_output_date');
        $parsedDate = $date ? date('Y-m-d', strtotime($date)) : now()->toDateString();
        $bedId = (int) ($request->input('bed_id') ?: $ipd->bed_id);
        $remarks = (string) ($request->input('remarks') ?? '');
        $userId = CurrentUser::id() ?? 1;

        $ioId = DB::table('ipd_intake_output')->insertGetId([
            'intake_output_date' => $parsedDate,
            'ipd_id'             => $ipdId,
            'bed_id'             => $bedId,
            'remarks'            => $remarks,
            'user_id'            => $userId,
        ]);

        if ($request->has('intake_output_time_value') && is_array($request->input('intake_output_time_value'))) {
            $times = $request->input('intake_output_time_value');
            $ivFluids = $request->input('iv_fluids_value', []);
            $oral = $request->input('oral_value', []);
            $urine = $request->input('urine_value', []);
            $stool = $request->input('stool_value', []);
            $vomiting = $request->input('vomiting_value', []);
            $rtAsp = $request->input('rt_asp_value', []);
            $drain = $request->input('drain_value', []);

            for ($i = 0; $i < count($times); $i++) {
                $t = $times[$i];
                if (!$t) continue;
                DB::table('ipd_intake_output_detail')->insert([
                    'ipd_intake_output_id' => $ioId,
                    'intake_output_time'   => date('H:i:s', strtotime($t)),
                    'iv_fluids'            => $ivFluids[$i] ?? '',
                    'oral'                 => $oral[$i] ?? '',
                    'urine'                => $urine[$i] ?? '',
                    'stool'                => $stool[$i] ?? '',
                    'vomiting'             => $vomiting[$i] ?? '',
                    'rt_asp'               => $rtAsp[$i] ?? '',
                    'drain'                => $drain[$i] ?? '',
                ]);
            }
        } elseif ($request->has('entries') && is_array($request->input('entries'))) {
            foreach ($request->input('entries') as $entry) {
                if (empty($entry['intake_output_time'])) continue;
                DB::table('ipd_intake_output_detail')->insert([
                    'ipd_intake_output_id' => $ioId,
                    'intake_output_time'   => date('H:i:s', strtotime($entry['intake_output_time'])),
                    'iv_fluids'            => $entry['iv_fluids'] ?? '',
                    'oral'                 => $entry['oral'] ?? '',
                    'urine'                => $entry['urine'] ?? '',
                    'stool'                => $entry['stool'] ?? '',
                    'vomiting'             => $entry['vomiting'] ?? '',
                    'rt_asp'               => $entry['rt_asp'] ?? '',
                    'drain'                => $entry['drain'] ?? '',
                ]);
            }
        }

        return response()->json([
            'ipd_intake_output_id' => $ioId,
            'success'              => true,
            'message'              => 'Intake/output saved.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Discharge
    // ─────────────────────────────────────────────

    public function dischargeForm(int $id, PermissionService $ps)
    {
        $canView = $ps->hasPermission('b_ipd_discharge_patient');
        if (!$canView) {
            return view('ipd.discharge', [
                'canView'                    => false,
                'notFound'                   => false,
                'ipd'                        => null,
                'admissionDateTimeFormatted' => '',
                'currentDateTimeFormatted'   => '',
                'consultantDoctors'          => collect(),
                'allDoctors'                 => collect(),
                'investigations'             => collect(),
                'procedures'                 => collect(),
                'latestMedicines'            => collect(),
                'ps'                         => $ps,
            ]);
        }

        $ipd = Ipd::with([
            'patient',
            'opd',
            'bed.floor',
            'bed.bedType',
            'bed.wardCategory',
            'discharge',
            'doctor.designation',
        ])->find($id);

        if (!$ipd) {
            return view('ipd.discharge', [
                'canView'                    => true,
                'notFound'                   => true,
                'ipd'                        => null,
                'admissionDateTimeFormatted' => '',
                'currentDateTimeFormatted'   => '',
                'consultantDoctors'          => collect(),
                'allDoctors'                 => collect(),
                'investigations'             => collect(),
                'procedures'                 => collect(),
                'latestMedicines'            => collect(),
                'ps'                         => $ps,
            ]);
        }

        $admissionDateTimeFormatted = !empty($ipd->admission_date_time)
            ? date('d-m-Y h:i A', strtotime($ipd->admission_date_time))
            : '';
        $currentDateTimeFormatted = date('d-m-Y h:i a');

        // Doctors associated with this admission
        $consultantDoctors = collect();
        if (Schema::hasTable('ipd_doctors')) {
            $consultantDoctors = DB::table('ipd_doctors')
                ->join('staff_members', 'staff_members.id', '=', 'ipd_doctors.doctor_id')
                ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('ipd_doctors.ipd_id', $id)
                ->select('ipd_doctors.id as ipd_doctor_id', 'staff_members.staff_name', 'staff_members.photo', 'designations.designation', 'staff_members.mobile_number')
                ->get();
        }

        // All active doctors for "Add Doctor" modal
        $allDoctors = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);

        // Investigations query - if discharge already has saved investigations, load them; else auto fill from lab-investigations
        $investigations = collect();
        if ($ipd->discharge && Schema::hasTable('ipd_discharge_investigations')) {
            $savedInv = DB::table('ipd_discharge_investigations')->where('discharge_id', $ipd->discharge->id)->get();
            if ($savedInv->isNotEmpty()) {
                $investigations = $savedInv;
            }
        }
        if ($investigations->isEmpty()) {
            $investigations = $this->getDischargeInvestigations($id);
        }

        // Procedures query - if discharge already has saved procedures, load them; else auto fill from procedures
        $procedures = collect();
        if ($ipd->discharge && Schema::hasTable('ipd_discharge_procedures')) {
            $savedProcs = DB::table('ipd_discharge_procedures')->where('discharge_id', $ipd->discharge->id)->get();
            if ($savedProcs->isNotEmpty()) {
                $procedures = $savedProcs->map(fn($p) => (object)[
                    'charge_title' => $p->procedure_detail,
                    'procedure'    => $p->procedure_detail,
                ]);
            }
        }
        if ($procedures->isEmpty()) {
            $procedures = $this->getDischargeProcedures($id);
        }

        // Latest prescription medicines (Treatment Given)
        $latestMedicines = collect();
        if (Schema::hasTable('ipd_prescriptions') && Schema::hasTable('ipd_medicines') && Schema::hasTable('pharma_products')) {
            $latestPrescription = DB::table('ipd_prescriptions')
                ->where('ipd_id', $id)
                ->orderByDesc('id')
                ->first();

            if ($latestPrescription) {
                $selectRaw = (DB::getDriverName() === 'sqlite')
                    ? "im.id, im.pharma_product_id, pp.product_name, im.dose_quantity, im.frequency_type_id, ft.display_text as frequency_display, im.meal_relation_id, mr.relation_name as meal_name, im.duration_days, im.medical_condition_id, mc.condition_name, GROUP_CONCAT(t.timing_name, ' + ') as timing_names"
                    : "im.id, im.pharma_product_id, pp.product_name, im.dose_quantity, im.frequency_type_id, ft.display_text as frequency_display, im.meal_relation_id, mr.relation_name as meal_name, im.duration_days, im.medical_condition_id, mc.condition_name, GROUP_CONCAT(t.timing_name ORDER BY t.sort_order SEPARATOR ' + ') as timing_names";

                $latestMedicines = DB::table('ipd_medicines as im')
                    ->join('pharma_products as pp', 'im.pharma_product_id', '=', 'pp.id')
                    ->leftJoin('medicine_frequency_types as ft', 'im.frequency_type_id', '=', 'ft.id')
                    ->leftJoin('medicine_meal_relation as mr', 'im.meal_relation_id', '=', 'mr.id')
                    ->leftJoin('ipd_medicine_timing_selections as imts', 'im.id', '=', 'imts.ipd_medicine_id')
                    ->leftJoin('medicine_timing_of_day as t', 'imts.timing_id', '=', 't.id')
                    ->leftJoin('medical_conditions as mc', 'mc.id', '=', 'im.medical_condition_id')
                    ->where('im.ipd_prescription_id', $latestPrescription->id)
                    ->groupByRaw('im.id, im.pharma_product_id, pp.product_name, im.dose_quantity, im.frequency_type_id, ft.display_text, im.meal_relation_id, mr.relation_name, im.duration_days, im.medical_condition_id, mc.condition_name')
                    ->orderBy('im.id')
                    ->selectRaw($selectRaw)
                    ->get();
            }
        }

        return view('ipd.discharge', compact(
            'ipd',
            'canView',
            'admissionDateTimeFormatted',
            'currentDateTimeFormatted',
            'consultantDoctors',
            'allDoctors',
            'investigations',
            'procedures',
            'latestMedicines',
            'ps'
        ));
    }

    /**
     * Auto fill investigations and their test results/notes from lab-investigations.
     */
    private function getDischargeInvestigations(int $ipdId): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('ipd_services') || !Schema::hasTable('charges')) {
            return collect();
        }

        $pathologyDeptId = Schema::hasTable('id_field_settings') ? (IdFieldSetting::first()?->pathology_department_id ?? 3) : 3;
        $radiologyDeptId = Schema::hasTable('id_field_settings') ? (IdFieldSetting::first()?->radiology_department_id ?? 4) : 4;

        $hasServiceTitle = Schema::hasColumn('ipd_services', 'service_title');

        // Query lab and radiology services for this IPD
        $servicesQuery = DB::table('ipd_services')->where('ipd_id', $ipdId);
        if (Schema::hasTable('charge_departments')) {
            $selectCols = ['ipd_services.id as service_id', 'charge_departments.department_name', 'charge_departments.category'];
            if ($hasServiceTitle) {
                $selectCols[] = 'ipd_services.service_title';
            }
            $servicesQuery->join('charge_departments', 'charge_departments.id', '=', 'ipd_services.charge_department_id')
                ->where(function ($q) use ($pathologyDeptId, $radiologyDeptId) {
                    $q->whereIn('charge_departments.category', ['Lab', 'Radiology'])
                      ->orWhereIn('charge_departments.id', [$pathologyDeptId, $radiologyDeptId]);
                })
                ->select($selectCols);
        } else {
            $selectCols = ['ipd_services.id as service_id'];
            if ($hasServiceTitle) {
                $selectCols[] = 'ipd_services.service_title';
            }
            $servicesQuery->where(function ($q) use ($pathologyDeptId, $radiologyDeptId) {
                $q->where('charge_department_id', $pathologyDeptId)
                  ->orWhere('charge_department_id', $radiologyDeptId);
            })->select($selectCols);
        }

        $services = $servicesQuery->orderBy('ipd_services.id')->get();
        if ($services->isEmpty()) {
            return collect();
        }

        $serviceIds = $services->pluck('service_id')->all();

        // Service details and charges
        $details = collect();
        if (Schema::hasTable('ipd_service_detail')) {
            $details = DB::table('ipd_service_detail')
                ->join('charges', 'charges.id', '=', 'ipd_service_detail.charge_id')
                ->whereIn('ipd_service_detail.ipd_service_id', $serviceIds)
                ->select('ipd_service_detail.id as detail_id', 'ipd_service_detail.ipd_service_id', 'charges.id as charge_id', 'charges.charge_name')
                ->orderBy('ipd_service_detail.id')
                ->get()
                ->groupBy('ipd_service_id');
        }

        // Biochemistry sub-test results
        $bioResults = collect();
        if (Schema::hasTable('biochemistry_tests_result') && Schema::hasTable('biochemistry_sub_tests')) {
            $bioResults = DB::table('biochemistry_tests_result')
                ->join('biochemistry_sub_tests', 'biochemistry_sub_tests.id', '=', 'biochemistry_tests_result.test_id')
                ->where('biochemistry_tests_result.ipd_id', $ipdId)
                ->select('biochemistry_tests_result.ipd_service_detail_id', 'biochemistry_tests_result.test_value', 'biochemistry_sub_tests.sub_test_name', 'biochemistry_sub_tests.main_category_id')
                ->get()
                ->groupBy('ipd_service_detail_id');
        }

        // Lab notes (conclusion)
        $labNotes = collect();
        if (Schema::hasTable('ipd_lab_investigation_notes')) {
            $labNotes = DB::table('ipd_lab_investigation_notes')
                ->whereIn('ipd_service_id', $serviceIds)
                ->pluck('note', 'ipd_service_id');
        }

        // Radiology notes
        $radNotes = collect();
        if (Schema::hasTable('ipd_radiology_notes')) {
            $radNotes = DB::table('ipd_radiology_notes')
                ->whereIn('ipd_service_id', $serviceIds)
                ->pluck('note', 'ipd_service_id');
        }

        $investigations = collect();
        $seen = [];

        foreach ($services as $svc) {
            $svcDetails = $details->get($svc->service_id, collect());
            $deptName = (string)($svc->department_name ?? '');
            $category = (string)($svc->category ?? '');
            $isRad = stripos($deptName, 'radiology') !== false || strtolower($category) === 'radiology';

            $radNote = trim(strip_tags(html_entity_decode((string)($radNotes[$svc->service_id] ?? ''), ENT_QUOTES, 'UTF-8')));
            $labNote = trim(strip_tags(html_entity_decode((string)($labNotes[$svc->service_id] ?? ''), ENT_QUOTES, 'UTF-8')));

            if ($svcDetails->isEmpty()) {
                $name = $svc->service_title ?: ($deptName ?: 'Investigation');
                $res = $isRad ? $radNote : $labNote;
                $key = strtolower($name);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $investigations->push((object) [
                        'investigation' => $name,
                        'charge_id'     => 0,
                        'result'        => $res,
                    ]);
                }
                continue;
            }

            foreach ($svcDetails as $det) {
                $chargeResults = $bioResults->get($det->detail_id, collect());
                $resultParts = [];
                foreach ($chargeResults as $cr) {
                    $val = trim((string)$cr->test_value);
                    if ($val !== '') {
                        $resultParts[] = $cr->sub_test_name . ': ' . $val;
                    }
                }

                if (!empty($resultParts)) {
                    $resultStr = implode(', ', $resultParts);
                } elseif ($isRad && $radNote !== '') {
                    $resultStr = $radNote;
                } elseif ($labNote !== '') {
                    $resultStr = $labNote;
                } else {
                    $resultStr = '';
                }

                $key = strtolower($det->charge_name);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $investigations->push((object) [
                        'investigation' => $det->charge_name,
                        'charge_id'     => $det->charge_id,
                        'result'        => $resultStr,
                    ]);
                } elseif ($resultStr !== '') {
                    $idx = $investigations->search(fn($item) => strtolower($item->investigation) === $key);
                    if ($idx !== false && empty($investigations[$idx]->result)) {
                        $investigations[$idx]->result = $resultStr;
                    }
                }
            }
        }

        return $investigations;
    }

    /**
     * Auto fill procedures and procedure notes from IPD procedures.
     */
    private function getDischargeProcedures(int $ipdId): \Illuminate\Support\Collection
    {
        $procedures = collect();
        $seen = [];

        // 1. Fetch from ipd_services (where category is Procedure)
        if (Schema::hasTable('ipd_services') && Schema::hasTable('charge_departments')) {
            $servicesQuery = IpdService::with(['details.charge', 'procedureNote', 'chargeDepartment'])
                ->where('ipd_id', $ipdId)
                ->whereHas('chargeDepartment', function ($q) {
                    $q->whereRaw("LOWER(category) = ?", ['procedure']);
                });
            if (Schema::hasColumn('ipd_services', 'service_date_time')) {
                $servicesQuery->orderBy('service_date_time');
            }
            $services = $servicesQuery->orderBy('id')->get();

            foreach ($services as $svc) {
                $note = trim(strip_tags(html_entity_decode((string)($svc->procedureNote?->note ?? ''), ENT_QUOTES, 'UTF-8')));
                $deptName = trim((string)($svc->chargeDepartment?->department_name ?? ''));
                $rawTitle = (string)($svc->service_title ?? '');
                $title = trim(preg_replace('/\s+\d+$/', '', $rawTitle));
                if ($title === '') {
                    $title = $deptName;
                }

                $charges = $svc->details->map(fn($d) => $d->charge?->charge_name ?? '')->filter()->implode(', ');

                if ($note !== '') {
                    if (($title !== '' && stripos($note, $title) !== false) || ($deptName !== '' && stripos($note, $deptName) !== false)) {
                        $procText = $note;
                    } elseif ($title !== '') {
                        $procText = $title . ': ' . $note;
                    } else {
                        $procText = $note;
                    }
                } else {
                    $procText = $title ?: ($charges ?: $svc->service_title);
                }

                $k = strtolower($procText);
                if ($procText !== '' && !isset($seen[$k])) {
                    $seen[$k] = true;
                    $procedures->push((object) [
                        'charge_title' => $procText,
                        'procedure'    => $procText,
                    ]);
                }
            }
        }

        // 2. Fetch from billing_charges (for any procedure charges not in ipd_services)
        if (Schema::hasTable('billing_charges') && Schema::hasTable('charge_departments')) {
            $billingProcs = DB::table('billing_charges')
                ->join('charge_departments', 'charge_departments.id', '=', 'billing_charges.charge_department_id')
                ->whereRaw("LOWER(charge_departments.category) = ?", ['procedure'])
                ->where('billing_charges.module_id', $ipdId)
                ->where('billing_charges.module', 'IPD')
                ->select('billing_charges.charge_title')
                ->get();

            foreach ($billingProcs as $bp) {
                $bText = trim($bp->charge_title);
                $cleanBText = trim(preg_replace('/\s+\d+$/', '', $bText));
                $alreadyCovered = false;
                foreach ($seen as $k => $v) {
                    if (stripos($k, strtolower($cleanBText)) !== false || stripos(strtolower($bText), $k) !== false) {
                        $alreadyCovered = true;
                        break;
                    }
                }
                if (!$alreadyCovered && $bText !== '') {
                    $seen[strtolower($bText)] = true;
                    $procedures->push((object) [
                        'charge_title' => $bText,
                        'procedure'    => $bText,
                    ]);
                }
            }
        }

        return $procedures;
    }

    public function discharge(IpdDischargeRequest $request, int $id): JsonResponse
    {
        $ipd = Ipd::findOrFail($id);

        $rawDateTime = $request->input('discharge_date_time');
        try {
            $dischargeDateTime = $rawDateTime ? \Carbon\Carbon::parse($rawDateTime)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $dischargeDateTime = now()->format('Y-m-d H:i:s');
        }

        $diagnosis = $request->input('diagnosis', '');
        $conditionOnDischarge = $request->input('condition_on_discharge', '');
        $followUpDetail = $request->input('follow_up_detail', '');
        $note = $request->input('note', '');
        $urgentCareCondition = $request->input('urgent_care_condition', '');
        $approvedDoctorId = $request->filled('approved_doctor_id') ? (int) $request->input('approved_doctor_id') : null;

        DB::beginTransaction();
        try {
            $discharge = IpdDischarge::updateOrCreate(
                ['ipd_id' => $id],
                [
                    'discharge_date_time'    => $dischargeDateTime,
                    'diagnosis'              => $diagnosis,
                    'condition_on_discharge' => $conditionOnDischarge,
                    'follow_up_detail'       => $followUpDetail,
                    'note'                   => $note,
                    'urgent_care_condition'  => $urgentCareCondition,
                    'approved'               => 'No',
                    'approved_doctor_id'     => $approvedDoctorId,
                ]
            );

            // Handle investigations
            if ($request->has('ipd_discharge_investigation_name') && Schema::hasTable('ipd_discharge_investigations')) {
                $names = (array) $request->input('ipd_discharge_investigation_name', []);
                $values = (array) $request->input('ipd_discharge_investigation_value', []);
                DB::table('ipd_discharge_investigations')->where('discharge_id', $discharge->id)->delete();
                for ($i = 0; $i < count($names); $i++) {
                    if (!empty($names[$i])) {
                        DB::table('ipd_discharge_investigations')->insert([
                            'discharge_id'  => $discharge->id,
                            'investigation' => $names[$i],
                            'result'        => $values[$i] ?? '',
                        ]);
                    }
                }
            }

            // Handle procedures
            if ($request->has('ipd_discharge_procedure') && Schema::hasTable('ipd_discharge_procedures')) {
                $procs = (array) $request->input('ipd_discharge_procedure', []);
                DB::table('ipd_discharge_procedures')->where('discharge_id', $discharge->id)->delete();
                foreach ($procs as $proc) {
                    if (!empty($proc)) {
                        DB::table('ipd_discharge_procedures')->insert([
                            'discharge_id'     => $discharge->id,
                            'procedure_detail' => $proc,
                        ]);
                    }
                }
            }

            // Handle medication given
            if ($request->has('medicine') && Schema::hasTable('ipd_discharge_medication_given')) {
                $meds = (array) $request->input('medicine', []);
                $doses = (array) $request->input('dose_quantity', []);
                $freqs = (array) $request->input('frequency', []);
                $timings = (array) $request->input('timing', []);
                $meals = (array) $request->input('meal', []);
                $durations = (array) $request->input('duration_days', []);
                DB::table('ipd_discharge_medication_given')->where('discharge_id', $discharge->id)->delete();
                for ($i = 0; $i < count($meds); $i++) {
                    if (!empty($meds[$i])) {
                        DB::table('ipd_discharge_medication_given')->insert([
                            'discharge_id'      => $discharge->id,
                            'medicine_name'     => $meds[$i],
                            'dose_quantity'     => $doses[$i] ?? '',
                            'frequency_display' => $freqs[$i] ?? '',
                            'timing_display'    => $timings[$i] ?? '',
                            'meal_relation'     => $meals[$i] ?? '',
                            'duration_days'     => (int) ($durations[$i] ?? 1),
                        ]);
                    }
                }
            }

            $ipd->update(['status' => 'Discharged']);

            // Close bed history entry if table exists
            if (Schema::hasTable('ipd_patient_beds')) {
                IpdPatientBed::where('ipd_id', $id)->whereNull('left_date_time')
                    ->update(['left_date_time' => $dischargeDateTime]);
            }

            // Update related DCD records for this patient with status 'TO IPD' to 'Discharged'
            if ($ipd->patient_id && Schema::hasTable('dcds')) {
                DB::table('dcds')
                    ->where('patient_id', $ipd->patient_id)
                    ->where('status', 'TO IPD')
                    ->update(['status' => 'Discharged']);
            }

            DB::commit();
            return response()->json([
                'success'      => true,
                'discharge_id' => $discharge->id,
                'message'      => 'Discharge request submitted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function approveDischarge(ApproveIpdDischargeRequest $request, int $id, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('b_discharge_approve_and_discharge'), 403);

        $ipd = Ipd::find($id);
        $discharge = $ipd ? IpdDischarge::where('ipd_id', $ipd->id)->first() : null;

        abort_unless($discharge && $ipd, 404);

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

        $discharge->update($updateData);

        // Handle treatment advised medicines
        if ($request->has('pharma_product_id_value') && Schema::hasTable('ipd_discharge_treatment_advised')) {
            $productIds = (array) $request->input('pharma_product_id_value', []);
            $doses      = (array) $request->input('dose_quantity_value', []);
            $freqs      = (array) $request->input('frequency_type_id_value', []);
            $meals      = (array) $request->input('meal_relation_id_value', []);
            $durations  = (array) $request->input('duration_days_value', []);
            $conditions = (array) $request->input('medical_condition_id_value', []);

            $oldTaIds = DB::table('ipd_discharge_treatment_advised')->where('discharge_id', $discharge->id)->pluck('id');
            if ($oldTaIds->isNotEmpty() && Schema::hasTable('ipd_discharge_treatment_timing_selections')) {
                DB::table('ipd_discharge_treatment_timing_selections')->whereIn('treatment_advised_id', $oldTaIds)->delete();
            }
            DB::table('ipd_discharge_treatment_advised')->where('discharge_id', $discharge->id)->delete();

            for ($i = 0; $i < count($productIds); $i++) {
                if (empty($productIds[$i])) continue;
                $taId = DB::table('ipd_discharge_treatment_advised')->insertGetId([
                    'discharge_id'         => $discharge->id,
                    'pharma_product_id'    => (int) $productIds[$i],
                    'dose_quantity'        => $doses[$i] ?? '',
                    'frequency_type_id'    => (int) ($freqs[$i] ?? 0),
                    'meal_relation_id'     => (int) ($meals[$i] ?? 0),
                    'duration_days'        => (int) ($durations[$i] ?? 0),
                    'medical_condition_id' => !empty($conditions[$i]) ? (int) $conditions[$i] : null,
                ]);

                $timingKey = 'timing_ids_' . ($i + 1);
                if ($request->has($timingKey) && Schema::hasTable('ipd_discharge_treatment_timing_selections')) {
                    $timings = (array) $request->input($timingKey);
                    foreach ($timings as $tId) {
                        DB::table('ipd_discharge_treatment_timing_selections')->insert([
                            'treatment_advised_id' => $taId,
                            'timing_id'            => (int) $tId,
                        ]);
                    }
                }
            }
        }

        // Update IPD status
        $ipd->update(['status' => 'Discharged']);

        // Free the bed
        if ($ipd->bed_id) {
            Bed::where('id', $ipd->bed_id)->update(['status' => 'Available']);
        }

        // Update patient status if patient exists
        if ($ipd->patient_id) {
            Patient::where('id', $ipd->patient_id)->update(['status' => 'Discharged']);
        }

        return response()->json([
            'success'      => true,
            'discharge_id' => $discharge->id,
            'message'      => 'Discharge approved.',
        ]);
    }

    public function updateDischargeDateTime(Request $request, int $ipdId): JsonResponse
    {
        $v = $request->validate(['discharge_date_time' => 'required|date']);
        IpdDischarge::where('ipd_id', $ipdId)->update(['discharge_date_time' => $v['discharge_date_time']]);
        return response()->json(['success' => true, 'message' => 'Discharge date/time updated.']);
    }

    // ─────────────────────────────────────────────
    // Progress Sheet / Patient File
    // ─────────────────────────────────────────────

    public function progressSheet(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_progress_sheet'), 403);
        $ipd = Ipd::with(['patient', 'bed.wardCategory'])->findOrFail($ipdId);
        $file = IpdPatientFile::where('ipd_id', $ipdId)->first();
        $progress_sheet = $file ? html_entity_decode($file->progress_sheet ?? '', ENT_QUOTES, 'UTF-8') : '';
        $save = $ps->hasPermission('b_ipd_progress_sheet_save');

        return view('ipd.progress-sheet', compact('ipd', 'progress_sheet', 'save', 'ps'));
    }

    public function saveProgressSheet(IpdProgressSheetRequest $request, int $ipdId): JsonResponse
    {
        abort_unless(
            app(PermissionService::class)->hasPermission('im_ipd_progress_sheet') || 
            app(PermissionService::class)->hasPermission('b_ipd_progress_sheet_save'), 
            403
        );

        $id = $ipdId;
        $progress_sheet = preg_replace('/<\/?span[^>]*>/i', '', (string) $request->input('progress_sheet', ''));

        $file = IpdPatientFile::where('ipd_id', $id)->first();
        if ($file) {
            $file->update(['progress_sheet' => $progress_sheet]);
        } else {
            IpdPatientFile::create([
                'ipd_id'                            => $id,
                'progress_sheet'                    => $progress_sheet,
                'relative_name'                     => '',
                'relationship'                      => '',
                'witness_name'                      => '',
                'admission_type'                    => '',
                'patient_condition'                 => '',
                'general_condition'                 => '',
                'source_of_history'                 => '',
                'chief_complaints'                  => '',
                'history_of_present_illness'        => '',
                'past_medical_history'              => '',
                'pain_score'                        => '',
                'pain_location'                     => '',
                'intervention'                      => '',
                'family_ho'                         => '',
                'current_medication'                => '',
                'allergies'                         => '',
                'ho_blood_transfusion'              => '',
                'substance_abuse'                   => '',
                'nutritional_status'                => '',
                'general_examination'               => '',
                'systemic_examination'              => '',
                'cns_system_mental_status'          => '',
                'cns_system_sensory_examination'    => '',
                'cns_system_motor_examination_tone' => '',
                'cns_system_reflexes'               => '',
                'cns_system_power'                  => '',
                'provisional_diagnosis'             => '',
                'diet_plan'                         => '',
                'rmo_notes'                         => '',
            ]);
        }

        return response()->json([
            'success' => true,
            'ipd_id'  => $id,
            'message' => 'Saved Successfully.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Lab Investigations
    // ─────────────────────────────────────────────

    public function labInvestigations(int $ipdId, PermissionService $ps, \App\Services\LabResultService $labs)
    {
        abort_unless($ps->hasPermission('im_ipd_lab_investigations'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);
        $services = $labs->investigations('ipd', $ipdId);

        return view('ipd.lab-investigations', compact('ipd', 'services', 'ps'));
    }

    /** Lab conclusion only (the result-entry page saves it together with the values). */
    public function storeLabNote(Request $request, int $serviceId, PermissionService $ps, \App\Services\LabResultService $labs): JsonResponse
    {
        abort_unless($ps->hasPermission('im_ipd_lab_investigations'), 403);
        $v = $request->validate(['note' => 'nullable|string|max:65000']);
        $labs->service('ipd', $serviceId);
        $labs->saveConclusion('ipd', $serviceId, $v['note'] ?? '', (int) CurrentUser::id());
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────
    // Treatments
    // ─────────────────────────────────────────────

    public function treatments(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_treatment'), 403);
        $ipd = Ipd::with(['patient', 'bed.wardCategory'])->findOrFail($ipdId);

        $new = $ps->hasPermission('b_ipd_new_treatment');
        $edit = $ps->hasPermission('b_ipd_edit_treatment');
        $delete = $ps->hasPermission('b_ipd_delete_treatment');

        $treatments = DB::table('ipd_treatments as t')
            ->leftJoin('staff_members as t1', 't1.id', '=', 't.doctor_id')
            ->leftJoin('staff_members as t2', 't2.id', '=', 't.user_id')
            ->join('ipds', 'ipds.id', '=', 't.ipd_id')
            ->leftJoin('beds', 'beds.id', '=', 'ipds.bed_id')
            ->leftJoin('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('t.ipd_id', $ipdId)
            ->select(
                't.id',
                't.ipd_id',
                't.doctor_id',
                't.bed_id',
                't.remarks',
                't.user_id',
                't.treatment_date_time',
                't1.staff_name as prescribed_by',
                't2.staff_name as added_by',
                'beds.bed_name',
                'ward_categories.ward_category'
            )
            ->orderByDesc('t.id')
            ->get()
            ->map(function ($row) {
                $row->treatment_date_time = $row->treatment_date_time ? \Carbon\Carbon::parse($row->treatment_date_time)->format('d-m-Y h:i A') : '';
                return $row;
            });

        $treatmentIds = $treatments->pluck('id')->toArray();
        $detailsByTreatment = [];
        if (!empty($treatmentIds)) {
            $details = DB::table('ipd_treatment_detail as td')
                ->join('pharma_products as pp', 'td.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'td.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'td.meal_relation_id', '=', 'mr.id')
                ->leftJoin('medical_conditions as mc', 'mc.id', '=', 'td.medical_condition_id')
                ->whereIn('td.ipd_treatment_id', $treatmentIds)
                ->orderBy('td.id')
                ->select(
                    'td.id',
                    'td.ipd_treatment_id',
                    'pp.product_name',
                    'td.dose_quantity',
                    'ft.display_text as frequency_display',
                    'mr.relation_name as meal_name',
                    'td.duration_days',
                    'td.medical_condition_id',
                    'mc.condition_name'
                )
                ->get();

            $detailIds = $details->pluck('id')->toArray();
            $timingsByDetail = [];
            if (!empty($detailIds)) {
                $timingRows = DB::table('ipd_treatment_timing_selections as tts')
                    ->join('medicine_timing_of_day as t', 'tts.timing_id', '=', 't.id')
                    ->whereIn('tts.treatment_detail_id', $detailIds)
                    ->orderBy('t.sort_order')
                    ->select('tts.treatment_detail_id', 't.id as timing_id', 't.timing_name')
                    ->get();
                foreach ($timingRows as $tr) {
                    $timingsByDetail[$tr->treatment_detail_id][] = $tr;
                }
            }

            foreach ($details as $d) {
                $tList = $timingsByDetail[$d->id] ?? [];
                $d->timing_names = implode(' + ', array_column($tList, 'timing_name'));
                $detailsByTreatment[$d->ipd_treatment_id][] = $d;
            }
        }

        foreach ($treatments as $t) {
            $t->details = $detailsByTreatment[$t->id] ?? [];
        }

        $doctors = \App\Models\StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);

        $medicines = DB::table('pharma_products as pp')
            ->leftJoin('medicine_dose_units as du', 'pp.dose_unit_id', '=', 'du.id')
            ->leftJoin('medicine_routes as r', 'pp.route_id', '=', 'r.id')
            ->where('pp.is_active', 1)
            ->orderBy('pp.product_name')
            ->select('pp.id', 'pp.product_name', 'du.unit_name', 'r.route_name')
            ->get();

        $frequencies = DB::table('medicine_frequency_types')->where('is_active', 1)->orderBy('sort_order')->get(['id', 'display_text']);
        $meals = DB::table('medicine_meal_relation')->where('is_active', 1)->orderBy('sort_order')->get(['id', 'relation_name']);
        $timings = DB::table('medicine_timing_of_day')->where('is_active', 1)->orderBy('sort_order')->get(['id', 'timing_name']);
        $conditions = DB::table('medical_conditions')->where('is_active', 1)->orderBy('condition_name')->get(['id', 'condition_name', 'category']);

        return view('ipd.treatments', compact(
            'ipd',
            'treatments',
            'doctors',
            'medicines',
            'frequencies',
            'meals',
            'timings',
            'conditions',
            'ps',
            'new',
            'edit',
            'delete'
        ));
    }

    public function destroyTreatment(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        $detailIds = DB::table('ipd_treatment_detail')->where('ipd_treatment_id', $id)->pluck('id');
        if ($detailIds->isNotEmpty()) {
            DB::table('ipd_treatment_timing_selections')->whereIn('treatment_detail_id', $detailIds)->delete();
            DB::table('ipd_treatment_detail')->where('ipd_treatment_id', $id)->delete();
        }
        DB::table('ipd_treatments')->where('id', $id)->delete();
        return response()->json(['result' => 'OK', 'success' => true, 'message' => 'Treatment deleted.']);
    }

    public function getTreatment(Request $request, ?int $id = null): JsonResponse
    {
        $id = (int) ($id ?: $request->input('id'));
        if ($id <= 0) {
            return response()->json(['error' => 'Invalid ID'], 400);
        }

        $row = DB::table('ipd_treatments')
            ->join('staff_members', 'staff_members.id', '=', 'ipd_treatments.doctor_id')
            ->where('ipd_treatments.id', $id)
            ->select('ipd_treatments.*', 'staff_members.staff_name')
            ->first();

        if (!$row) {
            return response()->json(['error' => 'Treatment not found'], 404);
        }

        $row->treatment_date_time = $row->treatment_date_time ? \Carbon\Carbon::parse($row->treatment_date_time)->format('d-m-Y h:i A') : '';

        $details = DB::table('ipd_treatment_detail as td')
            ->join('pharma_products as pp', 'td.pharma_product_id', '=', 'pp.id')
            ->leftJoin('medicine_frequency_types as ft', 'td.frequency_type_id', '=', 'ft.id')
            ->leftJoin('medicine_meal_relation as mr', 'td.meal_relation_id', '=', 'mr.id')
            ->leftJoin('medical_conditions as mc', 'mc.id', '=', 'td.medical_condition_id')
            ->where('td.ipd_treatment_id', $id)
            ->orderBy('td.id')
            ->select(
                'td.id',
                'td.pharma_product_id',
                'pp.product_name',
                'td.dose_quantity',
                'td.frequency_type_id',
                'ft.display_text as frequency_display',
                'td.meal_relation_id',
                'mr.relation_name as meal_name',
                'td.duration_days',
                'td.medical_condition_id',
                'mc.condition_name'
            )
            ->get();

        $detailIds = $details->pluck('id')->toArray();
        $timingsByDetail = [];
        if (!empty($detailIds)) {
            $timingRows = DB::table('ipd_treatment_timing_selections as tts')
                ->join('medicine_timing_of_day as t', 'tts.timing_id', '=', 't.id')
                ->whereIn('tts.treatment_detail_id', $detailIds)
                ->orderBy('t.sort_order')
                ->select('tts.treatment_detail_id', 't.id as timing_id', 't.timing_name')
                ->get();
            foreach ($timingRows as $tr) {
                $timingsByDetail[$tr->treatment_detail_id][] = $tr;
            }
        }

        $tableHtml = '';
        $i = 0;
        foreach ($details as $trow) {
            $i++;
            $tList = $timingsByDetail[$trow->id] ?? [];
            $timingHidden = '';
            $timingNamesArray = [];
            foreach ($tList as $tObj) {
                $timingHidden .= '<input type="hidden" name="timing_ids_' . $i . '[]" value="' . e($tObj->timing_id) . '">';
                $timingNamesArray[] = $tObj->timing_name;
            }
            $timingNames = implode(' + ', $timingNamesArray);
            $reasonDisplay = !empty($trow->condition_name) ? e($trow->condition_name) : 'Not Specified';

            $tableHtml .= '<tr id="epmr_' . $i . '">';
            $tableHtml .= '<td><a href="javascript:void(0)" onclick="removeETreatmentMedicine(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>';
            $tableHtml .= '<td>' . e($trow->product_name) . '<input type="hidden" name="pharma_product_id_value[]" value="' . e($trow->pharma_product_id) . '"></td>';
            $tableHtml .= '<td>' . e($trow->dose_quantity) . '<input type="hidden" name="dose_quantity_value[]" value="' . e($trow->dose_quantity) . '"></td>';
            $tableHtml .= '<td>' . e($trow->frequency_display) . '<input type="hidden" name="frequency_type_id_value[]" value="' . e($trow->frequency_type_id) . '"></td>';
            $tableHtml .= '<td>' . e($timingNames) . $timingHidden . '</td>';
            $tableHtml .= '<td>' . e($trow->meal_name) . '<input type="hidden" name="meal_relation_id_value[]" value="' . e($trow->meal_relation_id) . '"></td>';
            $tableHtml .= '<td>' . e($trow->duration_days) . ' days<input type="hidden" name="duration_days_value[]" value="' . e($trow->duration_days) . '"></td>';
            $tableHtml .= '<td>' . $reasonDisplay . '<input type="hidden" name="medical_condition_id_value[]" value="' . e($trow->medical_condition_id) . '"></td>';
            $tableHtml .= '</tr>';
        }

        return response()->json([
            'row'        => $row,
            'table_html' => $tableHtml,
            'nor'        => $i,
        ]);
    }

    public function updateTreatment(IpdTreatmentRequest $request, int $id): JsonResponse
    {
        $treatmentId = $id;
        $treatment = DB::table('ipd_treatments')->where('id', $treatmentId)->first();
        if (!$treatment) {
            return response()->json(['success' => false, 'error' => 'Treatment not found.'], 404);
        }

        $dateTime = $request->input('treatment_date_time');
        $parsedDateTime = $dateTime ? date('Y-m-d H:i:s', strtotime($dateTime)) : $treatment->treatment_date_time;
        $doctorId = (int) ($request->input('doctor_id') ?: $treatment->doctor_id);
        $remarks = (string) ($request->input('note') ?? $request->input('remarks') ?? $treatment->remarks);

        DB::table('ipd_treatments')->where('id', $treatmentId)->update([
            'treatment_date_time' => $parsedDateTime,
            'doctor_id'           => $doctorId,
            'remarks'             => $remarks,
        ]);

        $oldDetailIds = DB::table('ipd_treatment_detail')->where('ipd_treatment_id', $treatmentId)->pluck('id');
        if ($oldDetailIds->isNotEmpty()) {
            DB::table('ipd_treatment_timing_selections')->whereIn('treatment_detail_id', $oldDetailIds)->delete();
            DB::table('ipd_treatment_detail')->where('ipd_treatment_id', $treatmentId)->delete();
        }

        if ($request->has('pharma_product_id_value') && is_array($request->input('pharma_product_id_value'))) {
            $productIds = $request->input('pharma_product_id_value');
            $doseQuantities = $request->input('dose_quantity_value', []);
            $freqIds = $request->input('frequency_type_id_value', []);
            $mealIds = $request->input('meal_relation_id_value', []);
            $durationDays = $request->input('duration_days_value', []);
            $conditionIds = $request->input('medical_condition_id_value', []);

            for ($i = 0; $i < count($productIds); $i++) {
                $pid = (int) $productIds[$i];
                if (!$pid) continue;
                $dose = isset($doseQuantities[$i]) && $doseQuantities[$i] !== '' ? (float) $doseQuantities[$i] : null;
                $freq = isset($freqIds[$i]) && $freqIds[$i] !== '' ? (int) $freqIds[$i] : null;
                $meal = isset($mealIds[$i]) && $mealIds[$i] !== '' ? (int) $mealIds[$i] : null;
                $dur = isset($durationDays[$i]) && $durationDays[$i] !== '' ? (int) $durationDays[$i] : null;
                $cond = isset($conditionIds[$i]) && $conditionIds[$i] !== '' ? (int) $conditionIds[$i] : null;

                $detailId = DB::table('ipd_treatment_detail')->insertGetId([
                    'ipd_treatment_id'     => $treatmentId,
                    'pharma_product_id'    => $pid,
                    'dose_quantity'        => $dose,
                    'frequency_type_id'    => $freq,
                    'meal_relation_id'     => $meal,
                    'duration_days'        => $dur,
                    'medical_condition_id' => $cond,
                    'created_at'           => now()->toDateTimeString(),
                    'updated_at'           => now()->toDateTimeString(),
                ]);

                $timingKey = 'timing_ids_' . ($i + 1);
                if ($request->has($timingKey) && is_array($request->input($timingKey))) {
                    foreach ($request->input($timingKey) as $tid) {
                        $tid = (int) $tid;
                        if ($tid) {
                            DB::table('ipd_treatment_timing_selections')->insert([
                                'treatment_detail_id' => $detailId,
                                'timing_id'           => $tid,
                                'created_at'          => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json([
            'success'          => true,
            'ipd_treatment_id' => $treatmentId,
            'message'          => 'Updated Successfully.',
        ]);
    }

    public function storeTreatment(IpdTreatmentRequest $request, int $ipdId): JsonResponse
    {
        $ipd = Ipd::findOrFail($ipdId);

        $dateTime = $request->input('treatment_date_time');
        $parsedDateTime = $dateTime ? date('Y-m-d H:i:s', strtotime($dateTime)) : now()->toDateTimeString();
        $doctorId = (int) $request->input('doctor_id');
        $bedId = (int) ($request->input('bed_id') ?: $ipd->bed_id);
        $remarks = (string) ($request->input('note') ?? $request->input('remarks') ?? '');
        $userId = CurrentUser::id() ?? 1;

        $treatmentId = DB::table('ipd_treatments')->insertGetId([
            'treatment_date_time' => $parsedDateTime,
            'ipd_id'              => $ipdId,
            'doctor_id'           => $doctorId,
            'bed_id'              => $bedId,
            'remarks'             => $remarks,
            'user_id'             => $userId,
        ]);

        if ($request->has('pharma_product_id_value') && is_array($request->input('pharma_product_id_value'))) {
            $productIds = $request->input('pharma_product_id_value');
            $doseQuantities = $request->input('dose_quantity_value', []);
            $freqIds = $request->input('frequency_type_id_value', []);
            $mealIds = $request->input('meal_relation_id_value', []);
            $durationDays = $request->input('duration_days_value', []);
            $conditionIds = $request->input('medical_condition_id_value', []);

            for ($i = 0; $i < count($productIds); $i++) {
                $pid = (int) $productIds[$i];
                if (!$pid) continue;
                $dose = isset($doseQuantities[$i]) && $doseQuantities[$i] !== '' ? (float) $doseQuantities[$i] : null;
                $freq = isset($freqIds[$i]) && $freqIds[$i] !== '' ? (int) $freqIds[$i] : null;
                $meal = isset($mealIds[$i]) && $mealIds[$i] !== '' ? (int) $mealIds[$i] : null;
                $dur = isset($durationDays[$i]) && $durationDays[$i] !== '' ? (int) $durationDays[$i] : null;
                $cond = isset($conditionIds[$i]) && $conditionIds[$i] !== '' ? (int) $conditionIds[$i] : null;

                $detailId = DB::table('ipd_treatment_detail')->insertGetId([
                    'ipd_treatment_id'     => $treatmentId,
                    'pharma_product_id'    => $pid,
                    'dose_quantity'        => $dose,
                    'frequency_type_id'    => $freq,
                    'meal_relation_id'     => $meal,
                    'duration_days'        => $dur,
                    'medical_condition_id' => $cond,
                    'created_at'           => now()->toDateTimeString(),
                    'updated_at'           => now()->toDateTimeString(),
                ]);

                $timingKey = 'timing_ids_' . ($i + 1);
                if ($request->has($timingKey) && is_array($request->input($timingKey))) {
                    foreach ($request->input($timingKey) as $tid) {
                        $tid = (int) $tid;
                        if ($tid) {
                            DB::table('ipd_treatment_timing_selections')->insert([
                                'treatment_detail_id' => $detailId,
                                'timing_id'           => $tid,
                                'created_at'          => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        } elseif ($request->has('details') && is_array($request->input('details'))) {
            foreach ($request->input('details') as $d) {
                if (empty($d['pharma_product_id'])) continue;
                $detailId = DB::table('ipd_treatment_detail')->insertGetId([
                    'ipd_treatment_id'     => $treatmentId,
                    'pharma_product_id'    => (int) $d['pharma_product_id'],
                    'dose_quantity'        => isset($d['dose_quantity']) && $d['dose_quantity'] !== '' ? (float) $d['dose_quantity'] : null,
                    'frequency_type_id'    => isset($d['frequency_type_id']) && $d['frequency_type_id'] !== '' ? (int) $d['frequency_type_id'] : null,
                    'meal_relation_id'     => isset($d['meal_relation_id']) && $d['meal_relation_id'] !== '' ? (int) $d['meal_relation_id'] : null,
                    'duration_days'        => isset($d['duration_days']) && $d['duration_days'] !== '' ? (int) $d['duration_days'] : null,
                    'medical_condition_id' => isset($d['medical_condition_id']) && $d['medical_condition_id'] !== '' ? (int) $d['medical_condition_id'] : null,
                    'created_at'           => now()->toDateTimeString(),
                    'updated_at'           => now()->toDateTimeString(),
                ]);
                if (!empty($d['timing_ids']) && is_array($d['timing_ids'])) {
                    foreach ($d['timing_ids'] as $tid) {
                        if ($tid) {
                            DB::table('ipd_treatment_timing_selections')->insert([
                                'treatment_detail_id' => $detailId,
                                'timing_id'           => (int) $tid,
                                'created_at'          => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        }

        return response()->json([
            'success'          => true,
            'ipd_treatment_id' => $treatmentId,
            'message'          => 'Saved Successfully.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Bed History & Change Bed
    // ─────────────────────────────────────────────

    public function bedHistory(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_bed_history'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);
        $bedHistory    = app(\App\Services\IpdOverviewService::class)->bedHistory($ipdId);
        $availableBeds = Bed::with('wardCategory')->where('status', 'Available')->orderBy('bed_name')->get();
        return view('ipd.bed-history', compact('ipd', 'bedHistory', 'availableBeds', 'ps'));
    }

    /**
     * Move an admitted patient to another bed (legacy `changeipdbed`). The transfer time defaults to now,
     * as in legacy; the bed-history form may supply one.
     */
    public function changeBed(Request $request, int $ipdId, PermissionService $ps): JsonResponse
    {
        abort_unless($ps->hasPermission('b_ipd_change_bed'), 403);

        $v = $request->validate([
            'new_bed_id'        => 'required|integer|exists:beds,id',
            'transfer_datetime' => ['nullable', 'string', function (string $attribute, $value, \Closure $fail) {
                if (!$this->parseAdmissionDateTime((string) $value)) {
                    $fail('Transfer date must be a valid date and time.');
                }
            }],
            'remarks'           => 'nullable|string',
        ]);

        $transferAt = !empty($v['transfer_datetime'])
            ? $this->parseAdmissionDateTime($v['transfer_datetime'])->toDateTimeString()
            : now()->toDateTimeString();

        try {
            DB::transaction(function () use ($ipdId, $v, $transferAt) {
                $ipd = Ipd::whereKey($ipdId)->lockForUpdate()->firstOrFail();
                if (!$ipd->is_admitted) {
                    throw new \DomainException('Bed cannot be changed for a discharged patient.');
                }
                if ((int) $ipd->bed_id === (int) $v['new_bed_id']) {
                    throw new \DomainException('The patient is already in this bed.');
                }

                // Lock the target bed so two transfers can't take it at once.
                $newBed = Bed::whereKey($v['new_bed_id'])->lockForUpdate()->first();
                if (!$newBed || $newBed->status !== 'Available') {
                    throw new \DomainException('The selected bed is not available. Please choose another bed.');
                }

                IpdPatientBed::where('ipd_id', $ipdId)->whereNull('left_date_time')
                    ->update(['left_date_time' => $transferAt]);

                if ($ipd->bed_id) {
                    Bed::whereKey($ipd->bed_id)->update(['status' => 'Available']);
                }
                $newBed->update(['status' => 'Alloted']);

                IpdPatientBed::create([
                    'allot_date_time' => $transferAt,
                    'left_date_time'  => null,
                    'ipd_id'          => $ipdId,
                    'bed_id'          => $newBed->id,
                    'remarks'         => $v['remarks'] ?? '',
                    'user_id'         => (int) CurrentUser::id(),
                ]);

                $ipd->update(['bed_id' => $newBed->id]);
            });
        } catch (\DomainException $e) {
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Bed changed successfully.']);
    }

    // ─────────────────────────────────────────────
    // IPD Doctors (add/remove from ipd_doctors table)
    // ─────────────────────────────────────────────

    public function addDoctor(AddIpdDoctorRequest $request, int $ipdId): JsonResponse
    {
        $v = $request->validated();
        $exists = DB::table('ipd_doctors')->where('ipd_id', $ipdId)->where('doctor_id', $v['doctor_id'])->exists();
        if ($exists) return response()->json(['success' => false, 'message' => 'Doctor already added.']);
        $rowId = DB::table('ipd_doctors')->insertGetId([
            'ipd_id'     => $ipdId,
            'doctor_id'  => $v['doctor_id'],
            'created_on' => now()->toDateTimeString(),
            'user_id'    => CurrentUser::id(),
        ]);
        $doc = DB::table('ipd_doctors')
            ->join('staff_members', 'staff_members.id', '=', 'ipd_doctors.doctor_id')
            ->join('designations', 'designations.id', '=', 'staff_members.designation_id')
            ->where('ipd_doctors.id', $rowId)
            ->select('ipd_doctors.id as ipd_doctor_id','staff_members.staff_name',
                'staff_members.photo','designations.designation')
            ->first();
        return response()->json(['success' => true, 'ipd_doctor_id' => $rowId, 'message' => 'Doctor added.', 'data' => $doc]);
    }

    public function removeDoctor(int $ipdDoctorId): JsonResponse
    {
        DB::table('ipd_doctors')->where('id', $ipdDoctorId)->delete();
        return response()->json(['success' => true, 'message' => 'Doctor removed.']);
    }

    // ─── TPA update ──────────────────────────────────────────

    public function updateTpa(Request $request, int $ipdId): JsonResponse
    {
        $v = $request->validate([
            'tpa_provider_id'  => 'nullable|integer|exists:tpa_providers,id',
            'tpa_number'       => 'nullable|string|max:50',
            'tpa_credit_limit' => 'nullable|numeric|min:0',
        ]);
        // tpa_number and tpa_credit_limit are NOT NULL columns, so blanks are stored as '' / 0.
        Ipd::findOrFail($ipdId)->update([
            'tpa_provider_id'  => !empty($v['tpa_provider_id']) ? (int) $v['tpa_provider_id'] : null,
            'tpa_number'       => $v['tpa_number'] ?? '',
            'tpa_credit_limit' => (int) ($v['tpa_credit_limit'] ?? 0),
        ]);
        return response()->json(['success' => true, 'message' => 'TPA details updated.']);
    }

    // ─────────────────────────────────────────────
    // Patient File (Admission Examination)
    // ─────────────────────────────────────────────

    public function patientFile(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_patient_file'), 403);
        $ipd  = Ipd::with(['patient', 'doctor', 'patientFile'])->findOrFail($ipdId);
        $file = $ipd->patientFile;
        return view('ipd.patient-file', compact('ipd', 'file', 'ps'));
    }

    public function savePatientFile(IpdPatientFileRequest $request, int $ipdId): JsonResponse
    {
        abort_unless(
            app(PermissionService::class)->hasPermission('im_ipd_patient_file') || 
            app(PermissionService::class)->hasPermission('b_ipd_patient_file_save'), 
            403
        );

        $v = $request->validated();

        $substanceAbuse = $request->input('substance_abuse');
        $v['substance_abuse'] = is_array($substanceAbuse) ? implode(',', $substanceAbuse) : (string)($substanceAbuse ?? '');

        $genExam = $request->input('general_examination');
        $v['general_examination'] = is_array($genExam) ? implode(',', $genExam) : (string)($genExam ?? '');

        foreach (['chief_complaints', 'history_of_present_illness', 'past_medical_history', 'systemic_examination'] as $fld) {
            if (!empty($v[$fld])) {
                $v[$fld] = preg_replace('/<\/?span[^>]*>/i', '', $v[$fld]);
            }
        }

        // Convert any null to empty string for MySQL compatibility
        foreach ($v as $key => $val) {
            if ($val === null) {
                $v[$key] = '';
            }
        }

        IpdPatientFile::updateOrCreate(['ipd_id' => $ipdId], $v);

        return response()->json([
            'success' => true,
            'message' => 'Saved Successfully.',
            'ipd_id'  => $ipdId,
        ]);
    }

    public function getPatientFileJson(int $ipdId): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('im_ipd_patient_file'), 403);
        $file = IpdPatientFile::where('ipd_id', $ipdId)->first();
        return response()->json($file);
    }

    // ─────────────────────────────────────────────
    // Procedures
    // ─────────────────────────────────────────────
    public function procedures(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_procedures'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);
        $services = IpdService::with(['details.charge', 'procedureNote', 'chargeDepartment'])
            ->where('ipd_id', $ipdId)
            ->whereHas('chargeDepartment', fn($q) => $q->whereRaw("LOWER(category) = ?", ['procedure']))
            ->orderByDesc('service_date_time')->get();
        return view('ipd.procedures', compact('ipd', 'services', 'ps'));
    }

    public function getProcedureNote(int $serviceId): JsonResponse
    {
        $note = IpdProcedureNote::where('ipd_service_id', $serviceId)->value('note');
        return response()->json([
            'success' => true,
            'note'    => $note ?? '',
        ]);
    }

    public function storeProcedureNote(IpdProcedureNoteRequest $request, int $serviceId): JsonResponse
    {
        $svcId = $serviceId;
        $note = (string) $request->input('note', '');
        $userId = CurrentUser::id() ?: 1;

        IpdProcedureNote::updateOrCreate(
            ['ipd_service_id' => $svcId],
            [
                'note'       => $note,
                'updated_on' => now()->toDateTimeString(),
                'user_id'    => $userId,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Saved Successfully.']);
    }

    // ─────────────────────────────────────────────
    // Medications (flat chart from all prescriptions)
    // ─────────────────────────────────────────────
    public function medications(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_medication'), 403);
        $ipd = Ipd::with(['patient', 'doctor'])->findOrFail($ipdId);

        $prescriptions = IpdPrescription::with([
            'doctor',
            'medicines' => function ($q) {
                $q->with([
                    'product.doseUnit',
                    'product.route',
                    'frequency',
                    'mealRelation',
                    'timings' => function ($tq) {
                        $tq->with('timing');
                    },
                ])->orderBy('id');
            },
        ])
        ->has('medicines')
        ->where('ipd_id', $ipdId)
        ->orderByDesc('id')
        ->get();

        $doctors = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);
        $medicines = PharmaProduct::with(['doseUnit', 'route'])->where('is_active', 1)->orderBy('product_name')->get();
        $frequencies = MedicineFrequencyType::where('is_active', 1)->orderBy('sort_order')->get();
        $meals = MedicineMealRelation::where('is_active', 1)->orderBy('sort_order')->get();
        $timings = MedicineTimingOfDay::where('is_active', 1)->orderBy('sort_order')->get();

        $b_ipd_copy_medication = $ps->hasPermission('b_ipd_copy_medication');

        return view('ipd.medications', compact(
            'ipd',
            'prescriptions',
            'doctors',
            'medicines',
            'frequencies',
            'meals',
            'timings',
            'b_ipd_copy_medication',
            'ps'
        ));
    }

    // ─────────────────────────────────────────────
    // Discharge Approvals (global list)
    // ─────────────────────────────────────────────
    public function dischargeApprovals(Request $request, PermissionService $ps)
    {
        $canView = ($ps->hasPermission('m_discharge') || $ps->hasPermission('m_ipd')) && $ps->hasPermission('im_discharge_approval');
        $canApprovalView = $ps->hasPermission('b_discharge_approval_view');

        if (!$canView) {
            return view('ipd.discharge-approvals', [
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

        $countQuery = DB::table('ipd_discharge')
            ->join('ipds', 'ipds.id', '=', 'ipd_discharge.ipd_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where(function ($q) {
                $q->whereNull('ipd_discharge.approved')
                  ->orWhere('ipd_discharge.approved', '!=', 'Yes');
            });

        if ($loggedInPatientCategoryId > 0) {
            $countQuery->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $totalRecords = $countQuery->count();
        $totalPages = (int) ceil($totalRecords / $perPage);

        $query = DB::table('ipd_discharge')
            ->join('ipds', 'ipds.id', '=', 'ipd_discharge.ipd_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where(function ($q) {
                $q->whereNull('ipd_discharge.approved')
                  ->orWhere('ipd_discharge.approved', '!=', 'Yes');
            });

        if ($loggedInPatientCategoryId > 0) {
            $query->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $records = $query->select([
                'ipd_discharge.id',
                'ipd_discharge.ipd_id',
                'patients.patient_name',
                'ipds.opd_id',
                'ipds.admission_date_time',
            ])
            ->orderBy('ipd_discharge.id', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        foreach ($records as $row) {
            if (!empty($row->admission_date_time)) {
                try {
                    $row->admission_date_time_formatted = \Carbon\Carbon::parse($row->admission_date_time)->format('d-m-Y h:i A');
                } catch (\Exception $e) {
                    $row->admission_date_time_formatted = $row->admission_date_time;
                }
            } else {
                $row->admission_date_time_formatted = '';
            }
        }

        return view('ipd.discharge-approvals', compact(
            'canView',
            'records',
            'canApprovalView',
            'page',
            'totalPages',
            'totalRecords'
        ));
    }

    // ─────────────────────────────────────────────
    // Discharged List
    // ─────────────────────────────────────────────

    public function discharged(PermissionService $ps)
    {
        $canView = ($ps->hasPermission('m_discharge') || $ps->hasPermission('m_ipd')) && $ps->hasPermission('im_discharge_patients');
        return view('ipd.discharged', compact('canView', 'ps'));
    }

    public function dischargedData(Request $request, PermissionService $ps): JsonResponse
    {
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
            0 => 'ipd_discharge.id',
            1 => 'patients.patient_name',
            2 => 'patients.mobile_number',
            3 => 'ipd_discharge.diagnosis',
            4 => 'ipds.opd_id',
            5 => 'ipds.id',
            6 => 'ipds.admission_date_time',
            7 => 'ipd_discharge.discharge_date_time',
        ];

        $orderColumn = $columns[$orderColumnIndex] ?? 'ipd_discharge.discharge_date_time';

        $loggedInPatientCategoryId = (int) session('patient_category_id', session('logged_in_patient_category_id', 1));

        $baseQuery = DB::table('ipd_discharge')
            ->join('ipds', 'ipds.id', '=', 'ipd_discharge.ipd_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipd_discharge.approved', 'Yes');

        if ($loggedInPatientCategoryId > 0) {
            $baseQuery->where('patients.patient_category_id', $loggedInPatientCategoryId);
        }

        $totalRecords = $baseQuery->count();

        $query = clone $baseQuery;

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('patients.patient_name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('patients.mobile_number', 'LIKE', "%{$searchValue}%")
                  ->orWhere('ipd_discharge.diagnosis', 'LIKE', "%{$searchValue}%");
            });
        }

        $filteredRecords = $searchValue !== '' ? $query->count() : $totalRecords;

        $rows = $query->select([
                'ipd_discharge.id as discharge_id',
                'ipd_discharge.diagnosis',
                'ipd_discharge.discharge_date_time',
                'patients.patient_name',
                'patients.mobile_number',
                'ipds.id as ipd_id',
                'ipds.opd_id',
                'ipds.dcd_id',
                'ipds.admission_date_time',
            ])
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get();

        $canView = $ps->hasPermission('b_discharge_patient_view');

        $data = [];
        foreach ($rows as $row) {
            $dischargeId = (int) $row->discharge_id;
            $ipdId = (int) $row->ipd_id;
            $dcdId = (int) $row->dcd_id;
            $patientName = htmlspecialchars($row->patient_name ?? '', ENT_QUOTES, 'UTF-8');
            $mobileNumber = htmlspecialchars($row->mobile_number ?? '', ENT_QUOTES, 'UTF-8');
            $diagnosis = htmlspecialchars($row->diagnosis ?? '', ENT_QUOTES, 'UTF-8');
            $opdId = htmlspecialchars((string) ($row->opd_id ?? ''), ENT_QUOTES, 'UTF-8');
            $ipdIdDisplay = $ipdId;

            $admissionDateTime = '';
            if (!empty($row->admission_date_time)) {
                try {
                    $admissionDateTime = \Carbon\Carbon::parse($row->admission_date_time)->format('d-m-Y h:i A');
                } catch (\Exception $e) {
                    $admissionDateTime = (string) $row->admission_date_time;
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
            if ($dcdId > 0) {
                $actions .= '<a href="' . route('dcd.show', $dcdId) . '" data-bs-toggle="tooltip" title="DCD Dashboard"><i class="fas fa-hospital-user"></i></a> ';
            }
            $actions .= '<a href="' . route('ipd.show', $ipdId) . '" data-bs-toggle="tooltip" title="Dashboard"><i class="fas fa-border-outer"></i></a> ';
            if ($canView) {
                $actions .= '<a href="' . route('ipd.view-discharged', $dischargeId) . '" data-bs-toggle="tooltip" title="View"><i class="fas fa-list"></i></a> ';
            }
            $actions .= '<a target="_blank" href="' . route('pdf.ipd.patients-file', ['ipdId' => $ipdId]) . '" data-bs-toggle="tooltip" title="Patients File"><i class="fas fa-files-medical"></i></a> ';
            $actions .= '<a target="_blank" href="' . route('pdf.discharge.ipd', ['dischargeId' => $dischargeId]) . '" data-bs-toggle="tooltip" title="Print"><i class="fas fa-print"></i></a>';
            $actions .= '</div>';

            $dischargeDateEditable = '<span class="editable-discharge-date" data-discharge-id="' . $dischargeId . '" style="cursor: pointer;" title="Double-click to edit">' . $dischargeDateTime . '</span>';

            $data[] = [
                $actions,
                $patientName,
                $mobileNumber,
                $diagnosis,
                $opdId,
                $ipdIdDisplay,
                $admissionDateTime,
                $dischargeDateEditable,
            ];
        }

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function updateDischargeDate(UpdateIpdDischargeDateRequest $request): JsonResponse
    {
        $dischargeId = (int) $request->input('discharge_id');
        $dischargeDateTime = trim((string) $request->input('discharge_date_time'));

        try {
            $parsed = \Carbon\Carbon::parse($dischargeDateTime);
            $dbDateTime = $parsed->format('Y-m-d H:i:s');
            $formattedDate = $parsed->format('d-m-Y h:i A');
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Invalid date format']);
        }

        DB::table('ipd_discharge')->where('id', $dischargeId)->update([
            'discharge_date_time' => $dbDateTime,
        ]);

        return response()->json([
            'success'        => true,
            'message'        => 'Discharge date updated successfully',
            'formatted_date' => $formattedDate,
        ]);
    }

    public function viewDischarged(int $id, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('b_discharge_patient_view'), 403);
        $discharge = IpdDischarge::find($id);
        if ($discharge) {
            $ipd = Ipd::with(['patient', 'doctor', 'bed.wardCategory', 'bed.floor', 'discharge.approvedDoctor', 'opd', 'tpaProvider'])->find($discharge->ipd_id);
        } else {
            $ipd = Ipd::with(['patient', 'doctor', 'bed.wardCategory', 'bed.floor', 'discharge.approvedDoctor', 'opd', 'tpaProvider'])->find($id);
        }
        abort_unless($ipd, 404);
        return view('ipd.view-discharged', compact('ipd', 'ps'));
    }

    public function viewDischargeApproval(int $id, PermissionService $ps)
    {
        $canView = $ps->hasPermission('im_discharge_approve');
        $approve = $ps->hasPermission('b_discharge_approve_and_discharge');

        if (!$canView) {
            return view('ipd.view-discharge-approval', [
                'canView'   => false,
                'notFound'  => false,
                'discharge' => null,
                'ipd'       => null,
                'patient'   => null,
                'approve'   => false,
                'ps'        => $ps,
            ]);
        }

        $discharge = IpdDischarge::find($id);
        if ($discharge) {
            $ipd = Ipd::with(['patient', 'doctor.designation', 'bed.floor', 'bed.bedType', 'bed.wardCategory', 'opd'])->find($discharge->ipd_id);
        } else {
            $ipd = Ipd::with(['patient', 'doctor.designation', 'bed.floor', 'bed.bedType', 'bed.wardCategory', 'opd'])->find($id);
            $discharge = $ipd ? IpdDischarge::where('ipd_id', $ipd->id)->first() : null;
        }

        if (!$discharge || !$ipd) {
            return view('ipd.view-discharge-approval', [
                'canView'   => true,
                'notFound'  => true,
                'discharge' => null,
                'ipd'       => null,
                'patient'   => null,
                'approve'   => false,
                'ps'        => $ps,
            ]);
        }

        $patient = $ipd->patient;

        $dischargeDateTimeFormatted = !empty($discharge->discharge_date_time)
            ? date('d-m-Y h:i A', strtotime($discharge->discharge_date_time))
            : date('d-m-Y h:i A');

        $admissionDateTimeFormatted = !empty($ipd->admission_date_time)
            ? date('d-m-Y h:i A', strtotime($ipd->admission_date_time))
            : '';

        // Consultant Doctors associated with this IPD
        $consultantDoctors = collect();
        if (Schema::hasTable('ipd_doctors')) {
            $consultantDoctors = DB::table('ipd_doctors')
                ->join('staff_members', 'staff_members.id', '=', 'ipd_doctors.doctor_id')
                ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('ipd_doctors.ipd_id', $ipd->id)
                ->select('ipd_doctors.*', 'designations.designation', 'staff_members.staff_name', 'staff_members.mobile_number', 'staff_members.photo')
                ->get();
        }

        // Investigations
        $investigations = collect();
        if (Schema::hasTable('ipd_discharge_investigations')) {
            $investigations = DB::table('ipd_discharge_investigations')
                ->where('discharge_id', $discharge->id)
                ->get();
        }

        // Procedures
        $procedures = collect();
        if (Schema::hasTable('ipd_discharge_procedures')) {
            $procedures = DB::table('ipd_discharge_procedures')
                ->where('discharge_id', $discharge->id)
                ->get();
        }

        // Treatment given
        $medicationsGiven = collect();
        if (Schema::hasTable('ipd_discharge_medication_given')) {
            $medicationsGiven = DB::table('ipd_discharge_medication_given')
                ->where('discharge_id', $discharge->id)
                ->get();
        }

        // Master datasets for Treatment Advised
        $pharmaProducts    = Schema::hasTable('pharma_products') ? PharmaProduct::orderBy('product_name')->get(['id', 'product_name']) : collect();
        $frequencyTypes    = Schema::hasTable('medicine_frequency_types') ? DB::table('medicine_frequency_types')->orderBy('sort_order')->get(['id', 'display_text']) : collect();
        $mealRelations     = Schema::hasTable('medicine_meal_relation') ? DB::table('medicine_meal_relation')->orderBy('id')->get(['id', 'relation_name']) : collect();
        $timingList        = Schema::hasTable('medicine_timing_of_day') ? DB::table('medicine_timing_of_day')->orderBy('sort_order')->get(['id', 'timing_name']) : collect();
        $medicalConditions = Schema::hasTable('medical_conditions') ? DB::table('medical_conditions')->where('is_active', 1)->orderBy('condition_name')->get(['id', 'condition_name', 'category']) : collect();

        // All active doctors for Add Doctor modal
        $allDoctors = Schema::hasTable('staff_members') ? StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']) : collect();

        return view('ipd.view-discharge-approval', compact(
            'canView',
            'approve',
            'discharge',
            'ipd',
            'patient',
            'dischargeDateTimeFormatted',
            'admissionDateTimeFormatted',
            'consultantDoctors',
            'investigations',
            'procedures',
            'medicationsGiven',
            'pharmaProducts',
            'frequencyTypes',
            'mealRelations',
            'timingList',
            'medicalConditions',
            'allDoctors',
            'ps'
        ));
    }

    // ─────────────────────────────────────────────
    // Visits History (OPD visits linked to this IPD admission)
    // ─────────────────────────────────────────────

    public function visits(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_history') && $ps->hasPermission('b_ipd_view_history_detail'), 403);
        $ipd = Ipd::with(['patient', 'doctor', 'opd'])->findOrFail($ipdId);
        $patientId = (int) ($ipd->patient_id ?? 0);
        $opdId = (int) ($ipd->opd_id ?? 0);

        // All OTHER OPD visits for this patient (excluding the current admission's OPD)
        $otherOpds = \App\Models\Opd::with('doctor')
            ->where('patient_id', $patientId)
            ->where('id', '!=', $opdId)
            ->orderByDesc('id')
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

            $linkedDcd = \App\Models\Dcd::with('doctor')->where('opd_id', $otherOpd->id)->first();
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

        return view('ipd.visits', compact('ipd', 'visits', 'ps'));
    }

    public function getVisitDetail(Request $request)
    {
        $id = (int) $request->input('id');
        $type = strtoupper((string) $request->input('type'));

        if ($type === 'IPD') {
            $discharge = DB::table('ipd_discharge')->where('ipd_id', $id)->first();
            if (!$discharge) {
                return response('<div class="no-access"><i class="fa-duotone fa-shield-halved"></i><p>Patient is Admitted</p></div>');
            }

            $ipd = Ipd::with(['patient', 'opd', 'doctor', 'bed.floor', 'bed.bedType', 'bed.wardCategory'])->findOrFail($id);
            $patient = $ipd->patient;

            $doctors = DB::table('ipd_doctors')
                ->join('staff_members', 'staff_members.id', '=', 'ipd_doctors.doctor_id')
                ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('ipd_doctors.ipd_id', $id)
                ->select('staff_members.staff_name', 'staff_members.mobile_number', 'staff_members.photo', 'designations.designation')
                ->get();

            if ($doctors->isEmpty() && $ipd->doctor) {
                $desig = DB::table('designations')->where('id', $ipd->doctor->designation_id)->value('designation') ?? '';
                $doctors = collect([(object)[
                    'staff_name' => $ipd->doctor->staff_name,
                    'mobile_number' => $ipd->doctor->mobile_number,
                    'photo' => $ipd->doctor->photo,
                    'designation' => $desig,
                ]]);
            }

            $investigations = DB::table('ipd_discharge_investigations')->where('discharge_id', $discharge->id)->get();
            $procedures = DB::table('ipd_discharge_procedures')->where('discharge_id', $discharge->id)->get();
            $medicationsGiven = DB::table('ipd_discharge_medication_given')->where('discharge_id', $discharge->id)->get();

            $treatmentAdvised = DB::table('ipd_discharge_treatment_advised as m')
                ->join('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
                ->leftJoin('ipd_discharge_treatment_timing_selections as mts', 'm.id', '=', 'mts.treatment_advised_id')
                ->leftJoin('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
                ->where('m.discharge_id', $discharge->id)
                ->select('m.id', 'pp.product_name as medicine_name', 'm.dose_quantity', 'ft.display_text as frequency_display', 'mr.relation_name as meal_relation', 'm.duration_days', 't.timing_name', 't.sort_order')
                ->get()
                ->groupBy('id')
                ->map(function ($group) {
                    $first = $group->first();
                    $timings = $group->pluck('timing_name')->filter()->unique()->implode(', ');
                    return (object) [
                        'medicine_name' => $first->medicine_name,
                        'dose_quantity' => $first->dose_quantity,
                        'frequency_display' => $first->frequency_display,
                        'meal_relation' => $first->meal_relation,
                        'duration_days' => $first->duration_days,
                        'timing_display' => $timings,
                    ];
                });

            return view('ipd.partials.visit-detail-ipd', compact('ipd', 'patient', 'discharge', 'doctors', 'investigations', 'procedures', 'medicationsGiven', 'treatmentAdvised'));
        }

        if ($type === 'DCD') {
            $discharge = DB::table('dcd_discharge')->where('dcd_id', $id)->first();
            if (!$discharge) {
                return response('<div class="no-access"><i class="fa-duotone fa-shield-halved"></i><p>Patient is in Day Care</p></div>');
            }

            $dcd = \App\Models\Dcd::with(['patient', 'opd', 'doctor', 'bed.floor', 'bed.bedType', 'bed.wardCategory'])->findOrFail($id);
            $patient = $dcd->patient;

            $doctor = DB::table('staff_members')
                ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('staff_members.id', $dcd->consultant_doctor_id)
                ->select('staff_members.staff_name', 'staff_members.mobile_number', 'staff_members.photo', 'designations.designation')
                ->first();

            $investigations = DB::table('dcd_discharge_investigations')->where('discharge_id', $discharge->id)->get();
            $procedures = DB::table('dcd_discharge_procedures')->where('discharge_id', $discharge->id)->get();
            $medicationsGiven = DB::table('dcd_discharge_medication_given')->where('discharge_id', $discharge->id)->get();

            $treatmentAdvised = DB::table('dcd_discharge_treatment_advised as m')
                ->join('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
                ->leftJoin('dcd_discharge_treatment_timing_selections as mts', 'm.id', '=', 'mts.treatment_advised_id')
                ->leftJoin('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
                ->where('m.discharge_id', $discharge->id)
                ->select('m.id', 'pp.product_name as medicine_name', 'm.dose_quantity', 'ft.display_text as frequency_display', 'mr.relation_name as meal_relation', 'm.duration_days', 't.timing_name', 't.sort_order')
                ->get()
                ->groupBy('id')
                ->map(function ($group) {
                    $first = $group->first();
                    $timings = $group->pluck('timing_name')->filter()->unique()->implode(', ');
                    return (object) [
                        'medicine_name' => $first->medicine_name,
                        'dose_quantity' => $first->dose_quantity,
                        'frequency_display' => $first->frequency_display,
                        'meal_relation' => $first->meal_relation,
                        'duration_days' => $first->duration_days,
                        'timing_display' => $timings,
                    ];
                });

            return view('ipd.partials.visit-detail-dcd', compact('dcd', 'patient', 'discharge', 'doctor', 'investigations', 'procedures', 'medicationsGiven', 'treatmentAdvised'));
        }

        if ($type === 'OPD') {
            $opd = \App\Models\Opd::with(['patient', 'doctor'])->findOrFail($id);
            $patient = $opd->patient;

            $doctors = DB::table('opd_doctors')
                ->join('staff_members', 'staff_members.id', '=', 'opd_doctors.doctor_id')
                ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
                ->where('opd_doctors.opd_id', $id)
                ->select('staff_members.staff_name', 'staff_members.mobile_number', 'staff_members.photo', 'designations.designation')
                ->get();

            if ($doctors->isEmpty() && $opd->doctor) {
                $designation = DB::table('designations')->where('id', $opd->doctor->designation_id)->value('designation') ?? '';
                $doctors = collect([(object)[
                    'staff_name' => $opd->doctor->staff_name,
                    'mobile_number' => $opd->doctor->mobile_number,
                    'photo' => $opd->doctor->photo,
                    'designation' => $designation,
                ]]);
            }

            $labServices = DB::table('opd_services')
                ->join('charge_departments', 'charge_departments.id', '=', 'opd_services.charge_department_id')
                ->where('charge_departments.category', 'Lab')
                ->where('opd_services.opd_id', $id)
                ->select('opd_services.service_title')
                ->get();

            $procedureServices = DB::table('opd_services')
                ->join('charge_departments', 'charge_departments.id', '=', 'opd_services.charge_department_id')
                ->where('charge_departments.category', 'Procedure')
                ->where('opd_services.opd_id', $id)
                ->select('opd_services.service_title')
                ->get();

            $prescriptions = DB::table('opd_prescriptions')->where('opd_id', $id)->get();
            $prescriptionsData = [];
            foreach ($prescriptions as $p) {
                $medicines = DB::table('opd_medicines as m')
                    ->join('pharma_products as pp', 'm.pharma_product_id', '=', 'pp.id')
                    ->leftJoin('medicine_frequency_types as ft', 'm.frequency_type_id', '=', 'ft.id')
                    ->leftJoin('medicine_meal_relation as mr', 'm.meal_relation_id', '=', 'mr.id')
                    ->leftJoin('opd_medicine_timing_selections as mts', 'm.id', '=', 'mts.opd_medicine_id')
                    ->leftJoin('medicine_timing_of_day as t', 'mts.timing_id', '=', 't.id')
                    ->where('m.opd_prescription_id', $p->id)
                    ->select('m.id', 'pp.product_name as medicine_name', 'm.dose_quantity', 'ft.display_text as frequency_display', 'mr.relation_name as meal_relation', 'm.duration_days', 't.timing_name', 't.sort_order')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($group) {
                        $first = $group->first();
                        $timings = $group->pluck('timing_name')->filter()->unique()->implode(', ');
                        return (object) [
                            'medicine_name' => $first->medicine_name,
                            'dose_quantity' => $first->dose_quantity,
                            'frequency_display' => $first->frequency_display,
                            'meal_relation' => $first->meal_relation,
                            'duration_days' => $first->duration_days,
                            'timing_display' => $timings,
                        ];
                    });

                $prescriptionsData[] = [
                    'findings' => $p->findings,
                    'medicines' => $medicines,
                ];
            }

            return view('ipd.partials.visit-detail-opd', compact('opd', 'patient', 'doctors', 'labServices', 'procedureServices', 'prescriptionsData'));
        }

        return response('<div class="alert alert-warning">Unknown visit type</div>', 400);
    }

    // ─────────────────────────────────────────────
    // RMO Notes
    // ─────────────────────────────────────────────

    public function rmoNotes(int $ipdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_ipd_rmo_notes'), 403);
        $ipd = Ipd::with(['patient', 'bed.wardCategory'])->findOrFail($ipdId);
        $file = IpdPatientFile::where('ipd_id', $ipdId)->first();
        $rmo_notes = $file ? html_entity_decode($file->rmo_notes ?? '', ENT_QUOTES, 'UTF-8') : '';
        $save = $ps->hasPermission('b_ipd_rmo_notes_save');

        return view('ipd.rmo-notes', compact('ipd', 'rmo_notes', 'save', 'ps'));
    }

    public function saveRmoNotes(IpdRmoNotesRequest $request, int $ipdId): JsonResponse
    {
        abort_unless(
            app(PermissionService::class)->hasPermission('im_ipd_rmo_notes') || 
            app(PermissionService::class)->hasPermission('b_ipd_rmo_notes_save'), 
            403
        );

        $id = $ipdId;
        $rmo_notes = preg_replace('/<\/?span[^>]*>/i', '', (string) $request->input('rmo_notes', ''));

        $file = IpdPatientFile::where('ipd_id', $id)->first();
        if ($file) {
            $file->update(['rmo_notes' => $rmo_notes]);
        } else {
            IpdPatientFile::create([
                'ipd_id'                            => $id,
                'rmo_notes'                         => $rmo_notes,
                'relative_name'                     => '',
                'relationship'                      => '',
                'witness_name'                      => '',
                'admission_type'                    => '',
                'patient_condition'                 => '',
                'general_condition'                 => '',
                'source_of_history'                 => '',
                'chief_complaints'                  => '',
                'history_of_present_illness'        => '',
                'past_medical_history'              => '',
                'pain_score'                        => '',
                'pain_location'                     => '',
                'intervention'                      => '',
                'family_ho'                         => '',
                'current_medication'                => '',
                'allergies'                         => '',
                'ho_blood_transfusion'              => '',
                'substance_abuse'                   => '',
                'nutritional_status'                => '',
                'general_examination'               => '',
                'systemic_examination'              => '',
                'cns_system_mental_status'          => '',
                'cns_system_sensory_examination'    => '',
                'cns_system_motor_examination_tone' => '',
                'cns_system_reflexes'               => '',
                'cns_system_power'                  => '',
                'provisional_diagnosis'             => '',
                'diet_plan'                         => '',
                'progress_sheet'                    => '',
            ]);
        }

        return response()->json([
            'success' => true,
            'ipd_id'  => $id,
            'message' => 'Saved Successfully.',
        ]);
    }
}

