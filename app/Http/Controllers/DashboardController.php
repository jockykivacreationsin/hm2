<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Opd;
use App\Models\Ipd;
use App\Models\Dcd;
use App\Models\Patient;
use App\Models\BillingCharge;
use App\Models\StaffMember;
use App\Models\Bed;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(PermissionService $permissions)
    {
        $today = now()->toDateString();
        $currentMonth = now()->format('Y-m');
        $currentYear = now()->year;
        $daysInMonth = now()->daysInMonth;
        
        // Get logged-in user's patient category ID from session (default to 1 if not set)
        $loggedInCategoryId = session('patient_category_id', session('logged_in_patient_category_id', 1));

        // ============ TODAY'S INCOME BY MODULE ============
        
        // OPD Income (billing_charges + opd_charges)
        $opdToday = DB::table('billing_charges')
            ->join('opds', 'opds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->where('billing_charges.module', 'OPD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.total');
        
        $opdTodayFromCharges = DB::table('opds')
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->whereDate('opds.opd_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('opds.opd_charges');
        
        $opdToday += $opdTodayFromCharges;
        
        $opdTodayPaid = DB::table('billing_charges')
            ->join('opds', 'opds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->where('billing_charges.module', 'OPD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.paid_amount');
        
        $opdTodayPaid += $opdTodayFromCharges; // opd_charges are always paid
        
        // IPD Income
        $ipdToday = DB::table('billing_charges')
            ->join('ipds', 'ipds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('billing_charges.module', 'IPD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.total');
        
        $ipdTodayPaid = DB::table('billing_charges')
            ->join('ipds', 'ipds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('billing_charges.module', 'IPD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.paid_amount');
        
        // DCD Income
        $dcdToday = DB::table('billing_charges')
            ->join('dcds', 'dcds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where('billing_charges.module', 'DCD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.total');
        
        $dcdTodayPaid = DB::table('billing_charges')
            ->join('dcds', 'dcds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where('billing_charges.module', 'DCD')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->sum('billing_charges.paid_amount');
        
        // OTHERS Income (only for category 1)
        $othersToday = 0;
        $othersTodayPaid = 0;
        if ($loggedInCategoryId == 1) {
            $othersToday = BillingCharge::whereNotIn('module', ['OPD', 'IPD', 'DCD'])
                ->whereDate('charge_date_time', $today)
                ->sum('total');
            
            $othersTodayPaid = BillingCharge::whereNotIn('module', ['OPD', 'IPD', 'DCD'])
                ->whereDate('charge_date_time', $today)
                ->sum('paid_amount');
        }

        // ============ MONTHLY INCOME DATA (12 months) ============
        $monthlyData = array_fill(0, 12, 0);
        
        // Get monthly OPD income
        $monthlyOpd = DB::table('billing_charges')
            ->join('opds', 'opds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->where('billing_charges.module', 'OPD')
            ->whereYear('billing_charges.charge_date_time', $currentYear)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->selectRaw('MONTH(billing_charges.charge_date_time) as month, SUM(billing_charges.total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();
        
        $monthlyOpdCharges = DB::table('opds')
            ->join('patients', 'patients.id', '=', 'opds.patient_id')
            ->whereYear('opds.opd_date_time', $currentYear)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->selectRaw('MONTH(opds.opd_date_time) as month, SUM(opds.opd_charges) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();
        
        // Combine OPD billing + opd_charges
        foreach ($monthlyOpd as $month => $amount) {
            $monthlyData[$month - 1] += $amount;
        }
        foreach ($monthlyOpdCharges as $month => $amount) {
            $monthlyData[$month - 1] += $amount;
        }
        
        // Get monthly IPD income
        $monthlyIpd = DB::table('billing_charges')
            ->join('ipds', 'ipds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('billing_charges.module', 'IPD')
            ->whereYear('billing_charges.charge_date_time', $currentYear)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->selectRaw('MONTH(billing_charges.charge_date_time) as month, SUM(billing_charges.total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();
        
        foreach ($monthlyIpd as $month => $amount) {
            $monthlyData[$month - 1] += $amount;
        }
        
        // Get monthly DCD income
        $monthlyDcd = DB::table('billing_charges')
            ->join('dcds', 'dcds.id', '=', 'billing_charges.module_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where('billing_charges.module', 'DCD')
            ->whereYear('billing_charges.charge_date_time', $currentYear)
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->selectRaw('MONTH(billing_charges.charge_date_time) as month, SUM(billing_charges.total) as total')
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();
        
        foreach ($monthlyDcd as $month => $amount) {
            $monthlyData[$month - 1] += $amount;
        }
        
        // Get monthly OTHERS income (only for category 1)
        if ($loggedInCategoryId == 1) {
            $monthlyOthers = BillingCharge::whereNotIn('module', ['OPD', 'IPD', 'DCD'])
                ->whereYear('charge_date_time', $currentYear)
                ->selectRaw('MONTH(charge_date_time) as month, SUM(total) as total')
                ->groupBy('month')
                ->pluck('total', 'month')
                ->toArray();
            
            foreach ($monthlyOthers as $month => $amount) {
                $monthlyData[$month - 1] += $amount;
            }
        }

        // ============ DAILY INCOME DATA (current month) ============
        $dailyOpd = array_fill(0, $daysInMonth, 0);
        $dailyIpd = array_fill(0, $daysInMonth, 0);
        $dailyDcd = array_fill(0, $daysInMonth, 0);
        $dailyOthers = array_fill(0, $daysInMonth, 0);
        
        // Daily OPD income
        $dailyOpdData = BillingCharge::where('module', 'OPD')
            ->where('charge_date_time', 'like', $currentMonth . '%')
            ->selectRaw('DAY(charge_date_time) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();
        
        foreach ($dailyOpdData as $day => $amount) {
            $dailyOpd[$day - 1] = $amount;
        }
        
        // Daily IPD income
        $dailyIpdData = BillingCharge::where('module', 'IPD')
            ->where('charge_date_time', 'like', $currentMonth . '%')
            ->selectRaw('DAY(charge_date_time) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();
        
        foreach ($dailyIpdData as $day => $amount) {
            $dailyIpd[$day - 1] = $amount;
        }
        
        // Daily DCD income
        $dailyDcdData = BillingCharge::where('module', 'DCD')
            ->where('charge_date_time', 'like', $currentMonth . '%')
            ->selectRaw('DAY(charge_date_time) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();
        
        foreach ($dailyDcdData as $day => $amount) {
            $dailyDcd[$day - 1] = $amount;
        }
        
        // Daily OTHERS income
        $dailyOthersData = BillingCharge::whereNotIn('module', ['OPD', 'IPD', 'DCD'])
            ->where('charge_date_time', 'like', $currentMonth . '%')
            ->selectRaw('DAY(charge_date_time) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();
        
        foreach ($dailyOthersData as $day => $amount) {
            $dailyOthers[$day - 1] = $amount;
        }

        // ============ DISCHARGE APPROVALS ============
        $ipdDischargeApprovals = DB::table('ipd_discharge')
            ->join('ipds', 'ipds.id', '=', 'ipd_discharge.ipd_id')
            ->join('patients', 'patients.id', '=', 'ipds.patient_id')
            ->where('ipd_discharge.approved', '!=', 'Yes')
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->select('ipd_discharge.id', 'ipd_discharge.ipd_id', 'patients.patient_name', 
                     'ipds.opd_id', DB::raw("DATE_FORMAT(ipds.admission_date_time, '%d-%m-%Y %h:%i %p') as admission_date_time"))
            ->orderByDesc('ipd_discharge.id')
            ->limit(10)
            ->get();
        
        $dcdDischargeApprovals = DB::table('dcd_discharge')
            ->join('dcds', 'dcds.id', '=', 'dcd_discharge.dcd_id')
            ->join('patients', 'patients.id', '=', 'dcds.patient_id')
            ->where('dcd_discharge.approved', '!=', 'Yes')
            ->where('patients.patient_category_id', $loggedInCategoryId)
            ->select('dcd_discharge.id', 'dcd_discharge.dcd_id', 'patients.patient_name', 
                     'dcds.opd_id', DB::raw("DATE_FORMAT(dcds.dcd_date_time, '%d-%m-%Y %h:%i %p') as dcd_date_time"))
            ->orderByDesc('dcd_discharge.id')
            ->limit(10)
            ->get();

        // ============ PRESENT STAFF ============
        $presentStaff = DB::table('staff_attendance')
            ->join('staff_members', 'staff_members.id', '=', 'staff_attendance.staff_id')
            ->leftJoin('designations', 'designations.id', '=', 'staff_members.designation_id')
            ->whereDate('staff_attendance.attendance_date', $today)
            ->where('staff_attendance.status', 'Present')
            ->select('staff_members.id', 'staff_members.staff_name', 'staff_members.mobile_number', 
                     'staff_members.photo', 'designations.designation')
            ->distinct()
            ->orderBy('staff_members.staff_name')
            ->limit(10)
            ->get();

        // ============ BED STATISTICS ============
        $totalBeds = Bed::count();
        $occupiedBeds = Bed::where('status', 'Allotted')->count();
        $availableBeds = $totalBeds - $occupiedBeds;

        // ============ OTHER STATS ============
        $totalPatients = Patient::count();
        $totalStaff = StaffMember::count();

        // ============ PIE CHART PERCENTAGES ============
        $totalIncomeToday = $opdToday + $ipdToday + $dcdToday + $othersToday;
        $opdPercent = $totalIncomeToday > 0 ? round(($opdToday / $totalIncomeToday) * 100, 1) : 0;
        $ipdPercent = $totalIncomeToday > 0 ? round(($ipdToday / $totalIncomeToday) * 100, 1) : 0;
        $dcdPercent = $totalIncomeToday > 0 ? round(($dcdToday / $totalIncomeToday) * 100, 1) : 0;
        $othersPercent = $totalIncomeToday > 0 ? round(($othersToday / $totalIncomeToday) * 100, 1) : 0;

        return view('dashboard.index', compact(
            'opdToday', 'opdTodayPaid',
            'ipdToday', 'ipdTodayPaid',
            'dcdToday', 'dcdTodayPaid',
            'othersToday', 'othersTodayPaid',
            'monthlyData',
            'dailyOpd', 'dailyIpd', 'dailyDcd', 'dailyOthers', 'daysInMonth',
            'ipdDischargeApprovals', 'dcdDischargeApprovals',
            'presentStaff',
            'totalBeds', 'occupiedBeds', 'availableBeds',
            'totalPatients', 'totalStaff',
            'opdPercent', 'ipdPercent', 'dcdPercent', 'othersPercent',
            'totalIncomeToday', 'currentYear', 'loggedInCategoryId'
        ));
    }

    public function moduleCharges(Request $request)
    {
        $module = $request->input('module');
        $today = now()->toDateString();
        
        $query = DB::table('billing_charges')
            ->join('patients', function($join) use ($module) {
                if ($module == 'OPD') {
                    $join->on('patients.id', '=', DB::raw('(SELECT patient_id FROM opds WHERE id = billing_charges.module_id LIMIT 1)'));
                } elseif ($module == 'IPD') {
                    $join->on('patients.id', '=', DB::raw('(SELECT patient_id FROM ipds WHERE id = billing_charges.module_id LIMIT 1)'));
                } elseif ($module == 'DCD') {
                    $join->on('patients.id', '=', DB::raw('(SELECT patient_id FROM dcds WHERE id = billing_charges.module_id LIMIT 1)'));
                }
            })
            ->leftJoin('charge_departments', 'charge_departments.id', '=', 'billing_charges.charge_department_id')
            ->whereDate('billing_charges.charge_date_time', $today)
            ->select('billing_charges.*', 'patients.patient_name', 'charge_departments.department_name');
        
        if ($module != 'OTHERS') {
            $query->where('billing_charges.module', $module);
        } else {
            $query->whereNotIn('billing_charges.module', ['OPD', 'IPD', 'DCD']);
        }
        
        $charges = $query->get();
        
        return response()->json(['data' => $charges]);
    }
}
