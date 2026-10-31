<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddOpdDoctorRequest;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\CollectOpdPaymentsRequest;
use App\Http\Requests\SaveOpdChargeRequest;
use App\Http\Requests\SaveOpdPrescriptionRequest;
use App\Http\Requests\StoreOpdProcedureNoteRequest;
use App\Http\Requests\UpdateOpdOverviewRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Opd;
use App\Models\OpdPrescription;
use App\Models\OpdMedicine;
use App\Models\OpdService;
use App\Models\OpdServiceDetail;
use App\Models\OpdLabInvestigationNote;
use App\Models\OpdProcedureNote;
use App\Models\BillingCharge;
use App\Models\BillingChargeDetail;
use App\Models\BillingChargePayment;
use App\Models\ChargeDepartment;
use App\Models\Charge;
use App\Models\StaffMember;
use App\Models\Patient;
use App\Models\AbdmCareContext;
use App\Models\Ipd;
use App\Models\OpdMedicineTimingSelection;
use App\Models\PharmaProduct;
use App\Models\MedicineFrequencyType;
use App\Models\MedicineMealRelation;
use App\Models\MedicineTimingOfDay;
use App\Models\MedicalCondition;
use App\Models\IdFieldSetting;
use App\Services\ChargeLookupService;
use App\Services\PermissionService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\CurrentUser;
use App\Support\Uhid;
use App\Support\Uploads;

class OpdController extends Controller
{
    /**
     * Runs `$request` through the given Form Request (authorize + rules). The write actions below keep a plain
     * Request signature so they can also be invoked with the already-resolved request of the legacy dispatcher.
     */
    private function validatedForm(Request $request, string $formClass): FormRequest
    {
        if ($request instanceof $formClass) {
            return $request;
        }

        /** @var FormRequest $form */
        $form = $formClass::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        return $form;
    }

    // ─────────────────────────────────────────────
    // OPD Register List
    // ─────────────────────────────────────────────

    public function index(Request $request, PermissionService $ps, SettingsService $settings)
    {
        abort_unless($ps->hasPermission('m_opd'), 403);

        // Consultant filter options - only doctors who have at least one OPD visit registered today,
        // so the dropdown never lists doctors with an empty queue.
        $doctors = StaffMember::whereHas('opds', fn($q) => $q->whereDate('opd_date_time', today()))
            ->orderBy('staff_name')->get(['id', 'staff_name']);

        // Create OPD's consultant list: every active member of the configured doctor department.
        $doctorDeptId = $settings->getIdField('doctor_department_id');
        $consultants  = $doctorDeptId > 0
            ? StaffMember::where('department_id', $doctorDeptId)->where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name'])
            : collect();

        return view('opd.index', [
            'doctors'      => $doctors,
            'consultants'  => $consultants,
            'bloodGroups'  => DB::table('blood_groups')->pluck('blood_group'),
            'abhaTerms'    => $settings->abhaTerms(),
            // ESM / service fields only apply to non-default patient categories (legacy: category > 1).
            'showEsmFields' => (int) session('patient_category_id', 0) > 1,
            'canNewOpd'    => $ps->hasPermission('b_new_opd'),
            'today'        => now()->format('d-m-Y'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        // Accepts both the DataTables parameter names and the plain filter names.
        $period   = $request->input('report_period', $request->input('period', 'Daily'));
        $date     = $request->input('search_date', $request->input('date', date('d-m-Y')));
        $from     = $request->input('from_date', date('d-m-Y'));
        $to       = $request->input('to_date', date('d-m-Y'));
        $month    = $request->input('search_month', $request->input('month', date('n')));
        $year     = date('Y');
        $source   = $request->input('opd_source', $request->input('source', ''));
        $doctorId = $request->integer('consultant_doctor_id');
        // Same session key the login sets and PatientController reads; 0 = all categories (legacy: filter only when > 0).
        $catId    = (int) session('patient_category_id', 0);
        $search   = $request->input('search.value', '');

        $query = Opd::with(['patient', 'doctor'])
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->when($catId > 0, fn($q) => $q->where('patients.patient_category_id', $catId))
            ->select('opds.*', 'patients.status as patient_status');

        // Date filtering
        if ($period === 'Daily') {
            $d = \DateTime::createFromFormat('d-m-Y', $date);
            if ($d) {
                $query->whereDate('opds.opd_date_time', $d->format('Y-m-d'));
            }
        } elseif ($period === 'Monthly') {
            $query->whereYear('opds.opd_date_time', $year)->whereMonth('opds.opd_date_time', $month);
        } elseif ($period === 'Periodic') {
            $f = \DateTime::createFromFormat('d-m-Y', $from);
            $t = \DateTime::createFromFormat('d-m-Y', $to);
            if ($f && $t) {
                $query->whereBetween('opds.opd_date_time', [$f->format('Y-m-d 00:00:00'), $t->format('Y-m-d 23:59:59')]);
            }
        }

        // Source filter
        if ($source) {
            $query->where('opds.source', $source);
        }

        // Doctor filter
        if ($doctorId) {
            $query->where('opds.consultant_doctor_id', $doctorId);
        }

        // Search filter
        if ($search) {
            $uhidId = Uhid::parse((string) $search);   // a typed UHID finds that patient's visits
            $query->where(function($q) use ($search, $uhidId) {
                $q->where('patients.patient_name', 'LIKE', "%{$search}%")
                  ->orWhere('patients.mobile_number', 'LIKE', "%{$search}%")
                  ->when($uhidId !== null, fn ($u) => $u->orWhere('patients.id', $uhidId));
            });
        }

        $total = $query->count();
        
        // "All consultants": order by doctor name first so the client-side RowGroup can group same-doctor
        // rows; a single-consultant view keeps newest-first (legacy search_opds ordering).
        if (!$doctorId) {
            $query->leftJoin('staff_members', 'staff_members.id', '=', 'opds.consultant_doctor_id')
                  ->orderBy('staff_members.staff_name')
                  ->orderByDesc('opds.id');
        } else {
            $query->orderByDesc('opds.id');
        }
        
        $opds  = $query->skip($request->integer('start'))
            ->take($request->integer('length', 100))
            ->get();

        $data = $opds->map(function($opd) {
            $id = (int) $opd->id;
            $tip = 'data-bs-toggle="tooltip" data-bs-placement="top"';

            $actions = '<a href="' . e(url('opd/' . $id)) . '" ' . $tip . ' title="OPD Dashboard"><i class="fas fa-list"></i></a>'
                . ' <a href="' . e(route('pdf.opd.card', $id)) . '" target="_blank" ' . $tip . ' title="Print"><i class="fas fa-print"></i></a>'
                . ' <a href="' . e(route('pdf.opd.receipt', $id)) . '" target="_blank" ' . $tip . ' title="Receipt"><i class="fas fa-receipt"></i></a>'
                               . ' <a href="' . e(route('pdf.opd.card-slip', $id)) . '" target="_blank" ' . $tip . ' title="Card Slip"><i class="fas fa-id-card"></i></a>';

            // "Admit Patient" only while the patient is not already admitted.
            if ($opd->patient_status !== 'Admitted') {
                $actions .= ' <a href="javascript:void(0)" onclick="showAdmitPatientDialog(' . $id . ')" ' . $tip . ' title="Admit Patient"><i class="fas fa-bed"></i></a>';
            }

            // "Collect Payment" only on ABHA QR-scan visits with no payment recorded yet.
            if ($opd->source === 'abha' && empty($opd->payment_mode)) {
                $actions .= ' <a href="javascript:void(0)" onclick="collectOPDPaymentModal(' . $id . ', ' . (float) $opd->opd_charges . ')" ' . $tip . ' title="Collect Payment"><i class="fas fa-money-bill-wave"></i></a>';
            }

            $sourceLabel = $opd->source === 'abha'
                ? '<span class="badge bg-info">Abha</span>'
                : '<span class="badge bg-secondary">Manual</span>';

            $statusSwitch = '<div class="switch"><label>'
                . '<input class="opd-status-switch" type="checkbox" data-opd-id="' . $id . '"' . ($opd->status === 'Consulted' ? ' checked' : '') . '>'
                . '<span class="lever"></span></label></div>';

            return [
                'DT_RowId'        => 'row_' . $id,
                'action'          => $actions,
                'token_number'    => $opd->token_number ?? '-',
                'id'              => $id,
                'opd_date_time'   => date('d-m-Y h:i A', strtotime($opd->opd_date_time)),
                'patient_name'    => e($opd->patient?->patient_name ?? ''),
                'gender'          => $opd->patient?->gender ?? '',
                'age'             => $opd->patient?->age ?? '',
                'mobile_number'   => $opd->patient?->mobile_number ?? '',
                'source'          => $sourceLabel,
                'status_switch'   => $statusSwitch,
                'consultant_name' => e($opd->doctor?->staff_name ?? ''),
            ];
        });

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $total,
            'data'            => $data,
        ]);
    }

    // ─────────────────────────────────────────────
    // Create OPD
    // ─────────────────────────────────────────────

    /** The date pickers submit "DD-MM-YYYY hh:mm AM"; ISO-ish values are accepted too. Never guess the day/month order. */
    private function parseOpdDateTime(string $value): ?Carbon
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

    public function store(Request $request, SettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'patient_id'           => 'required|integer|exists:patients,id',
            'consultant_doctor_id' => 'required|integer|exists:staff_members,id',
            'opd_date_time'        => ['required', function (string $attribute, $value, \Closure $fail) {
                if (!$this->parseOpdDateTime((string) $value)) {
                    $fail('OPD date must be a valid date and time.');
                }
            }],
            'mobile_number'        => 'required|string|max:50',
            'address'              => 'required|string|max:255',
            'opd_charges'          => 'required|numeric|min:0',
            'payment_mode'         => 'nullable|string|in:Cash,UPI,Debit Card,Credit Card',
        ], [
            'opd_charges.required' => 'OPD charges are required (enter 0 if free).',
        ]);

