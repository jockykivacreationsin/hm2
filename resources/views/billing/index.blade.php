@extends('layouts.app')
@section('title', 'Billing')
@section('content')
@if(!isset($permissions) || !$permissions->hasPermission('m_billing'))
	<div class="themebody-wrap">
		<div class="no-access">
			<i class="fa-duotone fa-shield-halved"></i>
			<p>Access Denied</p>
		</div>
	</div>
@else
	<div class="themebody-wrap ipd-wrap">

		<div class="card-ipd">
			
			<div class="card-body">
				<div class="ipd-patient-consultant-row">
					<div class="row ipd-title-with-buttons row-bottom-border">
						<div class="col-md-4">
							<div class="ipd-title">Billing</div>
						</div>
						<div class="col-md-8">
							<div class="row flex-v-center row-col-margin">
								<div class="col-md-3">
									<div class="local-forms">
										<label>Report <span class="req-star">*</span></label>
										<select class="form-control" id="s_report_period">
											<option value="Daily">Daily</option>
											<option value="Monthly">Monthly</option>
											<option value="Periodic">Periodic</option>
										</select>
									</div>
								</div>
								<div class="col-md-3">
									<div id="report-period-daily" class="local-forms">
										<label>Date <span class="req-star">*</span></label>
										<input type="text" id="s_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
									</div>
									<div id="report-period-monthly" class="local-forms">
										<label>Month <span class="req-star">*</span></label>
										<select class="form-control" id="s_month" data-lookup-url="{{ route('pickers.months') }}">
											<option>Month</option>
										</select>
									</div>
									<div id="report-period-periodic" class="fields-flex-row">
										<div class="local-forms">
											<label>From <span class="req-star">*</span></label>
											<input type="text" id="s_from_date" name="from_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
										</div>
										<div id="report-period-daily" class="local-forms">
											<label>To <span class="req-star">*</span></label>
											<input type="text" id="s_to_date" name="to_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
										</div>
									</div>
								</div>
								<div class="col-md-3">
									<div class="local-forms">
										<label>Department <span class="req-star">*</span></label>
										<select class="form-control" id="s_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}">

										</select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="tbl-top-buttons">
										<ul>
											<li><a id="search-billing-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
											<li>@if(!isset($permissions) || $permissions->hasPermission('b_billing_new'))<a href="javascript:void(0)" onclick="addBillModal()"><i class="fa fa-plus"></i> New</a>@endif</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
					</div>	
					<div class="sm-table mt-10">
						<div class="table-responsive">  
							<table id="billings_tbl" class="table table-bordered table-sm table-text-sm"
															data-url="{{ route('billing.data') }}"
															data-show-url="{{ route('billing.show', ['id' => '__ID__']) }}"
															data-split-payments-url="{{ route('billing.split-payments', ['id' => '__ID__']) }}"
															data-cancel-url="{{ route('billing.cancel', ['id' => '__ID__']) }}"
															data-destroy-url="{{ route('billing.destroy', ['id' => '__ID__']) }}">
								<thead>
									<tr>
										<th class="no-sort tr3btns"></th>
										<th>No</th>
										<th>Date Time</th>
										<th>Patient</th>
										<th>Title</th>
										<th>Department</th>
										<th>Amount</th>
										<th>Discount</th>
										<th>Addon Discount</th>
										<th>Total</th>
										<th>Paid</th>
										<th>Payment Mode</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody>
									
								</tbody>
								
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<form id="add_billing_form" data-store-url="{{ route('billing.store') }}">
		@csrf
		<div class="modal fade" id="addBillModal">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Add Charges</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
					</div>

					<input type="hidden" id="charge_discount_amount" name="discount_amount">
					<input type="hidden" id="chargedepartmentid" name="charge_department_id">
					<div class="modal-body">
						<div class="row">
							<div class="col-lg-12">
								<div class="row">
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Date Time <span class="req-star">*</span></label>
											<input type="text" name="billing_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Department <span class="req-star">*</span></label>
											<select class="form-control" id="a_charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" onchange="updateCDIField(this.value, 'chargedepartmentid')" required>
												<option></option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-3">
										<div class="form-group local-forms">
											<label>Category <span class="req-star">*</span></label>
											<select id="patient_category_id" name="patient_category_id" class="form-control" required>
												@foreach($patientCategories as $pc)
												<option value="{{ $pc->id }}">{{ $pc->patient_category_name }}</option>
												@endforeach
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-3">
										<div class="form-group local-forms">
											<label>Patient Name <span class="req-star">*</span></label>
											<input type="text" name="patient_name" class="form-control" required>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Bill Title <span class="req-star">*</span></label>
											<input type="text" name="bill_title" class="form-control" required>
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
											<select class="form-control" id="a_ad_charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#a_charge_department_id" name="charge_id" onchange="showBillChargeAmountDisc(this.value)">
												<option></option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Amount <span class="req-star">*</span></label>
											<input type="number" id="charge_amount" name="amount" class="form-control" onkeyup="calcBillChargeTotal()">
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Disc(%) <span class="req-star">*</span></label>
											<input type="number" id="charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcBillChargeTotal()">
										</div>
									</div>
									
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>QNT<span class="req-star">*</span></label>
											<input type="number" id="charge_quantity" name="quantity" class="form-control" onkeyup="calcBillChargeTotal()">
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
											<button type="button" class="ssbtn" onclick="addBillChargeDetail()"><i class="fas fa-plus"></i></button>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-12 col-sm-12">
										<div class="table-responsive">  
											<table id="bill_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
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
													<tr><th class="aright" colspan="6">Amount<input type="hidden" id="bill_charges_amount" name="bill_charges_amount" value="0"></th><th class="aright" id="total_amount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Discount<input type="hidden" id="bill_charges_discount" name="bill_charges_discount" value="0"></th><th class="aright" id="total_discount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Addon Discount<input type="hidden" id="bill_charges_addon_discount" name="bill_charges_addon_discount" value="0"></th><th class="aright" id="addon_discount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Total<input type="hidden" id="bill_charges_total" name="bill_charges_total" value="0"></th><th class="aright" id="grand_total">0.00</th></tr>
												</tfoot>
											</table>
										</div>
									</div>

								</div>
								<div class="row mt-10" style="justify-content: flex-end;">
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms mb-0">
											<label>Discount</label>
											<select class="form-control" id="addon_discount_mode" name="addon_discount_mode" onchange="calcBillAddonDiscount()">
												<option value="Percentage">Percentage</option>
												<option value="Amount">Amount</option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms mb-0">
											<label>Discount Value<span class="req-star">*</span></label>
											<input type="number" id="addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcBillAddonDiscount()" required>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<input type="hidden" id="payment_mode" name="payment_mode">
										<div class="form-group local-forms mb-0">
											<label>Paid Amount<span class="req-star">*</span></label>
											<div class="input-group">
												<input type="number" id="paid_amount" name="paid_amount" step="any" class="form-control" value="0" readonly required>
												<button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addSplitPaymentModal">+</button>
											</div>
										</div>
									</div>
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

	<form id="edit_billing_form" data-update-url="{{ route('billing.update', ['id' => '__ID__']) }}">
		@csrf
		<div class="modal fade" id="editBillModal">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Update Bill</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
					</div>

					<input type="hidden" id="billing_id" name="billing_id" value="">
					<input type="hidden" id="e_charge_discount_amount" name="discount_amount">
					<input type="hidden" id="echargedepartmentid" name="charge_department_id">
					<div class="modal-body">
						<div class="row">
							<div class="col-lg-12">
								<div class="row">
									<div class="col-12 col-sm-3">
										<div class="form-group local-forms">
											<label>Date Time <span class="req-star">*</span></label>
											<input type="text" id="billing_date_time" name="billing_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
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
									<div class="col-12 col-sm-3">
										<div class="form-group local-forms">
											<label>Patient Name <span class="req-star">*</span></label>
											<input type="text" id="patient_name" name="patient_name" class="form-control" required>
										</div>
									</div>
									<div class="col-12 col-sm-3">
										<div class="form-group local-forms">
											<label>Title <span class="req-star">*</span></label>
											<input type="text" id="bill_title" name="bill_title" class="form-control" required>
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
											<select class="form-control" id="e_ad_charge_id" data-lookup-url="{{ route('charges.lookup.charges') }}" data-lookup-params="charge_department_id=#e_charge_department_id" name="charge_id" onchange="showEBillChargeAmountDisc(this.value)">
												<option></option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Amount <span class="req-star">*</span></label>
											<input type="number" id="e_charge_amount" name="amount" class="form-control" onkeyup="calcEBillChargeTotal()">
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>Disc(%) <span class="req-star">*</span></label>
											<input type="number" id="e_charge_discount_percentage" name="discount_percentage" class="form-control" onkeyup="calcEBillChargeTotal()">
											
										</div>
									</div>
									
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms">
											<label>QNT<span class="req-star">*</span></label>
											<input type="number" id="e_charge_quantity" name="quantity" class="form-control" onkeyup="calcEBillChargeTotal()">
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
											<button type="button" class="ssbtn" onclick="addEBillChargeDetail()"><i class="fas fa-plus"></i></button>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-12 col-sm-12">
										<div class="table-responsive">  
											<table id="e_bill_charge_detail_tbl" class="table table-bordered table-sm table-text-sm">
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
													<tr><th class="aright" colspan="6">Amount<input type="hidden" id="e_bill_charges_amount" name="bill_charges_amount" value="0"></th><th class="aright" id="e_total_amount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Discount<input type="hidden" id="e_bill_charges_discount" name="bill_charges_discount" value="0"></th><th class="aright" id="e_total_discount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Addon Discount<input type="hidden" id="e_bill_charges_addon_discount" name="bill_charges_addon_discount" value="0"></th><th class="aright" id="e_addon_discount">0.00</th></tr>
													<tr><th class="aright" colspan="6">Total<input type="hidden" id="e_bill_charges_total" name="bill_charges_total" value="0"></th><th class="aright" id="e_grand_total">0.00</th></tr>
												</tfoot>
											</table>
										</div>
									</div>

								</div>
								<div class="row mt-10" style="justify-content: flex-end;">
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms mb-0">
											<label>Discount</label>
											<select class="form-control" id="e_addon_discount_mode" name="addon_discount_mode" onchange="calcEBillAddonDiscount()">
												<option value="Percentage">Percentage</option>
												<option value="Amount">Amount</option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-2">
										<div class="form-group local-forms mb-0">
											<label>Discount Value<span class="req-star">*</span></label>
											<input type="number" id="e_addon_discount_value" name="addon_discount_value" class="form-control" value="0" onkeyup="calcEBillAddonDiscount()" required>
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
	<script>
		$(window).on("load", function() {

		});

	var _billingCancelUrl = $('#billings_tbl').data('cancel-url') || '';

	function cancelInvoice(id) {
		if (!confirm('Cancel this invoice? This cannot be undone.')) return;
		var url = _billingCancelUrl.replace('__ID__', id);
		$.ajax({
			url: url,
			type: 'PATCH',
			data: { _token: $('meta[name="csrf-token"]').attr('content') },
			success: function (res) {
				if (res && res.success) {
					appForm.toast('success', 'Cancelled', res.message || 'Invoice cancelled.');
					dtbillings.draw(false);
				} else {
					appForm.toast('error', 'Error', (res && res.message) ? res.message : 'Could not cancel invoice.');
				}
			},
			error: function () {
				appForm.toast('error', 'Error', 'Could not cancel invoice.');
			}
		});
	}

