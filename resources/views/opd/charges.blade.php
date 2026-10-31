@extends('layouts.app')
@section('title', 'OPD Charges')

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('opd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Add Charges</div>
                    @if($ps->hasPermission('b_opd_new_charge'))
                        <div class="ipd-buttons">
                            <ul>
                                <li>
                                    <input type="hidden" id="collection_amount" value="0">
                                    <a id="collect_opd_payments_btn" href="javascript:void(0)" onclick="collectOPDPaymentsModal()" style="display:none;"><i class="fa-solid fa-hand-holding-dollar"></i> Collect <span id="collection_amount_text">0</span></a>
                                    <input type="hidden" id="show_hide_collection_cb" value="0">
                                    <a href="javascript:void(0)" onclick="showHideOPDCollectionCB()"><i class="fa-solid fa-money-bill-trend-up"></i> Collection</a>
                                    <a href="javascript:void(0)" onclick="showOPDInvoiceOptions({{ $opd->id }})"><i class="fal fa-receipt"></i> Invoice</a>
                                    <a href="javascript:void(0)" onclick="addOPDChargeModal()"><i class="fa fa-plus"></i> New</a>
                                </li>
                            </ul>
                        </div>
                    @endif
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
                                    $charge_id = $charge->id;
                                    $balance_amount = floatval($charge->total - $charge->paid_amount);
                                    $is_hidden_class = ($charge->is_hidden == 'Yes') ? 'ihclass' : '';
                                @endphp
                                <tr id="row_{{ $charge_id }}">
                                    <td class="tr-sm-action-buttons center">
                                        @if($balance_amount > 0)
                                            <label class="checkbox-label inline-checkbox" style="display:none;"><input id="cb_{{ $charge_id }}" class="collection_cb" name="cb" type="checkbox" value="{{ $charge_id }}" onchange="addToOPDCollection(this.value)"><span></span></label>
                                        @endif
                                        <a target="_blank" href="{{ route('pdf.receipt', $charge_id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Print"><i class="fas fa-print"></i></a>
                                        @if($ps->hasPermission('b_opd_edit_charge'))
                                            <a href="javascript:void(0)" onclick="editOPDChargeModal({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                        @if($ps->hasPermission('b_opd_delete_charge'))
                                            <a href="javascript:void(0)" onclick="deleteOPDCharge({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                        @endif
                                    </td>
                                    <td class="{{ $is_hidden_class }}">{{ $charge->invoice_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($charge->charge_date_time)->format('d-m-Y h:i A') }}</td>
                                    <td>{{ $charge->charge_title }}</td>
                                    <td>{{ $charge->chargeDepartment?->department_name }}</td>
                                    <td>{{ number_format((float) $charge->amount, 2, '.', '') }}</td>
                                    <td>{{ number_format((float) $charge->discount, 2, '.', '') }}</td>
                                    <td>{{ number_format((float) $charge->total, 2, '.', '') }}<input id="balance_amount_{{ $charge_id }}" type="hidden" value="{{ $balance_amount }}"></td>
                                    <td>{{ number_format((float) $charge->paid_amount, 2, '.', '') }}</td>
                                    <td>{{ $charge->payment_mode }}</td>
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

{{-- Collect Payments Modal --}}
<div class="modal fade" id="collectPaymentsModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="collect_opd_payments_form">
                <input type="hidden" id="opd_id" name="opd_id" value="{{ $opd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-3">
                            <div class="form-group local-forms">
                                <label>Date Time <span class="req-star">*</span></label>
                                <input type="text" id="payment_date_time" name="payment_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
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
                                <label>Paid Amount <span class="req-star">*</span></label>
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
                    <button class="btn btn-primary ml-10" type="button" onclick="collectOPDSelectedAmount()">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add OPD Charge Modal --}}
<form id="add_opd_charges_form">
    @csrf
    <div class="modal fade" id="addOPDChargeModal">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Charges</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>


                <input type="hidden" id="opd_id" name="opd_id" value="{{ $opd->id }}">
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
                                                <input type="text" name="charge_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Department <span class="req-star">*</span></label>
                                                <select class="form-control" id="a_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" required>
                                                    <option></option>
                                                    @foreach($chargeDepts as $dept)
                                                        <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                                    @endforeach
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
                                                <select class="form-control" id="a_ad_charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#a_charge_department_id" name="charge_id" onchange="showOPDChargeAmount(this.value, {{ $patient_category_id }})">
                                                    <option></option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Amount <span class="req-star">*</span></label>
                                                <input type="number" id="charge_amount" name="amount" class="form-control" onkeyup="calcOPDChargeTotal()">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Disc(%) <span class="req-star">*</span></label>
                                                <input type="number" id="charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcOPDChargeTotal()">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>QNT <span class="req-star">*</span></label>
                                                <input type="number" id="charge_quantity" name="quantity" class="form-control" onkeyup="calcOPDChargeTotal()">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms">
                                                <label>Total <span class="req-star">*</span></label>
                                                <input type="number" id="charge_total" name="total" class="form-control" readonly>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-1">
                                            <div class="form-group local-forms">
                                                <button type="button" class="ssbtn" onclick="addOPDChargeDetail()"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 col-sm-12">
                                            <div class="table-responsive">  
                                                <table id="opd_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
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
                                                        <tr><th class="aright" colspan="7">Amount<input type="hidden" id="opd_charges_amount" name="opd_charges_amount" value="0"></th><th class="aright" id="total_amount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Discount<input type="hidden" id="opd_charges_discount" name="opd_charges_discount" value="0"></th><th class="aright" id="total_discount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Addon Discount<input type="hidden" id="opd_charges_addon_discount" name="opd_charges_addon_discount" value="0"></th><th class="aright" id="addon_discount">0.00</th></tr>
                                                        <tr><th class="aright" colspan="7">Total<input type="hidden" id="opd_charges_total" name="opd_charges_total" value="0"></th><th class="aright" id="grand_total">0.00</th></tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-10" style="justify-content: flex-end;">
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms mb-0">
                                                <label>Discount</label>
                                                <select class="form-control" id="addon_discount_mode" name="addon_discount_mode" onchange="calcOPDAddonDiscount()">
                                                    <option value="Percentage">Percentage</option>
                                                    <option value="Amount">Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <div class="form-group local-forms mb-0">
                                                <label>Discount Value <span class="req-star">*</span></label>
                                                <input type="number" id="addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcOPDAddonDiscount()" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-2">
                                            <input type="hidden" id="payment_mode" name="payment_mode">
                                            <div class="form-group local-forms mb-0">
                                                <label>Paid Amount <span class="req-star">*</span></label>
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
                                <select class="form-control" id="charge_prescriptions" name="charge_prescriptions" onchange="showPrescriptionDetails(this.value, 'OPD')">
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

    {{-- Add Split Payment Modal --}}
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
                                <label>Paid Amount <span class="req-star">*</span></label>
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