        $charges     = (float) $validated['opd_charges'];
        $paymentMode = (string) ($validated['payment_mode'] ?? '');
        if ($charges > 0 && $paymentMode === '') {
            return response()->json(['success' => false, 'message' => 'Please select a payment mode.'], 422);
        }

        $visitAt  = $this->parseOpdDateTime($validated['opd_date_time']);
        $deptId   = $settings->getIdField('opd_department_id');
        $userId   = (int) CurrentUser::id();
        $nowStamp = now()->toDateTimeString();

        try {
            $opd = DB::transaction(function () use ($validated, $visitAt, $charges, $paymentMode, $deptId, $userId, $nowStamp) {
                // Per-doctor, per-day sequential token. The row lock makes concurrent saves queue up instead of
                // handing out the same number (same counter OpdShareService uses for QR-scanned visits).
                $tokenMax = (int) DB::table('opds')
                    ->where('consultant_doctor_id', $validated['consultant_doctor_id'])
                    ->whereDate('opd_date_time', $visitAt->toDateString())
                    ->lockForUpdate()
                    ->max('token_number');

                // The opds table has NOT NULL text columns without defaults: store '' exactly like the legacy INSERT.
                $opd = Opd::create([
                    'opd_date_time'        => $visitAt->toDateTimeString(),
                    'token_number'         => $tokenMax + 1,
                    'patient_id'           => $validated['patient_id'],
                    'charge_department_id' => $deptId,
                    'consultant_doctor_id' => $validated['consultant_doctor_id'],
                    'height' => '', 'weight' => '', 'bp' => '', 'pulse' => '', 'temperature' => '',
                    'respiration' => '', 'spo2' => '', 'symptoms' => '', 'remarks' => '',
                    'opd_charges'          => $charges,
                    'payment_mode'         => $paymentMode,
                    'status'               => 'New',
                    'source'               => 'manual',
                    'created_on'           => $nowStamp,
                    'user_id'              => $userId,
                ]);

                // The Create OPD form lets staff correct the patient's contact details.
                Patient::whereKey($validated['patient_id'])->update([
                    'mobile_number' => $validated['mobile_number'],
                    'address'       => $validated['address'],
                ]);

                DB::table('opd_doctors')->insert([
                    'doctor_id'  => $validated['consultant_doctor_id'],
                    'opd_id'     => $opd->id,
                    'created_on' => $nowStamp,
                    'user_id'    => $userId,
                ]);

                return $opd;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Database error while saving the OPD visit.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OPD Created Successfully. Token Number: ' . $opd->token_number,
            'opd_id'  => $opd->id,
            'token'   => $opd->token_number,
            'charges' => $charges,
        ]);
    }

