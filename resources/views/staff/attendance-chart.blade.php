@extends('layouts.app')
@section('title', 'Staff Attendance Chart View')
@section('content')
<div class="themebody-wrap ipd-wrap">

	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Staff Attendance Chart View</div>
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
								<li><a id="attendance-cv-report-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
							</ul>
						</div>
					</div>

				</div>
				<div class="sm-table mt-10">
					<div class="table-responsive">
						<canvas id="attendanceChart" width="800" height="175"></canvas>
						<canvas id="attendanceHoursChart" width="800" height="175"></canvas>
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
		let attendanceChart;
		let attendanceHoursChart;

		// Month list is rendered server-side; replaces the remote select2 the global app.js binds to #s_month.
		if ($('#s_month').hasClass('select2-hidden-accessible')) {
			$('#s_month').select2('destroy');
		}
		$('#s_month').select2({ placeholder: 'Month' });

		$('#attendance-cv-report-btn').on('click', function () {
			$.ajax({
				url: "{{ route('staff.attendance-chart.data') }}",
				type: 'POST',
				dataType: 'json',
				data: {
					report_period: $('#s_report_period').val(),
					search_date: $('#s_date').val(),
					search_month: $('#s_month').val(),
					from_date: $('#s_from_date').val(),
					to_date: $('#s_to_date').val()
				},
				success: function (response) {
					if (response.error) {
						return alert(response.error);
					}
					renderStaffAttendanceChart(response.data);
				}
			});
		});

		function renderStaffAttendanceChart(data) {
			const labels = data.map(item => item.staff_name);
			const presentData = data.map(item => parseInt(item.total_working_days));
			const absentData = data.map(item => parseInt(item.absent_days));
			const leaveData = data.map(item => parseInt(item.leave_days));
			const hoursData = data.map(item => item.total_working_hours);

			const datasets = [
				{ label: 'Working Days', data: presentData, backgroundColor: 'rgba(75,192,192,0.5)' },
				{ label: 'Absent Days', data: absentData, backgroundColor: 'rgba(255,99,132,0.5)' },
				{ label: 'Leave Days', data: leaveData, backgroundColor: 'rgba(255,206,86,0.5)' }
			];

			const datasetsh = [
				{ label: 'Working Time', data: hoursData, backgroundColor: 'rgba(75,192,192,0.5)' }
			];

			if (!attendanceChart) {
				const ctx = document.getElementById('attendanceChart').getContext('2d');
				attendanceChart = new Chart(ctx, {
					type: 'bar',
					data: { labels, datasets },
					options: { responsive: true, scales: { y: { beginAtZero: true } } }
				});
			} else {
				attendanceChart.data.labels = labels;
				attendanceChart.data.datasets = datasets;
				attendanceChart.update();
			}

			if (!attendanceHoursChart) {
				const ctx = document.getElementById('attendanceHoursChart').getContext('2d');
				attendanceHoursChart = new Chart(ctx, {
					type: 'bar',
					// Legacy passed `datasetsh` as a property name (so the hours chart stayed empty); fixed here.
					data: { labels, datasets: datasetsh },
					options: { responsive: true, scales: { y: { beginAtZero: true } } }
				});
			} else {
				attendanceHoursChart.data.labels = labels;
				attendanceHoursChart.data.datasets = datasetsh;
				attendanceHoursChart.update();
			}
		}
	});
</script>
@endpush