{{-- Edit OPD Charge Modal --}}
<form id="edit_opd_charges_form">
    @csrf
    <div class="modal fade" id="editOPDChargeModal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Charges</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
                </div>


                <input type="hidden" id="e_opd_id" name="opd_id" value="{{ $opd->id }}">
                <input type="hidden" id="opd_charge_id" name="opd_charge_id" value="">
                <input type="hidden" id="e_charge_discount_amount" name="discount_amount">
                <input type="hidden" id="echargedepartmentid" name="charge_department_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="charge_date_time" name="charge_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Department <span class="req-star">*</span></label>
                                        <select class="form-control" id="e_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" onchange="updateCDIField(this.value, 'echargedepartmentid')" required>
                                            <option></option>
                                            @foreach($chargeDepts as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                            @endforeach
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
                                        <select class="form-control" id="e_ad_charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#e_charge_department_id" name="charge_id" onchange="showEOPDChargeAmount(this.value, {{ $patient_category_id }})">
                                            <option></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Amount <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_amount" name="amount" class="form-control" onkeyup="calcEOPDChargeTotal()">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Disc(%) <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcEOPDChargeTotal()">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>QNT <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_quantity" name="quantity" class="form-control" onkeyup="calcEOPDChargeTotal()">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Total <span class="req-star">*</span></label>
                                        <input type="number" id="e_charge_total" name="total" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addEOPDChargeDetail()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_opd_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
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
                                                <tr><th class="aright" colspan="6">Amount<input type="hidden" id="e_opd_charges_amount" name="opd_charges_amount" value="0"></th><th class="aright" id="e_total_amount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Discount<input type="hidden" id="e_opd_charges_discount" name="opd_charges_discount" value="0"></th><th class="aright" id="e_total_discount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Addon Discount<input type="hidden" id="e_opd_charges_addon_discount" name="opd_charges_addon_discount" value="0"></th><th class="aright" id="e_addon_discount">0.00</th></tr>
                                                <tr><th class="aright" colspan="6">Total<input type="hidden" id="e_opd_charges_total" name="opd_charges_total" value="0"></th><th class="aright" id="e_grand_total">0.00</th></tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-10" style="justify-content: flex-end;">
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <label>Discount</label>
                                        <select class="form-control" id="e_addon_discount_mode" name="addon_discount_mode" onchange="calcEOPDAddonDiscount()">
                                            <option value="Percentage">Percentage</option>
                                            <option value="Amount">Amount</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <label>Discount Value <span class="req-star">*</span></label>
                                        <input type="number" id="e_addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcEOPDAddonDiscount()" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms mb-0">
                                        <input type="hidden" id="e_payment_mode" name="payment_mode">
                                        <label>Paid Amount <span class="req-star">*</span></label>
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

    {{-- Edit Split Payment Modal --}}
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
                                <label>Paid Amount <span class="req-star">*</span></label>
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.data-tbl')) {
        $('.data-tbl').DataTable({
            responsive: true,
            order: [[2, 'desc']]
        });
    }

    if ($.fn.datetimepicker) {
        $('.datetimepicker').datetimepicker({
            format: 'd-m-Y h:i a',
            timepicker: true
        });
    }

    initSelect2Dropdowns();

    $('#addOPDChargeModal').on('hidden.bs.modal', function() {
        $('#add_opd_charges_form button[type="submit"]').prop('disabled', false).html('Save');
    });

    $('#editOPDChargeModal').on('hidden.bs.modal', function() {
        $('#edit_opd_charges_form button[type="submit"]').prop('disabled', false).html('Save');
    });

    $("#add_opd_charges_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var tbl_list = $('#opd_charge_detail_tbl >tbody >tr').length;
        if (tbl_list == 0) {
            Swal.fire({
                icon: "error",
                title: "Required!",
                text: "Please Enter Some Data",
                showConfirmButton: false,
                timer: 1500
            });
            return;
        }

        var $submitBtn = $('#add_opd_charges_form button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('Wait...');

        $.ajax({
            url: "{{ route('opd.charges.store', $opd->id) }}",
            type: "POST",
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                if (data.success || data.opd_charge_id || data.opd_charge_ids) {
                    $('#addOPDChargeModal').modal('hide');
                    Swal.fire({
                        icon: "success",
                        title: "Saved!",
                        text: "Charge saved successfully",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    var opd_charge_ids = data.opd_charge_ids || [data.opd_charge_id];
                    var $billForm = $('<form>', {
                        action: "{{ route('pdf.opd.selected-bills') }}",
                        method: 'POST',
                        target: '_blank'
                    }).append($('<input>', {
                        type: 'hidden',
                        name: '_token',
                        value: "{{ csrf_token() }}"
                    })).append($('<input>', {
                        type: 'hidden',
                        name: 'invoice_ids',
                        value: opd_charge_ids.join(',')
                    }));
                    $billForm.appendTo('body').submit().remove();
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $submitBtn.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: data.message || "Failed to save charge",
                        showConfirmButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                $submitBtn.prop('disabled', false).html(originalText);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Failed to save. Please try again.";
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: err,
                    showConfirmButton: true
                });
            }
        });
    });

    $("#edit_opd_charges_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var tbl_list = $('#e_opd_charge_detail_tbl >tbody >tr').length;
        if (tbl_list == 0) {
            Swal.fire({
                icon: "error",
                title: "Required!",
                text: "Please Enter Some Data",
                showConfirmButton: false,
                timer: 1500
            });
            return;
        }

        var $submitBtn = $('#edit_opd_charges_form button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('Wait...');
        var chargeId = $('#opd_charge_id').val();
        var formData = new FormData(this);
        formData.append('_method', 'PUT');

        $.ajax({
            url: "{{ route('opd.charges.update', ['chargeId' => '__ID__']) }}".replace('__ID__', chargeId),
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                if (data.success || data.result === 'OK') {
                    $('#editOPDChargeModal').modal('hide');
                    Swal.fire({
                        icon: "success",
                        title: "Saved!",
                        text: "Charge updated successfully",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    var singleId = data.opd_charge_id || chargeId;
                    window.open("{{ url('pdf/receipt') }}/" + singleId, '_blank');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $submitBtn.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: data.message || "Failed to update charge",
                        showConfirmButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                $submitBtn.prop('disabled', false).html(originalText);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Failed to update. Please try again.";
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: err,
                    showConfirmButton: true
                });
            }
        });
    });
});

