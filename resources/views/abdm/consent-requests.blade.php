@extends('layouts.app')
@section('title', 'Consent List')

@section('content')
@if(isset($canAccess) && !$canAccess)
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
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Consent List</div>
					<div class="tbl-filter-box">
						<div class="local-forms" style="width:250px;">
							<label>Patient</label>
							<select class="form-control" id="cr_f_patient_id">
								<option value="">All</option>
							</select>
						</div>
						<div class="local-forms">
							<label>Status</label>
							<select class="form-control" id="cr_f_status">
								<option value="">All</option>
								<option value="REQUESTED">Requested</option>
								<option value="GRANTED">Granted</option>
								<option value="DENIED">Denied</option>
								<option value="EXPIRED">Expired</option>
								<option value="REVOKED">Revoked</option>
								<option value="UNKNOWN">Unknown</option>
							</select>
						</div>
						<div class="local-forms">
							<label>Purpose</label>
							<select class="form-control" id="cr_f_purpose_code">
								<option value="">All</option>
								<option value="CAREMGT">Care Management</option>
								<option value="BTG">Break the Glass</option>
								<option value="PUBHLTH">Public Health</option>
								<option value="HPAYMT">Healthcare Payment</option>
								<option value="DSRCH">Disease Specific Healthcare Research</option>
								<option value="PATRQT">Self Requested</option>
							</select>
						</div>
						<div class="local-forms" style="width:250px;">
							<label>Created On</label>
							<div class="input-group">
								<input type="text" id="cr_f_created_on" class="form-control daterangepicker" placeholder="Select date range" autocomplete="off">
								<button class="btn btn-outline-secondary" type="button" id="cr_f_created_on_reset" title="Clear date range"><i class="fa fa-times"></i></button>
							</div>
						</div>
						<div class="tbl-top-buttons">
							<ul>
								<li><a id="search-consent-requests-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
								<li><a href="javascript:void(0)" onclick="openAddConsentRequestModal()"><i class="fa fa-plus"></i> New Consent Request</a></li>
							</ul>
						</div>
					</div>
				</div>
			</div>
			<div class="sm-table mt-10">
				<div class="table-responsive">
					<table id="consent_requests_tbl" class="table table-bordered table-sm table-text-sm">
						<thead>
							<tr>
								<th>Name</th>
								<th>Patient Identifier</th>
								<th>Status</th>
								<th>Purpose</th>
								<th>HI Type</th>
								<th>Consent Created On</th>
								<th>Consent Granted On</th>
								<th>Consent Expiry On</th>
								<th class="no-sort">Consent Range</th>
								<th class="no-sort">View Consent</th>
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

<!-- New Consent Request Modal (1.2 Consent Request Initiation) -->
<div class="modal fade" id="addConsentRequestModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Consent Request Form</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="create_consent_request_form">
				<div class="modal-body">
					<div id="consent_request_form_error" class="text-danger mb-10" style="display:none;"></div>

					<div class="form-group local-forms">
						<label>Existing Patient <span class="text-muted">(optional - auto-fills the ABHA Address below)</span></label>
						<select id="consent_existing_patient_dropdown" class="form-control patients_dropdown" style="width:100%;">
							<option></option>
						</select>
					</div>

					<div class="row">
						<div class="col-12 col-sm-7">
							<div class="form-group local-forms">
								<label>Patient's ABHA Address <span class="req-star">*</span></label>
								<input type="text" id="consent_abha_local" class="form-control" placeholder="e.g. rahul.agarwal">
							</div>
						</div>
						<div class="col-12 col-sm-2">
							<div class="form-group local-forms">
								<label>Domain</label>
								<select id="consent_abha_domain" class="form-control">
									<option value="sbx">@sbx</option>
									<option value="abdm">@abdm</option>
									<option value="ndhm">@ndhm</option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-3">
							<button type="button" id="consent_search_patient_btn" class="btn btn-info form-control" onclick="searchConsentPatientByAbha()"><i class="fa fa-search"></i> Search</button>
						</div>
					</div>
					<div id="consent_abha_search_result" class="mb-15" style="display:none;"></div>

					<div class="form-group local-forms">
						<label>Purpose of request</label>
						<select id="consent_purpose_code" class="form-control">
							<option value="CAREMGT">Care Management</option>
							<option value="BTG">Break the Glass</option>
							<option value="PUBHLTH">Public Health</option>
							<option value="HPAYMT">Healthcare Payment</option>
							<option value="DSRCH">Disease Specific Healthcare Research</option>
							<option value="PATRQT">Self Requested</option>
						</select>
					</div>

					<label>Health Info duration</label>
					<div class="row">
						<div class="col-12 col-sm-4">
							<div class="form-group local-forms">
								<select id="consent_duration_preset" class="form-control" onchange="applyConsentDurationPreset()">
									<option value="all">All</option>
									<option value="7">Last 7 Days</option>
									<option value="30">Last 1 Month</option>
									<option value="90">Last 3 Months</option>
									<option value="180" selected>Last 6 Months</option>
									<option value="custom">Custom Range</option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-4">
							<div class="form-group local-forms">
								<label class="fs-12">From</label>
								<input type="text" id="consent_from_date" class="form-control datepicker" disabled>
							</div>
						</div>
						<div class="col-12 col-sm-4">
							<div class="form-group local-forms">
								<label class="fs-12">To</label>
								<input type="text" id="consent_to_date" class="form-control datepicker" disabled>
							</div>
						</div>
					</div>

					<label>Health Info type</label>
					<div class="row mb-15">
						<div class="col-12 col-sm-6">
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_OPConsultation" class="consent-hi-type" value="OPConsultation" checked><span>OP Consultation</span></label></div>
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_DischargeSummary" class="consent-hi-type" value="DischargeSummary"><span>Discharge Summary</span></label></div>
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_ImmunizationRecord" class="consent-hi-type" value="ImmunizationRecord" checked><span>Immunization Record</span></label></div>
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_WellnessRecord" class="consent-hi-type" value="WellnessRecord"><span>Wellness Record</span></label></div>
						</div>
						<div class="col-12 col-sm-6">
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_DiagnosticReport" class="consent-hi-type" value="DiagnosticReport"><span>Diagnostic Report</span></label></div>
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_Prescription" class="consent-hi-type" value="Prescription" checked><span>Prescription</span></label></div>
							<div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hi_HealthDocumentRecord" class="consent-hi-type" value="HealthDocumentRecord"><span>Health Document Record</span></label></div>
						</div>
					</div>

					<div class="form-group local-forms">
						<label>Consent Expiry</label>
						<input type="text" id="consent_expiry" class="form-control datetimepicker">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" id="submit_consent_request_btn" class="btn btn-primary" onclick="submitConsentRequest()">Request Consent</button>
				</div>
			</form>
		</div>
	</div>
</div>

@include('abdm.partials.hiu-view-offcanvas')
@endif
@endsection

@push('scripts')
@if(!isset($canAccess) || $canAccess)
<script>
$(document).ready(function() {
	initConsentRequestsTable();
	initConsentRequestsFilters();
});
</script>
@endif
@endpush
