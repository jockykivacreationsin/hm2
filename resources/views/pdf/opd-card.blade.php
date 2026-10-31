{{-- OPD card (visit details, vitals, prescriptions). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Card - ' . ($opd->patient?->patient_name ?? ''))
@section('report-title', 'OPD CARD')

@section('content')
@php $patient = $opd->patient; @endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">OPD ID:</td><td>{{ $opd->id }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Visit Date:</td><td>{{ $opd->opd_date_time ? date('d-M-Y h:i A', strtotime($opd->opd_date_time)) : '' }}</td>
	</tr>
	<tr>
		<td class="label">Consultant Doctor:</td><td>{{ $opd->doctor?->staff_name }}</td>
		<td class="label">Token:</td><td>{{ $opd->token_number }}</td>
	</tr>
	@if(!empty($patient?->address))
	<tr><td class="label">Address:</td><td colspan="3">{{ $patient->address }}</td></tr>
	@endif
	@if(!empty($patient?->mobile_number))
	<tr><td class="label">Contact:</td><td colspan="3">{{ $patient->mobile_number }}</td></tr>
	@endif
</table></div>

@if($opd->bp || $opd->pulse || $opd->spo2 || $opd->temperature)
<p class="rpt-line">
	@if($opd->bp)<b>BP:</b> {{ $opd->bp }} &nbsp;&nbsp;@endif
	@if($opd->pulse)<b>Pulse:</b> {{ $opd->pulse }} &nbsp;&nbsp;@endif
	@if($opd->spo2)<b>SpO2:</b> {{ $opd->spo2 }} &nbsp;&nbsp;@endif
	@if($opd->temperature)<b>Temp:</b> {{ $opd->temperature }}@endif
</p>
@endif

@if($opd->symptoms)
<p class="rpt-line"><b>Symptoms:</b> {!! \App\Support\SafeHtml::basic($opd->symptoms) !!}</p>
@endif
@if($opd->remarks)
<p class="rpt-line"><b>Findings:</b> {!! \App\Support\SafeHtml::basic($opd->remarks) !!}</p>
@endif

@foreach($opd->prescriptions as $rx)
@if($rx->medicines->isNotEmpty())
	<div class="rpt-section">Rx - {{ $rx->prescription_date_time ? date('d-m-Y h:i A', strtotime($rx->prescription_date_time)) : '' }}</div>
	<table class="rpt-table">
		<tr><th style="width:6%;">#</th><th>Medicine</th><th style="width:20%;">Dose</th><th style="width:18%;">Duration</th></tr>
		@foreach($rx->medicines as $i => $med)
		<tr>
			<td>{{ $i + 1 }}</td>
			<td>{{ $med->product?->product_name ?? '-' }}</td>
			<td>{{ $med->dose_quantity ?? '-' }}</td>
			<td>{{ $med->duration_days ? $med->duration_days . ' days' : '-' }}</td>
		</tr>
		@endforeach
	</table>
@endif
@endforeach
@endsection
