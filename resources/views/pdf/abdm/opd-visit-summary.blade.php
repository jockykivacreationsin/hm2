{{-- ABDM FHIR attachment: OPD visit summary (clinical summary, prescriptions, investigations, procedures; no billing).
     Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (visit + patient + doctor, array), vitals (label => value), prescriptions, investigations, procedures, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Visit Summary - ' . $row['patient_name'])
@section('report-title', 'OPD VISIT SUMMARY')

@section('content')
@php
	$symptoms = trim((string) $row['symptoms']);
	$remarks  = trim((string) $row['remarks']);
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">OPD No:</td><td>{{ (int) $row['id'] }}/bmh/{{ $row['iyear'] }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($row['patient_id']) }}</td>
	</tr>
	<tr>
		<td class="label">Visit Date:</td><td>{{ $row['opd_date_time_display'] }}</td>
		<td class="label">Printed:</td><td>{{ date('d-M-Y') }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ trim((string) ($row['doctor_name'] ?? '')) !== '' ? $row['doctor_name'] : 'N/A' }}</td>
		<td class="label">Contact:</td><td>{{ $row['patient_mobile'] }}</td>
	</tr>
	<tr><td class="label">Address:</td><td colspan="3">{{ $row['patient_address'] }}</td></tr>
	{{-- ESM fields are shown for every patient category except the standard one (id 1). --}}
	@if($row['patient_category_id'] != 1)
	<tr>
		<td class="label">ESM Name:</td><td>{{ $row['esm_name'] }}</td>
		<td class="label">Card Number:</td><td>{{ $row['card_number'] }}</td>
	</tr>
	@endif
</table></div>

@if(!empty($vitals) || $symptoms !== '' || $remarks !== '')
	<div class="rpt-section">Clinical Summary</div>
	@if(!empty($vitals))
	<p class="rpt-line"><b>Vitals:</b>
		@foreach($vitals as $label => $value)<b>{{ $label }}:</b> {{ $value }}@if(!$loop->last) | @endif @endforeach
	</p>
	@endif
	{{-- Symptoms/findings are Summernote HTML (entity-encoded): decoded and reduced to basic tags. --}}
	@if($symptoms !== '')
	<p class="rpt-line"><b>Chief Complaint / Symptoms:</b></p>
	<div class="rpt-line">{!! \App\Support\SafeHtml::basic($row['symptoms']) !!}</div>
	@endif
	@if($remarks !== '')
	<p class="rpt-line"><b>Clinical Findings:</b></p>
	<div class="rpt-line">{!! \App\Support\SafeHtml::basic($row['remarks']) !!}</div>
	@endif
@endif

@if(count($prescriptions) > 0)
	<div class="rpt-section">Prescriptions</div>
	@foreach($prescriptions as $rx)
		<p class="rpt-line"><b>{{ $rx['date'] }}</b> - Prescribed by: {{ $rx['doctor'] }}</p>

		@if(trim((string) $rx['findings']) !== '')
		<div class="rpt-line">{!! \App\Support\SafeHtml::basic($rx['findings']) !!}</div>
		@endif

		@if(count($rx['medicines']) > 0)
		<table class="rpt-table rpt-table-sm">
			<tr><th>Medicine Name</th><th>Dose</th><th>Frequency</th><th>Timing</th><th>Meal Relation</th><th>Duration</th></tr>
			@foreach($rx['medicines'] as $m)
			<tr>
				<td>{{ $m['medicine_name'] }}</td>
				<td>{{ $m['dose_quantity'] }}</td>
				<td>{{ $m['frequency_display'] }}</td>
				<td>{{ $m['timing_display'] }}</td>
				<td>{{ $m['meal_relation'] }}</td>
				<td>{{ $m['duration_days'] }} days</td>
			</tr>
			@endforeach
		</table>
		@endif
	@endforeach
@endif

@if(count($investigations) > 0)
	<div class="rpt-section">Lab &amp; Radiology Investigations</div>
	@foreach($investigations as $svc)
		<p class="rpt-line"><b>{{ $svc['date'] }}</b> - {{ $svc['title'] }} ({{ $svc['charges'] }})</p>

		@if(count($svc['groups']) > 0)
		<table class="rpt-table rpt-table-sm">
			<tr><th style="width:40%;">Test Name</th><th style="width:30%;">Result</th><th style="width:30%;">Reference Range</th></tr>
			@foreach($svc['groups'] as $group)
			<tr class="rpt-group"><td colspan="3">{{ $group['charge_name'] }}</td></tr>
			@foreach($group['rows'] as $r)
			<tr{!! $r['highlighted'] ? ' class="rpt-highlight"' : '' !!}>
				<td>{{ $r['name'] }}</td>
				<td>{{ $r['value'] }}</td>
				<td>{{ $r['range'] }}</td>
			</tr>
			@endforeach
			@endforeach
		</table>
		@endif

		@if($svc['note'] !== '')
		<div class="rpt-note">
			<span class="rpt-note-title">{{ $svc['note_title'] }}</span>
			<span class="rpt-note-body">{{ $svc['note'] }}</span>
		</div>
		@endif
	@endforeach
@endif

@if(count($procedures) > 0)
	<div class="rpt-section">Procedures</div>
	@foreach($procedures as $proc)
		<p class="rpt-line"><b>{{ $proc['date'] }}</b> - {{ $proc['title'] }} ({{ $proc['charges'] }})</p>

		@if($proc['note'] !== '')
		<div class="rpt-note">
			<span class="rpt-note-title">Procedure Notes:</span>
			<span class="rpt-note-body">{{ $proc['note'] }}</span>
		</div>
		@endif
	@endforeach
@endif
@endsection

@section('signature')
	<p style="margin-top:25px;"><strong>Authorized Signature</strong></p>
	@if(!empty($sign_name))<p>{{ $sign_name }}</p>@endif
	@if(!empty($sign_designation))<p>{{ $sign_designation }}</p>@endif
@endsection
