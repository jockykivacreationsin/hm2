{{-- IPD discharge summary. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: ipd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'IPD Discharge Summary - ' . ($ipd->patient?->patient_name ?? ''))
@section('report-title', 'IPD DISCHARGE SUMMARY')

@section('content')
@php
	$patient = $ipd->patient;
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
		<td class="label">Blood Group:</td><td>{{ $patient?->blood_group }}</td>
	</tr>
	<tr>
		<td class="label">Contact:</td><td colspan="3">{{ $patient?->mobile_number }}</td>
	</tr>
	@if(!empty($patient?->address))
	<tr><td class="label">Address:</td><td colspan="3">{{ $patient->address }}</td></tr>
	@endif
</table></div>

<div class="rpt-section">Admission Details</div>
<div class="rpt-info"><table>
	<tr>
		<td class="label">IPD No:</td><td>{{ $ipd->ipd_ref }}</td>
		<td class="label">Consultant Doctor:</td><td>{{ $ipd->doctor?->staff_name }}</td>
	</tr>
	<tr>
		<td class="label">Admitted:</td><td>{{ $dt($ipd->admission_date_time) }}</td>
		<td class="label">Bed:</td><td>{{ $ipd->bed?->bed_name }} ({{ $ipd->bed?->wardCategory?->ward_category }})</td>
	</tr>
	@if($ipd->discharge)
	<tr>
		<td class="label">Discharged:</td><td>{{ $dt($ipd->discharge->discharge_date_time) }}</td>
		<td class="label">Stay:</td>
		{{-- Calendar days, admission day counted as day 1 (Carbon 3 diffInDays returns fractions). --}}
		<td>{{ \Carbon\Carbon::parse($ipd->admission_date_time)->startOfDay()->diffInDays(\Carbon\Carbon::parse($ipd->discharge->discharge_date_time)->startOfDay()) + 1 }} day(s)</td>
	</tr>
	@endif
	@if($ipd->tpaProvider)
	<tr>
		<td class="label">TPA:</td><td>{{ $ipd->tpaProvider->tpa_name }}</td>
		<td class="label">TPA No:</td><td>{{ $ipd->tpa_number }}</td>
	</tr>
	@endif
</table></div>

@if($ipd->discharge)
<div class="rpt-section">Discharge Details</div>
<div class="rpt-info"><table>
	<tr><td class="label">Diagnosis:</td><td>{{ $ipd->discharge->diagnosis }}</td></tr>
	<tr><td class="label">Condition:</td><td>{{ $ipd->discharge->condition_on_discharge }}</td></tr>
	@if($ipd->discharge->follow_up_detail)
	<tr><td class="label">Follow-up:</td><td>{!! \App\Support\SafeHtml::basic($ipd->discharge->follow_up_detail) !!}</td></tr>
	@endif
	@if($ipd->discharge->note)
	<tr><td class="label">Notes:</td><td>{!! \App\Support\SafeHtml::basic($ipd->discharge->note) !!}</td></tr>
	@endif
	@if($ipd->discharge->urgent_care_condition)
	<tr><td class="label">Urgent Care:</td><td>{{ $ipd->discharge->urgent_care_condition }}</td></tr>
	@endif
</table></div>
@endif

@if($ipd->prescriptions->isNotEmpty())
<div class="rpt-section">Prescriptions / Medications</div>
@foreach($ipd->prescriptions as $rx)
	<p class="rpt-line"><b>{{ $dt($rx->prescription_date_time) }}</b>@if($rx->doctor?->staff_name) &mdash; {{ $rx->doctor->staff_name }}@endif</p>
	@if($rx->findings)
	<p class="rpt-line"><b>Findings:</b> {!! \App\Support\SafeHtml::basic($rx->findings, false) !!}</p>
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
@endforeach
@endif

@if($ipd->services->isNotEmpty())
<div class="rpt-section">Charges Summary</div>
@php $total = 0; $paid = 0; @endphp
<table class="rpt-table">
	<tr>
		<th style="width:14%;">Date</th><th>Description</th><th style="width:22%;">Department</th>
		<th class="num" style="width:14%;">Amount (&#8377;)</th><th class="num" style="width:14%;">Paid (&#8377;)</th>
	</tr>
	@foreach($ipd->services as $svc)
	@php
		$total += $svc->billingCharge?->total ?? 0;
		$paid  += $svc->billingCharge?->paid_amount ?? 0;
	@endphp
	<tr>
		<td>{{ $svc->service_date_time ? date('d-m-Y', strtotime($svc->service_date_time)) : '' }}</td>
		<td>{{ $svc->service_title }}</td>
		<td>{{ $svc->chargeDepartment?->department_name }}</td>
		<td class="num">{{ number_format($svc->billingCharge?->total ?? 0, 2) }}</td>
		<td class="num">{{ number_format($svc->billingCharge?->paid_amount ?? 0, 2) }}</td>
	</tr>
	@endforeach
	<tr class="rpt-group">
		<td colspan="3" class="num">TOTAL</td>
		<td class="num">{{ number_format($total, 2) }}</td>
		<td class="num">{{ number_format($paid, 2) }}</td>
	</tr>
</table>
@endif
@endsection
