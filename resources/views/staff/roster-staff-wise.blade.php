@extends('layouts.app')
@section('title', 'Staff Wise Roster')
@section('content')
@if(!($canView ?? true))
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
					<div class="ipd-title">Staff Wise Roster</div>
					<div class="tbl-filter-box">
						<div class="local-forms">
							<label>Desig <span class="req-star">*</span></label>
							<select class="form-control" id="designation">
								<option value="Doctor">Doctor</option>
								<option value="Nurse">Nurse</option>
							</select>
						</div>

						<div class="local-forms">
							<label>Shift <span class="req-star">*</span></label>
							<select class="form-control" id="shift">
								<option value="Day">Day</option>
								<option value="Night">Night</option>
							</select>
						</div>

						<div class="local-forms">
							<label>Month <span class="req-star">*</span></label>
							<select class="form-control" id="salary_month">
								@php
									$curMonth = date('n');
								@endphp
								@for($m = 1; $m <= 12; $m++)
									@php
										$monthName = date('F', mktime(0, 0, 0, $m, 10));
									@endphp
									<option value="{{ $m }}" {{ $m == $curMonth ? 'selected' : '' }}>{{ $monthName }}</option>
								@endfor
							</select>
						</div>

						<div class="local-forms">
							<label>Year <span class="req-star">*</span></label>
							<select class="form-control" id="salary_year">
								@php
									$curYear = date('Y');
								@endphp
								@for($y = $curYear - 3; $y <= $curYear + 1; $y++)
									<option value="{{ $y }}" {{ $y == $curYear ? 'selected' : '' }}>{{ $y }}</option>
								@endfor
							</select>
						</div>

						<a class="fx-icon-btn" href="javascript:void(0)" id="printRoster"><i class="fa fa-print"></i></a>
					</div>

				</div>
				<div class="sm-table mt-10">

					<div class="table-responsive">

						<table id="staff_wise_roster_tbl" class="table table-bordered table-sm table-text-sm">

						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canView ?? true)
<script>
	function staffWiseFilters() {
		return {
			month: $('#salary_month').val(),
			year: $('#salary_year').val(),
			designation: $('#designation').val(),
			shift: $('#shift').val()
		};
	}

	function loadStaffWiseRosterData() {
		const month = $('#salary_month').val();
		const year = $('#salary_year').val();
		const designation = $('#designation').val();
		const shift = $('#shift').val();

		$.get("{{ route('staff.roster.staff-wise.data') }}", { month, year, designation, shift }, function(html) {
			$('#staff_wise_roster_tbl').html(html);
		});
	}

	$(function () {
		loadStaffWiseRosterData();
		$('#salary_month, #salary_year, #designation, #shift').change(loadStaffWiseRosterData);
	});

	$(document).on('click', '#printRoster', function () {
		const month = $('#salary_month').val();
		const year = $('#salary_year').val();
		const designation = $('#designation').val();
		const shift = $('#shift').val();

		const url = `rpt-staff-wise-roster.php?month=${month}&year=${year}&designation=${encodeURIComponent(designation)}&shift=${encodeURIComponent(shift)}`;
		window.open(url, '_blank');
	});
</script>
@endif
@endpush