function initSelect2Dropdowns() {
    $('#a_charge_department_id').select2({
        placeholder: "Department",
        dropdownParent: $('#addOPDChargeModal'),
        width: '100%'
    }).on('change', function() {
        var deptId = $(this).val();
        initChargeSelect2($('#a_ad_charge_id'), deptId, $('#addOPDChargeModal'));
    });

    $('#e_charge_department_id').select2({
        placeholder: "Department",
        dropdownParent: $('#editOPDChargeModal'),
        width: '100%'
    }).on('change', function() {
        var deptId = $(this).val();
        $('#echargedepartmentid').val(deptId);
        initChargeSelect2($('#e_ad_charge_id'), deptId, $('#editOPDChargeModal'));
    });

    initChargeSelect2($('#a_ad_charge_id'), $('#a_charge_department_id').val(), $('#addOPDChargeModal'));
    initChargeSelect2($('#e_ad_charge_id'), $('#e_charge_department_id').val(), $('#editOPDChargeModal'));
}

function initChargeSelect2(element, deptId, parentModal) {
    element.select2({
        placeholder: "Charge",
        dropdownParent: parentModal,
        width: '100%',
        ajax: {
            url: "{{ route('opd.charges.dropdown-ad-charges') }}",
            dataType: "json",
            type: "GET",
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    charge_department_id: deptId
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    });
}

function showOPDInvoiceOptions(opdId) {
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
            window.open("{{ url('rpt-opd-consolidated-bill.php') }}?id=" + opdId, '_blank');
        } else if (result.isDenied) {
            window.open("{{ url('rpt-opd-detailed-bill.php') }}?id=" + opdId, '_blank');
        }
    });
}

function showHideOPDCollectionCB() {
    var v = parseInt($("#show_hide_collection_cb").val()) || 0;
    if (v === 0) {
        $(".inline-checkbox").css('display', 'inline-block');
        $("#action-td").removeClass('tr3btns').addClass('tr4btns');
        $("#show_hide_collection_cb").val(1);
        $("#collect_opd_payments_btn").show();
    } else {
        $(".inline-checkbox").css('display', 'none');
        $("#action-td").addClass('tr3btns').removeClass('tr4btns');
        $("#show_hide_collection_cb").val(0);
        $("#collect_opd_payments_btn").hide();
    }
}

function addToOPDCollection(id) {
    var total = parseFloat($("#collection_amount").val()) || 0;
    var amount = parseFloat($("#balance_amount_" + id).val()) || 0;
    if ($('#cb_' + id).is(":checked")) {
        total = total + amount;
    } else {
        total = total - amount;
    }
    $("#collection_amount").val(total.toFixed(2));
    $("#collection_amount_text").html(total.toFixed(2));
}

function collectOPDPaymentsModal() {
    var amount = parseFloat($("#collection_amount").val()) || 0;
    if (amount > 0) {
        $("#split_collection_remaining").html(amount.toFixed(2));
        $("#split_collection_total_amount").val("0");
        $("#split_collection_total").html("0.00");
        $("#split_collection_tbl tbody").empty();
        $("#scnor").val("0");
        $("#collectPaymentsModal").modal('show');
    } else {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please select invoices before collection",
            showConfirmButton: false,
            timer: 1500
        });
    }
}

