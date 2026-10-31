@extends('layouts.app')
@section('title', 'Discharged Patients')
@section('content')
@if($canView)
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Discharged Patients</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">
						<table id="discharged_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr4btns"></th>
									<th>Patient Name</th>
									<th>Mobile Number</th>
									<th>Diagnosis</th>
									<th>OPD No</th>
									<th>IPD No</th>
									<th>Admission Date</th>
									<th>Discharge Date</th>
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
@if($canView)
<script>
	var dischargedTable; // Global variable to store DataTable instance
	
	$(document).ready(function() {
		// Initialize DataTables with server-side processing
		dischargedTable = $('#discharged_tbl').DataTable({
			processing: true,      // Show processing indicator
			serverSide: true,      // Enable server-side processing
			scrollX: false,        // Disable horizontal scrolling
			autoWidth: false,      // Disable automatic column width calculation
			ajax: {
				url: '{{ route('ipd.discharged.data') }}',
				type: 'POST',
				data: function(d) {
					// Add custom parameter
					d.get_discharged_datatable = 'Yes';
				}
			},
			columns: [
				{ data: 0, orderable: false },  // Actions column
				{ data: 1 },  // Patient Name
				{ data: 2 },  // Mobile Number
				{ data: 3 },  // Diagnosis
				{ data: 4 },  // OPD No
				{ data: 5 },  // IPD No
				{ data: 6 },  // Admission Date
				{ data: 7 }   // Discharge Date
			],
			order: [[7, 'desc']],  // Default sort by Discharge Date (newest first)
			pageLength: 50,        // Show 50 records per page
			lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
			language: {
				search: "Search Discharged Patients:",
				lengthMenu: "Show _MENU_ patients",
				info: "Showing _START_ to _END_ of _TOTAL_ discharged patients",
				infoEmpty: "No discharged patients found",
				infoFiltered: "(filtered from _MAX_ total discharged patients)",
				zeroRecords: "No matching discharged patients found",
				emptyTable: "No discharged patients in database",
				paginate: {
					first: "First",
					last: "Last",
					next: "Next",
					previous: "Previous"
				}
			},
			drawCallback: function() {
				if (typeof $('[data-bs-toggle="tooltip"]').tooltip === 'function') {
					$('[data-bs-toggle="tooltip"]').tooltip();
				}
			}
		});
	});
	
	// Inline edit discharge date on double click
	$(document).on('dblclick', '.editable-discharge-date', function() {
		var $span = $(this);
		var currentDate = $span.text().trim();
		var dischargeId = $span.data('discharge-id');
		
		// Create input field with flatpickr
		var $input = $('<input type="text" class="form-control form-control-sm inline-date-edit" style="width: 150px; display: inline-block;" />');
		$input.val(currentDate);
		
		// Replace span with input
		$span.hide();
		$span.after($input);
		
		// Initialize flatpickr
		var fp = flatpickr($input[0], {
			enableTime: true,
			dateFormat: "d-m-Y h:i K",
			defaultDate: currentDate,
			onClose: function(selectedDates, dateStr, instance) {
				if(dateStr && dateStr !== currentDate) {
					// Update via AJAX
					$.ajax({
						url: '{{ route('ipd.discharged.update-date') }}',
						type: 'POST',
						data: {
							_token: '{{ csrf_token() }}',
							discharge_id: dischargeId,
							discharge_date_time: dateStr
						},
						dataType: 'json',
						success: function(response) {
							if(response.success) {
								$span.text(response.formatted_date);
								$input.remove();
								$span.show();
								Swal.fire({
									icon: "success",
									title: "Updated!",
									text: "Discharge date updated successfully.",
									showConfirmButton: false,
									timer: 1500
								});
							} else {
								$input.remove();
								$span.show();
								Swal.fire({
									icon: "error",
									title: "Error!",
									text: response.message || "Failed to update discharge date",
									showConfirmButton: false,
									timer: 2000
								});
							}
						},
						error: function() {
							$input.remove();
							$span.show();
							Swal.fire({
								icon: "error",
								title: "Error!",
								text: "Failed to update discharge date",
								showConfirmButton: false,
								timer: 2000
							});
						}
					});
				} else {
					// No change or cancelled
					$input.remove();
					$span.show();
				}
			}
		});
		
		// Open picker and focus
		$input.focus();
		fp.open();
	});
</script>
@endif
@endpush
