@extends('layouts.app')
@section('title', 'DCD Discharged Patients')
@section('content')
@if($canView)
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Discharge Approvals</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">
						<table id="dcd_discharged_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr2btn"></th>
									<th>Patient Name</th>
									<th>Mobile Number</th>
									<th>Diagnosis</th>
									<th>OPD No</th>
									<th>DCD No</th>
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
	var dcdDischargedTable; // Global variable to store DataTable instance
	
	$(document).ready(function() {
		// Initialize DataTables with server-side processing
		dcdDischargedTable = $('#dcd_discharged_tbl').DataTable({
			processing: true,      // Show processing indicator
			serverSide: true,      // Enable server-side processing
			scrollX: false,        // Disable horizontal scrolling
			autoWidth: false,      // Disable automatic column width calculation
			ajax: {
				url: '{{ route('dcd.discharged.data') }}',
				type: 'POST',
				data: function(d) {
					d._token = '{{ csrf_token() }}';
					// Add custom parameter
					d.get_dcd_discharged_datatable = 'Yes';
				}
			},
			columns: [
				{ data: 0, orderable: false },  // Actions column
				{ data: 1 },  // Patient Name
				{ data: 2 },  // Mobile Number
				{ data: 3 },  // Diagnosis
				{ data: 4 },  // OPD No
				{ data: 5 },  // DCD No
				{ data: 6 },  // Admission Date
				{ data: 7 }   // Discharge Date
			],
			order: [[7, 'desc']],  // Default sort by Discharge Date (newest first)
			pageLength: 50,        // Show 50 records per page
			lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
			language: {
				search: "Search DCD Discharged Patients:",
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
</script>
@endif
@endpush
