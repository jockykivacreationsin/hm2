{{-- ABDM FHIR attachment: IPD discharge summary. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (admission + patient + discharge, array), patientFile (array|null), vitals, labCount, procedures, medications,
     discharge (diagnosis/condition/follow_up/urgent_care/note strings), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'IPD Discharge Summary - ' . $row['patient_name'])
@section('report-title', 'DISCHARGE SUMMARY')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">IPD No:</td><td>{{ (int) $row['id'] }}/bmh/{{ $row['iyear'] }}</td>
	</tr>
	<tr>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($row['patient_id']) }}</td>
		<td class="label">Printed:</td><td>{{ date('d-M-Y h:i A') }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Blood Group:</td><td>{{ $row['blood_group'] ?? 'N/A' }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $row['doctor_name'] ?? 'N/A' }}</td>
		<td class="label">Contact:</td><td>{{ $row['patient_mobile'] }}</td>
	</tr>
	<tr><td class="label">Address:</td><td colspan="3">{{ $row['patient_address'] }}</td></tr>
	<tr>
		<td class="label">Admission Date:</td><td>{{ $row['admission_display'] }}</td>
		<td class="label">Discharge Date:</td><td>{{ $row['discharge_display'] ?? 'N/A' }}</td>
	</tr>
	<tr>
		<td class="label">Length of Stay:</td><td colspan="3">{{ (int) ($row['stay_days'] ?? 0) }} day(s)</td>
	</tr>
	@if($row['patient_category_id'] != 1)
	<tr>
		<td class="label">ESM Name:</td><td>{{ $row['esm_name'] }}</td>
		<td class="label">Card Number:</td><td>{{ $row['card_number'] }}</td>
	</tr>
	@endif
</table></div>

@if($patientFile)
	@foreach($patientFile['history'] as [$title, $text])
	<div class="rpt-section">{{ $title }}</div>
	<p class="rpt-line">{!! nl2br(e($text)) !!}</p>
	@endforeach

	@if($patientFile['allergies'] !== '')
	<div class="rpt-note">
		<span class="rpt-note-title">Allergies:</span><br>
		<span class="rpt-note-body">{!! nl2br(e($patientFile['allergies'])) !!}</span>
	</div>
	@endif

	@foreach($patientFile['examinations'] as [$title, $text])
	<div class="rpt-section">{{ $title }}</div>
	<p class="rpt-line">{!! nl2br(e($text)) !!}</p>
	@endforeach

	@if($patientFile['cns'])
	<div class="rpt-section">CNS Examination</div>
	@foreach($patientFile['cns'] as [$label, $text])
	<p class="rpt-line"><b>{{ $label }}:</b> {{ $text }}</p>
	@endforeach
	@endif

	@if($patientFile['pain'])
	<div class="rpt-section">Pain Assessment</div>
	<div class="rpt-info"><table>
		@foreach($patientFile['pain'] as [$label, $value])
		<tr><td class="label">{{ $label }}:</td><td>{{ $value }}</td></tr>
		@endforeach
	</table></div>
	@endif
@endif

@if($vitals)
<div class="rpt-section">Vital Signs (At Discharge)</div>
<table class="rpt-table">
	<tr><th style="width:35%;">Parameter</th><th>Reading</th></tr>
	@foreach($vitals as [$label, $value])
	<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
	@endforeach
</table>
@endif

@if($labCount > 0)
<div class="rpt-section">Laboratory Investigations</div>
<p class="rpt-line"><b>Total Tests Conducted:</b> {{ $labCount }} tests</p>
<p class="rpt-meta"><i>Detailed lab reports are attached as separate DiagnosticReport bundle.</i></p>
@endif

@if($procedures)
<div class="rpt-section">Procedures Performed</div>
<table class="rpt-table">
	<tr><th>Procedure</th><th style="width:25%;">Date/Time</th><th style="width:15%;">Status</th></tr>
	@foreach($procedures as $proc)
	<tr><td>{{ $proc['name'] }}</td><td>{{ $proc['date'] }}</td><td>{{ $proc['status'] }}</td></tr>
	@endforeach
</table>
@endif

@if($medications)
<div class="rpt-section">Discharge Medications</div>
<table class="rpt-table">
	<tr><th>Medicine</th><th style="width:15%;">Dose</th><th style="width:20%;">Frequency</th><th style="width:15%;">Duration</th></tr>
	@foreach($medications as $med)
	@php $duration = $med['duration_days'] ?? null; @endphp
	<tr>
		<td>{{ $med['medicine_name'] ?? 'N/A' }}</td>
		<td>{{ $med['dose_quantity'] ?? 'N/A' }}</td>
		<td>{{ $med['frequency_display'] ?? 'N/A' }}</td>
		<td>{{ $duration === null || $duration === '' ? 'N/A' : $duration . ' days' }}</td>
	</tr>
	@endforeach
</table>
@endif

@if($discharge['diagnosis'] !== '')
<div class="rpt-section">Final Discharge Diagnosis</div>
<p class="rpt-line">{!! nl2br(e($discharge['diagnosis'])) !!}</p>
@endif

@if($discharge['condition'] !== '')
<div class="rpt-section">Condition on Discharge</div>
<p class="rpt-line">{!! nl2br(e($discharge['condition'])) !!}</p>
@endif

@if($discharge['follow_up'] !== '' || $discharge['urgent_care'] !== '' || $discharge['note'] !== '')
<div class="rpt-section">Follow-Up Instructions</div>
@if($discharge['follow_up'] !== '')
<p class="rpt-line"><b>Follow-Up:</b><br>{!! nl2br(e($discharge['follow_up'])) !!}</p>
@endif
@if($discharge['urgent_care'] !== '')
<div class="rpt-note">
	<span class="rpt-note-title">Urgent Care Instructions:</span><br>
	<span class="rpt-note-body">{!! nl2br(e($discharge['urgent_care'])) !!}</span>
</div>
@endif
@if($discharge['note'] !== '')
<p class="rpt-line"><b>Additional Notes:</b><br>{!! nl2br(e($discharge['note'])) !!}</p>
@endif
@endif
@endsection

@section('signature')
	<p><strong>Authorized Signatory</strong></p>
	<p><strong>{{ $row['doctor_name'] ?? 'N/A' }}</strong></p>
	@if(!empty($sign_designation))<p>{{ $sign_designation }}</p>@endif
	<p>Date: {{ date('d-M-Y') }}</p>
@endsection
