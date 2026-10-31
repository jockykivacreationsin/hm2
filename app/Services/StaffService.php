<?php

namespace App\Services;

use App\Models\Roster;
use App\Models\StaffAttendance;
use App\Models\StaffMember;
use App\Models\StaffSalaryAppraisal;
use App\Models\StaffSalaryPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Business logic of the Staff module (legacy staff-members / staff-attendance / staff-roster-* /
 * staff-salary* pages and their ajax.php handlers), kept out of StaffController.
 */
class StaffService
{
    public const ATTENDANCE_STATUSES = ['Absent', 'Present', 'Off', 'Halfday', 'Leave', 'Holiday'];
    public const ROSTER_DESIGNATIONS = ['Doctor', 'Nurse'];
    public const ROSTER_SHIFTS       = ['Day', 'Night'];

    // select2 lookups used by the staff modals / report filters: type => [table, text column]
    private const DROPDOWNS = [
        'departments'  => ['departments', 'department'],
        'designations' => ['designations', 'designation'],
        'specialities' => ['specialities', 'speciality'],
        'user-roles'   => ['user_roles', 'role'],
        'months'       => ['months', 'month_name'],
    ];

    public function __construct(private SettingsService $settings)
    {
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /** Legacy pages post d-m-Y (flatpickr) and parse with strtotime(); null when blank/invalid. */
    public static function parseDate(?string $value, string $format = 'Y-m-d'): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date($format, $ts);
    }

