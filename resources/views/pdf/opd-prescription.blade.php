{{-- OPD prescription. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd, prescriptions, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Prescription - ' . ($opd->patient?->patient_name ?? ''))
@section('report-title', 'PRESCRIPTION')

@section('content')
@php $patient = $opd->patient; @endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Visit Date:</td><td>{{ $opd->opd_date_time ? date('d-M-Y', strtotime($opd->opd_date_time)) : '' }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $opd->doctor?->staff_name }}</td>
		<td class="label">OPD ID:</td><td>{{ $opd->id }}</td>
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

	@include('pdf.partials.rx-test-lines')

	@if($rx->medicines->isNotEmpty())
	<table class="rpt-table">
		<tr>
			<th style="width:5%;">#</th>
			<th style="width:35%;">Medicine</th>
			<th style="width:15%;">Dose</th>
			<th style="width:20%;">Timing</th>
			<th style="width:12%;">Duration</th>
			<th style="width:13%;">Meal</th>
		</tr>
		@foreach($rx->medicines as $i => $med)
		@php
			$timingNames = $med->timings->map(fn($t) => $t->timing?->timing_name)->filter()->values();
			$timingText = $timingNames->isNotEmpty() ? $timingNames->implode(' + ') : ($med->remarks ?: '-');
		@endphp
		<tr>
			<td>{{ $i + 1 }}</td>
			<td>{{ $med->product?->product_name }}</td>
			<td>{{ $med->dose_quantity }} {{ $med->product?->doseUnit?->unit_name }}</td>
			<td>{{ $timingText }}</td>
			<td>{{ $med->duration_days ? $med->duration_days . ' days' : '-' }}</td>
			<td>{{ $med->mealRelation?->relation_name ?? '-' }}</td>
		</tr>
		@endforeach
	</table>
	@endif

	@if($rx->follow_up_detail)
	<p class="rpt-line"><b>Follow-up:</b> {{ $rx->follow_up_detail }}</p>
	@endif

	<p class="rpt-meta">Prescribed on: {{ $rx->prescription_date_time ? date('d-m-Y h:i A', strtotime($rx->prescription_date_time)) : '' }} by {{ $rx->doctor?->staff_name }}</p>

	@if(!$loop->last)<hr class="rpt-divider">@endif
@empty
	<div class="rpt-empty">No prescriptions found for this visit</div>
@endforelse
@endsection
