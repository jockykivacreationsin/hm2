@extends('layouts.app')
@section('title', 'Staff Attendance Report')
@section('content')
<div class="themebody-wrap ipd-wrap">

	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Staff Attendance Report</div>
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
						<div id="report-period-monthly" class="local-forms">
							<label>Month <span class="req-star">*</span></label>
							<select class="form-control" id="s_month" data-lookup-url="{{ route('pickers.months') }}">
								@foreach($months as $m)
									<option value="{{ $m['id'] }}" @selected($m['id'] == date('n'))>{{ $m['text'] }}</option>
								@endforeach
							</select>
						</div>
						<div id="report-period-periodic" class="fields-flex-row">
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
								<li><a id="attendance-report-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
							</ul>
						</div>
					</div>

				</div>
				<div class="sm-table mt-10">
					<div class="table-responsive">
						{{-- Not "staff_attendance_report_tbl": the global app.js auto-binds that id to its own data source. --}}
						<table id="staff_attendance_report_dt" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr3btns"></th>
									<th>Staff Name</th>
									<th>Designation</th>
									<th>Total Days</th>
									<th>Total Work Days</th>
									<th>Total Work Time</th>
									<th>Absent</th>
									<th>Leave</th>
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
@endsection

@push('scripts')
<script>
	$(document).ready(function () {
		// Month list is rendered server-side; replaces the remote select2 the global app.js binds to #s_month.
		if ($('#s_month').hasClass('select2-hidden-accessible')) {
			$('#s_month').select2('destroy');
		}
		$('#s_month').select2({ placeholder: 'Month' });

		var dtsar = $('#staff_attendance_report_dt').DataTable({
			dom: 'Blfrtip',
			"pageLength": 100,
			'processing': true,
			'serverSide': true,
			'autoWidth': false,
			'serverMethod': 'post',
			'ajax': {
				'url': "{{ route('staff.attendance-report.data') }}",
				'type': 'POST',
				'data': function (data) {
					data.report_period = $('#s_report_period').val();
					data.search_date = $('#s_date').val();
					data.search_month = $('#s_month').val();
					data.from_date = $('#s_from_date').val();
					data.to_date = $('#s_to_date').val();
				}
			},
			'columns': [
				{ data: 'action', orderable: false },
				{ data: 'staff_name' },
				{ data: 'designation' },
				{ data: 'total_days' },
				{ data: 'total_working_days' },
				{ data: 'total_working_hours' },
				{ data: 'absent_days' },
				{ data: 'leave_days' },
			],
			columnDefs: [{ className: 'tr-sm-action-buttons center', targets: [0] }],
			buttons: [
				{
					extend: 'excel',
					text: 'Excel',
					className: 'buttons-html5',
					exportOptions: {
						modifer: { page: 'all', search: 'none' },
						columns: 'th:not(:first-child)'
					}
				},
				{
					extend: 'pdf',
					text: 'PDF',
					className: 'buttons-html5',
					exportOptions: { columns: 'th:not(:first-child)' },
				},
				{
					extend: 'print',
					text: 'Print',
					className: 'buttons-html5',
					exportOptions: { columns: 'th:not(:first-child)' }
				}
			],
			initComplete: function () {
				$('[data-bs-toggle="tooltip"]').tooltip();
			},
			drawCallback: function () {
				$('[data-bs-toggle="tooltip"]').tooltip();
			}
		});

		$('#attendance-report-btn').click(function () {
			dtsar.draw();
		});
	});
</script>
@endpush
