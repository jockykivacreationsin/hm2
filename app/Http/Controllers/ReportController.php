<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\BillingCharge;
use App\Models\BillingChargePayment;
use App\Models\Division;
use App\Services\PermissionService;
use App\Models\Setting;
use App\Services\Pdf\LegacyReportRenderer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function collections(Request $request, PermissionService $permissions)
    {
        $canAccess = $permissions->hasPermission('m_reports') && $permissions->hasPermission('im_collections');
        $divisions = Division::orderBy('id')->get();

        return view('reports.collections', compact('canAccess', 'divisions', 'permissions'));
    }

    public function collectionsData(Request $request): JsonResponse
    {
        $reportPeriod = $request->input('report_period', 'Daily');
        $searchDate   = $request->input('search_date', now()->format('d-m-Y'));
        $searchMonth  = (int) $request->input('search_month', now()->month);
        $fromDate     = $request->input('from_date', now()->format('d-m-Y'));
        $toDate       = $request->input('to_date', now()->format('d-m-Y'));
        $paymentMode  = $request->input('payment_mode');
        $divisionId   = (int) $request->input('division_id', 0);
        $search       = $request->input('search.value', '');
        $year         = (int) $request->input('year', now()->year);

        // Normalize dd-mm-yyyy → Y-m-d
        if ($searchDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $searchDate)) {
            $searchDate = \DateTime::createFromFormat('d-m-Y', $searchDate)->format('Y-m-d');
        } elseif ($searchDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $searchDate)) {
            $searchDate = date('Y-m-d', strtotime($searchDate));
        }

        if ($fromDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $fromDate)) {
            $fromDate = \DateTime::createFromFormat('d-m-Y', $fromDate)->format('Y-m-d');
        } elseif ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
            $fromDate = date('Y-m-d', strtotime($fromDate));
        }

        if ($toDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $toDate)) {
            $toDate = \DateTime::createFromFormat('d-m-Y', $toDate)->format('Y-m-d');
        } elseif ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            $toDate = date('Y-m-d', strtotime($toDate));
        }

        // Part 1: billing_charges
        $bcQuery = DB::table('billing_charges')
            ->join('charge_departments', 'charge_departments.id', '=', 'billing_charges.charge_department_id')
            ->join('billing_charges_payments', 'billing_charges.id', '=', 'billing_charges_payments.billing_charge_id')
            ->where('billing_charges.paid_amount', '>', 0)
            ->where(function ($q) {
                $q->whereNull('billing_charges.is_hidden')->orWhere('billing_charges.is_hidden', '!=', 'Yes');
            });

        // Part 2: opds
        $opdQuery = DB::table('opds')
            ->join('charge_departments', 'charge_departments.id', '=', 'opds.charge_department_id')
            ->where('opds.opd_charges', '>', 0);

        if ($reportPeriod === 'Daily') {
            $bcQuery->whereDate('billing_charges_payments.payment_date_time', $searchDate);
            $opdQuery->whereDate('opds.opd_date_time', $searchDate);
        } elseif ($reportPeriod === 'Monthly') {
            $bcQuery->whereMonth('billing_charges_payments.payment_date_time', $searchMonth)
                    ->whereYear('billing_charges_payments.payment_date_time', $year);
            $opdQuery->whereMonth('opds.opd_date_time', $searchMonth)
                     ->whereYear('opds.opd_date_time', $year);
        } elseif ($reportPeriod === 'Periodic') {
            $bcQuery->whereBetween(DB::raw('date(billing_charges_payments.payment_date_time)'), [$fromDate, $toDate]);
            $opdQuery->whereBetween(DB::raw('date(opds.opd_date_time)'), [$fromDate, $toDate]);
        }

        if (!empty($paymentMode)) {
            $bcQuery->where('billing_charges_payments.payment_mode', $paymentMode);
            $opdQuery->where('opds.payment_mode', $paymentMode);
        }

        if ($divisionId > 0) {
            $bcQuery->where('charge_departments.division_id', $divisionId);
            $opdQuery->where('charge_departments.division_id', $divisionId);
        }

        $bcRows = $bcQuery->select([
            'billing_charges.charge_date_time as charge_date_time',
            'billing_charges_payments.payment_date_time as display_date_time',
            'billing_charges.charge_title as charge_title',
            'billing_charges_payments.amount as paid_amount',
            'billing_charges_payments.payment_mode as payment_mode',
            'charge_departments.department_name as department_name',
        ])->get();

        $opdRows = $opdQuery->select([
            'opds.opd_date_time as charge_date_time',
            'opds.opd_date_time as display_date_time',
            DB::raw("'OPD FEE' as charge_title"),
            'opds.opd_charges as paid_amount',
            'opds.payment_mode as payment_mode',
            'charge_departments.department_name as department_name',
        ])->get();

        $all = $bcRows->concat($opdRows);

        if ($search !== '') {
            $lowerSearch = strtolower($search);
            $all = $all->filter(function ($item) use ($lowerSearch) {
                return str_contains(strtolower($item->charge_title ?? ''), $lowerSearch)
                    || str_contains(strtolower($item->department_name ?? ''), $lowerSearch)
                    || str_contains(strtolower($item->payment_mode ?? ''), $lowerSearch);
            });
        }

        $totalRecords = $all->count();

        $sorted = $all->sortBy([
            ['department_name', 'asc'],
            ['payment_mode', 'asc'],
        ]);

        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 200);

        $sliced = $length > 0 ? $sorted->slice($start, $length)->values() : $sorted->slice($start)->values();

        $data = $sliced->map(function ($row) {
            $dt = $row->display_date_time
                ? \Carbon\Carbon::parse($row->display_date_time)->format('d-m-Y h:i A')
                : '';
            $paid = (float) $row->paid_amount;
            $paidStr = ($paid == (int) $paid) ? (string) (int) $paid : number_format($paid, 2, '.', '');
            return [
                'payment_mode'     => $row->payment_mode ?? '',
                'department_name'  => $row->department_name ?? '',
                'charge_date_time' => $dt,
                'charge_title'     => $row->charge_title ?? '',
                'paid_amount'      => $paidStr,
            ];
        })->values()->all();

        return response()->json([
            'draw'                 => intval($request->input('draw', 1)),
            'recordsTotal'         => $totalRecords,
            'recordsFiltered'      => $totalRecords,
            'iTotalRecords'        => $totalRecords,
            'iTotalDisplayRecords' => $totalRecords,
            'aaData'               => $data,
            'data'                 => $data,
        ]);
    }

    public function createTablePdf(Request $request): JsonResponse
    {
        $filename = $request->input('filename', 'collection-report');
        $title    = $request->input('title', 'Collection Report');
        $thead    = $request->input('thead', '');
        $tbody    = $request->input('tbody', '');
        $tfoot    = $request->input('tfoot', '');

        // The browser posts the table's rows as HTML. Keep only table markup (no img/link/script: mPDF would fetch
        // local files or URLs named in them).
        $allowed = '<tr><th><td><b><strong><i><em><br><span><sup><sub>';
        $clean   = fn ($v) => strip_tags((string) $v, $allowed);

        $renderer = app(LegacyReportRenderer::class);
        $html = view('pdf.table-report', array_merge([
            'settings' => Setting::find(1)?->toArray() ?? [],
            'title'    => $title,
            'thead'    => $clean($thead),
            'tbody'    => $clean($tbody),
            'tfoot'    => $clean($tfoot),
        ], $renderer->signer()))->render();

        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', $filename) . '_' . date('d_m_Y_h_i_s_A') . '.pdf';

        // Private disk; the authenticated pdf.static-file route serves it (nothing under public/ is reachable without login).
        Storage::disk(GeneratedPdfController::DISK)->put($safeName, $renderer->standard($html));

        $url = route('pdf.static-file', ['filename' => $safeName]);

        return response()->json([
            'fn'          => $url,
            'url'         => $url,
            'relative_fn' => 'pdf/' . $safeName,
        ]);
    }

    public function opd(Request $request, PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('m_reports'), 403);
        return view('reports.opd');
    }

    public function ipd(Request $request, PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('m_reports'), 403);
        return view('reports.ipd');
    }
}
