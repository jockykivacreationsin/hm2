{{-- IPD patient file. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: ipd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'IPD Patient File - ' . ($ipd->patient?->patient_name ?? ''))
@section('report-title', 'IPD PATIENT FILE')

@section('content')
@php
	$f = $ipd->patientFile;
	$patient = $ipd->patient;
	$rich = fn ($v) => \App\Support\SafeHtml::basic($v ?? '');
@endphp

<div class="rpt-section">Patient Information</div>
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Contact:</td><td>{{ $patient?->mobile_number }}</td>
	</tr>
	<tr><td class="label">Address:</td><td colspan="3">{{ $patient?->address }}</td></tr>
	<tr>
		<td class="label">IPD No:</td><td>{{ $ipd->ipd_ref }}</td>
		<td class="label">Admission:</td><td>{{ $ipd->admission_date_time ? date('d-M-Y h:i A', strtotime($ipd->admission_date_time)) : '' }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $ipd->doctor?->staff_name }}</td>
		<td class="label">Ward/Bed:</td><td>{{ $ipd->bed?->wardCategory?->ward_category }} / {{ $ipd->bed?->bed_name }}</td>
	</tr>
</table></div>

@if($f)
<div class="rpt-section">Admission Details</div>
<div class="rpt-info"><table>
	<tr>
		<td class="label">Relative Name:</td><td>{{ $f->relative_name }}</td>
		<td class="label">Relationship:</td><td>{{ $f->relationship }}</td>
	</tr>
	<tr>
		<td class="label">Witness:</td><td>{{ $f->witness_name }}</td>
		<td class="label">Admission Type:</td><td>{{ $f->admission_type }}</td>
	</tr>
	<tr>
		<td class="label">Patient Condition:</td><td>{{ $f->patient_condition }}</td>
		<td class="label">General Condition:</td><td>{{ $f->general_condition }}</td>
	</tr>
</table></div>

<div class="rpt-section">Clinical History</div>
<div class="rpt-info"><table>
	<tr><td class="label">Chief Complaints:</td><td colspan="3">{!! $rich($f->chief_complaints) !!}</td></tr>
	<tr><td class="label">Present Illness:</td><td colspan="3">{!! $rich($f->history_of_present_illness) !!}</td></tr>
	<tr><td class="label">Past History:</td><td colspan="3">{!! $rich($f->past_medical_history) !!}</td></tr>
	<tr>
		<td class="label">Allergies:</td><td>{{ $f->allergies }}</td>
		<td class="label">Blood Transfusion:</td><td>{{ $f->ho_blood_transfusion }}</td>
	</tr>
	<tr><td class="label">Current Medication:</td><td colspan="3">{{ $f->current_medication }}</td></tr>
	<tr><td class="label">Family History:</td><td colspan="3">{!! $rich($f->family_ho) !!}</td></tr>
</table></div>

<div class="rpt-section">Examination</div>
<div class="rpt-info"><table>
	<tr>
		<td class="label">Pain Score:</td><td>{{ $f->pain_score }}</td>
		<td class="label">Pain Location:</td><td>{{ $f->pain_location }}</td>
	</tr>
	<tr><td class="label">General Examination:</td><td colspan="3">{{ $f->general_examination }}</td></tr>
	<tr><td class="label">Systemic Exam:</td><td colspan="3">{!! $rich($f->systemic_examination) !!}</td></tr>
	<tr>
		<td class="label">CNS Mental Status:</td><td>{{ $f->cns_system_mental_status }}</td>
		<td class="label">CNS Sensory:</td><td>{{ $f->cns_system_sensory_examination }}</td>
	</tr>
	<tr><td class="label">Provisional Diagnosis:</td><td colspan="3"><strong>{{ $f->provisional_diagnosis }}</strong></td></tr>
	<tr><td class="label">Diet Plan:</td><td colspan="3">{{ $f->diet_plan }}</td></tr>
</table></div>
@endif

@if($ipd->opd)
<div class="rpt-section">Vitals (from OPD)</div>
<table class="rpt-table">
	<tr><th>Height</th><th>Weight</th><th>BP</th><th>Pulse</th><th>Temp</th><th>Resp</th><th>SpO2</th></tr>
	<tr>
		<td>{{ $ipd->opd->height }}</td><td>{{ $ipd->opd->weight }}</td>
		<td>{{ $ipd->opd->bp }}</td><td>{{ $ipd->opd->pulse }}</td>
		<td>{{ $ipd->opd->temperature }}</td><td>{{ $ipd->opd->respiration }}</td><td>{{ $ipd->opd->spo2 }}</td>
	</tr>
</table>
@endif
@endsection

@section('signature')
	<div style="margin: 20px 0 0 auto; width:150px; border-top:1px solid #333; padding-top:2px; text-align:center; font-size:10px;">Doctor's Signature</div>
@endsection