    /** "9:05 AM" (timepicker) → "09:05:00"; null when blank/invalid. */
    private function parseTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : date('H:i:s', $ts);
    }

    /** Month navigation exactly like the legacy calendars: ?month=0 / 13 roll over into the neighbouring year. */
    public function resolveMonth(Request $request): array
    {
        $month = (int) $request->input('month', date('n'));
        $year  = (int) $request->input('year', date('Y'));
        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }
        $year = max(2000, min(2100, $year));

        return [$month, $year];
    }

    /** Legacy getHexColorFromID(): stable pill colour per staff member. */
    public static function rosterColor(int $id): array
    {
        $r = ($id * 123) % 256;
        $g = ($id * 321) % 256;
        $b = ($id * 213) % 256;
        $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return ['background' => "rgb($r, $g, $b)", 'text' => $luminance > 150 ? 'black' : 'white'];
    }

    /** Settings → designation id for "Doctor" / "Nurse" (id_field_settings). */
    public function rosterDesignationId(string $designation): int
    {
        $id = $this->settings->getIdField($designation === 'Nurse' ? 'nurse_designation_id' : 'doctor_designation_id');
        if ($id > 0) {
            return $id;
        }

        try {
            return (int) (DB::table('designations')->where('designation', $designation)->value('id') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Staff members
    // ─────────────────────────────────────────────────────────────

    public function dropdown(string $type, string $search = ''): array
    {
        [$table, $text] = self::DROPDOWNS[$type];

        return DB::table($table)
            ->when($search !== '', fn ($q) => $q->where($text, 'like', '%' . $search . '%'))
            ->orderBy($type === 'months' ? 'id' : $text)
            ->get(['id', $text . ' as text'])
            ->map(fn ($r) => ['id' => $r->id, 'text' => $r->text])
            ->all();
    }

    /** @return array{0: Collection, 1: int} page rows + total active members */
    public function memberPage(int $page, int $perPage): array
    {
        $total = StaffMember::where('active', 'Yes')->count();

        $rows = DB::table('staff_members as sm')
            ->join('designations as d', 'd.id', '=', 'sm.designation_id')
            ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
            ->where('sm.active', 'Yes')
            ->orderBy('sm.designation_id')
            ->orderBy('sm.staff_name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get(['sm.id', 'sm.staff_name', 'sm.mobile_number', 'd.designation', 'dp.department']);

        return [$rows, $total];
    }

    /** Edit modal payload (legacy getstaffmember). The password hash is deliberately NOT returned. */
    public function memberDetail(int $id): ?array
    {
        $row = DB::table('staff_members as sm')
            ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
            ->join('designations as d', 'd.id', '=', 'sm.designation_id')
            ->join('user_roles as ur', 'ur.id', '=', 'sm.user_role_id')
            ->where('sm.id', $id)
            ->selectRaw("sm.id, sm.joining_date, sm.dob, sm.staff_name, sm.designation_id,
                sm.department_id, COALESCE(sm.specialities, '') as specialities, sm.gender, sm.mobile_number,
                sm.email, sm.residential_address, sm.permanent_address, sm.qualification, sm.experience_years,
                sm.aadhaar_number, sm.aadhaar_number as aadhar_number, sm.photo, sm.current_salary, sm.user_role_id, sm.active,
                '' as password, dp.department, d.designation, ur.role,
                sm.medical_registration_number, sm.registration_council, sm.hpr_id, sm.hpr_address")
            ->first();

        if (! $row) {
            return null;
        }

        $arr = (array) $row;
        $arr['joining_date'] = $arr['joining_date'] ? date('d-m-Y', strtotime($arr['joining_date'])) : '';
        $arr['dob']          = $arr['dob'] ? date('d-m-Y', strtotime($arr['dob'])) : '';

        return $arr;
    }

    private function memberColumns(array $v): array
    {
        $specialities = collect($v['specialities'] ?? [])
            ->filter(fn ($s) => $s !== null && $s !== '')
            ->map(fn ($s) => (int) $s)
            ->implode(',');

        // staff_members columns are NOT NULL: optional blanks are stored as '' (legacy behaviour).
        return [
            'joining_date'                => self::parseDate($v['joining_date'] ?? null),
            'staff_name'                  => trim($v['staff_name']),
            'designation_id'              => (int) $v['designation_id'],
            'department_id'               => (int) $v['department_id'],
            'specialities'                => $specialities,
            'gender'                      => $v['gender'],
            'dob'                         => self::parseDate($v['dob'] ?? null),
            'mobile_number'               => $v['mobile_number'],
            'email'                       => $v['email'] ?? '',
            'residential_address'         => $v['residential_address'],
            'permanent_address'           => $v['permanent_address'] ?? '',
            'qualification'               => $v['qualification'] ?? '',
            'experience_years'            => (int) $v['experience_years'],
            'aadhaar_number'              => $v['aadhaar_number'] ?? '',
            'photo'                       => $v['photo'] ?? '',
            'user_role_id'                => (int) $v['user_role_id'],
            'medical_registration_number' => $v['medical_registration_number'] ?? null,
            'registration_council'        => $v['registration_council'] ?? null,
            'hpr_id'                      => $v['hpr_id'] ?? null,
            'hpr_address'                 => $v['hpr_address'] ?? null,
        ];
    }

    public function createMember(array $v): StaffMember
    {
        return StaffMember::create($this->memberColumns($v) + [
            'password'       => Hash::make($v['password']),
            'current_salary' => (int) ($v['current_salary'] ?? 0),
            'salaried'       => 'No',
            'active'         => 'Yes',
        ]);
    }

    public function updateMember(StaffMember $member, array $v): StaffMember
    {
        $columns = $this->memberColumns($v);
        if (!empty($v['password'])) {
            $columns['password'] = Hash::make($v['password']);
        }
        $member->update($columns);

        return $member;
    }

    /** Legacy delstaffmember: soft delete (active = 'No'). */
    public function deactivateMember(int $id): void
    {
        StaffMember::whereKey($id)->update(['active' => 'No']);
    }

    // ─────────────────────────────────────────────────────────────
    // Attendance
    // ─────────────────────────────────────────────────────────────

    /** Rows of the Staff Attendance sheet: active + salaried staff, grouped by designation. */
    public function attendanceSheetStaff(): Collection
    {
        return DB::table('staff_members as sm')
            ->join('designations as d', 'd.id', '=', 'sm.designation_id')
            ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
            ->where('sm.active', 'Yes')
            ->where('sm.salaried', 'Yes')
            ->orderBy('sm.designation_id')
            ->orderBy('sm.staff_name')
            ->get(['sm.id', 'sm.staff_name', 'd.designation']);
    }

    public function attendanceExists(string $date): bool
    {
        $day = self::parseDate($date);

        return $day !== null && StaffAttendance::where('attendance_date', $day)->exists();
    }

    /**
     * Legacy get_attendance: saved attendance of a day, the oldest earlier day whose Present/Halfday staff never
     * checked out ("pending"), and — for a day with nothing saved — the doctors/nurses NOT rostered that day as "Off".
     */
    public function attendanceForDate(string $date): array
    {
        $day = self::parseDate($date) ?? date('Y-m-d');

        $pending = StaffAttendance::where('attendance_date', '<', $day)
            ->whereNull('check_out_time')
            ->whereIn('status', ['Present', 'Halfday'])
            ->min('attendance_date');

        $saved = StaffAttendance::where('attendance_date', $day)->get();

        if ($saved->isNotEmpty()) {
            $attendance = $saved->map(fn ($r) => [
                'staff_id'       => $r->staff_id,
                'status'         => $r->status,
                'check_in_time'  => $r->check_in_time ? date('g:i A', strtotime($r->check_in_time)) : null,
                'check_out_time' => $r->check_out_time ? date('g:i A', strtotime($r->check_out_time)) : null,
                'remarks'        => $r->remarks,
            ])->values()->all();
        } else {
            $designationIds = array_filter([
                $this->settings->getIdField('doctor_designation_id'),
                $this->settings->getIdField('nurse_designation_id'),
            ]);
            $attendance = StaffMember::whereIn('designation_id', $designationIds)
                ->whereNotIn('id', Roster::where('roster_date', $day)->select('staff_id'))
                ->get(['id'])
                ->map(fn ($m) => ['staff_id' => $m->id, 'status' => 'Off'])
                ->values()->all();
        }

        return [
            'pending_date' => $pending ? date('d-m-Y', strtotime($pending)) : '',
            'attendance'   => $attendance,
        ];
    }

    /** Legacy save_staff_attendance: insert-or-update one row per (staff, date). Returns "Inserted"/"Updated". */
    public function saveAttendance(string $date, array $rows, int $userId): string
    {
        $day = self::parseDate($date) ?? date('Y-m-d');
        $response = '';

        DB::transaction(function () use ($day, $rows, $userId, &$response) {
            foreach ($rows as $row) {
                $values = [
                    'check_in_time'  => $this->parseTime($row['check_in_time'] ?? null),
                    'check_out_time' => $this->parseTime($row['check_out_time'] ?? null),
                    'status'         => $row['status'],
                    'remarks'        => (string) ($row['remarks'] ?? ''),
                    'user_id'        => $userId,
                ];
                $existing = StaffAttendance::where('staff_id', $row['staff_id'])->where('attendance_date', $day)->first();
                if ($existing) {
                    $existing->update($values);
                    $response = 'Updated';
                } else {
                    StaffAttendance::create($values + ['staff_id' => $row['staff_id'], 'attendance_date' => $day]);
                    $response = 'Inserted';
                }
            }
        });

        return $response;
    }

    /**
     * Legacy api/attendance.php (QR scanner kiosk): first scan of the day = check-in, second = check-out,
     * a third is refused. Returns the legacy JSON payload.
     */
    public function qrScan(int $staffId): array
    {
        if (!StaffMember::whereKey($staffId)->where('active', 'Yes')->exists()) {
            return ['success' => false, 'message' => 'Staff not found'];
        }

        $today = date('Y-m-d');
        $now   = date('H:i:s');
        $row   = StaffAttendance::where('staff_id', $staffId)->where('attendance_date', $today)->first();

        if ($row) {
            if ($row->check_out_time === null || $row->check_out_time == '') {
                $row->update([
                    'check_out_time' => $now,
                    'status'         => 'Present',
                    'remarks'        => 'Checked out via QR Scanner',
                ]);

                return [
                    'success'        => true,
                    'message'        => "Check-out recorded successfully for Staff ID: $staffId",
                    'type'           => 'check_out',
                    'check_in_time'  => $row->check_in_time,
                    'check_out_time' => $now,
                ];
            }

            return ['success' => false, 'message' => 'Attendance already completed for today'];
        }

        StaffAttendance::create([
            'staff_id'        => $staffId,
            'attendance_date' => $today,
            'check_in_time'   => $now,
            'check_out_time'  => null,
            'status'          => 'Present',
            'remarks'         => 'Checked in via QR Scanner',
            // Legacy stored 0, which the staff_attendance.user_id foreign key rejects; the kiosk records the staff member itself.
            'user_id'         => $staffId,
        ]);

        return [
            'success'       => true,
            'message'       => "Check-in recorded successfully for Staff ID: $staffId",
            'type'          => 'check_in',
            'check_in_time' => $now,
        ];
    }


    /** [staff_id][Y-m-d] => status for the attendance calendar. */
    public function attendanceCalendar(int $month, int $year): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $matrix = [];
        StaffAttendance::whereBetween('attendance_date', [$start, $end])
            ->get(['staff_id', 'attendance_date', 'status'])
            ->each(function ($r) use (&$matrix) {
                $matrix[$r->staff_id][$r->attendance_date instanceof \DateTimeInterface ? $r->attendance_date->format('Y-m-d') : (string) $r->attendance_date] = $r->status;
            });

        return $matrix;
    }

    public function calendarStaff(): Collection
    {
        return DB::table('staff_members as sm')
            ->join('designations as d', 'd.id', '=', 'sm.designation_id')
            ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
            ->where('sm.active', 'Yes')
            ->orderBy('sm.designation_id')
            ->orderBy('sm.staff_name')
            ->get(['sm.id', 'sm.staff_name']);
    }

    /** WHERE-clause pieces shared by the attendance report + chart (Daily / Monthly / Periodic). */
    private function reportPeriod($query, Request $request)
    {
        $period = $request->input('report_period', 'Daily');

        if ($period === 'Daily' && ($d = self::parseDate($request->input('search_date')))) {
            $query->where('sa.attendance_date', $d);
        } elseif ($period === 'Monthly' && ($m = (int) $request->input('search_month')) > 0) {
            // Legacy filters the month within the CURRENT year.
            $query->whereMonth('sa.attendance_date', $m)->whereYear('sa.attendance_date', (int) date('Y'));
        } elseif ($period === 'Periodic'
            && ($from = self::parseDate($request->input('from_date')))
            && ($to = self::parseDate($request->input('to_date')))) {
            $query->whereBetween('sa.attendance_date', [$from, $to]);
        }

        return $query;
    }

    /** Legacy staff_attendance_report (DataTables server side). */
    public function attendanceReport(Request $request): array
    {
        $search = trim((string) $request->input('search.value', ''));

        $base = fn () => $this->reportPeriod(
            DB::table('staff_attendance as sa')
                ->join('staff_members as sm', 'sm.id', '=', 'sa.staff_id')
                ->join('designations as d', 'd.id', '=', 'sm.designation_id')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('sm.staff_name', 'like', "%{$search}%")
                    ->orWhere('d.designation', 'like', "%{$search}%"))),
            $request
        );

        $total = $base()->distinct()->count('sa.staff_id');

        $sortable = [
            'staff_name' => 'sm.staff_name', 'designation' => 'd.designation', 'total_days' => 'total_days',
            'total_working_days' => 'total_working_days', 'total_working_hours' => 'total_working_hours',
            'leave_days' => 'leave_days', 'absent_days' => 'absent_days',
        ];
        $col   = $request->input('columns.' . $request->input('order.0.column', 1) . '.data');
        $order = $sortable[$col] ?? 'sm.staff_name';
        $dir   = strtolower((string) $request->input('order.0.dir')) === 'desc' ? 'desc' : 'asc';

        $query = $base()
            ->selectRaw("sa.staff_id, sm.staff_name, d.designation,
                SEC_TO_TIME(SUM(CASE WHEN sa.status = 'Present' AND sa.check_in_time IS NOT NULL AND sa.check_out_time IS NOT NULL
                    THEN TIME_TO_SEC(TIMEDIFF(sa.check_out_time, sa.check_in_time)) ELSE 0 END)) AS total_working_hours,
                COUNT(CASE WHEN sa.status != 'Holiday' THEN 1 END) AS total_days,
                COUNT(CASE WHEN sa.status IN ('Present','Halfday') THEN 1 END) AS total_working_days,
                COUNT(CASE WHEN sa.status = 'Leave' THEN 1 END) AS leave_days,
                COUNT(CASE WHEN sa.status = 'Absent' THEN 1 END) AS absent_days")
            ->groupBy('sa.staff_id', 'sm.staff_name', 'd.designation')
            ->orderBy($order, $dir);

        $length = (int) $request->input('length', 100);
        if ($length > 0) {
            $query->offset(max(0, (int) $request->input('start', 0)))->limit($length);
        }

        $data = $query->get()->map(fn ($r) => [
            'action'              => '',
            'staff_name'          => $r->staff_name,
            'designation'         => $r->designation,
            'total_days'          => $r->total_days,
            'total_working_days'  => $r->total_working_days,
            'total_working_hours' => $r->total_working_hours,
            'absent_days'         => $r->absent_days,
            'leave_days'          => $r->leave_days,
        ])->all();

        return [
            'draw'                 => (int) $request->input('draw'),
            'iTotalRecords'        => $total,
            'iTotalDisplayRecords' => $total,
            'aaData'               => $data,
        ];
    }

    /** Legacy staff_attendance_chart. */
    public function attendanceChart(Request $request): array
    {
        return $this->reportPeriod(
            DB::table('staff_attendance as sa')->join('staff_members as sm', 'sm.id', '=', 'sa.staff_id'),
            $request
        )
            ->selectRaw("sm.staff_name,
                COUNT(CASE WHEN sa.status IN ('Present','Halfday') THEN 1 END) AS total_working_days,
                COUNT(CASE WHEN sa.status = 'Absent' THEN 1 END) AS absent_days,
                COUNT(CASE WHEN sa.status = 'Leave' THEN 1 END) AS leave_days,
                ROUND(SUM(CASE WHEN sa.status = 'Present' AND sa.check_in_time IS NOT NULL AND sa.check_out_time IS NOT NULL
                    THEN TIME_TO_SEC(TIMEDIFF(sa.check_out_time, sa.check_in_time)) ELSE 0 END) / 3600, 2) AS total_working_hours")
            ->groupBy('sa.staff_id', 'sm.staff_name')
            ->orderBy('sm.staff_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    // ─────────────────────────────────────────────────────────────
    // Roster
    // ─────────────────────────────────────────────────────────────

    /** @return array<string, array<string, array<string, array>>> [designation][shift][Y-m-d] => roster pills */
    public function rosterGrid(int $month, int $year): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $grid = [];
        DB::table('roster as r')
            ->join('staff_members as sm', 'sm.id', '=', 'r.staff_id')
            ->whereBetween('r.roster_date', [$start, $end])
            ->whereIn('r.designation', self::ROSTER_DESIGNATIONS)
            ->whereIn('r.shift', self::ROSTER_SHIFTS)
            ->orderBy('r.id')
            ->get(['r.id as roster_id', 'r.staff_id', 'sm.staff_name', 'r.roster_date', 'r.designation', 'r.shift'])
            ->each(function ($r) use (&$grid) {
                $grid[$r->designation][$r->shift][$r->roster_date][] = [
                    'roster_id'  => (int) $r->roster_id,
                    'staff_id'   => (int) $r->staff_id,
                    'staff_name' => $r->staff_name,
                ];
            });

        return $grid;
    }

    /** <option> list for the Add / Replace roster modals (legacy get_staff_for_roster / get_replace_staff_for_roster). */
    public function rosterStaffOptions(int $designationId, ?int $excludeStaffId = null): string
    {
        $html = '<option></option>';
        StaffMember::where('designation_id', $designationId)
            ->where('active', 'Yes')
            ->when($excludeStaffId, fn ($q) => $q->where('id', '!=', $excludeStaffId))
            ->orderBy('staff_name')
            ->get(['id', 'staff_name'])
            ->each(function ($s) use (&$html) {
                $html .= '<option value="' . (int) $s->id . '">' . e($s->staff_name) . '</option>';
            });

        return $html;
    }

    /** Legacy add_roster_staff: roster one staff member on N consecutive days (existing rows are re-used). */
    public function addRoster(string $date, int $staffId, string $designation, string $shift, int $days, ?int $designationId = null): array
    {
        $start = self::parseDate($date) ?? date('Y-m-d');
        $staff = StaffMember::findOrFail($staffId);

        $rosterIds = [];
        $current   = $start;

        DB::transaction(function () use ($start, $staffId, $designation, $shift, $days, &$rosterIds, &$current) {
            for ($i = 0; $i < $days; $i++) {
                $current = date('Y-m-d', strtotime($start . " +$i days"));
                $row = Roster::firstOrCreate([
                    'roster_date' => $current,
                    'designation' => $designation,
                    'shift'       => $shift,
                    'staff_id'    => $staffId,
                ]);
                $rosterIds[] = ['date' => $current, 'id' => $row->id];
            }
        });

        return [
            'start_date'     => $start,
            'end_date'       => $current,
            'designation'    => $designation,
            'designation_id' => $designationId ?: $this->rosterDesignationId($designation),
            'shift'          => $shift,
            'staff_id'       => $staffId,
            'staff_name'     => $staff->staff_name,
            'roster_ids'     => $rosterIds,
        ];
    }

    public function removeRoster(int $id): void
    {
        Roster::whereKey($id)->delete();
    }

    /** Legacy replace_roster_staff. */
    public function replaceRoster(int $rosterId, int $staffId): array
    {
        $roster = Roster::findOrFail($rosterId);
        $staff  = StaffMember::findOrFail($staffId);
        $roster->update(['staff_id' => $staffId]);

        return ['id' => $rosterId, 'sn' => $staff->staff_name];
    }

    /** staff-roster-dates.php: [staff_name, "1,2,3"] per staff for a designation + shift in a month. */
    public function rosterDates(int $designationId, string $shift, int $month, int $year): Collection
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        if (DB::getDriverName() === 'sqlite') {
            return DB::table('roster as r')
                ->join('staff_members as sm', 'sm.id', '=', 'r.staff_id')
                ->where('r.shift', $shift)
                ->where('sm.designation_id', $designationId)
                ->whereBetween('r.roster_date', [$start, $end])
                ->orderBy('sm.staff_name')
                ->orderBy('r.roster_date')
                ->get(['r.staff_id', 'sm.staff_name', 'r.roster_date'])
                ->groupBy('staff_id')
                ->map(function ($rows) {
                    $days = $rows->map(fn ($r) => (int) date('j', strtotime($r->roster_date)))->implode(',');
                    return (object) [
                        'staff_name'  => $rows->first()->staff_name,
                        'roster_days' => $days,
                    ];
                })
                ->values();
        }

        return DB::table('roster as r')
            ->join('staff_members as sm', 'sm.id', '=', 'r.staff_id')
            ->where('r.shift', $shift)
            ->where('sm.designation_id', $designationId)
            ->whereBetween('r.roster_date', [$start, $end])
            ->selectRaw("sm.staff_name, GROUP_CONCAT(DAY(r.roster_date) ORDER BY r.roster_date ASC SEPARATOR ',') AS roster_days")
            ->groupBy('r.staff_id', 'sm.staff_name')
            ->orderBy('sm.staff_name')
            ->get();
    }

    /** Staff-wise roster for the View Print Roster page (same data as rpt-staff-wise-roster.php). */
    public function staffWiseRoster(int $month, int $year, string $designation, string $shift): Collection
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        return DB::table('roster as r')
            ->join('staff_members as sm', 'sm.id', '=', 'r.staff_id')
            ->whereBetween('r.roster_date', [$start, $end])
            ->when($designation !== '', fn ($q) => $q->where('r.designation', $designation))
            ->when($shift !== '', fn ($q) => $q->where('r.shift', $shift))
            ->orderBy('sm.staff_name')
            ->orderBy('r.roster_date')
            ->get(['sm.id', 'sm.staff_name', 'r.roster_date'])
            ->groupBy('id')
            ->map(fn ($rows) => [
                'staff_name' => $rows->first()->staff_name,
                'days'       => $rows->map(fn ($r) => date('d', strtotime($r->roster_date)))->unique()->values()->all(),
            ])
            ->values();
    }

    // ─────────────────────────────────────────────────────────────
    // Salary
    // ─────────────────────────────────────────────────────────────

    /**
     * Legacy get_staff_salary_list: per-staff working days / hours and the pro-rata salary for a month, plus
     * whether a salary payment was already recorded in that month. Only staff with a salary and attendance in the
     * month are listed. Salary = working days * (current salary / days in month).
     */
    public function salaryList(int $month, int $year): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));
        $paid  = "'present','halfday','holiday','off'";

        if (DB::getDriverName() === 'sqlite') {
            $daysInMonth = (int) date('t', strtotime($start));
            $staffIds = DB::table('staff_members as sm')
                ->join('staff_attendance as sa', 'sa.staff_id', '=', 'sm.id')
                ->where('sm.current_salary', '>', 0)
                ->whereBetween('sa.attendance_date', [$start, $end])
                ->pluck('sm.id')
                ->unique();

            if ($staffIds->isEmpty()) {
                return [];
            }

            $members = DB::table('staff_members as sm')
                ->join('designations as d', 'd.id', '=', 'sm.designation_id')
                ->whereIn('sm.id', $staffIds)
                ->orderBy('d.designation')
                ->orderBy('sm.staff_name')
                ->get(['sm.id as staff_id', 'sm.staff_name', 'd.designation', 'sm.current_salary']);

            $attendances = DB::table('staff_attendance')
                ->whereIn('staff_id', $staffIds)
                ->whereBetween('attendance_date', [$start, $end])
                ->get()
                ->groupBy('staff_id');

            $payments = DB::table('staff_salary_payments')
                ->whereIn('staff_id', $staffIds)
                ->whereBetween('salary_date_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
                ->get()
                ->groupBy('staff_id');

            $workingStatuses = ['present', 'halfday', 'holiday', 'off'];
            $absentStatuses = ['absent', 'leave'];

            $result = [];
            foreach ($members as $m) {
                $staffAtt = $attendances->get($m->staff_id, collect());
                $workingDays = $staffAtt->filter(fn ($a) => in_array(strtolower($a->status), $workingStatuses))->count();
                $absentDays = $staffAtt->filter(fn ($a) => in_array(strtolower($a->status), $absentStatuses))->count();

                $totalSec = 0;
                foreach ($staffAtt as $a) {
                    if (!empty($a->check_in_time) && !empty($a->check_out_time)) {
                        $inSec = strtotime("1970-01-01 " . $a->check_in_time);
                        $outSec = strtotime("1970-01-01 " . $a->check_out_time);
                        if ($outSec >= $inSec) {
                            $totalSec += ($outSec - $inSec);
                        } else {
                            $totalSec += (86400 - $inSec) + $outSec;
                        }
                    }
                }
                $totalWorkingHours = round($totalSec / 3600, 2);
                $totalSalary = (int) round($workingDays * ($m->current_salary / $daysInMonth));

                $staffPay = $payments->get($m->staff_id);
                $paid = ($staffPay && $staffPay->isNotEmpty()) ? 'Yes' : 'No';
                $salaryPaymentId = ($paid === 'Yes') ? (int) $staffPay->last()->id : 0;

                $result[] = (object) [
                    'staff_id'            => (int) $m->staff_id,
                    'staff_name'          => $m->staff_name,
                    'designation'         => $m->designation,
                    'current_salary'      => (int) $m->current_salary,
                    'working_days'        => $workingDays,
                    'absent_days'         => $absentDays,
                    'total_working_hours' => $totalWorkingHours,
                    'total_salary'        => $totalSalary,
                    'paid'                => $paid,
                    'salary_payment_id'   => $salaryPaymentId,
                ];
            }

            return $result;
        }

        return DB::select("
            SELECT sm.id AS staff_id, sm.staff_name, d.designation, sm.current_salary,
                COUNT(CASE WHEN sa.status IN ($paid) THEN 1 END) AS working_days,
                COUNT(CASE WHEN sa.status IN ('absent','leave') THEN 1 END) AS absent_days,
                ROUND(COUNT(CASE WHEN sa.status IN ($paid) THEN 1 END) * (sm.current_salary / DAY(LAST_DAY(?))), 0) AS total_salary,
                ROUND(SUM(
                    CASE WHEN sa.check_in_time AND sa.check_out_time THEN
                        CASE WHEN sa.check_out_time >= sa.check_in_time
                            THEN TIME_TO_SEC(TIMEDIFF(sa.check_out_time, sa.check_in_time)) / 3600
                            ELSE (TIME_TO_SEC(TIMEDIFF('24:00:00', sa.check_in_time)) + TIME_TO_SEC(sa.check_out_time)) / 3600
                        END
                    ELSE 0 END
                ), 2) AS total_working_hours,
                CASE WHEN sp.id IS NOT NULL THEN 'Yes' ELSE 'No' END AS paid,
                IFNULL(sp.id, 0) AS salary_payment_id
            FROM staff_members sm
            JOIN designations d ON d.id = sm.designation_id
            JOIN staff_attendance sa ON sa.staff_id = sm.id
            LEFT JOIN (
                SELECT staff_id, MAX(id) AS id FROM staff_salary_payments
                WHERE salary_date_time BETWEEN ? AND ? GROUP BY staff_id
            ) sp ON sp.staff_id = sm.id
            WHERE sm.current_salary > 0 AND sa.attendance_date BETWEEN ? AND ?
            GROUP BY sm.id, sm.staff_name, d.designation, sm.current_salary, sp.id
            ORDER BY d.designation, sm.staff_name
        ", [$start, $start . ' 00:00:00', $end . ' 23:59:59', $start, $end]);
    }

    public function saveSalaryPayment(array $v): StaffSalaryPayment
    {
        return StaffSalaryPayment::create([
            'staff_id'         => (int) $v['staff_id'],
            'salary_date_time' => date('Y-m-d H:i:s', strtotime($v['salary_date_time'])),
            'amount'           => (int) round((float) $v['amount']),
            'payment_mode'     => $v['payment_mode'],
            'remarks'          => (string) ($v['remarks'] ?? ''),
        ]);
    }

    public function salaryPaymentDetail(int $id): ?array
    {
        $p = StaffSalaryPayment::find($id);

        return $p ? [
            'salary_date_time' => date('d-m-Y h:i A', strtotime($p->salary_date_time)),
            'amount'           => $p->amount,
            'payment_mode'     => $p->payment_mode,
            'remarks'          => $p->remarks,
        ] : null;
    }

    /** Settings list (staff-salary-setting.php): members with last appraisal date + days since. */
    public function salarySettingRows(): Collection
    {
        if (DB::getDriverName() === 'sqlite') {
            $members = DB::table('staff_members as sm')
                ->join('designations as d', 'd.id', '=', 'sm.designation_id')
                ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
                ->where('sm.active', 'Yes')
                ->orderBy('sm.designation_id')
                ->orderBy('sm.staff_name')
                ->get(['sm.id', 'sm.staff_name', 'sm.mobile_number', 'sm.current_salary', 'd.designation']);

            $appraisals = DB::table('staff_salary_appraisal')
                ->whereIn('staff_id', $members->pluck('id'))
                ->orderBy('appraisal_date', 'desc')
                ->get()
                ->groupBy('staff_id');

            return $members->map(function ($m) use ($appraisals) {
                $staffApps = $appraisals->get($m->id);
                $latest = $staffApps ? $staffApps->first() : null;
                $lastAppraisalDate = '';
                $daysSince = '';
                if ($latest && !empty($latest->appraisal_date)) {
                    $ts = strtotime($latest->appraisal_date);
                    $lastAppraisalDate = date('d-m-Y', $ts);
                    $daysSince = (string) floor((time() - $ts) / 86400);
                }

                return (object) [
                    'id'                   => $m->id,
                    'staff_name'           => $m->staff_name,
                    'mobile_number'        => $m->mobile_number,
                    'current_salary'       => $m->current_salary,
                    'designation'          => $m->designation,
                    'last_appraisal_date'  => $lastAppraisalDate,
                    'days_since_appraisal' => $daysSince,
                ];
            });
        }

        return DB::table('staff_members as sm')
            ->join('designations as d', 'd.id', '=', 'sm.designation_id')
            ->join('departments as dp', 'dp.id', '=', 'sm.department_id')
            ->leftJoin('staff_salary_appraisal as ssa', 'ssa.staff_id', '=', 'sm.id')
            ->where('sm.active', 'Yes')
            ->selectRaw("sm.id, sm.staff_name, sm.mobile_number, sm.current_salary, d.designation,
                IFNULL(DATE_FORMAT(MAX(ssa.appraisal_date), '%d-%m-%Y'), '') AS last_appraisal_date,
                IFNULL(DATEDIFF(CURDATE(), MAX(ssa.appraisal_date)), '') AS days_since_appraisal")
            ->groupBy('sm.id', 'sm.staff_name', 'sm.mobile_number', 'sm.current_salary', 'sm.designation_id', 'd.designation')
            ->orderBy('sm.designation_id')
            ->orderBy('sm.staff_name')
            ->get();
    }

    /** Legacy update_staff_salary: appraisal row + staff_members.current_salary. */
    public function saveAppraisal(int $staffId, string $date, int $newSalary, ?string $remarks, int $userId): array
    {
        $day = self::parseDate($date) ?? date('Y-m-d');

        DB::transaction(function () use ($staffId, $day, $newSalary, $remarks, $userId) {
            $staff = StaffMember::lockForUpdate()->findOrFail($staffId);

            StaffSalaryAppraisal::create([
                'appraisal_date' => $day,
                'staff_id'       => $staffId,
                'current_salary' => (int) $staff->current_salary,
                'new_salary'     => $newSalary,
                // Legacy schema has remarks as INT (see migration 2026_10_16_130000); keep working until it is run.
                'remarks'        => $this->remarksColumnIsText() ? (string) $remarks : 0,
                'user_id'        => $userId,
            ]);

            $staff->update(['current_salary' => $newSalary]);
        });

        return ['id' => $staffId, 'new_salary' => $newSalary, 'last_appraisal_date' => date('d-m-Y', strtotime($day))];
    }

    private function remarksColumnIsText(): bool
    {
        $type = strtolower((string) Schema::getColumnType('staff_salary_appraisal', 'remarks'));

        return !in_array($type, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true);
    }

    public function appraisals(int $staffId): Collection
    {
        return StaffSalaryAppraisal::where('staff_id', $staffId)->orderBy('appraisal_date')->get();
    }
}
