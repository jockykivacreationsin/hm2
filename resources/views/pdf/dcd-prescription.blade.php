{{-- DCD (day care) prescription. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: dcd, prescriptions, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Prescription - ' . ($dcd->patient?->patient_name ?? ''))
@section('report-title', 'DAY CARE PRESCRIPTION')

@section('content')
@php $patient = $dcd->patient; @endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Visit Date:</td><td>{{ $dcd->dcd_date_time ? date('d-M-Y', strtotime($dcd->dcd_date_time)) : '' }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $dcd->doctor?->staff_name }}</td>
		<td class="label">DCD ID:</td><td>{{ $dcd->id }}</td>
	</tr>
	@if(!empty($patient?->mobile_number))
	<tr><td class="label">Contact:</td><td colspan="3">{{ $patient->mobile_number }}</td></tr>
	@endif
</table></div>

@forelse($prescriptions as $rx)
	<div class="rpt-rx">&#8478;</div>

	@if($rx->findings)
	<div class="rpt-note">
		<span class="rpt-note-title">Findings/Diagnosis:</span><br>
		<span class="rpt-note-body">{!! \App\Support\SafeHtml::basic($rx->findings) !!}</span>
	</div>
	@endif

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

	@if($rx->prescription_date_time)
	<p class="rpt-meta">Prescribed on: {{ date('d-m-Y h:i A', strtotime($rx->prescription_date_time)) }}@if($rx->doctor?->staff_name) by {{ $rx->doctor->staff_name }}@endif</p>
	@endif

	@if(!$loop->last)<hr class="rpt-divider">@endif
@empty
	<div class="rpt-empty">No prescriptions found for this visit</div>
@endforelse
@endsection