    /**
     * Existing-patient details for the Create OPD modal: the summary card
     * with last OPD / last discharge, the contact fields, and the ABDM identity columns the "Patient
     * Identity" box needs.
     */
    public function patientDetails(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $now     = now();

        $lastDischarge = Ipd::query()
            ->join('ipd_discharge', 'ipd_discharge.ipd_id', '=', 'ipds.id')
            ->where('ipds.status', 'Discharged')->where('ipds.patient_id', $id)
            ->orderByDesc('ipds.admission_date_time')
            ->first(['ipd_discharge.discharge_date_time']);

        $lastOpd = Opd::where('patient_id', $id)->orderByDesc('opd_date_time')->first();

        $lastDetail = '';
        if ($lastDischarge) {
            $at = Carbon::parse($lastDischarge->discharge_date_time);
            $lastDetail .= 'Discharged: ' . $at->format('d-m-Y h:i A') . '( Before ' . $at->startOfDay()->diffInDays($now->copy()->startOfDay(), true) . ' days) ';
        }
        if ($lastOpd) {
            $at = Carbon::parse($lastOpd->opd_date_time);
            $lastDetail .= 'Last OPD: ' . $at->format('d-m-Y h:i A') . '( Before ' . $at->startOfDay()->diffInDays($now->copy()->startOfDay(), true) . ' days) Rs.' . $lastOpd->opd_charges;
        }
        if ($lastDetail === '') {
            $lastDetail = 'No Previous OPD IPD Record';
        }

        $photo = Uploads::url(Uploads::PATIENTS, $patient->photo);

        $html = '<div class="opd-pdwi-box">'
            . '<div class="box-left f1-mr10">'
            . '<p class="opd-last-detail">' . e($lastDetail) . '</p>'
            . '<p class="opd-pd-name">' . e($patient->patient_name) . ' (' . $patient->id . ')</p>'
            . '<p class="opd-pd-l2 mt-10"><span><i class="fas fa-venus-mars"></i>' . e($patient->gender) . '</span>'
            . '<span><i class="fas fa-tint"></i>' . e($patient->blood_group) . '</span>'
            . '<span><i class="fas fa-hourglass-half"></i>' . e($patient->age) . '</span></p>'
            . '</div>'
            . '<div class="opd-pimg"><img class="w100" src="' . e($photo) . '"></div>'
            . '</div>';

        return response()->json([
            'id'             => $patient->id,
            'patient_name'   => $patient->patient_name,
            'gender'         => $patient->gender,
            'mobile_number'  => $patient->mobile_number,
            'address'        => $patient->address,
            'aadhaar_number' => $patient->aadhaar_number,
            'abha_number'    => $patient->abha_number,
            'abha_address'   => $patient->abha_address,
            'date_of_birth'  => $patient->date_of_birth ? (string) $patient->date_of_birth : null,
            'photo'          => $patient->photo,
            'html'           => $html,
        ]);
    }

    /** Records a payment on a visit that did not collect one at creation (ABHA QR-scan visits). */
    public function collectPayment(Request $request, int $id): JsonResponse
    {
        $opd = Opd::findOrFail($id);

        $v = $request->validate([
            'payment_mode' => 'required|string|in:Cash,UPI,Debit Card,Credit Card',
            'opd_charges'  => 'required|numeric|min:0',
        ], [
            'payment_mode.required' => 'Payment mode and amount are required',
            'opd_charges.required'  => 'Payment mode and amount are required',
        ]);

        $opd->update(['opd_charges' => $v['opd_charges'], 'payment_mode' => $v['payment_mode']]);

        return response()->json(['success' => true]);
    }

    /** Inline New / Consulted toggle of the register - any other value falls back to 'New'. */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $opd    = Opd::findOrFail($id);
        $status = $request->input('status') === 'Consulted' ? 'Consulted' : 'New';

        $opd->update(['status' => $status]);

