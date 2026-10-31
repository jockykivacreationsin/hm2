<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\BillingCharge;
use App\Models\BillingChargeDetail;
use App\Models\BillingChargePayment;
use App\Models\Charge;
use App\Models\ChargeDepartment;
use App\Models\PatientCategory;
use App\Http\Requests\StoreBillingRequest;
use App\Http\Requests\UpdateBillingRequest;
use App\Services\PermissionService;
use App\Services\Abdm\CareContextService;
use App\Services\Abdm\LinkTokenService;
use Illuminate\Support\Facades\DB;
use App\Support\CurrentUser;

class BillingController extends Controller
{
    public function index(PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('m_billing'), 403);

        $chargeDepartments = ChargeDepartment::orderBy('sort_order')->get();
        $patientCategories = PatientCategory::orderBy('id')->get();
        $charges           = Charge::with('chargeDepartment')->orderBy('charge_name')->get();

        return view('billing.index', compact('chargeDepartments', 'patientCategories', 'charges', 'permissions'));
    }

    public function data(Request $request): JsonResponse
    {
        $draw = (int) $request->input('draw', 1);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 100);

        $report_period = $request->input('report_period', 'Daily');
        $search_date = $request->input('search_date');
        $search_month = $request->input('search_month');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $charge_department_id = $request->input('charge_department_id');
        $searchValue = $request->input('search.value');

        $query = BillingCharge::with('chargeDepartment')
            ->where(function ($q) {
                $q->where('module_id', 0)->orWhereNull('module_id');
            });

        if ($report_period === 'Daily' && !empty($search_date)) {
            $d = date('Y-m-d', strtotime($search_date));
            if ($d !== '1970-01-01') {
                $query->whereDate('charge_date_time', $d);
            }
        } elseif ($report_period === 'Monthly' && !empty($search_month)) {
            $year = now()->year;
            $query->whereMonth('charge_date_time', $search_month)->whereYear('charge_date_time', $year);
        } elseif ($report_period === 'Periodic' && !empty($from_date) && !empty($to_date)) {
            $fd = date('Y-m-d', strtotime($from_date));
            $td = date('Y-m-d', strtotime($to_date));
            if ($fd !== '1970-01-01' && $td !== '1970-01-01') {
                $query->whereBetween(DB::raw('DATE(charge_date_time)'), [$fd, $td]);
            }
        }

        if (!empty($charge_department_id)) {
            $query->where('charge_department_id', $charge_department_id);
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('module', 'like', "%{$searchValue}%")
                  ->orWhere('charge_title', 'like', "%{$searchValue}%")
                  ->orWhere('id', $searchValue);
            });
        }

        $recordsTotal = BillingCharge::where(function ($q) {
            $q->where('module_id', 0)->orWhereNull('module_id');
        })->count();

        $recordsFiltered = (clone $query)->count();

        $records = $query->orderByDesc('id')
            ->skip($start)
            ->take($length)
            ->get();

        $ps = app(PermissionService::class);
        $canEdit   = $ps->hasPermission('b_billing_edit');
        $canDelete = $ps->hasPermission('b_billing_delete');
        $canCancel = $ps->hasPermission('b_billing_edit');

        $data = [];
        foreach ($records as $row) {
            $id = $row->id;
            $isCancelled = ($row->invoice_status ?? 'issued') === 'cancelled';
            $formattedDate = $row->charge_date_time ? \Carbon\Carbon::parse($row->charge_date_time)->format('d-m-Y h:i A') : '';
            $action = '<a href="' . url('rpt-bill.php?id=' . $id) . '" target="_blank" class="small-action-btn" data-bs-toggle="tooltip" data-bs-placement="top" title="Print"><i class="fas fa-print"></i></a>';
            if ($canEdit && !$isCancelled) {
                $action .= ' <a href="javascript:void(0)" class="small-action-btn" onclick="editBillModal(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>';
            }
            if ($canCancel && !$isCancelled) {
                $action .= ' <a href="javascript:void(0)" class="small-action-btn text-warning" onclick="cancelInvoice(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Cancel Invoice"><i class="fas fa-ban"></i></a>';
            }
            if ($canDelete) {
                $action .= ' <a href="javascript:void(0)" class="small-action-btn" onclick="deleteBill(' . $id . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>';
            }
            $data[] = [
                'DT_RowId'              => 'row_' . $id,
                'action'                => $action,
                'id'                    => $id,
                'billing_date_time'     => $formattedDate,
                'patient_name'          => $row->module,
                'bill_title'            => $row->charge_title,
                'department_name'       => $row->chargeDepartment?->department_name ?? '',
                'amount'                => number_format((float)$row->amount, 2),
                'discount'              => number_format((float)$row->discount, 2),
                'addon_discount_amount' => number_format((float)$row->addon_discount_amount, 2),
                'total'                 => number_format((float)$row->total, 2),
                'paid_amount'           => number_format((float)$row->paid_amount, 2),
                'payment_mode'          => $row->payment_mode ?? '',
                'invoice_status'        => $row->invoice_status ?? 'issued',
            ];
        }

        return response()->json([
            'draw'                 => $draw,
            'recordsTotal'         => $recordsTotal,
            'recordsFiltered'      => $recordsFiltered,
            'data'                 => $data,
            'iTotalRecords'        => $recordsTotal,
            'iTotalDisplayRecords' => $recordsFiltered,
            'aaData'               => $data,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $bill = BillingCharge::with(['chargeDepartment', 'details.charge', 'payments'])->findOrFail($id);

        $tableHtml = '';
        $nor = 0;
        foreach ($bill->details as $d) {
            $nor++;
            $chargeName = $d->charge?->charge_name ?? 'Charge #' . $d->charge_id;
            $tableHtml .= '<tr id="chr_' . $nor . '">';
            $tableHtml .= '<td class="tr-sm-action-buttons center"><a href="javascript:void(0)" onclick="removeEBillChargeDetail(' . $nor . ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>';
            $tableHtml .= '<td>' . e($chargeName) . '<input type="hidden" name="charge_id_value[]" value="' . $d->charge_id . '"><input type="hidden" name="charge_name_value[]" value="' . e($chargeName) . '"></td>';
            $tableHtml .= '<td class="aright"><input type="hidden" id="echamount_' . $nor . '" name="amount_value[]" value="' . $d->amount . '">' . number_format((float)$d->amount, 2) . '</td>';
            $tableHtml .= '<td class="aright"><input type="hidden" id="echdiscper_' . $nor . '" name="discount_percentage_value[]" value="' . $d->discount_percentage . '">' . $d->discount_percentage . '</td>';
            $tableHtml .= '<td class="aright"><input type="hidden" id="echdiscamount_' . $nor . '" name="discount_amount_value[]" value="' . $d->discount_amount . '">' . number_format((float)$d->discount_amount, 2) . '</td>';
            $tableHtml .= '<td class="aright"><input type="hidden" id="echquantity_' . $nor . '" name="quantity_value[]" value="' . $d->quantity . '">' . $d->quantity . '</td>';
            $tableHtml .= '<td class="aright"><input type="hidden" id="echtotal_' . $nor . '" name="total_value[]" value="' . $d->total . '">' . number_format((float)$d->total, 2) . '</td>';
            $tableHtml .= '</tr>';
        }

        return response()->json([
            'success'    => true,
            'edit'       => 'Yes',
            'nor'        => $nor,
            'table_html' => $tableHtml,
            'row'        => [
                'id'                   => $bill->id,
                'charge_date_time'     => $bill->charge_date_time ? \Carbon\Carbon::parse($bill->charge_date_time)->format('d-m-Y h:i a') : '',
                'charge_title'         => $bill->charge_title,
                'module'               => $bill->module,
                'charge_department_id' => $bill->charge_department_id,
                'department_name'      => $bill->chargeDepartment?->department_name ?? '',
                'amount'               => $bill->amount,
                'discount'             => $bill->discount,
                'addon_discount_mode'  => $bill->addon_discount_mode ?? 'Percentage',
                'addon_discount_value' => $bill->addon_discount_value ?? 0,
                'addon_discount_amount'=> $bill->addon_discount_amount ?? 0,
                'total'                => $bill->total,
                'paid_amount'          => $bill->paid_amount,
                'payment_mode'         => $bill->payment_mode,
                'invoice_status'       => $bill->invoice_status ?? 'issued',
            ],
            'payments'   => $bill->payments,
        ]);
    }

    public function getBillingSplitPayments(int $id): JsonResponse
    {
        $payments = BillingChargePayment::where('billing_charge_id', $id)->get();
        $total = 0;
        $html = '';
        $i = 0;
        foreach ($payments as $p) {
            $i++;
            $pm = e($p->payment_mode);
            $amt = (float) $p->amount;
            $total += $amt;
            $html .= "<tr id='e_sp_{$i}'><td><a href='javascript:void(0)' onclick='delESplitPayment({$i});'>x</a></td><td>{$pm}<input name='sp_mode[]' type='hidden' value='{$pm}'></td><td>{$amt}<input id='e_sp_amount_{$i}' name='sp_amount[]' type='hidden' value='{$amt}'></td></tr>";
        }
        return response()->json([
            'success' => true,
            'html'    => $html,
            'nor'     => $i,
            'total'   => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // General Billing submission (the Add Bill modal); a module-specific charge carries module_id instead.
        if ($request->has('save_billing') || $request->has('bill_title') || !$request->has('module_id')) {
            return $this->storeBill(app(StoreBillingRequest::class));
        }

        return $this->storeModuleCharge($request);
    }

    /** Add Bill modal: a stand-alone bill with its own line items and split payments. */
    public function storeBill(StoreBillingRequest $request): JsonResponse
    {
        $billing_date_time = $request->input('billing_date_time');
        $billing_date_time = $billing_date_time ? date('Y-m-d H:i:s', strtotime($billing_date_time)) : now()->toDateTimeString();
        $patient_name = $request->input('patient_name', '');
        $bill_title = $request->input('bill_title', '');
        $charge_department_id = (int) $request->input('charge_department_id');

        $amount = (float) $request->input('bill_charges_amount', $request->input('amount', 0));
        $discount = (float) $request->input('bill_charges_discount', $request->input('discount', 0));
        $addon_discount_mode = $request->input('addon_discount_mode', 'Percentage');
        $addon_discount_value = (float) $request->input('addon_discount_value', 0);
        $addon_discount_amount = (float) $request->input('bill_charges_addon_discount', $request->input('addon_discount_amount', 0));
        $total = (float) $request->input('bill_charges_total', $request->input('total', 0));
        $paid_amount = (float) $request->input('paid_amount', 0);
        $payment_mode = $request->input('payment_mode', '');
        $ih = $request->has('ih') ? 'Yes' : '';

        $invoice_number = $this->nextInvoiceNumber($charge_department_id);
        $invoice_number_two = $ih === 'Yes' ? $this->nextGlobalInvoiceNumber() : 0;

        DB::beginTransaction();
        try {
            $charge = BillingCharge::create([
                'invoice_number'        => $invoice_number,
                'invoice_number_two'    => $invoice_number_two,
                'charge_date_time'      => $billing_date_time,
                'module'                => $patient_name,
                'module_id'             => 0,
                'charge_title'          => $bill_title,
                'charge_department_id'  => $charge_department_id,
                'amount'                => $amount,
                'discount'              => $discount,
                'addon_discount_mode'   => $addon_discount_mode,
                'addon_discount_value'  => $addon_discount_value,
                'addon_discount_amount' => $addon_discount_amount,
                'total'                 => $total,
                'paid_amount'           => $paid_amount,
                'payment_mode'          => $payment_mode,
                'is_hidden'             => $ih,
                'created_on'            => now()->toDateTimeString(),
                'user_id'               => CurrentUser::id() ?? 1,
            ]);

            // Line items
            $charge_ids = $request->input('charge_id_value', []);
            $quantities = $request->input('quantity_value', []);
            $amounts = $request->input('amount_value', []);
            $disc_pers = $request->input('discount_percentage_value', []);
            $disc_amts = $request->input('discount_amount_value', []);
            $totals = $request->input('total_value', []);

            for ($i = 0; $i < count($charge_ids); $i++) {
                BillingChargeDetail::create([
                    'module_charge_id'    => $charge->id,
                    'charge_id'           => $charge_ids[$i],
                    'quantity'            => $quantities[$i] ?? 1,
                    'amount'              => $amounts[$i] ?? 0,
                    'discount_percentage' => $disc_pers[$i] ?? 0,
                    'discount_amount'     => $disc_amts[$i] ?? 0,
                    'total'               => $totals[$i] ?? 0,
                ]);
            }

            // Payments
            $sp_modes = $request->input('sp_mode', []);
            $sp_amts = $request->input('sp_amount', []);
            for ($i = 0; $i < count($sp_modes); $i++) {
                BillingChargePayment::create([
                    'payment_date_time' => $billing_date_time,
                    'billing_charge_id' => $charge->id,
                    'payment_mode'      => $sp_modes[$i],
                    'amount'            => $sp_amts[$i],
                ]);
            }

            DB::commit();
            return response()->json([
                'success'           => true,
                'message'           => 'Bill created successfully.',
                'billing_id'        => $charge->id,
                'billing_charge_id' => $charge->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /** Module-specific charge (OPD, IPD, DCD). */
    private function storeModuleCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module'              => 'required|in:OPD,IPD,DCD',
            'module_id'           => 'required|integer',
            'charge_date_time'    => 'required|date',
            'charge_title'        => 'required|string|max:255',
            'charge_department_id'=> 'required|integer|exists:charge_departments,id',
            'amount'              => 'required|numeric|min:0',
            'discount'            => 'nullable|numeric|min:0',
            'paid_amount'         => 'nullable|numeric|min:0',
            'payment_mode'        => 'nullable|string',
            'charges'             => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $invoiceNumber = $this->nextInvoiceNumber($validated['charge_department_id']);
            $invoiceNumberTwo = $this->nextGlobalInvoiceNumber();

            $addon_discount_mode  = $request->input('addon_discount_mode', 'Percentage');
            $addon_discount_value = (float) $request->input('addon_discount_value', 0);
            $amount   = (float) $validated['amount'];
            $discount = (float) ($validated['discount'] ?? 0);
            $addon_discount_amount = $addon_discount_mode === 'Percentage'
                ? round($amount * $addon_discount_value / 100, 2)
                : $addon_discount_value;
            $total = $amount - $discount - $addon_discount_amount;

            $charge = BillingCharge::create([
                'invoice_number'       => $invoiceNumber,
                'invoice_number_two'   => $invoiceNumberTwo,
                'charge_date_time'     => $validated['charge_date_time'],
                'module'               => $validated['module'],
                'module_id'            => $validated['module_id'],
                'charge_title'         => $validated['charge_title'],
                'charge_department_id' => $validated['charge_department_id'],
                'amount'               => $amount,
                'discount'             => $discount,
                'addon_discount_mode'  => $addon_discount_mode,
                'addon_discount_value' => $addon_discount_value,
                'addon_discount_amount'=> $addon_discount_amount,
                'total'                => $total,
                'paid_amount'          => $validated['paid_amount'] ?? 0,
                'payment_mode'         => $validated['payment_mode'] ?? '',
                'is_hidden'            => 'Yes',
                'created_on'           => now()->toDateTimeString(),
                'user_id'              => CurrentUser::id() ?? 1,
            ]);

            if (!empty($validated['charges'])) {
                foreach ($validated['charges'] as $item) {
                    BillingChargeDetail::create([
                        'module_charge_id'    => $charge->id,
                        'charge_id'           => $item['charge_id'] ?? 0,
                        'quantity'            => $item['quantity'] ?? 1,
                        'amount'              => $item['amount'] ?? 0,
                        'discount_percentage' => $item['discount_percentage'] ?? 0,
                        'discount_amount'     => $item['discount_amount'] ?? 0,
                        'total'               => $item['total'] ?? 0,
                    ]);
                }
            }

            if ($request->has('payments')) {
                foreach ($request->input('payments', []) as $payment) {
                    BillingChargePayment::create([
                        'billing_charge_id' => $charge->id,
                        'payment_date_time' => $validated['charge_date_time'],
                        'payment_mode'      => $payment['mode'],
                        'amount'            => $payment['amount'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success'           => true,
                'message'           => 'Charge saved.',
                'billing_charge_id' => $charge->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function update(Request $request, int $id = 0): JsonResponse
    {
        $id = $id ?: (int) ($request->input('billing_id') ?: $request->input('id'));

        return $this->updateBill(app(UpdateBillingRequest::class), $id);
    }

    /** Edit Bill modal. */
    public function updateBill(UpdateBillingRequest $request, int $id): JsonResponse
    {
        $charge = BillingCharge::findOrFail($id);
        $billing_date_time = $request->input('billing_date_time');
        $billing_date_time = $billing_date_time ? date('Y-m-d H:i:s', strtotime($billing_date_time)) : $charge->charge_date_time;
        $patient_name = $request->input('patient_name', $charge->module);
        $bill_title = $request->input('bill_title', $charge->charge_title);
        $charge_department_id = (int) $request->input('charge_department_id', $charge->charge_department_id);

        $amount = (float) $request->input('bill_charges_amount', $charge->amount);
        $discount = (float) $request->input('bill_charges_discount', $charge->discount);
        $addon_discount_mode = $request->input('addon_discount_mode', $charge->addon_discount_mode);
        $addon_discount_value = (float) $request->input('addon_discount_value', $charge->addon_discount_value);
        $addon_discount_amount = (float) $request->input('bill_charges_addon_discount', $charge->addon_discount_amount);
        $total = (float) $request->input('bill_charges_total', $charge->total);
        $paid_amount = (float) $request->input('paid_amount', $charge->paid_amount);
        $payment_mode = $request->input('payment_mode', $charge->payment_mode);

        DB::beginTransaction();
        try {
            $charge->update([
                'charge_date_time'      => $billing_date_time,
                'module'                => $patient_name,
                'charge_title'          => $bill_title,
                'charge_department_id'  => $charge_department_id,
                'amount'                => $amount,
                'discount'              => $discount,
                'addon_discount_mode'   => $addon_discount_mode,
                'addon_discount_value'  => $addon_discount_value,
                'addon_discount_amount' => $addon_discount_amount,
                'total'                 => $total,
                'paid_amount'           => $paid_amount,
                'payment_mode'          => $payment_mode,
            ]);

            if ($request->has('charge_id_value')) {
                BillingChargeDetail::where('module_charge_id', $id)->delete();
                $charge_ids = $request->input('charge_id_value', []);
                $quantities = $request->input('quantity_value', []);
                $amounts = $request->input('amount_value', []);
                $disc_pers = $request->input('discount_percentage_value', []);
                $disc_amts = $request->input('discount_amount_value', []);
                $totals = $request->input('total_value', []);

                for ($i = 0; $i < count($charge_ids); $i++) {
                    BillingChargeDetail::create([
                        'module_charge_id'    => $id,
                        'charge_id'           => $charge_ids[$i],
                        'quantity'            => $quantities[$i] ?? 1,
                        'amount'              => $amounts[$i] ?? 0,
                        'discount_percentage' => $disc_pers[$i] ?? 0,
                        'discount_amount'     => $disc_amts[$i] ?? 0,
                        'total'               => $totals[$i] ?? 0,
                    ]);
                }
            }

            if ($request->has('sp_mode')) {
                BillingChargePayment::where('billing_charge_id', $id)->delete();
                $sp_modes = $request->input('sp_mode', []);
                $sp_amts = $request->input('sp_amount', []);
                for ($i = 0; $i < count($sp_modes); $i++) {
                    BillingChargePayment::create([
                        'payment_date_time' => $billing_date_time,
                        'billing_charge_id' => $id,
                        'payment_mode'      => $sp_modes[$i],
                        'amount'            => $sp_amts[$i],
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'result'  => 'OK',
                'message' => 'Bill updated successfully.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error(static::class . ' failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    public function getModuleCharges(Request $request): JsonResponse
    {
        $module   = $request->input('module');
        $moduleId = $request->input('module_id');

        $charges = BillingCharge::with(['details.charge', 'payments'])
            ->where('module', $module)
            ->where('module_id', $moduleId)
            ->where('is_hidden', 'Yes')
            ->orderByDesc('charge_date_time')
            ->get();

        return response()->json(['success' => true, 'data' => $charges]);
    }

    public function destroy(int $id = 0): JsonResponse
    {
        $id = $id ?: (int) request()->input('id');
        $charge = BillingCharge::findOrFail($id);
        BillingChargeDetail::where('module_charge_id', $id)->delete();
        BillingChargePayment::where('billing_charge_id', $id)->delete();
        $charge->delete();

        return response()->json([
            'success' => true,
            'result'  => 'OK',
            'message' => 'Bill deleted successfully.'
        ]);
    }

    /**
     * Cancel every billing_charges row that shares the same invoice_number as the given row.
     * Cancelled invoices cannot be edited but can still be viewed/printed.
     */
    public function cancelInvoice(int $id): JsonResponse
    {
        $charge = BillingCharge::findOrFail($id);

        abort_unless(app(PermissionService::class)->hasPermission('b_billing_edit'), 403);

        if (($charge->invoice_status ?? 'issued') === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Invoice is already cancelled.']);
        }

        $invoiceNumber = $charge->invoice_number;

        DB::table('billing_charges')
            ->where('invoice_number', $invoiceNumber)
            ->update(['invoice_status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Invoice cancelled successfully.']);
    }

    private function nextInvoiceNumber(int $deptId): int
    {
        return (BillingCharge::where('charge_department_id', $deptId)->max('invoice_number') ?? 0) + 1;
    }

    private function nextGlobalInvoiceNumber(): int
    {
        return (BillingCharge::max('invoice_number_two') ?? 0) + 1;
    }
}
