{{-- Consolidated bill for an OPD / IPD / DCD visit. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd|ipd|dcd, charges, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Consolidated Bill')
@section('report-title', 'CONSOLIDATED BILL')

@section('content')
@php
	$patient = isset($opd) ? $opd->patient : (isset($ipd) ? $ipd->patient : (isset($dcd) ? $dcd->patient : null));
	$grandTotal = 0; $grandPaid = 0;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Date:</td><td>{{ now()->format('d-M-Y') }}</td>
	</tr>
</table></div>

<table class="rpt-table">
	<tr>
		<th style="width:14%;">Date</th><th style="width:22%;">Department</th><th>Description</th>
		<th class="num" style="width:15%;">Amount (&#8377;)</th><th class="num" style="width:15%;">Paid (&#8377;)</th>
	</tr>
	@forelse($charges as $c)
	@php $grandTotal += $c->total; $grandPaid += $c->paid_amount; @endphp
	<tr>
		<td>{{ $c->charge_date_time ? date('d-m-Y', strtotime($c->charge_date_time)) : '' }}</td>
		<td>{{ $c->chargeDepartment?->department_name }}</td>
		<td>{{ $c->charge_title }}</td>
		<td class="num">{{ number_format($c->total, 2) }}</td>
		<td class="num">{{ number_format($c->paid_amount, 2) }}</td>
	</tr>
	@empty
	<tr><td colspan="5" class="center">No charges found.</td></tr>
	@endforelse
	<tr class="rpt-group">
		<td colspan="3" class="num">GRAND TOTAL</td>
		<td class="num">{{ number_format($grandTotal, 2) }}</td>
		<td class="num">{{ number_format($grandPaid, 2) }}</td>
	</tr>
	@if($grandTotal - $grandPaid > 0)
	<tr>
		<td colspan="3" class="num">Due Amount</td>
		<td colspan="2" class="num">{{ number_format($grandTotal - $grandPaid, 2) }}</td>
	</tr>
	@endif
</table>
@endsection
