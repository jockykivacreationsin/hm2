@extends('layouts.app')
@section('title', 'Staff Attendance Calendar')
@section('content')
@php
	$statusColours = ['Present' => '#4da047', 'Halfday' => '#7DBD78', 'Absent' => '#ff4242', 'Leave' => '#f9ce55'];
@endphp
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Staff Attendance Calendar</div>
					<div class="attendance-dots">
						<span style="display:inline-block;width:15px;height:15px;background:#4da047;border:1px solid #4da047;margin-right:5px;"></span> Present
						<span style="display:inline-block;width:15px;height:15px;background:#7DBD78;border:1px solid #7DBD78;margin-left:15px;margin-right:5px;"></span> Halfday
						<span style="display:inline-block;width:15px;height:15px;background:#ff4242;border:1px solid #ff4242;margin-left:15px;margin-right:5px;"></span> Absent
						<span style="display:inline-block;width:15px;height:15px;background:#f9ce55;border:1px solid #f9ce55;margin-left:15px;margin-right:5px;"></span> Leave
						<span style="display:inline-block;width:15px;height:15px;background:#ffffff;border:1px solid #ccc;margin-left:15px;margin-right:5px;"></span> Empty
					</div>
					<div class="fc-toolbar">
						<div class="fc-left">
							<a href="{{ route('staff.attendance-calendar', ['d' => 'l', 'month' => $month - 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-left"></i></a>
						</div>
						<div class="fc-center"><h2>{{ $monthName }} {{ $year }}</h2></div>
						<div class="fc-right">
							<a href="{{ route('staff.attendance-calendar', ['d' => 'r', 'month' => $month + 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-right"></i></a>
						</div>
					</div>
				</div>

				<div class="sm-table mt-10">
					<div class="table-responsive">
						<table class="table table-sm table-bordered">
							<thead>
								<tr>
									<th>Employee</th>
									@for($i = 1; $i <= $days; $i++)
										<th>{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</th>
									@endfor
								</tr>
							</thead>
							<tbody>
								@foreach($employees as $employee)
								<tr>
									<td>{{ $employee->staff_name }}</td>
									@for($i = 1; $i <= $days; $i++)
										@php
											$date   = sprintf('%04d-%02d-%02d', $year, $month, $i);
											$colour = $statusColours[$attendance[$employee->id][$date] ?? ''] ?? null;
										@endphp
										@if($colour)
											<td style="background:{{ $colour }};border:1px solid {{ $colour }};"></td>
										@else
											<td></td>
										@endif
									@endfor
								</tr>
								@endforeach
							</tbody>
						</table>

						<div class="attendance-dots mt-3">
							<span style="display:inline-block;width:15px;height:15px;background:#4da047;border:1px solid #4da047;margin-right:5px;"></span> Present
							<span style="display:inline-block;width:15px;height:15px;background:#7DBD78;border:1px solid #7DBD78;margin-left:15px;margin-right:5px;"></span> Halfday
							<span style="display:inline-block;width:15px;height:15px;background:#f92020;border:1px solid #f92020;margin-left:15px;margin-right:5px;"></span> Absent
							<span style="display:inline-block;width:15px;height:15px;background:#f9ce55;border:1px solid #f9ce55;margin-left:15px;margin-right:5px;"></span> Leave
							<span style="display:inline-block;width:15px;height:15px;background:#ffffff;border:1px solid #ccc;margin-left:15px;margin-right:5px;"></span> Empty
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