/* appForm:wired */
	// Opens the receipt of the saved bill in a new tab.
	function openBillReceipt(id) {
		window.open("{{ route('pdf.receipt', ['chargeId' => '__ID__']) }}".replace('__ID__', id), '_blank');
	}

	function billingPaymentCheck(paidSel, modeSel) {
		var paid = parseFloat($(paidSel).val());
		return (paid > 0 && $(modeSel).val() === '') ? 'Please Select Payment Mode' : true;
	}

	appForm.bind('#add_billing_form', {
		url: "{{ route('billing.store') }}",
		requiredRows: '#bill_charge_detail_tbl > tbody > tr',
		validate: function () { return billingPaymentCheck('#paid_amount', '#payment_mode'); },
		busyText: 'Wait...',
		onSuccess: function (data) {
			appForm.toast('success', 'Saved!', 'Saved Successfully.');
			openBillReceipt(data.billing_id);
			dtbillings.draw();
			$('#addBillModal').modal('hide');
		}
	});

	appForm.bind('#edit_billing_form', {
		method: 'PUT',
		url: function () { return "{{ route('billing.update', ['id' => '__ID__']) }}".replace('__ID__', $('#billing_id').val()); },
		requiredRows: '#e_bill_charge_detail_tbl > tbody > tr',
		validate: function () { return billingPaymentCheck('#e_paid_amount', '#e_payment_mode'); },
		busyText: 'Wait...',
		onSuccess: function (data) {
			appForm.toast('success', 'Saved!', 'Saved Successfully.');
			openBillReceipt(data.billing_id);
			dtbillings.draw();
			$('#editBillModal').modal('hide');
		}
	});
	</script>
@endif
@endsection
