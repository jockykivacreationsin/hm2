<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffMemberRequest;
use App\Models\StaffMember;
use App\Services\PermissionService;
use App\Services\SettingsService;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\CurrentUser;

/**
 * Staff module: members, attendance (+ calendar/report/chart), roster (calendar/dates/staff-wise) and salary.
 * Ports of bmhabdm/staff-*.php, roster-staff-wise.php and their ajax.php handlers; logic lives in StaffService.
 */
class StaffController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private StaffService $staff, private PermissionService $ps)
    {
    }

    private function authorizeModule(): void
    {
        abort_unless($this->ps->hasPermission('m_staff'), 403);
    }

    // ─────────────────────────────────────────────
    // Staff members (staff-members.php)
    // ─────────────────────────────────────────────

    public function index(Request $request)
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.index', [
                'canView' => false,
            ]);
        }

        $page = max(1, (int) $request->query('page', 1));
        [$members, $total] = $this->staff->memberPage($page, self::PER_PAGE);

        return view('staff.index', [
            'canView'    => true,
            'members'    => $members,
            'page'       => $page,
            'perPage'    => self::PER_PAGE,
            'total'      => $total,
            'totalPages' => (int) ceil($total / self::PER_PAGE),
            'canNew'     => $this->ps->hasPermission('b_staff_new'),
            'canEdit'    => $this->ps->hasPermission('b_staff_edit'),
            'canDelete'  => $this->ps->hasPermission('b_staff_delete'),
        ]);
    }

    /** select2 sources (departments, designations, specialities, user-roles, months); search term is `q` or `searchTerm`. */
    public function dropdown(Request $request, string $type): JsonResponse
    {
        $this->authorizeModule();
        $search = (string) ($request->query('searchTerm') ?: $request->query('q', ''));

        return response()->json($this->staff->dropdown($type, $search));
    }

    /** Edit-modal payload (legacy getstaffmember). */
    public function show(int $id): JsonResponse
    {
        abort_unless($this->ps->hasPermission('b_staff_edit'), 403);

        return response()->json($this->staff->memberDetail($id) ?? []);
    }

    public function store(StaffMemberRequest $request): JsonResponse
    {
        $member = $this->staff->createMember($request->validated());

        return response()->json(['staff_member_id' => $member->id]);
    }

    public function update(StaffMemberRequest $request, int $id): JsonResponse
    {
        $member = StaffMember::findOrFail($id);
        $this->staff->updateMember($member, $request->validated());

        return response()->json(['staff_member_id' => $member->id]);
    }

    public function destroy(int $id): JsonResponse
    {
        abort_unless($this->ps->hasPermission('b_staff_delete'), 403);
        $this->staff->deactivateMember($id);

        return response()->json(['result' => 'OK']);
    }

    // ─────────────────────────────────────────────
    // Attendance (staff-attendance.php)
    // ─────────────────────────────────────────────

    public function attendance()
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.attendance', [
                'canView' => false,
            ]);
        }

        return view('staff.attendance', [
            'canView' => true,
            'staff'   => $this->staff->attendanceSheetStaff(),
        ]);
    }

    /** Legacy get_attendance. */
    public function attendanceData(Request $request): JsonResponse
    {
        $this->authorizeModule();
        $request->validate(['attendance_date' => ['required', 'string']]);

        return response()->json($this->staff->attendanceForDate($request->input('attendance_date')));
    }

    /** Legacy check_attendance_exists. */
    public function attendanceExists(Request $request): JsonResponse
    {
        $this->authorizeModule();
        $request->validate(['attendance_date' => ['required', 'string']]);

        return response()->json(['exists' => $this->staff->attendanceExists($request->input('attendance_date'))]);
    }

    /** Legacy save_staff_attendance (parallel arrays staff_id[] status[] check_in_time[] ...). */
    public function storeAttendance(Request $request): JsonResponse
    {
        $this->authorizeModule();

        $v = $request->validate([
            'attendance_date'   => ['required', 'date_format:d-m-Y'],
            'staff_id'          => ['required', 'array', 'min:1'],
            'staff_id.*'        => ['required', 'integer', 'exists:staff_members,id'],
            'status'            => ['required', 'array'],
            'status.*'          => ['required', Rule::in(StaffService::ATTENDANCE_STATUSES)],
            'check_in_time'     => ['nullable', 'array'],
            'check_in_time.*'   => ['nullable', 'string', 'max:20'],
            'check_out_time'    => ['nullable', 'array'],
            'check_out_time.*'  => ['nullable', 'string', 'max:20'],
            'remarks'           => ['nullable', 'array'],
            'remarks.*'         => ['nullable', 'string', 'max:255'],
        ]);

        $rows = [];
        foreach ($v['staff_id'] as $i => $staffId) {
            $row = [
                'staff_id'       => (int) $staffId,
                'status'         => $v['status'][$i] ?? 'Absent',
                'check_in_time'  => $v['check_in_time'][$i] ?? null,
                'check_out_time' => $v['check_out_time'][$i] ?? null,
                'remarks'        => $v['remarks'][$i] ?? '',
            ];
            if ($row['status'] === 'Present' && trim((string) $row['check_in_time']) === '') {
                throw ValidationException::withMessages([
                    'check_in_time' => 'Check-in time is required for Present staff (row ' . ($i + 1) . ')',
                ]);
            }
            $rows[] = $row;
        }

        $userId = (int) ($request->input('user_id') ?: CurrentUser::id());
        $response = $this->staff->saveAttendance($v['attendance_date'], $rows, $userId);

        return response()->json(['response' => $response]);
    }

    /** staff-attendance-calendar.php */
    public function attendanceCalendar(Request $request)
    {
        $this->authorizeModule();
        [$month, $year] = $this->staff->resolveMonth($request);

        return view('staff.attendance-calendar', [
            'month'      => $month,
            'year'       => $year,
            'days'       => cal_days_in_month(CAL_GREGORIAN, $month, $year),
            'monthName'  => date('F', mktime(0, 0, 0, $month, 10)),
            'employees'  => $this->staff->calendarStaff(),
            'attendance' => $this->staff->attendanceCalendar($month, $year),
        ]);
    }

    /** staff-attendance-report.php */
    public function attendanceReport()
    {
        $this->authorizeModule();

        return view('staff.attendance-report', ['months' => $this->staff->dropdown('months')]);
    }

    /** Legacy staff_attendance_report (DataTables server side). */
    public function attendanceReportData(Request $request): JsonResponse
    {
        $this->authorizeModule();

        return response()->json($this->staff->attendanceReport($request));
    }

    /** staff-attendance-report-cv.php */
    public function attendanceChartView()
    {
        $this->authorizeModule();

        return view('staff.attendance-chart', ['months' => $this->staff->dropdown('months')]);
    }

    /** Legacy staff_attendance_chart. */
    public function attendanceChartData(Request $request): JsonResponse
    {
        $this->authorizeModule();

        return response()->json(['data' => $this->staff->attendanceChart($request)]);
    }

    // ─────────────────────────────────────────────
    // Roster (staff-roster-calendar.php, staff-roster-dates.php, roster-staff-wise.php)
    // ─────────────────────────────────────────────

    public function rosterCalendar(Request $request, SettingsService $settings)
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.roster-calendar', [
                'canView' => false,
            ]);
        }

        [$month, $year] = $this->staff->resolveMonth($request);

        $doctorDesignationId = $settings->getIdField('doctor_designation_id') ?: $this->staff->rosterDesignationId('Doctor');
        $nurseDesignationId  = $settings->getIdField('nurse_designation_id') ?: $this->staff->rosterDesignationId('Nurse');

        return view('staff.roster-calendar', [
            'canView'             => true,
            'month'               => $month,
            'year'                => $year,
            'days'                => cal_days_in_month(CAL_GREGORIAN, $month, $year),
            'monthName'           => date('F', mktime(0, 0, 0, $month, 10)),
            'grid'                => $this->staff->rosterGrid($month, $year),
            'doctorDesignationId' => $doctorDesignationId,
            'nurseDesignationId'  => $nurseDesignationId,
        ]);
    }

    /** Legacy get_staff_for_roster. */
    public function rosterStaffOptions(Request $request): JsonResponse
    {
        $this->authorizeModule();
        $designationId = (int) $request->input('designation_id', 0);

        return response()->json(['s' => $this->staff->rosterStaffOptions($designationId)]);
    }

    /** Legacy add_roster_staff. */
    public function addRoster(Request $request): JsonResponse
    {
        $this->authorizeModule();

        $v = $request->validate([
            'roster_date'    => ['required', function ($attribute, $value, $fail) {
                if (!strtotime($value)) {
                    $fail('The ' . $attribute . ' is not a valid date.');
                }
            }],
            'staff_id'       => ['required', 'integer', 'exists:staff_members,id'],
            'designation'    => ['required', Rule::in(StaffService::ROSTER_DESIGNATIONS)],
            'shift'          => ['required', Rule::in(StaffService::ROSTER_SHIFTS)],
            'date_number'    => ['required', 'integer', 'min:1', 'max:62'],
            'designation_id' => ['nullable', 'integer'],
        ]);

        $this->assertStaffMatchesDesignation((int) $v['staff_id'], $v['designation']);

        return response()->json($this->staff->addRoster(
            $v['roster_date'],
            (int) $v['staff_id'],
            $v['designation'],
            $v['shift'],
            (int) $v['date_number'],
            isset($v['designation_id']) ? (int) $v['designation_id'] : null
        ));
    }

    /** Legacy remove_roster_staff. */
    public function removeRoster(Request $request, ?int $id = null): JsonResponse
    {
        $this->authorizeModule();
        $targetId = $id ?: (int) $request->input('id');
        $this->staff->removeRoster($targetId);

        return response()->json(['result' => 'OK']);
    }

    /** Legacy get_replace_staff_for_roster. */
    public function rosterReplaceOptions(Request $request, ?int $id = null): JsonResponse
    {
        $this->authorizeModule();
        $targetId = $id ?: (int) $request->input('id');
        $designationId = (int) $request->input('designation_id', 0);
        $roster = \App\Models\Roster::find($targetId);
        $excludeStaffId = $roster ? (int) $roster->staff_id : null;

        return response()->json(['s' => $this->staff->rosterStaffOptions($designationId, $excludeStaffId)]);
    }

    /** Legacy replace_roster_staff. */
    public function replaceRoster(Request $request, ?int $id = null): JsonResponse
    {
        $this->authorizeModule();
        $targetId = $id ?: (int) $request->input('roster_id', $request->input('id'));
        $v = $request->validate(['staff_id' => ['required', 'integer', 'exists:staff_members,id']]);

        $roster = \App\Models\Roster::findOrFail($targetId);
        $this->assertStaffMatchesDesignation((int) $v['staff_id'], $roster->designation);

        return response()->json($this->staff->replaceRoster($targetId, (int) $v['staff_id']));
    }

    /** A roster slot for "Doctor"/"Nurse" can only hold staff of the designation configured for it in Settings. */
    private function assertStaffMatchesDesignation(int $staffId, string $designation): void
    {
        $expected = $this->staff->rosterDesignationId($designation);
        if ($expected > 0 && (int) StaffMember::whereKey($staffId)->value('designation_id') !== $expected) {
            throw ValidationException::withMessages(['staff_id' => "Selected staff is not a $designation."]);
        }
    }

    public function rosterDates(Request $request, SettingsService $settings)
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.roster-dates', [
                'canView' => false,
            ]);
        }

        [$month, $year] = $this->staff->resolveMonth($request);

        $sections = [];
        foreach ([['Day Doctors', 'Doctor', 'Day'], ['Night Doctors', 'Doctor', 'Night'], ['Day Nurses', 'Nurse', 'Day'], ['Night Nurses', 'Nurse', 'Night']] as [$title, $designation, $shift]) {
            $sections[$title] = $this->staff->rosterDates($this->staff->rosterDesignationId($designation), $shift, $month, $year);
        }

        return view('staff.roster-dates', [
            'canView'   => true,
            'month'     => $month,
            'year'      => $year,
            'monthName' => date('F', mktime(0, 0, 0, $month, 10)),
            'sections'  => $sections,
        ]);
    }

    public function rosterStaffWise()
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.roster-staff-wise', [
                'canView' => false,
            ]);
        }

        return view('staff.roster-staff-wise', [
            'canView' => true,
        ]);
    }

    /** HTML of the staff-wise roster table (filters: month, year, designation, shift). */
    public function rosterStaffWiseData(Request $request)
    {
        $this->authorizeModule();
        $month = (int) $request->input('month', date('n'));
        $year  = (int) $request->input('year', date('Y'));
        $designation = (string) $request->input('designation', '');
        $shift = (string) $request->input('shift', '');

        return view('staff.partials.staff-wise-roster-table', [
            'rows' => $this->staff->staffWiseRoster($month, $year, $designation, $shift),
        ]);
    }

    // ─────────────────────────────────────────────
    // Salary (staff-salary.php, staff-salary-setting.php)
    // ─────────────────────────────────────────────

    public function salary()
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.salary', [
                'canView' => false,
            ]);
        }

        return view('staff.salary', [
            'canView' => true,
        ]);
    }

    /** Legacy get_staff_salary_list: returns the <tr> rows. */
    public function salaryList(Request $request)
    {
        $this->authorizeModule();
        $month = (int) ($request->input('month') ?: date('n'));
        $year  = (int) ($request->input('year') ?: date('Y'));

        return view('staff.partials.salary-rows', ['rows' => $this->staff->salaryList($month, $year)]);
    }

    /** Legacy save_staff_salary_payment. */
    public function storeSalaryPayment(Request $request): JsonResponse
    {
        $this->authorizeModule();
        $v = $request->validate([
            'staff_id'         => ['required', 'integer', 'exists:staff_members,id'],
            'salary_date_time' => ['required', 'string'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'payment_mode'     => ['required', Rule::in(['Cash', 'Netbanking'])],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->staff->saveSalaryPayment($v);

        return response()->json(['staff_id' => (int) $v['staff_id'], 'staff_payment_id' => $payment->id]);
    }

    /** Legacy getsalarypaymentdetail. */
    public function salaryPaymentDetail(int $id): JsonResponse
    {
        $this->authorizeModule();
        if ($id <= 0) {
            return response()->json(['html' => '<p>Invalid ID</p>']);
        }
        $detail = $this->staff->salaryPaymentDetail($id);

        return response()->json([
            'html' => $detail
                ? view('staff.partials.salary-payment-detail', $detail)->render()
                : '<p>No data found</p>',
        ]);
    }

    public function salarySettings()
    {
        $canView = $this->ps->hasPermission('m_staff');
        if (! $canView) {
            return view('staff.salary-settings', [
                'canView' => false,
            ]);
        }

        return view('staff.salary-settings', [
            'canView'   => true,
            'rows'      => $this->staff->salarySettingRows(),
            'canNew'    => $this->ps->hasPermission('b_staff_new'),
            'canEdit'   => $this->ps->hasPermission('b_staff_edit'),
            'canDelete' => $this->ps->hasPermission('b_staff_delete'),
        ]);
    }

    /** Legacy getcurrentstaffsalary. */
    public function currentSalary(int $id): JsonResponse
    {
        $this->authorizeModule();

        return response()->json(['current_salary' => (int) StaffMember::whereKey($id)->value('current_salary')]);
    }

    /** Legacy update_staff_salary. */
    public function storeSalaryAppraisal(Request $request): JsonResponse
    {
        $this->authorizeModule();
        $v = $request->validate([
            'staff_member_id' => ['required', 'integer', 'exists:staff_members,id'],
            'appraisal_date'  => ['required', 'string'],
            'new_salary'      => ['required', 'integer', 'min:0'],
            'remarks'         => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->staff->saveAppraisal(
            (int) $v['staff_member_id'], $v['appraisal_date'], (int) $v['new_salary'], $v['remarks'] ?? '', (int) CurrentUser::id()
        ));
    }

    /** Legacy staffappraisaldetail. */
    public function appraisalDetail(int $id): JsonResponse
    {
        $this->authorizeModule();
        $rows = $this->staff->appraisals($id);

        return response()->json([
            'nor'  => $rows->count(),
            'html' => $rows->isEmpty() ? '' : view('staff.partials.appraisal-detail', ['rows' => $rows])->render(),
        ]);
    }
}