function addSplitCollection() {
    var nor = parseInt($("#scnor").val()) || 0;
    var total_amount = parseFloat($("#split_collection_total_amount").val()) || 0;
    var payment_mode = $("#split_collection_mode").val();
    if (!payment_mode) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please select payment mode",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }
    var amount = parseFloat($("#split_collection_amount").val()) || 0;
    if (amount <= 0) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please enter amount",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }

    var spid = nor + 1;
    var total = total_amount + amount;
    $("#split_collection_tbl tbody").append("<tr id='sc_sp_" + spid + "'><td><a href='javascript:void(0)' onclick='delSplitCollection(" + spid + ");'>x</a></td><td>" + payment_mode + "<input name='sc_mode[]' type='hidden' value='" + payment_mode + "'></td><td>" + amount.toFixed(2) + "<input id='sc_sp_amount_" + spid + "' name='sc_amount[]' type='hidden' value='" + amount + "'></td></tr>");
    $("#split_collection_total_amount").val(total.toFixed(2));
    $("#split_collection_total").html(total.toFixed(2));
    $("#scnor").val(spid);
    $("#split_collection_mode").val('');
    $("#split_collection_amount").val('');
    updateSplitCollectionRemaining();
}

function delSplitCollection(id) {
    var total_amount = parseFloat($("#split_collection_total_amount").val()) || 0;
    var amount = parseFloat($("#sc_sp_amount_" + id).val()) || 0;
    var total = total_amount - amount;
    $("#split_collection_total_amount").val(total.toFixed(2));
    $("#split_collection_total").html(total.toFixed(2));
    $("#sc_sp_" + id).remove();
    updateSplitCollectionRemaining();
}

function updateSplitCollectionRemaining() {
    var collection_amount = parseFloat($("#collection_amount").val()) || 0;
    var collecting_amount = parseFloat($("#split_collection_total_amount").val()) || 0;
    var remaining = collection_amount - collecting_amount;
    $("#split_collection_remaining").html(remaining.toFixed(2));
    if (remaining < 0) {
        $("#split_collection_remaining").css('color', '#dc3545');
    } else if (remaining === 0) {
        $("#split_collection_remaining").css('color', '#28a745');
    } else {
        $("#split_collection_remaining").css('color', '#dc3545');
    }
}

function collectOPDSelectedAmount() {
    var collection_amount = parseFloat($("#collection_amount").val()) || 0;
    var collecting_amount = parseFloat($("#split_collection_total_amount").val()) || 0;
    if (collection_amount !== collecting_amount) {
        Swal.fire({
            icon: "error",
            title: "Amount Mismatch!",
            text: "You are collecting " + collecting_amount.toFixed(2) + ". Amount should be " + collection_amount.toFixed(2),
            showConfirmButton: false,
            timer: 3000
        });
        return false;
    }

    var sp_mode = [];
    $('input[name="sc_mode[]"]').each(function() {
        sp_mode.push($(this).val());
    });

    var sp_amount = [];
    $('input[name="sc_amount[]"]').each(function() {
        sp_amount.push($(this).val());
    });

    var cb_values = [];
    $("input[name='cb']:checked").each(function() {
        cb_values.push(this.value);
    });

    var opd_id = $("#opd_id").val();
    var payment_date_time = $("#payment_date_time").val();

    if (sp_mode.length === 0) {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please select payment mode",
            showConfirmButton: false,
            timer: 1500
        });
        return;
    }

    $.ajax({ 
        type: 'POST',
        url: "{{ route('opd.charges.collect-payments', ['id' => '__ID__']) }}".replace('__ID__', opd_id),
        data: {
            payment_date_time: payment_date_time,
            cb: cb_values,
            sp_mode: sp_mode,
            sp_amount: sp_amount,
            _token: "{{ csrf_token() }}"
        },                       
        dataType: 'json',                   
        success: function(data) {
            $("#collectPaymentsModal").modal('hide');
            Swal.fire({
                icon: "success",
                title: "Success!",
                text: "Saved Successfully",
                showConfirmButton: false,
                timer: 1500
            });
            location.reload();
        },
        error: function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Failed to collect the payment.";
            Swal.fire({ icon: "error", title: "Error!", text: msg });
        }
    });
}

function addOPDChargeModal() {
    var $submitBtn = $('#add_opd_charges_form button[type="submit"]');
    $submitBtn.prop('disabled', false).html('Save');
    $("#addOPDChargeModal").modal('show');
    $("#add_opd_charges_form")[0].reset();
    $('#a_charge_department_id').val('').trigger('change');
    $('#a_ad_charge_id').val('').trigger('change');
    $("#opd_charge_detail_tbl > tbody").empty();
    $("#total_amount, #total_discount, #addon_discount, #grand_total").html('0.00');
    $("#opd_charges_amount, #opd_charges_discount, #opd_charges_addon_discount, #opd_charges_total").val('0');
    $("#paid_amount").val('0');
    $("#chnor").val('0');
    // The prescription panel is rebuilt on demand: a list left from an earlier opening could be out of date.
    $("#prescription_charge_types").empty();
    $("#prescription_td").empty();
}

function editOPDChargeModal(id) {
    var $submitBtn = $('#edit_opd_charges_form button[type="submit"]');
    $submitBtn.prop('disabled', false).html('Save');
    $.ajax({
        type: 'GET',
        url: "{{ route('opd.charges.get', ['chargeId' => '__ID__']) }}".replace('__ID__', id),
        dataType: 'json',                   
        success: function(data) {
            if (data.edit === 'No') {
                Swal.fire({
                    icon: "warning",
                    title: "Locked!",
                    text: "Edit Locked Process Started",
                    showConfirmButton: false,
                    timer: 1500
                });
                return;
            }
            $("#editOPDChargeModal").modal('show');
            $("#edit_opd_charges_form #opd_charge_id").val(id);
            $("#edit_opd_charges_form #echnor").val(data.nor);
            $("#edit_opd_charges_form #charge_date_time").val(data.row.charge_date_time);
            $("#edit_opd_charges_form #charge_title").val(data.row.charge_title);
            $("#edit_opd_charges_form #echargedepartmentid").val(data.row.charge_department_id);
            $("#edit_opd_charges_form #e_charge_department_id").val(data.row.charge_department_id).trigger('change');
            $("#edit_opd_charges_form #e_addon_discount_mode").val(data.row.addon_discount_mode);
            $("#edit_opd_charges_form #e_addon_discount_value").val(data.row.addon_discount_value);
            $("#edit_opd_charges_form #e_paid_amount").val(data.row.paid_amount);

            $("#e_opd_charge_detail_tbl tbody").html(data.table_html);
            calcEOPDChargeDetail();

            $.ajax({
                type: 'GET',
                url: "{{ route('opd.charges.split-payments', ['chargeId' => '__ID__']) }}".replace('__ID__', id),
                dataType: 'json',                   
                success: function(spData) {
                    $("#e_split_payments_tbl tbody").html(spData.html);
                    $("#e_spnor").val(spData.nor);
                    $("#e_split_payment_total_amount").val(spData.total);
                    $("#e_split_payment_total").html(spData.total);
                    updateEditChargePaymentRemaining();
                }
            });
        } 
    });	
}

