{{-- DCD discharge bill. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: dcd, charges, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Discharge Bill - ' . ($dcd->patient?->patient_name ?? ''))
@section('report-title', 'DCD DISCHARGE BILL')

@section('content')
@php
	$patient = $dcd->patient;
	$dt = fn ($v) => $v ? date('d-M-Y h:i A', strtotime($v)) : '';
	$grandTotal = 0; $grandPaid = 0;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">DCD ID:</td><td>{{ $dcd->id }}</td>
		<td class="label">Consultant Doctor:</td><td>{{ $dcd->doctor?->staff_name }}</td>
	</tr>
	<tr>
		<td class="label">Admission:</td><td>{{ $dt($dcd->dcd_date_time) }}</td>
		<td class="label">Discharge:</td><td>{{ $dt($dcd->discharge?->discharge_date_time) ?: '-' }}</td>
	</tr>
</table></div>

<div class="rpt-section">Charges</div>
<table class="rpt-table">
	<tr>
		<th style="width:5%;">#</th><th style="width:13%;">Date</th><th>Description</th><th style="width:14%;">Department</th>
		<th class="num" style="width:9%;">Amount</th><th class="num" style="width:8%;">Discount</th>
		<th class="num" style="width:9%;">Total</th><th class="num" style="width:8%;">Paid</th><th class="num" style="width:8%;">Due</th><th style="width:8%;">Status</th>
	</tr>
	@forelse($charges as $c)
	@php $grandTotal += $c->total; $grandPaid += $c->paid_amount; $cCancelled = ($c->invoice_status ?? 'issued') === 'cancelled'; @endphp
	<tr>
		<td>{{ $loop->iteration }}</td>
		<td>{{ $c->charge_date_time ? date('d-m-Y', strtotime($c->charge_date_time)) : '' }}</td>
		<td>{{ $c->charge_title }}</td>
		<td>{{ $c->chargeDepartment?->department_name }}</td>
		<td class="num">{{ number_format($c->amount, 2) }}</td>
		<td class="num">{{ number_format($c->discount, 2) }}</td>
		<td class="num">{{ number_format($c->total, 2) }}</td>
		<td class="num">{{ number_format($c->paid_amount, 2) }}</td>
		<td class="num">{{ number_format($c->total - $c->paid_amount, 2) }}</td>
		<td>@if($cCancelled)<span style="color:#c00;font-size:9px;font-weight:bold;">CANCELLED</span>@else<span style="color:#396;font-size:9px;">Issued</span>@endif</td>
	</tr>
	@empty
	<tr><td colspan="10" class="center">No charges found.</td></tr>
	@endforelse
	<tr class="rpt-group">
		<td colspan="6" class="num">Grand Total</td>
		<td class="num">{{ number_format($grandTotal, 2) }}</td>
		<td class="num">{{ number_format($grandPaid, 2) }}</td>
		<td class="num" colspan="2">{{ number_format($grandTotal - $grandPaid, 2) }}</td>
	</tr>
</table>
@endsection

@section('signature')
	@include('pdf.partials.bill-signature')
@endsection
