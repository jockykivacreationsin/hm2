@extends('layouts.app')
@section('title', 'DCD Register')
@section('content')
@if($ps->hasPermission('m_dcd'))
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">DCD Register</div>
					<div class="tbl-filter-box">
						<div class="local-forms">
							<label>Status</label>
							<select class="form-control" id="filter_dcd_status">
								<option value="New Admission">New Admission</option>
								<option value="TO IPD">TO IPD</option>
								<option value="Discharged">Discharged</option>
								<option value="All">All</option>
							</select>
						</div>
						@if($canNew)
							<div class="tbl-top-buttons">
								<ul>
									<li><a href="javascript:void(0)" onclick="createDCDModal()"><i class="fa fa-plus"></i> New</a></li>
								</ul>
							</div>
						@endif
					</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table id="dcd_list_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr1btn">Actions</th>
									<th>Date</th>
									<th>DCD No</th>
									<th>Name</th>
									<th>Gender</th>
									<th>Age</th>
									<th>Phone</th>
									<th>Bed</th>
								</tr>
							</thead>
							<tbody>
								<!-- Data will be loaded via AJAX by DataTables -->
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="createDCDModal">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create DCD</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="create_dcd_form" action="{{ route('dcd.store') }}" method="POST">
				@csrf
				<input type="hidden" id="patient_id" name="patient_id">
				<div class="modal-body">
					<div class="row">
						<div class="col-lg-8">
							<div class="row">
								<div class="col-12 col-sm-12 flex-row-wb">
									<div class="form-group local-forms">
										<label>Enter OPD ID <span class="req-star">*</span></label>
										<input type="number" id="opd_id" name="opd_id" class="form-control" required>
									</div>
									<button class="btn btn-info" type="button" onclick="showOPDIDPatient()"><i class="fas fa-plus"></i></button>
								</div>
							</div>
							<div id="ipd_patient_info_box" class="row fg-bottom-border" style="display:none;">
								<div id="ipd_patient_info" class="col-12 col-sm-12"></div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Mobile Number <span class="req-star">*</span></label>
										<input type="number" id="mobile_number" name="mobile_number" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-8">
									<div class="form-group local-forms">
										<label>Address <span class="req-star">*</span></label>
										<input type="text" id="address" name="address" class="form-control" required>
									</div>
								</div>
							</div>
							<div class="col-12 col-sm-12 mt-20">
								<div class="form-group local-forms">
									<label>Previous Medical Note</label>
									<textarea name="previous_medical_note" class="form-control"></textarea>
								</div>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="row">
								<div class="col-12 col-sm-12">
									<div class="form-group local-forms">
										<label>Date <span class="req-star">*</span></label>
										<input type="text" name="dcd_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
									</div>
								</div>
								<div class="col-12 col-sm-12">
									<div class="form-group local-forms">
										<label>Consultant Doctor <span class="req-star">*</span></label>
										<select class="form-control" id="a_doctor_id" name="consultant_doctor_id" required>
											<option value=""></option>
											@foreach($doctors ?? [] as $doc)
												<option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
											@endforeach
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-12">
									<div class="form-group local-forms mb-25">
										<input type="hidden" id="selected_bed_id" name="bed_id" value="0">
										<div>
											<a href="javascript:void(0)" class="btn btn-sm btn-outline-success" onclick="showBedsUIModal()">Choose Bed</a><span id="selected_bed_name" class="ml-10">No Bed Selected</span>
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
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="showBedsUIModal">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Beds</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<div class="modal-body nptop">
				<div id="beds-ui-box"></div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
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
	window.dcdRoutes = Object.assign(window.dcdRoutes || {}, {
		opdPatient: "{{ route('dcd.opd-patient', ['opdId' => '__ID__']) }}"
	});

	var dcdListTable; // Global variable to store DataTable instance
	
	$(document).ready(function() {
		// Initialize DataTables with server-side processing
		dcdListTable = $('#dcd_list_tbl').DataTable({
			processing: true,      // Show processing indicator
			serverSide: true,      // Enable server-side processing
			scrollX: false,        // Disable horizontal scrolling
			autoWidth: false,      // Disable automatic column width calculation
			ajax: {
				url: '{{ route('dcd.data') }}',
				type: 'GET',
				data: function(d) {
					// Add custom filter parameters
					d.filter_dcd_status = $('#filter_dcd_status').val();
				}
			},
			columns: [
				{ data: 0, orderable: false },  // Actions column
				{ data: 1 },  // Date
				{ data: 2 },  // DCD No
				{ data: 3 },  // Name
				{ data: 4 },  // Gender
				{ data: 5 },  // Age
				{ data: 6 },  // Phone
				{ data: 7 }   // Bed
			],
			order: [[2, 'desc']],  // Default sort by DCD No (descending)
			pageLength: 50,        // Show 50 records per page
			lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
			language: {
				search: "Search DCD:",
				lengthMenu: "Show _MENU_ records",
				info: "Showing _START_ to _END_ of _TOTAL_ DCD records",
				infoEmpty: "No DCD records found",
				infoFiltered: "(filtered from _MAX_ total records)",
				zeroRecords: "No matching DCD records found",
				emptyTable: "No DCD records in database",
				paginate: {
					first: "First",
					last: "Last",
					next: "Next",
					previous: "Previous"
				}
			}
		});
		
		// Reload table when status filter changes
		$('#filter_dcd_status').on('change', function() {
			dcdListTable.ajax.reload();
		});

		$('#createDCDModal').on('shown.bs.modal', function () {
			$('#a_doctor_id').select2({
				dropdownParent: $('#createDCDModal'),
				placeholder: "Doctor"
			});
		});
		
		var opdIdParam = {{ $opdIdParam ?? 0 }};
		
		// Auto-open Create DCD modal and fill OPD ID if parameter exists
		if (opdIdParam > 0) {
			// Open the modal
			$('#createDCDModal').modal('show');
			
			// Fill the OPD ID input
			$('#opd_id').val(opdIdParam);
			
			// Trigger the showOPDIDPatient function to fetch patient data
			setTimeout(function() {
				showOPDIDPatient();
			}, 500);
		}
	});

	function pUpload(error, data, response) {
		$("#photo").val(response.file);
	}

appForm.bind('#create_dcd_form', {
        validate: function () { return $('#selected_bed_id').val() === '0' ? 'Please Select Bed' : true; },
        onSuccess: function () {
            $('#create_dcd_form')[0].reset();
            $('#createDCDModal').modal('hide');
            appForm.toast('success', 'Saved!', 'Saved Successfully.');
            window.location.href = '{{ route('dcd.index') }}';
        }
    });
</script>
@endpush