function showOPDChargeAmount(id, patient_category_id) {
    if (id > 0) {
        $.ajax({
            type: 'GET',
            url: "{{ route('opd.charges.charge-amount') }}",
            data: { id: id, patient_category_id: patient_category_id },                       
            dataType: 'json',                   
            success: function(data) {
                $("#add_opd_charges_form #charge_amount").val(data.amount);
                $("#add_opd_charges_form #charge_discount_percentage").val(0);
                $("#add_opd_charges_form #charge_quantity").val(1);
                calcOPDChargeTotal();
            } 
        });
    }
}

function showEOPDChargeAmount(id, patient_category_id) {
    if (id > 0) {
        $.ajax({
            type: 'GET',
            url: "{{ route('opd.charges.charge-amount') }}",
            data: { id: id, patient_category_id: patient_category_id },                       
            dataType: 'json',                   
            success: function(data) {
                $("#edit_opd_charges_form #e_charge_amount").val(data.amount);
                $("#edit_opd_charges_form #e_charge_discount_percentage").val(0);
                $("#edit_opd_charges_form #e_charge_quantity").val(1);
                calcEOPDChargeTotal();
            } 
        });
    }
}

function calcOPDChargeTotal() {
    var amount = parseFloat($("#charge_amount").val()) || 0;
    var discount_percentage = parseFloat($("#charge_discount_percentage").val()) || 0;
    var quantity = parseFloat($("#charge_quantity").val()) || 1;
    var discount_amount = (amount * discount_percentage) / 100;
    var total_amount = (amount - discount_amount) * quantity;
    $("#charge_discount_amount").val(discount_amount.toFixed(2));
    $("#charge_total").val(total_amount.toFixed(2));
}

function calcEOPDChargeTotal() {
    var amount = parseFloat($("#e_charge_amount").val()) || 0;
    var discount_percentage = parseFloat($("#e_charge_discount_percentage").val()) || 0;
    var quantity = parseFloat($("#e_charge_quantity").val()) || 1;
    var discount_amount = (amount * discount_percentage) / 100;
    var total_amount = (amount - discount_amount) * quantity;
    $("#e_charge_discount_amount").val(discount_amount.toFixed(2));
    $("#e_charge_total").val(total_amount.toFixed(2));
}

