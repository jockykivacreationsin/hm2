{{-- DCD detailed discharge bill (itemised per charge). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: dcd, charges, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Detailed Discharge Bill - ' . ($dcd->patient?->patient_name ?? ''))
@section('report-title', 'DCD DETAILED DISCHARGE BILL')

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
		<td class="label">Visit Date:</td><td>{{ $dt($dcd->dcd_date_time) }}</td>
		<td class="label">Discharged:</td><td>{{ $dt($dcd->discharge?->discharge_date_time) ?: '-' }}</td>
	</tr>
</table></div>

@forelse($charges as $c)
	@php
		$grandTotal += $c->total; $grandPaid += $c->paid_amount;
		$cCancelled = ($c->invoice_status ?? 'issued') === 'cancelled';
		$cHasTax    = $c->details->contains(fn($d) => ($d->tax_amount ?? 0) > 0);
	@endphp
	<div class="rpt-section">
		{{ $c->charge_title }} &mdash; {{ $dt($c->charge_date_time) }}
		@if($cCancelled) &nbsp;<span style="color:#c00;font-weight:bold;">[CANCELLED]</span>@endif
	</div>
	<table class="rpt-table">
		@if($cHasTax)
		<tr><th style="width:6%;">#</th><th>Charge</th><th class="num" style="width:14%;">Amount (&#8377;)</th><th class="num" style="width:10%;">Tax %</th><th class="num" style="width:12%;">Tax (&#8377;)</th><th class="num" style="width:14%;">Total (&#8377;)</th></tr>
		@else
		<tr><th style="width:6%;">#</th><th>Charge</th><th class="num" style="width:20%;">Amount (&#8377;)</th></tr>
		@endif
		@forelse($c->details as $d)
		<tr>
			<td>{{ $loop->iteration }}</td>
			<td>{{ $d->charge?->charge_name ?? 'Item' }}</td>
			<td class="num">{{ number_format($d->total - ($d->tax_amount ?? 0), 2) }}</td>
			@if($cHasTax)
			<td class="num">{{ ($d->tax_percentage ?? 0) > 0 ? number_format($d->tax_percentage, 2) . '%' : '-' }}</td>
			<td class="num">{{ ($d->tax_amount ?? 0) > 0 ? number_format($d->tax_amount, 2) : '-' }}</td>
			<td class="num">{{ number_format($d->total, 2) }}</td>
			@endif
		</tr>
		@empty
		<tr><td>1</td><td>{{ $c->charge_title }}</td><td class="num">{{ number_format($c->amount, 2) }}</td></tr>
		@endforelse
		<tr class="rpt-group"><td colspan="{{ $cHasTax ? 5 : 2 }}" class="num">Subtotal</td><td class="num">{{ number_format($c->total, 2) }}</td></tr>
		@if($c->paid_amount > 0)
		<tr><td colspan="{{ $cHasTax ? 5 : 2 }}" class="num">Paid</td><td class="num">{{ number_format($c->paid_amount, 2) }}</td></tr>
		<tr><td colspan="{{ $cHasTax ? 5 : 2 }}" class="num">Balance</td><td class="num">{{ number_format($c->total - $c->paid_amount, 2) }}</td></tr>
		@endif
	</table>
@empty
	<div class="rpt-empty">No charges found</div>
@endforelse

<table class="rpt-table" style="margin-top:15px;">
	<tr class="rpt-group"><td>Grand Total</td><td class="num" style="width:20%;">&#8377;{{ number_format($grandTotal, 2) }}</td></tr>
	<tr><td>Total Paid</td><td class="num">&#8377;{{ number_format($grandPaid, 2) }}</td></tr>
	<tr class="rpt-group"><td>Balance Due</td><td class="num">&#8377;{{ number_format($grandTotal - $grandPaid, 2) }}</td></tr>
</table>
@endsection

@section('signature')
	@include('pdf.partials.bill-signature')
@endsection
