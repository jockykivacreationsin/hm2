@extends('layouts.app')
@section('title', 'DCD Charges')

@section('content')
@if(!$ps->hasPermission('im_dcd_charges'))
    <div class="themebody-wrap">
        <div class="no-access">
            <i class="fa-duotone fa-shield-halved"></i>
            <p>Access Denied</p>
        </div>
    </div>
@else
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('dcd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Add Charges</div>
                    <div class="ipd-buttons">
                        <ul>
                            <li>
                                <input type="hidden" id="collection_amount" value="0">
                                <a id="collect_dcd_payments_btn" href="javascript:void(0)" onclick="collectDCDPaymentsModal()" style="display: none;"><i class="fa-solid fa-hand-holding-dollar"></i> Collect <span id="collection_amount_text">0</span></a> 
                                <input type="hidden" id="show_hide_collection_cb" value="0">
                                <a href="javascript:void(0)" onclick="showHideDCDCollectionCB()"><i class="fa-solid fa-money-bill-trend-up"></i> Collection</a> 
                                @if($ps->hasPermission('b_dcd_invoice'))
                                    <a href="javascript:void(0)" onclick="showDCDInvoiceOptions({{ $dcd->id }})"><i class="fal fa-receipt"></i> Invoice</a>
                                @endif 
                                @if($ps->hasPermission('b_dcd_new_charge'))
                                    <a href="javascript:void(0)" onclick="addDCDChargeModal()"><i class="fa fa-plus"></i> New</a>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th id="action-td" class="no-sort tr3btns"></th>
                                    <th>No</th>
                                    <th>Date Time</th>
                                    <th>Title</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Discount</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Payment Mode</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($charges as $charge)
                                @php
                                    $balance_amount = floatval($charge->total - $charge->paid_amount);
                                    $charge_id = (int) $charge->id;
                                    $bill_number = $charge->invoice_number;
                                    $charge_date_time = \Carbon\Carbon::parse($charge->charge_date_time)->format('d-m-Y h:i A');
                                    $charge_title = $charge->charge_title;
                                    $department_name = $charge->chargeDepartment?->department_name ?? '';
                                    $amount = number_format((float) $charge->amount, 2, '.', '');
                                    $discount = number_format((float) $charge->discount, 2, '.', '');
                                    $total = number_format((float) $charge->total, 2, '.', '');
                                    $paid_amount = number_format((float) $charge->paid_amount, 2, '.', '');
                                    $payment_mode = $charge->payment_mode;
                                    $is_hidden_class = ($charge->is_hidden == 'Yes') ? 'ihclass' : '';
                                @endphp
                                <tr id="row_{{ $charge_id }}">
                                    <td class="tr-sm-action-buttons center">
                                        @if($balance_amount > 0)
                                            <label class="checkbox-label inline-checkbox" style="display: none;"><input id="cb_{{ $charge_id }}" class="collection_cb" name="cb" type="checkbox" value="{{ $charge_id }}" onchange="addToDCDCollection(this.value)"><span></span></label>
                                        @endif 
                                        <a target="_blank" href="{{ route('pdf.receipt', $charge_id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Print"><i class="fas fa-print"></i></a> 
                                        @if($ps->hasPermission('b_dcd_edit_charge'))
                                            <a href="javascript:void(0)" onclick="editDCDChargeModal({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                        @if($ps->hasPermission('b_dcd_delete_charge'))
                                            <a href="javascript:void(0)" onclick="deleteDCDCharge({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                        @endif
                                    </td>
                                    <td class="{{ $is_hidden_class }}">{{ $bill_number }}</td>
                                    <td>{{ $charge_date_time }}</td>
                                    <td>{{ $charge_title }}</td>
                                    <td>{{ $department_name }}</td>
                                    <td>{{ $amount }}</td>
                                    <td>{{ $discount }}</td>
                                    <td>{{ $total }}<input id="balance_amount_{{ $charge_id }}" type="hidden" value="{{ $balance_amount }}"></td>
                                    <td>{{ $paid_amount }}</td>
                                    <td>{{ $payment_mode }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="collectPaymentsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="">
                <input type="hidden" id="dcd_id" name="dcd_id" value="{{ $dcd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-3">
                            <div class="form-group local-forms">
                                <label>Date Time <span class="req-star">*</span></label>
                                <input type="text" id="payment_date_time" name="payment_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i a') }}" required>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms mb-0">
                                <label>Payment Mode</label>
                                <select class="form-control" id="split_collection_mode">
                                    <option></option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-3">
                            <div class="form-group local-forms mb-0">
                                <label>Paid Amount<span class="req-star">*</span></label>
                                <input type="number" id="split_collection_amount" step="any" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm-2">
                            <div class="form-group local-forms">
                                <button type="button" class="ssbtn" onclick="addSplitCollection()"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <input type="hidden" id="scnor" value="0">
                        <div class="col-12 col-sm-12">
                            <div class="table-responsive">  
                                <table id="split_collection_tbl" class="table table-bordered table-sm table-text-sm">
                                    <thead>
                                        <tr>
                                            <th class="tr1btn"></th>
                                            <th>Mode</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                    <tfoot>
                                        <tr><th class="aright" colspan="2">Total<input type="hidden" id="split_collection_total_amount" value="0"></th><th class="aright" id="split_collection_total">0.00</th></tr>
                                        <tr><th class="aright" colspan="2">Remaining</th><th class="aright" id="split_collection_remaining" style="color: #dc3545;">0.00</th></tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="button" onclick="collectDCDSelectedAmount()">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="add_dcd_charges_form" action="{{ route('dcd.charges.store', $dcd->id) }}" method="POST">
    @csrf
    <div class="modal fade" id="addDCDChargeModal">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Charges</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>
                        <input type="hidden" id="dcd_id" name="dcd_id" value="{{ $dcd->id }}">
                <input type="hidden" id="patient_category_id" value="{{ $patient_category_id }}">
                <input type="hidden" id="charge_discount_amount" name="discount_amount">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Date Time <span class="req-star">*</span></label>
                                                <input type="text" name="charge_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i a') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Department <span class="req-star">*</span></label>
                                                <select class="form-control" id="a_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" required>
                                                    <option></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <input type="hidden" id="chnor" value="0">
                                    <div class="row">
                                        <div class="col-12 col-sm-3">
                                            <div class="form-group local-forms">
                                                <label>Charge <span class="req-star">*</span></label>
                                                <select class="form-control" id="a_ad_charge_id" name="charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#a_charge_department_id" onchange="showDCDChargeAmount(this.value, {{ $patient_category_id }})">
                                                    <option></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Amount <span class="req-star">*</span></label>
                                                <input type="number" id="charge_amount" name="amount" class="form-control" onkeyup="calcDCDChargeTotal()">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Disc(%) <span class="req-star">*</span></label>
                                                <input type="number" id="charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcDCDChargeTotal()">
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>QNT<span class="req-star">*</span></label>
                                                <input type="number" id="charge_quantity" name="quantity" class="form-control" onkeyup="calcDCDChargeTotal()">
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Total<span class="req-star">*</span></label>
                                                <input type="number" id="charge_total" name="total" class="form-control" readonly>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-1">
                                            <div class="form-group local-forms">
                                                <button type="button" class="ssbtn" onclick="addDCDChargeDetail()"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 col-sm-12">
                                            <div class="table-responsive">  
                                                <table id="dcd_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                                    <thead>
                                                        <tr>
                                                            <th class="tr1btn"></th>
                                                            <th>Department</th>
                                                            <th>Name</th>
                                                            <th>Amount</th>
                                                            <th>Disc(%)</th>
                                                            <th>Discount</th>
                                                            <th>QNT</th>
                                                            <th>Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>

                                                    </tbody>
                                                    <tfoot>
                                                        <tr><th class="aright" colspan="7">Amount<input type="hidden" id="dcd_charges_amount" name="dcd_charges_amount" value="0"></th><th class="aright" id="total_amount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Discount<input type="hidden" id="dcd_charges_discount" name="dcd_charges_discount" value="0"></th><th class="aright" id="total_discount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Addon Discount<input type="hidden" id="dcd_charges_addon_discount" name="dcd_charges_addon_discount" value="0"></th><th class="aright" id="addon_discount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Total<input type="hidden" id="dcd_charges_total" name="dcd_charges_total" value="0"></th><th class="aright" id="grand_total">0.00</th></tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-10" style="justify-content: flex-end;">
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms mb-0">
                                                <label>Discount</label>
                                                <select class="form-control" id="addon_discount_mode" name="addon_discount_mode" onchange="calcDCDAddonDiscount()">
                                                    <option value="Percentage">Percentage</option>
                                                    <option value="Amount">Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms mb-0">
                                                <label>Discount Value<span class="req-star">*</span></label>
                                                <input type="number" id="addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcDCDAddonDiscount()" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <input type="hidden" id="payment_mode" name="payment_mode">
                                            <div class="form-group local-forms mb-0">
                                                <label>Paid Amount<span class="req-star">*</span></label>
                                                <div class="input-group">
                                                    <input type="number" id="paid_amount" name="paid_amount" step="any" class="form-control" readonly required>
                                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addSplitPaymentModal">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="form-group local-forms mb-0">
                                <label>Prescriptions</label>
                                <select class="form-control" id="charge_prescriptions" name="charge_prescriptions" onchange="showPrescriptionDetails(this.value, 'DCD')">
                                    <option></option>
                                    @foreach($prescriptions as $prs)
                                    <option value="{{ $prs->id }}">{{ $prs->id }} - {{ \Carbon\Carbon::parse($prs->prescription_date_time)->format('d-m-Y h:i A') }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group local-forms mt-20">
                                <label>Prescription</label>
                                <select class="form-control" id="prescription_charge_types" name="prescription_charge_types" onchange="showPrescriptionTD()">
                                </select>
                            </div>

                            <div id="prescription_td">

                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <label class="checkbox-label"><input name="ih" type="checkbox" value="Yes" checked><span></span></label>
                    <button class="btn btn-primary ml-10" type="submit">Save</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addSplitPaymentModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payments</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>

                <div class="modal-body">
                    <div class="row mt-10" style="justify-content: flex-end;">
                        <div class="col-12 col-sm-6">
                            <div class="form-group local-forms mb-0">
                                <label>Payment Mode</label>
                                <select class="form-control" id="split_payment_mode">
                                    <option></option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms mb-0">
                                <label>Paid Amount<span class="req-star">*</span></label>
                                <input type="number" id="split_payment_amount" step="any" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm-2">
                            <div class="form-group local-forms">
                                <button type="button" class="ssbtn" onclick="addSplitPayment()"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <input type="hidden" id="spnor" value="0">
                        <div class="col-12 col-sm-12">
                            <div class="table-responsive">  
                                <table id="split_payments_tbl" class="table table-bordered table-sm table-text-sm">
                                    <thead>
                                        <tr>
                                            <th class="tr1btn"></th>
                                            <th>Mode</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                    <tfoot>
                                        <tr><th class="aright" colspan="2">Total<input type="hidden" id="split_payment_total_amount" value="0"></th><th class="aright" id="split_payment_total">0.00</th></tr>
                                        <tr><th class="aright" colspan="2">Remaining</th><th class="aright" id="split_payment_remaining" style="color: #dc3545;">0.00</th></tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="button" onclick="applySplitPayments()">Apply</button>
                </div>
            </div>
        </div>
    </div>
</form>

<form id="edit_dcd_charges_form" data-action-template="{{ route('dcd.charges.update', ['id' => '__ID__']) }}" method="POST">
    @csrf
    <div class="modal fade" id="editDCDChargeModal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Charges</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>

                
                <input type="hidden" id="e_dcd_id" name="dcd_id" value="{{ $dcd->id }}">
                <input type="hidden" id="dcd_charge_id" name="dcd_charge_id" value="">
                <input type="hidden" id="e_charge_discount_amount" name="discount_amount">
                <input type="hidden" id="echargedepartmentid" name="charge_department_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="charge_date_time" name="charge_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Department <span class="req-star">*</span></label>
                                        <select class="form-control" id="e_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" onchange="updateCDIField(this.value, 'echargedepartmentid')" required>
                                            <option></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Title <span class="req-star">*</span></label>
                                        <input type="text" id="charge_title" name="charge_title" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <input type="hidden" id="echnor" value="0">
                            <div class="row">
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Charge <span class="req-star">*</span></label>
                                        <select class="form-control" id="e_ad_charge_id" name="charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#e_charge_department_id" onchange="showEDCDChargeAmount(this.value, {{ $patient_category_id }})">
                                            <option></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Amount <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_amount" name="amount" class="form-control" onkeyup="calcEDCDChargeTotal()">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Disc(%) <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcEDCDChargeTotal()">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>QNT<span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_quantity" name="quantity" class="form-control" onkeyup="calcEDCDChargeTotal()">
                                    </div>
                                </div>

                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Total<span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_total" name="total" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addEDCDChargeDetail()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_dcd_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Name</th>
                                                    <th>Amount</th>
                                                    <th>Disc(%)</th>
                                                    <th>Discount</th>
                                                    <th>QNT</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                            </tbody>
                                            <tfoot>
                                                <tr><th class="aright" colspan="6">Amount<input type="hidden" id="e_dcd_charges_amount" name="dcd_charges_amount" value="0"></th><th class="aright" id="e_total_amount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Discount<input type="hidden" id="e_dcd_charges_discount" name="dcd_charges_discount" value="0"></th><th class="aright" id="e_total_discount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Addon Discount<input type="hidden" id="e_dcd_charges_addon_discount" name="dcd_charges_addon_discount" value="0"></th><th class="aright" id="e_addon_discount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Total<input type="hidden" id="e_dcd_charges_total" name="dcd_charges_total" value="0"></th><th class="aright" id="e_grand_total">0.00</th></tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-10" style="justify-content: flex-end;">
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <label>Discount</label>
                                        <select class="form-control" id="e_addon_discount_mode" name="addon_discount_mode" onchange="calcEDCDAddonDiscount()">
                                            <option value="Percentage">Percentage</option>
                                            <option value="Amount">Amount</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <label>Discount Value<span class="req-star">*</span></label>
                                        <input type="number" id="e_addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcEDCDAddonDiscount()" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <input type="hidden" id="e_payment_mode" name="payment_mode">
                                        <label>Paid Amount<span class="req-star">*</span></label>
                                        <div class="input-group">
                                            <input type="number" id="e_paid_amount" name="paid_amount" step="any" class="form-control" readonly required>
                                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#editSplitPaymentModal">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="submit">Save</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editSplitPaymentModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payments</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>

                <div class="modal-body">
                    <div class="row mt-10" style="justify-content: flex-end;">
                        <div class="col-12 col-sm-6">
                            <div class="form-group local-forms mb-0">
                                <label>Payment Mode</label>
                                <select class="form-control" id="e_split_payment_mode">
                                    <option></option>
                                    <option value="Cash">Cash</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Debit Card">Debit Card</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms mb-0">
                                <label>Paid Amount<span class="req-star">*</span></label>
                                <input type="number" id="e_split_payment_amount" step="any" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm-2">
                            <div class="form-group local-forms">
                                <button type="button" class="ssbtn" onclick="addESplitPayment()"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <input type="hidden" id="e_spnor" value="0">
                        <div class="col-12 col-sm-12">
                            <div class="table-responsive">  
                                <table id="e_split_payments_tbl" class="table table-bordered table-sm table-text-sm">
                                    <thead>
                                        <tr>
                                            <th class="tr1btn"></th>
                                            <th>Mode</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                    <tfoot>
                                        <tr><th class="aright" colspan="2">Total<input type="hidden" id="e_split_payment_total_amount" value="0"></th><th class="aright" id="e_split_payment_total">0.00</th></tr>
                                        <tr><th class="aright" colspan="2">Remaining</th><th class="aright" id="e_split_payment_remaining" style="color: #dc3545;">0.00</th></tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="button" onclick="applyESplitPayments()">Apply</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
    window.dcdRoutes = Object.assign(window.dcdRoutes || {}, {
        chargeGet: "{{ route('dcd.charges.get', ['id' => '__ID__']) }}",
        chargeSplitPayments: "{{ route('dcd.charges.split-payments', ['id' => '__ID__']) }}",
        chargeUpdate: "{{ route('dcd.charges.update', ['id' => '__ID__']) }}",
        chargeDestroy: "{{ route('dcd.charges.destroy', ['id' => '__ID__']) }}",
        collectPayments: "{{ route('dcd.charges.collect-payments', $dcd->id) }}"
    });

    // The "+" next to a prescription test/procedure pre-fills the Add Charges modal. Overrides the legacy global in app.js
    // (like opd/charges and ipd/charges do): that one wrote a hidden #chargedepartmentid and looked for a category field,
    // so the visible Department select stayed empty. Uses the shared charges.lookup.* routes.
    function addPrescriptionItemToCharges(charge_id, charge_type) {
        var patientCategoryId = $('#patient_category_id').val();

        $.ajax({
            type: 'GET',
            url: "{{ route('charges.lookup.charge-for-prescription') }}",
            data: { charge_id: charge_id, charge_type: charge_type, patient_category_id: patientCategoryId },
            dataType: 'json',
            success: function (data) {
                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error loading charge details' });
                    return;
                }
                // dept_name and charge_name now come from the single response — no extra round-trips needed
                var dName = data.department_name || ('Department #' + data.charge_department_id);
                var cName = data.charge_name     || ('Charge #'     + data.charge_id);
                $('#a_charge_department_id').append(new Option(dName, data.charge_department_id, true, true)).trigger('change');
                $('#a_ad_charge_id').append(new Option(cName, data.charge_id, true, true)).trigger('change');
                $('#charge_amount').val(data.amount);
                $('#charge_discount_percentage').val(0);
                $('#charge_quantity').val(1);
                calcDCDChargeTotal();
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error adding charge. Please try again.' });
            }
        });
    }

    function showDCDInvoiceOptions(dcdId) {
        Swal.fire({
            title: 'Select Invoice Type',
            text: 'Choose the type of invoice you want to generate',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: '<i class="fas fa-file-alt"></i> Consolidated',
            denyButtonText: '<i class="fas fa-list"></i> Detailed',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            denyButtonColor: '#1e88e5',
            cancelButtonColor: '#6c757d',
            customClass: {
                confirmButton: 'swal-btn-consolidated',
                denyButton: 'swal-btn-detailed'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Open Consolidated Bill
                window.open('{{ route("pdf.dcd.consolidated-bill", ["dcdId" => "__ID__"]) }}'.replace('__ID__', dcdId), '_blank');
            } else if (result.isDenied) {
                // Open Detailed Bill
                window.open('{{ route("pdf.dcd.discharge-detailed-bill", ["dcdId" => "__ID__"]) }}'.replace('__ID__', dcdId), '_blank');
            }
        });
    }

// Opens the consolidated bill(s) the server just created in a new tab (POST so the id list can be long).
    function openDcdSelectedBills(ids) {
        var $f = $('<form>', { action: '{{ route('pdf.dcd.selected-bills') }}', method: 'POST', target: '_blank' })
            .append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }))
            .append($('<input>', { type: 'hidden', name: 'invoice_ids', value: ids.join(',') }));
        $f.appendTo('body').submit().remove();
    }

    appForm.bind('#add_dcd_charges_form', {
        requiredRows: '#dcd_charge_detail_tbl > tbody > tr',
        busyText: 'Wait...',
        onSuccess: function (data) {
            appForm.toast('success', 'Saved!', 'Saved Successfully.');
            openDcdSelectedBills(data.dcd_charge_ids || [data.dcd_charge_id]);
            location.reload();
        }
    });

    appForm.bind('#edit_dcd_charges_form', {
        requiredRows: '#e_dcd_charge_detail_tbl > tbody > tr',
        busyText: 'Wait...',
        url: function (form) { return appForm.fill($(form).data('actionTemplate'), $('#dcd_charge_id').val()); },
        method: 'PUT',
        onSuccess: function (data) {
            appForm.toast('success', 'Saved!', 'Saved Successfully.');
            window.open('{{ route('pdf.receipt', ['chargeId' => '__ID__']) }}'.replace('__ID__', data.dcd_charge_id), '_blank');
            location.reload();
        }
    });
</script>
@endpush
