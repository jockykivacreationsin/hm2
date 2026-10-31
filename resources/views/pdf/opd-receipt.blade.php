{{-- OPD registration-fee receipt (legacy rpt-opd-receipt.php), half page. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Receipt - ' . ($opd->patient?->patient_name ?? ''))
@section('report-title', 'RECEIPT')

@section('content')
@php
	$patient = $opd->patient;
	$visitAt = $opd->opd_date_time ? strtotime($opd->opd_date_time) : null;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">Receipt No:</td><td>{{ $opd->id }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ (int) $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Date:</td><td>{{ $visitAt ? date('d-M-Y h:i A', $visitAt) : '' }}</td>
	</tr>
	<tr>
		<td class="label">OPD No:</td><td>{{ $opd->id }}/bmh/{{ $visitAt ? date('Y', $visitAt) : '' }}</td>
		<td class="label">Contact:</td><td>{{ $patient?->mobile_number }}</td>
	</tr>
	@if(!empty($patient?->address))
	<tr><td class="label">Address:</td><td colspan="3">{{ $patient->address }}</td></tr>
	@endif
</table></div>

<table class="rpt-table">
	<tr><th>Description</th><th class="num" style="width:30%;">Amount (&#8377;)</th></tr>
	<tr><td>OPD Charges</td><td class="num">{{ number_format((float) $opd->opd_charges, 2) }}</td></tr>
	<tr class="rpt-group"><td class="num">Total</td><td class="num">{{ number_format((float) $opd->opd_charges, 2) }}</td></tr>
</table>

@if(!empty($opd->payment_mode))
<p class="rpt-line"><b>Payment Mode:</b> {{ $opd->payment_mode }}</p>
@endif
@endsection
