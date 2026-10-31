{{-- ABDM FHIR attachment: OPD prescription. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (visit + patient + doctor, array), prescriptions (list of ['row' => prescription, 'medicines' => list]), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Prescription')
@section('report-title', 'PRESCRIPTION')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">OPD ID:</td><td>{{ $row['id'] }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Visit Date:</td><td>{{ $row['opd_date'] }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $row['doctor_name'] ?? '' }}</td>
		<td class="label">Contact:</td><td>{{ $row['mobile_number'] }}</td>
	</tr>
</table></div>

@forelse($prescriptions as $rx)
	<div class="rpt-rx">&#8478;</div>

	@if($rx['medicines'])
	<table class="rpt-table">
		<tr>
			<th style="width:35%;">Medicine</th><th style="width:15%;">Dose</th><th style="width:20%;">Timing</th>
			<th style="width:15%;">Duration</th><th style="width:15%;">Meal</th>
		</tr>
		@foreach($rx['medicines'] as $m)
		@php $timing = trim((string) ($m['timing_names'] ?? '')); @endphp
		<tr>
			<td>{{ $m['medicine_name'] }}</td>
			<td>{{ $m['dose_quantity'] ?? '1' }}</td>
			<td>{{ $timing === '' ? '-' : $timing }}</td>
			<td>{{ ($m['duration_days'] ?? '') === '' || $m['duration_days'] === null ? '-' : $m['duration_days'] . ' days' }}</td>
			<td>{{ $m['meal_relation'] ?? '-' }}</td>
		</tr>
		@endforeach
	</table>
	@endif

	@if(!empty($rx['row']['findings']))
	<div class="rpt-note">
		<span class="rpt-note-title">Findings:</span><br>
		<span class="rpt-note-body">{!! nl2br(e($rx['row']['findings'])) !!}</span>
	</div>
	@endif

	@if(!empty($rx['row']['prescription_date_time']))
	<p class="rpt-meta">Prescribed on: {{ $rx['row']['prescription_date_time'] }}</p>
	@endif

	@if(!$loop->last)<hr class="rpt-divider">@endif
@empty
	<div class="rpt-empty">No prescriptions recorded for this visit</div>
@endforelse
@endsection

@section('signature')
	<p><strong>{{ $row['doctor_name'] ?? 'N/A' }}</strong></p>
	@if(!empty($row['qualification']))<p>{{ $row['qualification'] }}</p>@endif
@endsection