function addOPDChargeDetail() {
    var chnor = parseInt($("#chnor").val()) || 0;
    var charge_department_id = $("#a_charge_department_id").val();
    var department_data = $('#a_charge_department_id').select2('data');
    var department_name = (department_data && department_data[0]) ? department_data[0].text : ($('#a_charge_department_id option:selected').text() || '');
    var charge_id = $("#a_ad_charge_id").val();
    var charge_data = $('#a_ad_charge_id').select2('data');
    var charge_name = (charge_data && charge_data[0]) ? charge_data[0].text : ($('#a_ad_charge_id option:selected').text() || '');
    var charge_amount = parseFloat($("#charge_amount").val());
    var charge_discount_percentage = $("#charge_discount_percentage").val();
    var charge_discount_amount = parseFloat($("#charge_discount_amount").val()) || 0;
    var charge_quantity = $("#charge_quantity").val();
    var charge_total = parseFloat($("#charge_total").val());

    if (!charge_department_id || !charge_id || isNaN(charge_amount) || charge_discount_percentage === '' || !charge_quantity) {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please fill all required fields",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }

    chnor = chnor + 1;
    $("#chnor").val(chnor);
    $("#opd_charge_detail_tbl tbody").append('<tr id="chr_' + chnor + '"><td><a href="javascript:void(0)" onclick="removeOPDChargeDetail(' + chnor + ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>' + department_name + '<input type="hidden" name="charge_department_id_value[]" value="' + charge_department_id + '"></td><td>' + charge_name + '<input type="hidden" name="charge_id_value[]" value="' + charge_id + '"></td><td>' + charge_amount + '<input type="hidden" name="amount_value[]" value="' + charge_amount + '"></td><td>' + charge_discount_percentage + '<input type="hidden" name="discount_percentage_value[]" value="' + charge_discount_percentage + '"></td><td>' + charge_discount_amount + '<input type="hidden" name="discount_amount_value[]" value="' + charge_discount_amount + '"></td><td>' + charge_quantity + '<input type="hidden" name="quantity_value[]" value="' + charge_quantity + '"></td><td>' + charge_total + '<input type="hidden" name="total_value[]" value="' + charge_total + '"></td></tr>');

    calcOPDChargeDetail();
    showPrescriptionTD(); // the item is in billing now: drop it from the prescription list

    $("#charge_amount").val('');
    $("#charge_discount_percentage").val('');
    $("#charge_discount_amount").val('');
    $("#charge_quantity").val('');
    $("#charge_total").val('');
    try { $('#a_ad_charge_id').select2('open'); } catch(e) {}
}

function removeOPDChargeDetail(id) {
    $("#chr_" + id).remove();
    calcOPDChargeDetail();
    showPrescriptionTD(); // removed from billing: offer the item again
}

function calcOPDChargeDetail() {
    var total_amount = 0;
    var total_discount = 0;
    var grand_total = 0;
    var i = 0;
    $("#opd_charge_detail_tbl tbody tr").each(function() { 
        var quantity = parseFloat($(this).find("input[name='quantity_value[]']").val()) || 1; 
        var amount = parseFloat($(this).find("input[name='amount_value[]']").val()) || 0; 
        total_amount = total_amount + (amount * quantity);
        var discount = parseFloat($(this).find("input[name='discount_amount_value[]']").val()) || 0; 
        total_discount = total_discount + (discount * quantity);
        var total = parseFloat($(this).find("input[name='total_value[]']").val()) || 0; 
        grand_total = grand_total + total;
        i++;
    });
    $("#opd_charges_amount").val(total_amount.toFixed(2));
    $("#opd_charges_discount").val(total_discount.toFixed(2));
    $("#opd_charges_total").val(grand_total.toFixed(2));
    $("#total_amount").html(total_amount.toFixed(2));
    $("#total_discount").html(total_discount.toFixed(2));
    $("#grand_total").html(grand_total.toFixed(2));
    $("#paid_amount").val('0');
    calcOPDAddonDiscount();
}

function calcOPDAddonDiscount() {
    var opd_charges_amount = parseFloat($("#opd_charges_amount").val()) || 0;
    var opd_charges_discount = parseFloat($("#opd_charges_discount").val()) || 0;
    var after_charges_discount_amount = opd_charges_amount - opd_charges_discount;
    var addon_discount_mode = $("#addon_discount_mode").val();
    var addon_discount_value = parseFloat($("#addon_discount_value").val()) || 0;
    var calc_addon = 0;
    if (addon_discount_mode === 'Percentage') {
        calc_addon = (opd_charges_amount * addon_discount_value) / 100;
    } else {
        calc_addon = addon_discount_value;
    }
    $("#opd_charges_addon_discount").val(calc_addon.toFixed(2));
    $("#addon_discount").html(calc_addon.toFixed(2));
    var grand_total = after_charges_discount_amount - calc_addon;
    $("#opd_charges_total").val(grand_total.toFixed(2));
    $("#grand_total").html(grand_total.toFixed(2));
    $("#paid_amount").val('0');
}

function addEOPDChargeDetail() {
    var chnor = parseInt($("#echnor").val()) || 0;
    var charge_id = $("#e_ad_charge_id").val();
    var charge_data = $('#e_ad_charge_id').select2('data');
    var charge_name = (charge_data && charge_data[0]) ? charge_data[0].text : ($('#e_ad_charge_id option:selected').text() || '');
    var charge_amount = parseFloat($("#e_charge_amount").val());
    var charge_discount_percentage = $("#e_charge_discount_percentage").val();
    var charge_discount_amount = parseFloat($("#e_charge_discount_amount").val()) || 0;
    var charge_quantity = $("#e_charge_quantity").val();
    var charge_total = parseFloat($("#e_charge_total").val());

    if (!charge_id || isNaN(charge_amount) || charge_discount_percentage === '' || !charge_quantity) {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please fill all required fields",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }

    chnor = chnor + 1;
    $("#echnor").val(chnor);
    $("#e_opd_charge_detail_tbl tbody").append('<tr id="chr_' + chnor + '"><td><a href="javascript:void(0)" onclick="removeEOPDChargeDetail(' + chnor + ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>' + charge_name + '<input type="hidden" name="charge_id_value[]" value="' + charge_id + '"></td><td>' + charge_amount + '<input type="hidden" name="amount_value[]" value="' + charge_amount + '"></td><td>' + charge_discount_percentage + '<input type="hidden" name="discount_percentage_value[]" value="' + charge_discount_percentage + '"></td><td>' + charge_discount_amount + '<input type="hidden" name="discount_amount_value[]" value="' + charge_discount_amount + '"></td><td>' + charge_quantity + '<input type="hidden" name="quantity_value[]" value="' + charge_quantity + '"></td><td>' + charge_total + '<input type="hidden" name="total_value[]" value="' + charge_total + '"></td></tr>');

    calcEOPDChargeDetail();

    $("#e_charge_amount").val('');
    $("#e_charge_discount_percentage").val('');
    $("#e_charge_discount_amount").val('');
    $("#e_charge_quantity").val('');
    $("#e_charge_total").val('');
    try { $('#e_ad_charge_id').select2('open'); } catch(e) {}
}

function removeEOPDChargeDetail(id) {
    $("#chr_" + id).remove();
    calcEOPDChargeDetail();
}

function calcEOPDChargeDetail() {
    var total_amount = 0;
    var total_discount = 0;
    var grand_total = 0;
    var i = 0;
    $("#e_opd_charge_detail_tbl tbody tr").each(function() { 
        var quantity = parseFloat($(this).find("input[name='quantity_value[]']").val()) || 1; 
        var amount = parseFloat($(this).find("input[name='amount_value[]']").val()) || 0; 
        total_amount = total_amount + (amount * quantity);
        var discount = parseFloat($(this).find("input[name='discount_amount_value[]']").val()) || 0; 
        total_discount = total_discount + (discount * quantity);
        var total = parseFloat($(this).find("input[name='total_value[]']").val()) || 0; 
        grand_total = grand_total + total;
        i++;
    });
    $("#e_opd_charges_amount").val(total_amount.toFixed(2));
    $("#e_opd_charges_discount").val(total_discount.toFixed(2));
    $("#e_opd_charges_total").val(grand_total.toFixed(2));
    $("#e_total_amount").html(total_amount.toFixed(2));
    $("#e_total_discount").html(total_discount.toFixed(2));
    $("#e_grand_total").html(grand_total.toFixed(2));

    if (i > 0) {
        $("#e_charge_department_id").prop("disabled", true);
    } else {
        $("#e_charge_department_id").prop("disabled", false);
    }
    calcEOPDAddonDiscount();
}

function calcEOPDAddonDiscount() {
    var opd_charges_amount = parseFloat($("#e_opd_charges_amount").val()) || 0;
    var opd_charges_discount = parseFloat($("#e_opd_charges_discount").val()) || 0;
    var after_charges_discount_amount = opd_charges_amount - opd_charges_discount;
    var addon_discount_mode = $("#e_addon_discount_mode").val();
    var addon_discount_value = parseFloat($("#e_addon_discount_value").val()) || 0;
    var calc_addon = 0;
    if (addon_discount_mode === 'Percentage') {
        calc_addon = (opd_charges_amount * addon_discount_value) / 100;
    } else {
        calc_addon = addon_discount_value;
    }
    $("#e_opd_charges_addon_discount").val(calc_addon.toFixed(2));
    $("#e_addon_discount").html(calc_addon.toFixed(2));
    var grand_total = after_charges_discount_amount - calc_addon;
    $("#e_opd_charges_total").val(grand_total.toFixed(2));
    $("#e_grand_total").html(grand_total.toFixed(2));
}

function addSplitPayment() {
    var nor = parseInt($("#spnor").val()) || 0;
    var total_amount = parseFloat($("#split_payment_total_amount").val()) || 0;
    var payment_mode = $("#split_payment_mode").val();
    if (!payment_mode) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please select payment mode",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }
    var amount = parseFloat($("#split_payment_amount").val()) || 0;
    if (amount <= 0) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please enter amount",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }
    var spid = nor + 1;
    var total = total_amount + amount;
    $("#split_payments_tbl tbody").append("<tr id='sp_" + spid + "'><td><a href='javascript:void(0)' onclick='delSplitPayment(" + spid + ");'>x</a></td><td>" + payment_mode + "<input name='sp_mode[]' type='hidden' value='" + payment_mode + "'></td><td>" + amount.toFixed(2) + "<input id='sp_amount_" + spid + "' name='sp_amount[]' type='hidden' value='" + amount + "'></td></tr>");
    $("#split_payment_total_amount").val(total.toFixed(2));
    $("#split_payment_total").html(total.toFixed(2));
    $("#spnor").val(spid);
    $("#split_payment_mode").val('');
    $("#split_payment_amount").val('');
    updateChargePaymentRemaining();
}

function delSplitPayment(id) {
    var total_amount = parseFloat($("#split_payment_total_amount").val()) || 0;
    var amount = parseFloat($("#sp_amount_" + id).val()) || 0;
    var total = total_amount - amount;
    $("#split_payment_total_amount").val(total.toFixed(2));
    $("#split_payment_total").html(total.toFixed(2));
    $("#sp_" + id).remove();
    $("#paid_amount").val(total.toFixed(2));
    updateChargePaymentRemaining();
}

function applySplitPayments() {
    var paid_amount = $("#split_payment_total_amount").val();
    if (parseFloat(paid_amount) === 0 || !paid_amount) {
        $("#payment_mode").val('');
        $("#paid_amount").val('0.00');
    } else {
        let payment_modes = $('#split_payments_tbl tbody input[name="sp_mode[]"]').map(function() {
            return $(this).val();
        }).get().join(',');
        $("#payment_mode").val(payment_modes);
        $("#paid_amount").val(parseFloat(paid_amount).toFixed(2));
    }
    $("#addSplitPaymentModal").modal('hide');
}

function updateChargePaymentRemaining() {
    var charge_total = parseFloat($("#opd_charges_total").val()) || 0;
    var payment_total = parseFloat($("#split_payment_total_amount").val()) || 0;
    var remaining = charge_total - payment_total;
    $("#split_payment_remaining").html(remaining.toFixed(2));
    if (remaining < 0) {
        $("#split_payment_remaining").css('color', '#dc3545');
    } else if (remaining === 0) {
        $("#split_payment_remaining").css('color', '#28a745');
    } else {
        $("#split_payment_remaining").css('color', '#ffc107');
    }
}

function addESplitPayment() {
    var nor = parseInt($("#e_spnor").val()) || 0;
    var total_amount = parseFloat($("#e_split_payment_total_amount").val()) || 0;
    var payment_mode = $("#e_split_payment_mode").val();
    if (!payment_mode) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please select payment mode",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }
    var amount = parseFloat($("#e_split_payment_amount").val()) || 0;
    if (amount <= 0) {
        Swal.fire({
            icon: "error",
            title: "Required",
            text: "Please enter amount",
            showConfirmButton: false,
            timer: 1500
        });
        return false;
    }
    var spid = nor + 1;
    var total = total_amount + amount;
    $("#e_split_payments_tbl tbody").append("<tr id='e_sp_" + spid + "'><td><a href='javascript:void(0)' onclick='delESplitPayment(" + spid + ");'>x</a></td><td>" + payment_mode + "<input name='sp_mode[]' type='hidden' value='" + payment_mode + "'></td><td>" + amount.toFixed(2) + "<input id='e_sp_amount_" + spid + "' name='sp_amount[]' type='hidden' value='" + amount + "'></td></tr>");
    $("#e_split_payment_total_amount").val(total.toFixed(2));
    $("#e_split_payment_total").html(total.toFixed(2));
    $("#e_spnor").val(spid);
    $("#e_split_payment_mode").val('');
    $("#e_split_payment_amount").val('');
    updateEditChargePaymentRemaining();
}

function delESplitPayment(id) {
    var total_amount = parseFloat($("#e_split_payment_total_amount").val()) || 0;
    var amount = parseFloat($("#e_sp_amount_" + id).val()) || 0;
    var total = total_amount - amount;
    $("#e_split_payment_total_amount").val(total.toFixed(2));
    $("#e_split_payment_total").html(total.toFixed(2));
    $("#e_sp_" + id).remove();
    $("#e_paid_amount").val(total.toFixed(2));
    updateEditChargePaymentRemaining();
}

function applyESplitPayments() {
    var paid_amount = $("#e_split_payment_total_amount").val();
    if (parseFloat(paid_amount) === 0 || !paid_amount) {
        $("#e_payment_mode").val('');
        $("#e_paid_amount").val('0.00');
    } else {
        let payment_modes = $('#e_split_payments_tbl tbody input[name="sp_mode[]"]').map(function() {
            return $(this).val();
        }).get().join(',');
        $("#e_payment_mode").val(payment_modes);
        $("#e_paid_amount").val(parseFloat(paid_amount).toFixed(2));
    }
    $("#editSplitPaymentModal").modal('hide');
}

function updateEditChargePaymentRemaining() {
    var charge_total = parseFloat($("#e_opd_charges_total").val()) || 0;
    var payment_total = parseFloat($("#e_split_payment_total_amount").val()) || 0;
    var remaining = charge_total - payment_total;
    $("#e_split_payment_remaining").html(remaining.toFixed(2));
    if (remaining < 0) {
        $("#e_split_payment_remaining").css('color', '#dc3545');
    } else if (remaining === 0) {
        $("#e_split_payment_remaining").css('color', '#28a745');
    } else {
        $("#e_split_payment_remaining").css('color', '#ffc107');
    }
}

function showPrescriptionDetails(prescription_id, department) {
    if (!prescription_id) {
        $("#prescription_charge_types").html('');
        $("#prescription_td").html('');
        return;
    }
    $.ajax({ 
        type: 'POST',
        url: "{{ route('opd.charges.prescription-details') }}",
        data: {
            id: prescription_id,
            department: department,
            _token: "{{ csrf_token() }}"
        },                       
        dataType: 'json',                   
        success: function(data) {
            $("#prescription_charge_types").html(data.html);
            showPrescriptionTD();
        } 
    });
}

// Charge ids queued in the modal's table but not saved yet - they count as "in billing" for the prescription list.
function stagedOPDChargeIds() {
    return $('#opd_charge_detail_tbl tbody input[name="charge_id_value[]"]').map(function() { return $(this).val(); }).get();
}

// Lists the selected prescription's items that are NOT in billing yet; called again whenever billing changes.
function showPrescriptionTD() {
    var prescription_id = $("#charge_prescriptions").val();
    var type = $("#prescription_charge_types option:selected").text();
    var values = $("#prescription_charge_types option:selected").val();
    if (type !== '' && values) {
        $.ajax({ 
            type: 'POST',
            url: "{{ route('opd.charges.prescription-td') }}",
            data: {
                prescription_id: prescription_id,
                type: type,
                values: values,
                exclude: stagedOPDChargeIds(),
                _token: "{{ csrf_token() }}"
            },                       
            dataType: 'json',                   
            success: function(data) {
                $("#prescription_td").html(data.html);
            } 
        });
    } else {
        $("#prescription_td").html('');
    }
}

function addPrescriptionItemToCharges(charge_id, charge_type) {
    var patient_category_id = $("#patient_category_id").val();
    $.ajax({
        type: 'POST',
        url: "{{ route('opd.charges.charge-for-prescription') }}",
        data: {
            charge_id: charge_id,
            charge_type: charge_type,
            patient_category_id: patient_category_id,
            _token: "{{ csrf_token() }}"
        },
        dataType: 'json',
        success: function(data) {
            if (!data.success) return;
            // dept_name and charge_name now come from the single response — no extra round-trips needed
            var dName = data.department_name || ('Department #' + data.charge_department_id);
            var cName = data.charge_name     || ('Charge #'     + data.charge_id);
            $('#a_charge_department_id').append(new Option(dName, data.charge_department_id, true, true)).trigger('change');
            $('#a_ad_charge_id').append(new Option(cName, data.charge_id, true, true)).trigger('change');
            $('#charge_amount').val(data.amount);
            $('#charge_discount_percentage').val(0);
            $('#charge_quantity').val(1);
            calcOPDChargeTotal();
        }
    });
}

function updateCDIField(val, targetId) {
    $("#" + targetId).val(val);
}

function deleteOPDCharge(id) {
    Swal.fire({
        title: "Delete?",
        text: "Are you sure you want to delete!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({ 
                type: 'DELETE',
                url: "{{ route('opd.charges.destroy', ['__ID__']) }}".replace('__ID__', id),
                data: {
                    _token: "{{ csrf_token() }}"
                },                       
                dataType: 'json',                   
                success: function(data) {
                    $("#row_" + id).remove();
                    showPrescriptionTD(); // a deleted bill puts its items back on the prescription list
                    Swal.fire({
                        icon: "success",
                        title: "Deleted!",
                        text: "Deleted Successfully.",
                        showConfirmButton: false,
                        timer: 1500
                    });
                },
                error: function() {
                    Swal.fire({
                        icon: "error",
                        title: "Error!",
                        text: "Failed to delete.",
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            });
        }
    });
}

</script>
@endpush
