{{-- ABDM FHIR attachment: DCD discharge summary. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (visit + patient + discharge, array), stay, printed, procedures (list of title/when/status), investigations
     (lab_count + rows of investigation/result), medications (list), notes (diagnosis, condition, follow_up, urgent_care,
     additional), signDesignation, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Discharge Summary - ' . $row['patient_name'])
@section('report-title', 'DAY CARE DISCHARGE SUMMARY')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($row['patient_id']) }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Blood Group:</td><td>{{ $row['blood_group'] ?? 'N/A' }}</td>
	</tr>
	<tr>
		<td class="label">Mobile:</td><td>{{ $row['patient_mobile'] }}</td>
		<td class="label">DCD ID:</td><td>{{ (int) $row['id'] }}</td>
	</tr>
	<tr><td class="label">Address:</td><td colspan="3">{{ $row['patient_address'] }}</td></tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $row['doctor_name'] ?? 'N/A' }}</td>
		<td class="label">Printed:</td><td>{{ $printed }}</td>
	</tr>
	<tr>
		<td class="label">Admission Date:</td><td>{{ $row['admission_display'] }}</td>
		<td class="label">Discharge Date:</td><td>{{ $row['discharge_display'] ?? 'N/A' }}</td>
	</tr>
	<tr><td class="label">Length of Stay:</td><td colspan="3">{{ $stay }}</td></tr>
</table></div>

@if($procedures)
<div class="rpt-section">Procedures Performed</div>
<table class="rpt-table">
	<tr><th>Procedure</th><th style="width:25%;">Date/Time</th><th style="width:15%;">Status</th></tr>
	@foreach($procedures as $proc)
	<tr><td>{{ $proc['title'] }}</td><td>{{ $proc['when'] }}</td><td>{{ $proc['status'] }}</td></tr>
	@endforeach
</table>
@endif

@if($investigations['lab_count'] > 0 || $investigations['rows'])
<div class="rpt-section">Laboratory Investigations</div>
@if($investigations['lab_count'] > 0)
<p class="rpt-line"><b>Total Tests Conducted:</b> {{ $investigations['lab_count'] }} tests</p>
<p class="rpt-meta"><i>Detailed lab reports are attached as separate DiagnosticReport bundle.</i></p>
@endif
@if($investigations['rows'])
<table class="rpt-table">
	<tr><th style="width:35%;">Investigation</th><th>Result</th></tr>
	@foreach($investigations['rows'] as $inv)
	<tr><td>{{ $inv['investigation'] }}</td><td>{!! nl2br(e($inv['result'])) !!}</td></tr>
	@endforeach
</table>
@endif
@endif

@if($medications)
<div class="rpt-section">Medications Summary</div>
<table class="rpt-table">
	<tr><th>Medicine</th><th style="width:15%;">Dose</th><th style="width:20%;">Frequency</th><th style="width:15%;">Duration</th></tr>
	@foreach($medications as $med)
	<tr>
		<td>{{ $med['medicine_name'] ?? 'N/A' }}</td>
		<td>{{ $med['dose_quantity'] ?? 'N/A' }}</td>
		<td>{{ $med['frequency_display'] ?? 'N/A' }}</td>
		<td>{{ $med['duration_days'] ?? 'N/A' }} days</td>
	</tr>
	@endforeach
</table>
<p class="rpt-meta"><i>Detailed prescription is attached as separate Prescription bundle.</i></p>
@endif

@if($notes['diagnosis'] !== '')
<div class="rpt-section">Final Discharge Diagnosis</div>
<p class="rpt-line">{!! nl2br(e($notes['diagnosis'])) !!}</p>
@endif

@if($notes['condition'] !== '')
<div class="rpt-section">Condition on Discharge</div>
<p class="rpt-line">{!! nl2br(e($notes['condition'])) !!}</p>
@endif

@if($notes['follow_up'] !== '' || $notes['urgent_care'] !== '' || $notes['additional'] !== '')
<div class="rpt-section">Follow-Up Instructions</div>
@if($notes['follow_up'] !== '')
<div class="rpt-note">
	<span class="rpt-note-title">Follow-Up:</span><br>
	<span class="rpt-note-body">{!! nl2br(e($notes['follow_up'])) !!}</span>
</div>
@endif
@if($notes['urgent_care'] !== '')
<div class="rpt-note">
	<span class="rpt-note-title">&#9888; Urgent Care Instructions:</span><br>
	<span class="rpt-note-body">{!! nl2br(e($notes['urgent_care'])) !!}</span>
</div>
@endif
@if($notes['additional'] !== '')
<div class="rpt-note">
	<span class="rpt-note-title">Additional Notes:</span><br>
	<span class="rpt-note-body">{!! nl2br(e($notes['additional'])) !!}</span>
</div>
@endif
@endif
@endsection

@section('signature')
	<p><strong>Authorized Signatory</strong></p>
	<p><strong>{{ $row['doctor_name'] ?? 'N/A' }}</strong></p>
	@if($signDesignation !== '')<p>{{ $signDesignation }}</p>@endif
	<p>Date: {{ date('d-M-Y') }}</p>
@endsection
