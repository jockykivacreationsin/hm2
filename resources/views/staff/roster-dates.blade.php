@extends('layouts.app')
@section('title', 'Staff Roster Dates')
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
					<div class="ipd-title">Staff Roster Dates</div>
					<div class="fc-toolbar">
						<div class="fc-left">
							<a href="{{ route('staff.roster-dates', ['d' => 'l', 'month' => $month - 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-left"></i></a>
						</div>
						<div class="fc-center"><h2>{{ $monthName }} {{ $year }}</h2></div>
						<div class="fc-right">
							<a href="{{ route('staff.roster-dates', ['d' => 'r', 'month' => $month + 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-right"></i></a>
						</div>
					</div>
				</div>	
			</div>
		</div>
	</div>
	<div class="card">
		<div class="card-body">
			@foreach($sections as $title => $rows)
				<h4 class="card-title">{{ $title }}</h4>
				<table class="table table-bordered table-sm mb-10">
					<thead>
						<tr><th style="width: 30%">Name</th><th style="width: 70%">Dates</th></tr>
					</thead>
					<tbody>
						@foreach($rows as $row)
							<tr>
								<td>{{ $row->staff_name }}</td>
								<td>{{ $row->roster_days }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@endforeach
		</div>
	</div>
</div>
@endif
@endsection
