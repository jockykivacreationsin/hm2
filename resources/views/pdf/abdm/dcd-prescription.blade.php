{{-- ABDM FHIR attachment: DCD prescription. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (visit + patient + doctor, array), isDischarged, prescriptions (list of ['row', 'medicines']), dischargeMeds (list), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Prescription - ' . $row['patient_name'])
@section('report-title', 'DCD PRESCRIPTION')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">DCD ID:</td><td>{{ $row['id'] }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Contact:</td><td>{{ $row['mobile_number'] }}</td>
	</tr>
	<tr>
		<td class="label">Admission Date:</td><td>{{ $row['dcd_date'] }}</td>
		<td class="label">Discharge Date:</td><td>{{ $row['discharge_date'] ?? 'Not Discharged' }}</td>
	</tr>
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $row['doctor_name'] ?? 'N/A' }}</td></tr>
</table></div>

@if($isDischarged && !empty($row['diagnosis']))
<div class="rpt-note">
	<span class="rpt-note-title">Diagnosis:</span><br>
	<span class="rpt-note-body">{!! nl2br(e($row['diagnosis'])) !!}</span>
</div>
@endif

<div class="rpt-section">1. Doctor Prescriptions (During Admission)</div>
@forelse($prescriptions as $rx)
	<p class="rpt-meta"><b>Prescription Date:</b> {{ $rx['row']['prescription_datetime'] }}</p>
	@if($rx['medicines'])
	<table class="rpt-table">
		<tr>
			<th style="width:33%;">Medicine</th><th style="width:12%;">Dose</th><th style="width:15%;">Frequency</th>
			<th style="width:18%;">Timing</th><th style="width:11%;">Duration</th><th style="width:11%;">Meal</th>
		</tr>
		@foreach($rx['medicines'] as $m)
		@php $timing = trim((string) ($m['timing_names'] ?? '')); @endphp
		<tr>
			<td>{{ $m['medicine_name'] }}</td>
			<td>{{ $m['dose_quantity'] ?? '1' }}</td>
			<td>{{ $m['frequency_display'] ?? '-' }}</td>
			<td>{{ $timing === '' ? '-' : $timing }}</td>
			<td>{{ ($m['duration_days'] ?? '') === '' ? '-' : $m['duration_days'] . ' days' }}</td>
			<td>{{ $m['meal_relation'] ?? '-' }}</td>
		</tr>
		@endforeach
	</table>
	@else
	<div class="rpt-empty">No medicines prescribed</div>
	@endif
	@if(!empty($rx['row']['findings']))
	<div class="rpt-note">
		<span class="rpt-note-title">Findings:</span><br>
		<span class="rpt-note-body">{!! nl2br(e($rx['row']['findings'])) !!}</span>
	</div>
	@endif
@empty
	<div class="rpt-empty">No prescriptions found</div>
@endforelse

@if($isDischarged)
<div class="rpt-section">2. Medications Advised on Discharge (To Continue)</div>
@if($dischargeMeds)
<table class="rpt-table">
	<tr>
		<th style="width:30%;">Medicine</th><th style="width:11%;">Dose</th><th style="width:13%;">Frequency</th>
		<th style="width:17%;">Timing</th><th style="width:12%;">Duration</th><th style="width:17%;">Meal / Reason</th>
	</tr>
	@foreach($dischargeMeds as $m)
	@php $timing = trim((string) ($m['timing_names'] ?? '')); @endphp
	<tr>
		<td>{{ $m['medicine_name'] }}</td>
		<td>{{ $m['dose_quantity'] ?? '1' }}</td>
		<td>{{ $m['frequency_display'] ?? '-' }}</td>
		<td>{{ $timing === '' ? '-' : $timing }}</td>
		<td>{{ ($m['duration_days'] ?? '') === '' ? '-' : $m['duration_days'] . ' days' }}</td>
		<td>{{ $m['meal_relation'] ?? '-' }}@if(!empty($m['reason']))<br><span style="color:#666; font-size:10px;">({{ $m['reason'] }})</span>@endif</td>
	</tr>
	@endforeach
</table>
@else
<div class="rpt-empty">No discharge medications advised</div>
@endif
@endif
@endsection

@section('signature')
	<p><strong>{{ $row['doctor_name'] ?? 'N/A' }}</strong></p>
	@if(!empty($row['qualification']))<p>{{ $row['qualification'] }}</p>@endif
@endsection
