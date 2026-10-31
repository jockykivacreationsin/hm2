<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\BillingCharge;
use App\Models\BillingChargePayment;
use App\Models\Opd;
use App\Models\Ipd;
use App\Models\Dcd;
use App\Http\Requests\ResetInvoiceNumberTwoRequest;
use App\Services\PermissionService;
use Illuminate\Support\Facades\DB;

class SaleBillController extends Controller
{
    public function index(PermissionService $ps)
    {
        $canAccess = $ps->hasPermission('m_sale_bill') && $ps->hasPermission('im_sale_bill');
        return view('sale-bills.index', compact('canAccess', 'ps'));
    }

    public function data(Request $request): JsonResponse
    {
        $from = $request->input('from_date', now()->format('Y-m-d'));
        $to   = $request->input('to_date', now()->format('Y-m-d'));
        $search = $request->input('search.value', '');

        // Normalize dd-mm-yyyy → Y-m-d
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $from)) {
            $from = \DateTime::createFromFormat('d-m-Y', $from)->format('Y-m-d');
        }
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $to)) {
            $to = \DateTime::createFromFormat('d-m-Y', $to)->format('Y-m-d');
        }

        $query = BillingCharge::query()
            ->leftJoin('charge_departments', 'charge_departments.id', '=', 'billing_charges.charge_department_id')
            ->whereBetween(DB::raw('date(billing_charges.charge_date_time)'), [$from, $to])
            ->where(function ($q) {
                $q->whereNull('billing_charges.is_hidden')->orWhere('billing_charges.is_hidden', '!=', 'Yes');
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('billing_charges.charge_title', 'like', "%{$search}%")
                    ->orWhere('billing_charges.payment_mode', 'like', "%{$search}%")
                    ->orWhere('charge_departments.department_name', 'like', "%{$search}%")
                    ->orWhere('billing_charges.invoice_number_two', 'like', "%{$search}%");
            });
        }

        $totalRecords = (clone $query)->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 1000);

        $charges = $query->select([
            'billing_charges.id',
            'billing_charges.invoice_number_two',
            'billing_charges.charge_date_time',
            'billing_charges.charge_title',
            'billing_charges.total',
            'billing_charges.payment_mode',
            'charge_departments.department_name',
        ])
            ->orderBy('billing_charges.invoice_number_two', 'asc')
            ->orderBy('billing_charges.id', 'asc')
            ->skip($start)
            ->take($length > 0 ? $length : 1000)
            ->get();

        $data = $charges->map(function ($c) {
            $displayDateTime = $c->charge_date_time
                ? \Carbon\Carbon::parse($c->charge_date_time)->format('d-m-Y h:i A')
                : '';
            return [
                'id'                => $c->invoice_number_two ?: $c->id,
                'billing_date_time' => $displayDateTime,
                'department_name'   => $c->department_name ?? '',
                'bill_title'        => $c->charge_title ?? '',
                'total'             => number_format((float) $c->total, 2, '.', ''),
                'payment_mode'      => $c->payment_mode ?? '',
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

    public function resetInvoiceNumberTwo(Request $request): JsonResponse
    {
        return $this->resetInvoiceNumbers(app(ResetInvoiceNumberTwoRequest::class));
    }

    /** Renumbers the visible (not hidden) charges of a date range sequentially from the given start value. */
    private function resetInvoiceNumbers(ResetInvoiceNumberTwoRequest $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        if ($startDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $startDate)) {
            $startDate = \DateTime::createFromFormat('d-m-Y', $startDate)->format('Y-m-d');
        } elseif ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d', strtotime($startDate));
        }
        $endDate = $request->input('end_date');
        if ($endDate && preg_match('/^\d{2}-\d{2}-\d{4}$/', $endDate)) {
            $endDate = \DateTime::createFromFormat('d-m-Y', $endDate)->format('Y-m-d');
        } elseif ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = date('Y-m-d', strtotime($endDate));
        }
        $invNum = (int) $request->input('invoice_number_two', 1);

        $charges = DB::table('billing_charges')
            ->whereBetween(DB::raw('date(charge_date_time)'), [$startDate, $endDate])
            ->where(function ($q) {
                $q->whereNull('is_hidden')->orWhere('is_hidden', '!=', 'Yes');
            })
            ->orderBy('id')
            ->get(['id']);

        foreach ($charges as $c) {
            DB::table('billing_charges')->where('id', $c->id)->update([
                'invoice_number_two' => $invNum++,
            ]);
        }

        return response()->json(['result' => 'OK', 'success' => true]);
    }

    /** Collect payment for an outstanding charge */
    public function collectPayment(Request $request, int $id): JsonResponse
    {
        $charge = BillingCharge::findOrFail($id);
        $v = $request->validate([
            'payment_date_time' => 'required|date',
            'payment_mode'      => 'required|string|max:50',
            'amount'            => 'required|numeric|min:0.01',
        ]);

        BillingChargePayment::create([
            'billing_charge_id' => $id,
            'payment_date_time' => $v['payment_date_time'],
            'payment_mode'      => $v['payment_mode'],
            'amount'            => $v['amount'],
        ]);

        $totalPaid = BillingChargePayment::where('billing_charge_id', $id)->sum('amount');
        $charge->update([
            'paid_amount'  => $totalPaid,
            'payment_mode' => $v['payment_mode'],
        ]);

        return response()->json(['success' => true, 'message' => 'Payment recorded.', 'total_paid' => $totalPaid]);
    }

    /** Sale bills grouped by department/payment mode */
    public function grouped(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_sale_bill'), 403);
        return view('sale-bills.grouped');
    }

    /** Sale search (cross-module, with payment status filter) */
    public function search(PermissionService $ps)
    {
        abort_unless($ps->hasPermission('im_sale_search'), 403);
        return view('sale-bills.search');
    }
}
