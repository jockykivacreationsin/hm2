{{-- OPD visit summary (prescriptions + charges). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd, prescriptions, services, chargeNames, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Visit Summary - ' . ($opd->patient?->patient_name ?? ''))
@section('report-title', 'OPD VISIT SUMMARY')

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
</table></div>

@if($prescriptions->isNotEmpty())
	<div class="rpt-section">Prescriptions</div>
	@foreach($prescriptions as $rx)
		<p class="rpt-line"><b>{{ $rx->prescription_date_time ? date('d-m-Y h:i A', strtotime($rx->prescription_date_time)) : '' }}</b></p>
		@if($rx->findings)<p class="rpt-line"><b>Findings:</b> {!! \App\Support\SafeHtml::basic($rx->findings) !!}</p>@endif
		@include('pdf.partials.rx-test-lines')
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

@if($services->isNotEmpty())
	<div class="rpt-section">Charges</div>
	@php $total = 0; $paid = 0; @endphp
	<table class="rpt-table">
		<tr>
			<th style="width:14%;">Date</th><th style="width:22%;">Department</th><th>Description</th>
			<th class="num" style="width:14%;">Amount</th><th class="num" style="width:14%;">Paid</th>
		</tr>
		@foreach($services as $svc)
		@php
			$total += $svc->billingCharge?->total ?? 0;
			$paid  += $svc->billingCharge?->paid_amount ?? 0;
		@endphp
		<tr>
			<td>{{ $svc->service_date_time ? date('d-m-Y', strtotime($svc->service_date_time)) : '' }}</td>
			<td>{{ $svc->chargeDepartment?->department_name }}</td>
			<td>{{ $svc->service_title }}</td>
			<td class="num">{{ number_format($svc->billingCharge?->total ?? 0, 2) }}</td>
			<td class="num">{{ number_format($svc->billingCharge?->paid_amount ?? 0, 2) }}</td>
		</tr>
		@endforeach
		<tr class="rpt-group">
			<td colspan="3" class="num">Total</td>
			<td class="num">&#8377;{{ number_format($total, 2) }}</td>
			<td class="num">&#8377;{{ number_format($paid, 2) }}</td>
		</tr>
	</table>
@endif

@if($prescriptions->isEmpty() && $services->isEmpty())
	<div class="rpt-empty">No prescriptions or charges recorded for this visit</div>
@endif
@endsection
