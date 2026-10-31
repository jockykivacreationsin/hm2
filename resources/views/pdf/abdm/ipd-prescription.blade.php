{{-- ABDM FHIR attachment: IPD prescription. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (admission + patient + doctor, array), prescriptions / treatments (list of ['row', 'medicines']),
     isDischarged, given, advised (lists of row objects), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'IPD Prescription - ' . $row['patient_name'])
@section('report-title', 'IPD PRESCRIPTION')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">IPD No:</td><td>{{ (int) $row['id'] }}/bmh/{{ $row['iyear'] }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Contact:</td><td>{{ $row['patient_mobile'] }}</td>
	</tr>
	<tr>
		<td class="label">Admission Date:</td><td>{{ $row['admission_date'] }}</td>
		<td class="label">Discharge Date:</td><td>{{ $row['discharge_date'] ?? 'Not Discharged' }}</td>
	</tr>
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $row['doctor_name'] ?? 'N/A' }}</td></tr>
</table></div>

<div class="rpt-section">1. Doctor Prescriptions (During Admission)</div>
@forelse($prescriptions as $rx)
	<p class="rpt-meta"><b>Prescribed on:</b> {{ $rx['row']['prescription_datetime'] }}</p>
	@if($rx['medicines'])
		@include('pdf.partials.ipd-rx-table', ['rows' => $rx['medicines'], 'timingKey' => 'timing_names'])
	@endif
	@if(!empty($rx['row']['findings']))
	<div class="rpt-note">
		<span class="rpt-note-title">Findings:</span><br>
		<span class="rpt-note-body">{!! nl2br(e($rx['row']['findings'])) !!}</span>
	</div>
	@endif
@empty
	<div class="rpt-empty">No prescription records</div>
@endforelse

<div class="rpt-section">2. Treatment Chart (Nurse Station)</div>
@forelse($treatments as $tr)
	<p class="rpt-meta"><b>Treatment on:</b> {{ $tr['row']['treatment_datetime'] }}</p>
	@if($tr['medicines'])
		@include('pdf.partials.ipd-rx-table', ['rows' => $tr['medicines'], 'timingKey' => 'timing_names'])
	@endif
@empty
	<div class="rpt-empty">No treatment chart records</div>
@endforelse

@if($isDischarged)
	<div class="rpt-section">3. Medications Given at Discharge</div>
	@if($given)
		@include('pdf.partials.ipd-rx-table', ['rows' => $given, 'timingKey' => 'timing_display'])
	@else
		<div class="rpt-empty">No medications given at discharge</div>
	@endif

	<div class="rpt-section">4. Medications Advised on Discharge (To Continue)</div>
	@if($advised)
		@include('pdf.partials.ipd-rx-table', ['rows' => $advised, 'timingKey' => 'timing_names'])
	@else
		<div class="rpt-empty">No medications advised on discharge</div>
	@endif
@endif
@endsection

@section('signature')
	<p><strong>{{ $row['doctor_name'] ?? 'N/A' }}</strong></p>
	@if(!empty($row['qualification']))<p>{{ $row['qualification'] }}</p>@endif
@endsection