        return response()->json(['success' => true, 'status' => $status]);
    }

    // ─────────────────────────────────────────────
    // Overview (all sub-pages share this layout)
    // ─────────────────────────────────────────────

    public function show(int $id, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_opd_overview'), 403);

        $opd = Opd::with(['patient', 'doctor'])->findOrFail($id);
        $patient = $opd->patient;
        $patient_id = $patient?->id ?? 0;

        app(AuditLogger::class)->viewOpd($id, $patient?->id, $patient?->patient_name);

        $save = $ps->hasPermission('b_opd_save');

        $opdDateTime = $opd->opd_date_time ? Carbon::parse($opd->opd_date_time)->format('d-m-Y h:i A') : '';

        // Extract systolic and diastolic BP from "systolic/diastolic"
        $bp = (string) ($opd->bp ?? '');
        $bp_systolic = '';
        $bp_diastolic = '';
        if (strpos($bp, '/') !== false) {
            $parts = explode('/', $bp);
            $bp_systolic = $parts[0] ?? '';
            $bp_diastolic = $parts[1] ?? '';
        }

        // ABDM badge data — 5-state: Not Linked → Pending → Linked → Consent Received → Data Pushed
        $abdm_cc_badge = '';
        $abdm_push_badge = '';
        // Nothing left to link: the overview then drops its "Link to ABDM" button (the badge stays).
        $abdmFullyLinked = !empty($patient?->abha_address)
            && app(\App\Services\Abdm\CareContextService::class)->isFullyLinked('opd', (int) $id);

        if (!empty($patient?->abha_address)) {
            $careContext = DB::table('abdm_care_contexts')
                ->where('opd_id', $id)
                ->orderByDesc('id')
                ->first();

            $abdm_cc_status = $careContext?->status;
            $abdm_cc_reference = $careContext?->care_context_reference_number;

            $consent_status = null;
            if ($abdm_cc_reference) {
                $consent = DB::table('abdm_consents')
                    ->where('care_contexts', 'LIKE', '%' . $abdm_cc_reference . '%')
                    ->where('status', 'GRANTED')
                    ->orderByDesc('id')
                    ->first();
                $consent_status = $consent?->status;
            }

            if ($abdm_cc_status === 'linked') {
                $push = DB::table('abdm_health_info_pushes')
                    ->where('opd_id', $id)
                    ->where('push_succeeded', 1)
                    ->orderByDesc('id')
                    ->first();

                if ($push) {
                    $abdm_cc_badge = '<span class="badge bg-success" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Health data has been successfully pushed to ABDM (M4 complete)">Data Pushed</span>';
                } elseif ($consent_status === 'GRANTED') {
                    $abdm_cc_badge = '<span class="badge bg-info text-white" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Consent received from patient, waiting for ABDM to request data (M3 complete)">Consent Received</span>';
                } else {
                    $abdm_cc_badge = '<span class="badge bg-primary" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Care context linked, waiting for patient consent (M2 complete)">ABDM Linked</span>';
                }
            } elseif ($abdm_cc_status === 'pending') {
                $abdm_cc_badge = '<span class="badge bg-warning text-dark" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Care-context link request sent - awaiting ABDM confirmation">ABDM Pending</span>';
            } else {
                $abdm_cc_badge = '<span class="badge bg-secondary" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="This OPD visit has not been linked as a care context on ABDM yet">ABDM Not Linked</span>';
            }

            $abdm_push_row = DB::table('abdm_health_info_pushes')
                ->where('opd_id', $id)
                ->where('push_succeeded', 1)
                ->orderByDesc('id')
                ->first(['created_on']);

            if ($abdm_push_row && !empty($abdm_push_row->created_on)) {
                $abdm_push_badge = '<span class="badge bg-info text-dark" style="margin-left:6px;" data-bs-toggle="tooltip" data-bs-placement="top" title="Health data for this visit was last successfully pushed to a HIU on this date (UTC) - only re-sent automatically if a HIU requests it again">Pushed: ' . date('d-m-Y', strtotime($abdm_push_row->created_on)) . '</span>';
            }
        }

        // Consultant doctors list
        $doctors = DB::table('opd_doctors')
            ->join('staff_members', 'staff_members.id', '=', 'opd_doctors.doctor_id')
            ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
            ->where('opd_doctors.opd_id', $id)
            ->select(
                'opd_doctors.id',
                'opd_doctors.doctor_id',
                'staff_members.staff_name',
                'staff_members.mobile_number',
                'staff_members.photo',
                'designations.designation'
            )
            ->get();

        // Billing payment/progress boxes
        $chargeDepartments = DB::table('charge_departments')->orderBy('sort_order')->get();
        $billingBoxes = [];
        foreach ($chargeDepartments as $cd) {
            $billing = DB::table('billing_charges')
                ->where('module_id', $id)
                ->where('module', 'OPD')
                ->where('charge_department_id', $cd->id)
                ->selectRaw('SUM(total) as total, SUM(paid_amount) as paid_amount')
                ->first();
            $total = (int) ($billing->total ?? 0);
            $paid = (int) ($billing->paid_amount ?? 0);
            $percent = $total > 0 ? round(($paid / $total) * 100) : 0;
            $billingBoxes[] = (object) [
                'id' => $cd->id,
                'department_name' => $cd->department_name,
                'icon' => $cd->icon ?: 'fas fa-dollar-sign',
                'total' => $total,
                'paid' => $paid,
                'percent' => $percent,
            ];
        }

        // Medication summary (prescriptions with medicines)
        $hasPrescriptions = DB::table('opd_prescriptions')
            ->join('opd_medicines', 'opd_medicines.opd_prescription_id', '=', 'opd_prescriptions.id')
            ->where('opd_prescriptions.opd_id', $id)
            ->exists();

        $medicines = collect();
        if ($hasPrescriptions) {
            $rawMedicines = DB::table('opd_prescriptions')
                ->join('opd_medicines as om', 'om.opd_prescription_id', '=', 'opd_prescriptions.id')
                ->join('pharma_products as pp', 'om.pharma_product_id', '=', 'pp.id')
                ->leftJoin('medicine_frequency_types as ft', 'om.frequency_type_id', '=', 'ft.id')
                ->leftJoin('medicine_meal_relation as mr', 'om.meal_relation_id', '=', 'mr.id')
                ->leftJoin('opd_medicine_timing_selections as omts', 'om.id', '=', 'omts.opd_medicine_id')
                ->leftJoin('medicine_timing_of_day as t', 'omts.timing_id', '=', 't.id')
                ->where('opd_prescriptions.opd_id', $id)
                ->select(
                    'om.id',
                    'pp.product_name',
                    'om.dose_quantity',
                    'ft.display_text as frequency_display',
                    'mr.relation_name as meal_name',
                    'om.duration_days',
                    't.timing_name',
                    't.sort_order'
                )
                ->orderBy('om.id')
                ->get();

            $medicines = $rawMedicines->groupBy('id')->map(function ($group) {
                $first = $group->first();
                $timings = $group->sortBy('sort_order')->pluck('timing_name')->filter()->values()->implode(' + ');
                return (object) [
                    'id'                => $first->id,
                    'product_name'      => $first->product_name,
                    'dose_quantity'     => $first->dose_quantity,
                    'frequency_display' => $first->frequency_display,
                    'meal_name'         => $first->meal_name,
                    'duration_days'     => $first->duration_days,
                    'timing_names'      => $timings,
                ];
            })->values();
        }

        // Patient visit history (other visits for same patient)
        $otherOpds = DB::table('opds')
            ->join('staff_members', 'staff_members.id', '=', 'opds.consultant_doctor_id')
            ->where('opds.patient_id', $opd->patient_id)
            ->where('opds.id', '!=', $id)
            ->select('opds.id', 'opds.opd_date_time', 'staff_members.staff_name')
            ->orderByDesc('opds.id')
            ->get();

        $visitHistory = [];
        foreach ($otherOpds as $ov) {
            $formattedOpdDate = $ov->opd_date_time ? Carbon::parse($ov->opd_date_time)->format('d-m-Y h:i A') : '';
            $visitHistory[] = (object) [
                'id'          => $ov->id,
                'type'        => 'OPD',
                'label'       => 'OPD ' . $ov->id,
                'is_ipd'      => false,
                'date_time'   => $formattedOpdDate,
                'doctor_name' => $ov->staff_name,
            ];

            $linkedIpd = DB::table('ipds')
                ->join('opds', 'opds.id', '=', 'ipds.opd_id')
                ->join('staff_members', 'staff_members.id', '=', 'opds.consultant_doctor_id')
                ->where('ipds.opd_id', $ov->id)
                ->select('ipds.id', 'ipds.admission_date_time', 'staff_members.staff_name')
                ->first();

            if ($linkedIpd) {
                $formattedIpdDate = $linkedIpd->admission_date_time ? Carbon::parse($linkedIpd->admission_date_time)->format('d-m-Y h:i A') : '';
                $visitHistory[] = (object) [
                    'id'          => $linkedIpd->id,
                    'type'        => 'IPD',
                    'label'       => 'IPD OPD ' . $ov->id,
                    'is_ipd'      => true,
                    'date_time'   => $formattedIpdDate,
                    'doctor_name' => $linkedIpd->staff_name,
                ];
            }
        }

        return view('opd.overview', compact(
            'opd', 'patient', 'patient_id', 'save', 'opdDateTime',
            'bp_systolic', 'bp_diastolic', 'abdm_cc_badge', 'abdm_push_badge', 'abdmFullyLinked',
            'doctors', 'billingBoxes', 'medicines', 'visitHistory', 'ps'
        ));
    }

    public function updateOverview(Request $request, ?int $opdId = null): JsonResponse
    {
        $request = $this->validatedForm($request, UpdateOpdOverviewRequest::class);
        $id = $opdId ?? (int) ($request->input('id') ?: $request->input('opd_id'));
        $opd = Opd::findOrFail($id);

        $bpSystolic = $request->input('bp_systolic');
        $bpDiastolic = $request->input('bp_diastolic');
        $bpSystolicInt = ($bpSystolic !== null && $bpSystolic !== '') ? (int) $bpSystolic : 0;
        $bpDiastolicInt = ($bpDiastolic !== null && $bpDiastolic !== '') ? (int) $bpDiastolic : 0;

        $bp = '';
        if ($bpSystolicInt > 0 && $bpDiastolicInt > 0) {
            $bp = $bpSystolicInt . '/' . $bpDiastolicInt;
        } elseif ($bpSystolicInt > 0 || $bpDiastolicInt > 0) {
            $bp = ($bpSystolicInt > 0 ? $bpSystolicInt : '0') . '/' . ($bpDiastolicInt > 0 ? $bpDiastolicInt : '0');
        } elseif ($request->has('bp')) {
            $bp = (string) $request->input('bp');
        }

        $data = [
            'height'      => (string) $request->input('height', $opd->height ?? ''),
            'weight'      => (string) $request->input('weight', $opd->weight ?? ''),
            'bp'          => $bp,
            'pulse'       => (string) $request->input('pulse', $opd->pulse ?? ''),
            'temperature' => (string) $request->input('temperature', $opd->temperature ?? ''),
            'respiration' => (string) $request->input('respiration', $opd->respiration ?? ''),
            'spo2'        => (string) $request->input('spo2', $opd->spo2 ?? ''),
            'symptoms'    => (string) $request->input('symptoms', $opd->symptoms ?? ''),
            'remarks'     => (string) $request->input('remarks', $opd->remarks ?? ''),
        ];

        if ($request->has('consultant_doctor_id')) {
            $data['consultant_doctor_id'] = (int) $request->input('consultant_doctor_id');
        }

        $opd->update($data);

        return response()->json([
            'opd_id'  => $id,
            'success' => true,
            'message' => 'OPD overview updated successfully.',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->updateOverview($request, $id);
    }

    public function addDoctor(Request $request): JsonResponse
    {
        $form = $this->validatedForm($request, AddOpdDoctorRequest::class);
        $id = (int) $form->validated('opd_id');
        $doctorId = (int) $form->validated('doctor_id');

        $userId = (int) CurrentUser::id();

        $opdDoctorId = DB::table('opd_doctors')->insertGetId([
            'doctor_id'  => $doctorId,
            'opd_id'     => $id,
            'created_on' => now()->toDateTimeString(),
            'user_id'    => $userId,
        ]);

        return response()->json([
            'opd_doctor_id' => $opdDoctorId,
            'success'       => true,
            'message'       => 'Doctor added successfully.',
        ]);
    }

    public function removeDoctor(int $id): JsonResponse
    {
        if ($id <= 0) {
            return response()->json(['error' => 'Invalid ID', 'success' => false], 422);
        }

        DB::table('opd_doctors')->where('id', $id)->delete();

        return response()->json([
            'result'  => 'OK',
            'success' => true,
            'message' => 'Doctor removed successfully.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Prescriptions
    // ─────────────────────────────────────────────

    public function prescriptions(int $opdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_opd_prescription'), 403);
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $prescriptions = OpdPrescription::with(['doctor', 'medicines.product', 'medicines.timings.timing'])
            ->where('opd_id', $opdId)->orderByDesc('prescription_date_time')->get();

        $doctors = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);
        $medicines = PharmaProduct::where('is_active', 1)->with(['doseUnit', 'route'])->orderBy('product_name')->get();
        $frequencies = MedicineFrequencyType::where('is_active', 1)->orderBy('sort_order')->get();
        $meals = MedicineMealRelation::where('is_active', 1)->orderBy('sort_order')->get();
        $timings = MedicineTimingOfDay::where('is_active', 1)->orderBy('sort_order')->get();
        $conditions = MedicalCondition::where('is_active', 1)->orderBy('condition_name')->get();

        $pathologyDeptId = IdFieldSetting::first()?->pathology_department_id ?? 3;
        $radiologyDeptId = IdFieldSetting::first()?->radiology_department_id ?? 4;

        $pathologyCharges = Charge::where('charge_department_id', $pathologyDeptId)->orderBy('charge_name')->get();
        $radiologyCharges = Charge::where('charge_department_id', $radiologyDeptId)->orderBy('charge_name')->get();
        $procedureCharges = Charge::whereHas('chargeDepartment', function($q) {
            $q->where('category', 'Procedure');
        })->orderBy('charge_name')->get();

        return view('opd.prescriptions', compact(
            'opd', 'prescriptions', 'doctors', 'medicines', 'frequencies',
            'meals', 'timings', 'conditions', 'pathologyCharges', 'radiologyCharges',
            'procedureCharges', 'ps'
        ));
    }

    public function storePrescription(Request $request, ?int $opdId = null): JsonResponse
    {
        $request = $this->validatedForm($request, SaveOpdPrescriptionRequest::class);
        $opdId = $opdId ?: (int) $request->input('opd_id');

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

        $rx = OpdPrescription::create([
            'prescription_date_time' => $parsedDateTime,
            'opd_id'                 => $opdId,
            'doctor_id'              => (int) $request->input('doctor_id'),
            'pathology_tests'        => $pathologyTests,
            'radiology_tests'        => $radiologyTests,
            'procedures'             => $procedures,
            'findings'               => $request->input('findings') ?? '',
            'follow_up_detail'       => $request->input('follow_up_detail') ?? '',
        ]);

        $this->savePrescriptionMedicines($request, $rx->id, $opdId);

        return response()->json([
            'success'             => true,
            'message'             => 'Prescription saved successfully.',
            'opd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
    }

    public function destroyPrescription(int $id): JsonResponse
    {
        $rx = OpdPrescription::findOrFail($id);
        $medIds = OpdMedicine::where('opd_prescription_id', $id)->pluck('id');
        OpdMedicineTimingSelection::whereIn('opd_medicine_id', $medIds)->delete();
        OpdMedicine::where('opd_prescription_id', $id)->delete();
        $rx->delete();

        return response()->json([
            'result'  => 'OK',
            'success' => true,
            'message' => 'Prescription deleted.',
        ]);
    }

    // ─────────────────────────────────────────────
    // Charges / Services
    // ─────────────────────────────────────────────

    public function charges(int $opdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_opd_charges'), 403);
        $opd = Opd::with(['patient.patientCategory', 'doctor'])->findOrFail($opdId);
        $patient_category_id = (int) ($opd->patient?->patient_category_id ?? 0);
        $charges = BillingCharge::with(['chargeDepartment', 'payments', 'details.charge'])
            ->where('module', 'OPD')
            ->where('module_id', $opdId)
            ->orderByDesc('id')
            ->get();
        $chargeDepts = ChargeDepartment::where('active', 'Yes')->orderBy('department_name')->get();
        $prescriptions = OpdPrescription::where('opd_id', $opdId)->orderByDesc('id')->get();

        return view('opd.charges', compact('opd', 'charges', 'chargeDepts', 'prescriptions', 'patient_category_id', 'ps'));
    }

    public function storeCharge(Request $request, ?int $opdId = null): JsonResponse
    {
        $request = $this->validatedForm($request, SaveOpdChargeRequest::class);
        $opdId = $opdId ?: (int) $request->input('opd_id');
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
        $addon_discount_amount_total = (float) $request->input('opd_charges_addon_discount', $request->input('addon_discount_amount', 0));
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
                'amount'   => $g_amount,
                'discount' => $g_discount,
                'subtotal' => $g_subtotal,
            ];
            $overall_subtotal += $g_subtotal;
        }

        $dept_ids = array_keys($group_data);
        $dept_count = count($dept_ids);
        $first_opd_charge_id = 0;
        $created_opd_charge_ids = [];

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
                    'module'                => 'OPD',
                    'module_id'             => $opdId,
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
                $created_opd_charge_ids[] = $bc->id;
                if ($first_opd_charge_id === 0) {
                    $first_opd_charge_id = $bc->id;
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

                $opd_service_id = 0;
                if ($is_service) {
                    $svc = OpdService::create([
                        'service_date_time'    => $charge_date_time,
                        'service_title'        => $department_name . ' ' . $bc->id,
                        'opd_id'               => $opdId,
                        'opd_charge_id'        => $bc->id,
                        'charge_department_id' => $charge_dept_id,
                        'status'               => 'New',
                        'created_on'           => now()->toDateTimeString(),
                        'user_id'              => CurrentUser::id(),
                    ]);
                    $opd_service_id = $svc->id;
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

                    if ($opd_service_id > 0) {
                        OpdServiceDetail::create([
                            'opd_service_id'        => $opd_service_id,
                            'opd_charge_detail_id'  => $bcd->id,
                            'charge_id'             => $cId,
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json([
                'success'        => true,
                'message'        => 'Charge saved.',
                'opd_charge_id'  => $first_opd_charge_id,
                'opd_charge_ids' => $created_opd_charge_ids,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function getCharge(int $id): JsonResponse
    {
        $charge = BillingCharge::forModule('OPD')->with(['chargeDepartment', 'details.charge'])->findOrFail($id);
        $table_html = '';
        $i = 0;
        foreach ($charge->details as $detail) {
            $i++;
            $cName = e($detail->charge?->charge_name ?? '');
            $table_html .= '<tr id="chr_' . $i . '"><td><a href="javascript:void(0)" onclick="removeEOPDChargeDetail(' . $i . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>' . $cName . '<input type="hidden" name="charge_id_value[]" value="' . $detail->charge_id . '"></td><td>' . $detail->amount . '<input type="hidden" name="amount_value[]" value="' . $detail->amount . '"></td><td>' . $detail->discount_percentage . '<input type="hidden" name="discount_percentage_value[]" value="' . $detail->discount_percentage . '"></td><td>' . $detail->discount_amount . '<input type="hidden" name="discount_amount_value[]" value="' . $detail->discount_amount . '"></td><td>' . $detail->quantity . '<input type="hidden" name="quantity_value[]" value="' . $detail->quantity . '"></td><td>' . $detail->total . '<input type="hidden" name="total_value[]" value="' . $detail->total . '"></td></tr>';
        }

        $pathologyDeptId = IdFieldSetting::first()?->pathology_department_id ?? 3;
        $radiologyDeptId = IdFieldSetting::first()?->radiology_department_id ?? 4;

        $edit = 'Yes';
        $svc = OpdService::whereIn('charge_department_id', [$pathologyDeptId, $radiologyDeptId])
            ->where('opd_charge_id', $id)
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
        BillingCharge::forModule('OPD')->findOrFail($id);
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

    public function updateCharge(Request $request, int $id): JsonResponse
    {
        $request = $this->validatedForm($request, SaveOpdChargeRequest::class);
        $charge = BillingCharge::forModule('OPD')->findOrFail($id);
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
                'amount'                => (float) $request->input('opd_charges_amount', $request->input('amount', $charge->amount)),
                'discount'              => (float) $request->input('opd_charges_discount', $request->input('discount', $charge->discount)),
                'addon_discount_mode'   => $request->input('addon_discount_mode', $charge->addon_discount_mode),
                'addon_discount_value'  => (float) $request->input('addon_discount_value', $charge->addon_discount_value),
                'addon_discount_amount' => (float) $request->input('opd_charges_addon_discount', $charge->addon_discount_amount),
                'total'                 => (float) $request->input('opd_charges_total', $request->input('total', $charge->total)),
                'paid_amount'           => (float) $request->input('paid_amount', $charge->paid_amount),
                'payment_mode'          => (string) $request->input('payment_mode', $charge->payment_mode),
                'user_id'               => CurrentUser::id(),
            ]);

            BillingChargeDetail::where('module_charge_id', $id)->delete();

            $oldSvc = OpdService::where('opd_charge_id', $id)->first();
            if ($oldSvc) {
                OpdServiceDetail::where('opd_service_id', $oldSvc->id)->delete();
                $oldSvc->delete();
            }

            $newSvcId = 0;
            if ($is_service) {
                $newSvc = OpdService::create([
                    'service_date_time'    => $charge_date_time,
                    'service_title'        => $charge->charge_title,
                    'opd_id'               => $charge->module_id,
                    'opd_charge_id'        => $id,
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
                    OpdServiceDetail::create([
                        'opd_service_id'       => $newSvcId,
                        'opd_charge_detail_id' => $bcd->id,
                        'charge_id'            => (int) $chargeIds[$i],
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'opd_charge_id' => $id, 'message' => 'Saved Successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function destroyCharge(int $id): JsonResponse
    {
        BillingCharge::forModule('OPD')->findOrFail($id);
        DB::beginTransaction();
        try {
            BillingChargeDetail::where('module_charge_id', $id)->delete();
            BillingChargePayment::where('billing_charge_id', $id)->delete();
            $svc = OpdService::where('opd_charge_id', $id)->first();
            if ($svc) {
                OpdServiceDetail::where('opd_service_id', $svc->id)->delete();
                if (\Illuminate\Support\Facades\Schema::hasTable('opd_lab_investigation_notes')) {
                    OpdLabInvestigationNote::where('opd_service_id', $svc->id)->delete();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('opd_procedure_notes')) {
                    OpdProcedureNote::where('opd_service_id', $svc->id)->delete();
                }
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

    public function collectPayments(Request $request, ?int $opdId = null): JsonResponse
    {
        $request = $this->validatedForm($request, CollectOpdPaymentsRequest::class);
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
                'amount'       => (float) ($spAmounts[$i] ?? 0),
                'payment_mode' => $spModes[$i],
            ];
        }

        $billing_charges = BillingCharge::whereIn('id', $cb)->get();
        $used_amounts = [
            'Cash'        => 0.0,
            'UPI'         => 0.0,
            'Debit Card'  => 0.0,
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
            return response()->json(['result' => 'OK', 'success' => true, 'message' => 'Saved Successfully']);
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

    public function getPrescriptionDetails(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $department = (string) $request->input('department', 'OPD');
        if ($department === 'IPD') {
            $rx = \App\Models\IpdPrescription::find($id);
        } elseif ($department === 'DCD') {
            $rx = DB::table('dcd_prescriptions')->where('id', $id)->first();
        } else {
            $rx = OpdPrescription::find($id);
        }

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
     * (saved on this visit's bills, or queued in the modal's table: `exclude[]`) are not offered - see
     * ChargeLookupService::prescriptionChargesHtml().
     */
    public function getPrescriptionTD(Request $request, ChargeLookupService $lookup): JsonResponse
    {
        return response()->json(['html' => $lookup->prescriptionChargesHtml(
            (string) $request->input('type', ''),
            (string) $request->input('values', ''),
            'OPD',
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


    // ─────────────────────────────────────────────
    // Lab Investigations
    // ─────────────────────────────────────────────

    public function labInvestigations(int $opdId, PermissionService $ps, \App\Services\LabResultService $labs)
    {
        abort_unless($ps->hasPermission('im_opd_lab_investigations'), 403);
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        // Lab + Radiology services (legacy opd-lab-investigations.php); result entry lives in LabResultController
        $services = $labs->investigations('opd', $opdId);

        return view('opd.lab-investigations', compact('opd', 'services', 'ps'));
    }

    /** Lab conclusion only (the result-entry page saves it together with the values). */
    public function storeLabNote(Request $request, int $serviceId, PermissionService $ps, \App\Services\LabResultService $labs): JsonResponse
    {
        abort_unless($ps->hasPermission('im_opd_lab_investigations'), 403);
        $validated = $request->validate(['note' => 'nullable|string|max:65000']);
        $labs->service('opd', $serviceId);
        $labs->saveConclusion('opd', $serviceId, $validated['note'] ?? '', (int) CurrentUser::id());
        return response()->json(['success' => true, 'message' => 'Lab note saved.']);
    }

    // ─────────────────────────────────────────────
    // Procedures
    // ─────────────────────────────────────────────

    public function procedures(int $opdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_opd_procedures'), 403);
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $services = OpdService::with(['details.charge', 'procedureNote', 'chargeDepartment'])
            ->where('opd_id', $opdId)
            ->whereHas('chargeDepartment', fn($q) => $q->whereRaw('LOWER(category) = ?', ['procedure']))
            ->orderBy('id')
            ->get();

        return view('opd.procedures', compact('opd', 'services', 'ps'));
    }

    public function getProcedureNote(int $serviceId): JsonResponse
    {
        $note = OpdProcedureNote::where('opd_service_id', $serviceId)->value('note');
        return response()->json([
            'success' => true,
            'note'    => $note ?? '',
        ]);
    }

    public function storeProcedureNote(Request $request): JsonResponse
    {
        $form = $this->validatedForm($request, StoreOpdProcedureNoteRequest::class);
        $svcId = (int) $form->validated('opd_service_id');
        $note = (string) ($form->validated('note') ?? '');
        $userId = CurrentUser::id() ?: 1;

        OpdProcedureNote::updateOrCreate(
            ['opd_service_id' => $svcId],
            [
                'note'       => $note,
                'updated_on' => now()->toDateTimeString(),
                'user_id'    => $userId,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Saved Successfully.']);
    }

    // ─────────────────────────────────────────────
    // Visit History (past OPDs for this patient)
    // ─────────────────────────────────────────────

    public function visits(int $opdId, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_opd_history') && $ps->hasPermission('b_opd_view_history_detail'), 403);
        $opd = Opd::with(['patient', 'doctor'])->findOrFail($opdId);
        $patientId = (int) ($opd->patient_id ?? 0);

        // All OTHER OPD visits for this patient (excluding the current visit)
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

            $linkedIpd = \App\Models\Ipd::with('doctor')->where('opd_id', $otherOpd->id)->first();
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

        $history = $otherOpds;

        return view('opd.visits', compact('opd', 'patientId', 'visits', 'history', 'ps'));
    }

    // ─────────────────────────────────────────────
    // Autocomplete helpers
    // ─────────────────────────────────────────────

    // ─────────────────────────────────────────────
    // Prescription GET/UPDATE (for Edit modal)
    // ─────────────────────────────────────────────

    public function getPrescription(int $rxId): JsonResponse
    {
        $rx = OpdPrescription::with([
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

            $tableHtml .= '<tr id="epmr_'.$nor.'">
                <td><a href="javascript:void(0)" onclick="removeEPrescriptionMedicine('.$nor.')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>
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
            'follow_up_detail' => $rx->follow_up_detail ?? '',
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
        $rxId = $rxId ?: (int) $request->input('opd_prescription_id');
        $rx = OpdPrescription::findOrFail($rxId);

        $request = $this->validatedForm($request, SaveOpdPrescriptionRequest::class);

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
            'follow_up_detail'       => $request->input('follow_up_detail') ?? '',
        ]);

        // Delete old medicines and their timing selections
        $medIds = OpdMedicine::where('opd_prescription_id', $rxId)->pluck('id');
        OpdMedicineTimingSelection::whereIn('opd_medicine_id', $medIds)->delete();
        OpdMedicine::where('opd_prescription_id', $rxId)->delete();

        $this->savePrescriptionMedicines($request, $rxId, $rx->opd_id);

        return response()->json([
            'success'             => true,
            'message'             => 'Prescription updated successfully.',
            'opd_prescription_id' => $rx->id,
            'id'                  => $rx->id,
        ]);
    }

    private function savePrescriptionMedicines(Request $request, int $prescriptionId, int $opdId): void
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
                $med = OpdMedicine::create([
                    'opd_prescription_id'  => $prescriptionId,
                    'opd_id'               => $opdId,
                    'pharma_product_id'    => $pId,
                    'dose_quantity'        => !empty($doseQuantities[$i]) ? (float) $doseQuantities[$i] : 1.0,
                    'frequency_type_id'    => !empty($frequencyIds[$i]) ? (int) $frequencyIds[$i] : 1,
                    'meal_relation_id'     => (!empty($mealIds[$i]) && $mealIds[$i] !== '') ? (int) $mealIds[$i] : null,
                    'duration_days'        => !empty($durationDays[$i]) ? (int) $durationDays[$i] : 1,
                    'medical_condition_id' => (!empty($conditionIds[$i]) && $conditionIds[$i] !== '') ? (int) $conditionIds[$i] : null,
                    'remarks'              => '',
                    'created_on'           => now()->toDateTimeString(),
                    'updated_on'           => now()->toDateTimeString(),
                ]);

                $timingKey = 'timing_ids_' . $rowNum;
                $timingIds = $request->input($timingKey, []);
                if (is_array($timingIds)) {
                    foreach ($timingIds as $timingId) {
                        if (!empty($timingId)) {
                            OpdMedicineTimingSelection::create([
                                'opd_medicine_id' => $med->id,
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

                $med = OpdMedicine::create([
                    'opd_prescription_id'  => $prescriptionId,
                    'opd_id'               => $opdId,
                    'pharma_product_id'    => $pId,
                    'dose_quantity'        => isset($m['dose_quantity']) ? (float) $m['dose_quantity'] : 1.0,
                    'frequency_type_id'    => isset($m['frequency_type_id']) ? (int) $m['frequency_type_id'] : 1,
                    'meal_relation_id'     => !empty($m['meal_relation_id']) ? (int) $m['meal_relation_id'] : null,
                    'duration_days'        => isset($m['duration_days']) ? (int) $m['duration_days'] : 1,
                    'medical_condition_id' => !empty($m['medical_condition_id']) ? (int) $m['medical_condition_id'] : null,
                    'remarks'              => $m['remarks'] ?? '',
                    'created_on'           => now()->toDateTimeString(),
                    'updated_on'           => now()->toDateTimeString(),
                ]);
                if (!empty($m['timing_ids']) && is_array($m['timing_ids'])) {
                    foreach ($m['timing_ids'] as $timingId) {
                        if (!empty($timingId)) {
                            OpdMedicineTimingSelection::create([
                                'opd_medicine_id' => $med->id,
                                'timing_id'       => (int) $timingId,
                                'created_on'      => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    /** Select2 source of every active staff member (consultant / prescribing doctor pickers). */
    public function dropdownDoctors(Request $request): JsonResponse
    {
        $q = (string) $request->input('q', '');
        $query = StaffMember::where('active', 'Yes');
        if ($q !== '') {
            $query->where('staff_name', 'like', "%{$q}%");
        }

        return response()->json(
            $query->orderBy('staff_name')->get(['id', 'staff_name'])->map(fn ($r) => ['id' => $r->id, 'text' => $r->staff_name])
        );
    }

    public function patientAutocomplete(Request $request): JsonResponse
    {
        // Legacy dropdown_patients: match on id / name / mobile, never offer an already-admitted patient,
        // and with no search term list the 50 most recent patients.
        $search = trim((string) $request->input('q', ''));
        $uhidId = Uhid::parse($search);   // a typed UHID (e.g. BMH/26/0000014) finds that patient

        $patients = Patient::where('status', '!=', 'Admitted')
            ->when($search !== '', fn($q) => $q->where(fn($sq) => $sq
                ->where('id', 'LIKE', "%{$search}%")
                ->orWhere('patient_name', 'LIKE', "%{$search}%")
                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                ->when($uhidId !== null, fn($u) => $u->orWhere('id', $uhidId))))
            ->when($search !== '', fn($q) => $q->orderBy('patient_name'), fn($q) => $q->orderByDesc('id'))
            ->limit(50)->get(['id', 'patient_name', 'mobile_number']);

        return response()->json([
            'results' => $patients->map(fn($p) => [
                'id'   => $p->id,
                'text' => $p->patient_name . ' ' . $p->mobile_number . ' ' . $p->id,
            ]),
        ]);
    }
}
