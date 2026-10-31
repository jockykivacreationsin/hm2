@extends('layouts.app')
@section('title', 'Patient Medicines')

@section('content')
@if($canAccess)
	<div class="themebody-wrap ipd-wrap">

		<div class="card-ipd">
			<div class="card-body">
				<div class="row ipd-patient-consultant-row">
					<div class="ipd-title-with-buttons row-bottom-border">
						<div class="ipd-title">Patient Medicines</div>
						<div class="tbl-filter-box">
							<div class="local-forms">
								<label>Report <span class="req-star">*</span></label>
								<select class="form-control" id="s_report_period">
									<option value="Daily">Daily</option>
									<option value="Monthly">Monthly</option>
									<option value="Periodic">Periodic</option>
								</select>
							</div>
							<div id="report-period-daily" class="local-forms">
								<label>Date <span class="req-star">*</span></label>
								<input type="text" id="s_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
							<div id="report-period-monthly" class="local-forms" style="display:none;">
								<label>Month <span class="req-star">*</span></label>
								<select class="form-control" id="s_month" data-lookup-url="{{ route('pickers.months') }}">
									@for($m = 1; $m <= 12; $m++)
										<option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
									@endfor
								</select>
							</div>
							<div id="report-period-periodic" class="fields-flex-row" style="display:none;">
								<div class="local-forms">
									<label>From <span class="req-star">*</span></label>
									<input type="text" id="s_from_date" name="from_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
								</div>
								<div class="local-forms">
									<label>To <span class="req-star">*</span></label>
									<input type="text" id="s_to_date" name="to_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
								</div>
							</div>
							<div class="tbl-top-buttons">
								<ul>
									<li><a id="search-patient-medicine-issue-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
									@if($canAdd)
										<li><a href="javascript:void(0)" onclick="addPatientMedicineModal()"><i class="fa fa-plus"></i> New</a></li>
									@endif
								</ul>
							</div>
						</div>

					</div>	
					<div class="sm-table mt-10">
						<div class="table-responsive">  
							<table id="patients_medicine_issue_tbl" class="table table-bordered table-sm table-text-sm">
								<thead>
									<tr>
										<th class="no-sort tr3btns"></th>
										<th>Issue Date Time</th>
										<th>Next Date</th>
										<th>Patient Name</th>
										<th>Mobile</th>
										<th>Address</th>
										<th>Diseases</th>
										<th>Delivery</th>
										<th>Ramarks</th>
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

	<!-- Add Patient Medicine Modal -->
	<div class="modal fade" id="addPatientMedicineModal">
		<div class="modal-dialog modal-fullscreen">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Issue Patient Medicine <span id="previous_medicine_dates" style="color: #d32027;"></span></h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
				</div>
				<form id="add_patient_medicine_form">

					<div class="modal-body">
						<div class="row">
							<div class="col-lg-8">
								<div class="row">
									<div class="col-lg-8">
										<div class="row">
											<div class="col-12 col-sm-12 flex-row-wb">
												<div class="form-group local-forms">
													<label>Patient Name / Mobile Number <span class="req-star">*</span></label>
													<select id="a_patient_id" class="form-control pmi-patient-list" name="patient_id" onchange="showPatientMedicineRecord(this.value)" required>
													</select>
												</div>
												<button class="btn btn-info" type="button" onclick="addPMIPatientModal()"><i class="fas fa-plus"></i></button>
											</div>

										</div>
									</div>
									<div class="col-lg-4">
										<div class="row">
											<div class="col-8 col-sm-8">
												<div class="form-group local-forms">
													<label>Date Time <span class="req-star">*</span></label>
													<input type="text" name="medicine_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
												</div>
											</div>
											<div class="col-4 col-sm-4">
												<div class="form-group local-forms">
													<label>Days <span class="req-star">*</span></label>
													<input type="number" name="days" class="form-control" required>
												</div>
											</div>
										</div>
									</div>

									<div class="col-lg-12">
										<div class="row">
											<div class="col-3 col-sm-3">
												<div class="form-group local-forms">
													<label>Delivery Mode <span class="req-star">*</span></label>
													<select class="form-control" name="delivery" required>
														<option></option>
														<option value="Walk In">Walk In</option>
														<option value="Home Delivery">Home Delivery</option>
														<option value="Courier">Courier</option>
													</select>
												</div>
											</div>

											<div class="col-6 col-sm-6">
												<div class="form-group local-forms">
													<label>Diseases <span class="req-star">*</span></label>
													<select class="form-control select2" name="disease_ids[]" multiple required>
														<option></option>
														@foreach($diseases as $drow)
															<option value="{{ $drow->id }}">{{ $drow->disease_name }}</option>
														@endforeach
													</select>
												</div>
											</div>
											<div class="col-3 col-sm-3">
												<div class="form-group local-forms">
													<label>Remarks </label>
													<input type="text" name="remarks" class="form-control">
												</div>
											</div>
										</div>
									</div>

									<hr style="color: #9f9f9f;">

									<div id="patient_medicine_detail" class="col-lg-12 mt-20">
										<input type="hidden" id="pmnor" value="0">
										<div class="row">
											<div class="col-12 col-sm-3">
												<div class="form-group local-forms">
													<label>Medicine Name <span class="req-star">*</span></label>
													<select id="a_medicine_name" name="medicine_name" class="form-control"> 
													</select>
												</div>
											</div>
											<div class="col-12 col-sm-2">
												<div class="form-group local-forms">
													<label>Dose <span class="req-star">*</span></label>
													<input type="text" id="a_dose" name="dose" class="form-control">
												</div>
											</div>
											<div class="col-12 col-sm-2">
												<div class="form-group local-forms">
													<label>In Day <span class="req-star">*</span></label>
													<select id="a_dose_interval" name="dose_interval" class="form-control"> 
													</select>
												</div>
											</div>
											<div class="col-12 col-sm-4">
												<div class="form-group local-forms">
													<label>Remarks</label>
													<input type="text" id="a_remarks" name="remarks" class="form-control">
												</div>
											</div>
											<div class="col-12 col-sm-1">
												<div class="form-group local-forms">
													<button type="button" class="ssbtn" onclick="addPatientMedicine()"><i class="fas fa-plus"></i></button>
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-12 col-sm-12">
												<div class="table-responsive">  
													<table id="patient_medicine_tbl" class="table table-bordered table-sm table-text-sm">
														<thead>
															<tr>
																<th class="tr1btn"></th>
																<th>Medicine</th>
																<th>Dose</th>
																<th>Interval</th>
																<th>Remarks</th>
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
							<div class="col-lg-4">
								<table id="patient_previous_medicines_tbl" class="table table-bordered table-sm">
									<thead>
										<tr>
											<th colspan="4" style="text-align: center;background: #0c4079;color: #FFF;border: 2px solid #0c4079;">Previous Medicines List</th>
										</tr>
										<tr>
											<th class="tr1btn"></th>
											<th>Medicine</th>
											<th>Dose</th>
											<th>Interval</th>
										</tr>
									</thead>
									<tbody id="patient_previous_medicines_list">
										<tr>
											<td colspan="4" style="text-align: center;">No Previous Medicine Record</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button id="add-patient-medicine-btn" class="btn btn-primary ml-10" type="submit">Save</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Edit Patient Medicine Modal -->
	<div class="modal fade" id="editPatientMedicineModal">
		<div class="modal-dialog modal-fullscreen">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Issue Patient Medicine <span id="e_previous_medicine_dates" style="color: #d32027;"></span></h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
				</div>
				<form id="edit_patient_medicine_form">

					<input type="hidden" id="patient_periodic_medicine_id" name="patient_periodic_medicine_id" value="">
					<div class="modal-body">
						<div class="row">
							<div class="col-lg-8">
								<div class="row">
									<div class="col-lg-8">
										<div class="row">
											<div class="col-12 col-sm-12 flex-row-wb">
												<div class="form-group local-forms">
													<label>Patient Name / Mobile Number <span class="req-star">*</span></label>
													<select id="e_patient_id" class="form-control pmi-patient-list" name="patient_id" onchange="showPatientMedicineRecord(this.value)" required>
													</select>
												</div>
												<button class="btn btn-info" type="button" onclick="addPMIPatientModal()"><i class="fas fa-plus"></i></button>
											</div>

										</div>
									</div>
									<div class="col-lg-4">
										<div class="row">
											<div class="col-8 col-sm-8">
												<div class="form-group local-forms">
													<label>Date Time <span class="req-star">*</span></label>
													<input type="text" id="medicine_date_time" name="medicine_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
												</div>
											</div>
											<div class="col-4 col-sm-4">
												<div class="form-group local-forms">
													<label>Days <span class="req-star">*</span></label>
													<input type="number" id="days" name="days" class="form-control" required>
												</div>
											</div>
										</div>
									</div>

									<div class="col-lg-12">
										<div class="row">
											<div class="col-3 col-sm-3">
												<div class="form-group local-forms">
													<label>Delivery Mode <span class="req-star">*</span></label>
													<select class="form-control" id="delivery" name="delivery" required>
														<option></option>
														<option value="Walk In">Walk In</option>
														<option value="Home Delivery">Home Delivery</option>
														<option value="Courier">Courier</option>
													</select>
												</div>
											</div>

											<div class="col-6 col-sm-6">
												<div class="form-group local-forms">
													<label>Diseases <span class="req-star">*</span></label>
													<select class="form-control select2" id="disease_ids" name="disease_ids[]" multiple required>
														<option></option>
														@foreach($diseases as $drow)
															<option value="{{ $drow->id }}">{{ $drow->disease_name }}</option>
														@endforeach
													</select>
												</div>
											</div>
											<div class="col-3 col-sm-3">
												<div class="form-group local-forms">
													<label>Remarks </label>
													<input type="text" id="remarks" name="remarks" class="form-control">
												</div>
											</div>
										</div>
									</div>

									<hr style="color: #9f9f9f;">

									<div id="e_patient_medicine_detail" class="col-lg-12 mt-20">
										<input type="hidden" id="epmnor" value="0">
										<div class="row">
											<div class="col-12 col-sm-3">
												<div class="form-group local-forms">
													<label>Medicine Name <span class="req-star">*</span></label>
													<select id="e_medicine_name" name="medicine_name" class="form-control"> 
													</select>
												</div>
											</div>
											<div class="col-12 col-sm-2">
												<div class="form-group local-forms">
													<label>Dose <span class="req-star">*</span></label>
													<input type="text" id="e_dose" name="dose" class="form-control">
												</div>
											</div>
											<div class="col-12 col-sm-2">
												<div class="form-group local-forms">
													<label>In Day <span class="req-star">*</span></label>
													<select id="e_dose_interval" name="dose_interval" class="form-control"> 
													</select>
												</div>
											</div>
											<div class="col-12 col-sm-4">
												<div class="form-group local-forms">
													<label>Remarks</label>
													<input type="text" id="e_remarks" name="remarks" class="form-control">
												</div>
											</div>
											<div class="col-12 col-sm-1">
												<div class="form-group local-forms">
													<button type="button" class="ssbtn" onclick="addEPatientMedicine()"><i class="fas fa-plus"></i></button>
												</div>
											</div>
										</div>

										<div class="row">
											<div class="col-12 col-sm-12">
												<div class="table-responsive">  
													<table id="e_patient_medicine_tbl" class="table table-bordered table-sm table-text-sm">
														<thead>
															<tr>
																<th class="tr1btn"></th>
																<th>Medicine</th>
																<th>Dose</th>
																<th>Interval</th>
																<th>Remarks</th>
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
							<div class="col-lg-4">
								<table id="e_patient_previous_medicines_tbl" class="table table-bordered table-sm">
									<thead>
										<tr>
											<th colspan="4" style="text-align: center;background: #0c4079;color: #FFF;border: 2px solid #0c4079;">Previous Medicines List</th>
										</tr>
										<tr>
											<th class="tr1btn"></th>
											<th>Medicine</th>
											<th>Dose</th>
											<th>Interval</th>
										</tr>
									</thead>
									<tbody id="e_patient_previous_medicines_list">
										<tr>
											<td colspan="4" style="text-align: center;">No Previous Medicine Record</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button id="edit-patient-medicine-btn" class="btn btn-primary ml-10" type="submit">Save</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<!-- Add PMI Patient Modal -->
	<div class="modal fade" id="addPMIPatientModal">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Add Patient</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
				</div>
				<form id="add-pmi-patient-form" enctype="multipart/form-data">
					<div class="modal-body">

						<div class="row">
							<div class="col-12 col-sm-9">
								<div class="row">
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Category <span class="req-star">*</span></label>
											<select name="patient_category_id" class="form-control" required>
												@foreach($patientCategories as $pcrow)
													<option value="{{ $pcrow->id }}">{{ $pcrow->patient_category_name }}</option>
												@endforeach
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Patient Name <span class="req-star">*</span></label>
											<input type="text" name="patient_name" class="form-control" required>
										</div>
									</div>
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Guardian Name</label>
											<input type="text" name="guardian_name" class="form-control">
										</div>
									</div>
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Gender <span class="req-star">*</span></label>
											<select name="gender" class="form-control" required>
												<option></option>
												<option value="Male">Male</option>
												<option value="Female">Female</option>
												<option value="Other">Other</option>
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Age <span class="req-star">*</span></label>
											<input type="number" name="age" class="form-control" required>
										</div>
									</div>
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Blood Group</label>
											<select class="form-control" name="blood_group">
												<option></option>
												@foreach($bloodGroups as $bg_row)
													<option value="{{ $bg_row->blood_group }}">{{ $bg_row->blood_group }}</option>
												@endforeach
											</select>
										</div>
									</div>
									<div class="col-12 col-sm-6">
										<div class="form-group local-forms">
											<label>Mobile Number <span class="req-star">*</span></label>
											<input type="number" name="mobile_number" class="form-control" required>
										</div>
									</div>
									<div class="col-12 col-sm-6">
										<div class="form-group local-forms">
											<label>Alernate Mobile Number</label>
											<input type="number" name="alternate_mobile_number" class="form-control">
										</div>
									</div>
								</div>
							</div>
							<div class="col-12 col-sm-3">
								<div class="slim_image_box">
									<input class="form-control" type="hidden" id="photo" name="photo" value="">
									<div class="slim"
									data-label="Upload Image"
									data-ratio="1:1"
									data-size="300,300"
									data-did-upload="pUpload"
									data-service="{{ route('uploads.photo.store', 'patient') }}?_token={{ csrf_token() }}">
									<input type="file" name="slim[]" capture />
								</div>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="row">
								<div class="col-12 col-sm-12">
									<div class="form-group local-forms">
										<label>Address <span class="req-star">*</span></label>
										<input type="text" name="address" class="form-control" required>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Save</button>
				</div>
			</form>
		</div>
	</div>
	</div>

	<!-- Detail Modal -->
	<div class="modal fade" id="issueMedicineDetailModal">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
				</div>
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="issue-medicine-detail">
								<table id="issue-medicine-detail-tbl" class="table table-bordered table-sm">
									<thead>
										<tr>
											<th>Medicine</th>
											<th>Dose</th>
											<th>Interval</th>
											<th>Remarks</th>
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
	</div>
@else
	<div class="themebody-wrap">
		<div class="no-access">
			<i class="fa-duotone fa-shield-halved"></i>
			<p>Access Denied</p>
		</div>
	</div>
@endif
@endsection

@push('scripts')
<script>
	function pUpload(error, data, response) {
		$("#photo").val(response.file);
	}

	$(document).ready(function() {
		if ($('#s_month').hasClass('select2-hidden-accessible')) {
			$('#s_month').select2('destroy');
		}
		$('#s_month').select2({ placeholder: 'Month' });
	});
</script>
@endpush
