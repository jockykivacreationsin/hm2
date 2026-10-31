{{-- DCD (day care) discharge summary. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: dcd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Discharge Summary - ' . ($dcd->patient?->patient_name ?? ''))
@section('report-title', 'DAY CARE DISCHARGE SUMMARY')

@section('content')
@php
	$patient = $dcd->patient;
	$dt = fn ($v) => $v ? date('d-M-Y h:i A', strtotime($v)) : '';
@endphp

<div class="rpt-section">Patient Information</div>
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">DCD ID:</td><td>{{ $dcd->id }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $dcd->doctor?->staff_name }}</td>
		<td class="label">Visit Date:</td><td>{{ $dt($dcd->dcd_date_time) }}</td>
	</tr>
</table></div>

@if($dcd->discharge)
<div class="rpt-section">Discharge Details</div>
<div class="rpt-info"><table>
	<tr><td class="label">Discharged:</td><td>{{ $dt($dcd->discharge->discharge_date_time) }}</td></tr>
	<tr><td class="label">Diagnosis:</td><td>{{ $dcd->discharge->diagnosis }}</td></tr>
	<tr><td class="label">Condition:</td><td>{{ $dcd->discharge->condition_on_discharge }}</td></tr>
	@if($dcd->discharge->follow_up_detail)
	<tr><td class="label">Follow-up:</td><td>{!! \App\Support\SafeHtml::basic($dcd->discharge->follow_up_detail) !!}</td></tr>
	@endif
	@if($dcd->discharge->discharged_to)
	<tr><td class="label">Discharged To:</td><td>{{ $dcd->discharge->discharged_to }}</td></tr>
	@endif
</table></div>
@endif

@if($dcd->prescriptions->isNotEmpty())
<div class="rpt-section">Prescriptions</div>
@foreach($dcd->prescriptions as $rx)
	<p class="rpt-line"><b>{{ $dt($rx->prescription_date_time) }}</b>@if($rx->doctor?->staff_name) &mdash; {{ $rx->doctor->staff_name }}@endif</p>
	@if($rx->medicines->isNotEmpty())
	<table class="rpt-table">
		<tr><th style="width:6%;">#</th><th>Medicine</th><th style="width:20%;">Dose</th><th style="width:18%;">Duration</th></tr>
		@foreach($rx->medicines as $i => $med)
		<tr>
			<td>{{ $i + 1 }}</td>
			<td>{{ $med->product?->product_name }}</td>
			<td>{{ $med->dose_quantity }}</td>
			<td>{{ $med->duration_days ? $med->duration_days . ' days' : '' }}</td>
		</tr>
		@endforeach
	</table>
	@endif
@endforeach
@endif
@endsection
