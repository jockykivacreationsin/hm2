{{-- Single OPD bill. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: charge, opd, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Bill - ' . ($charge->invoice_number ?? ''))
@section('report-title', 'BILL / RECEIPT')

@section('content')
@php
    $patient = $opd->patient ?? null;
    $isCancelled = ($charge->invoice_status ?? 'issued') === 'cancelled';
    $hasTax = $charge->details->contains(fn($d) => ($d->tax_amount ?? 0) > 0);
@endphp

@if($isCancelled)
<div style="text-align:center;border:2px solid #c00;color:#c00;font-size:16px;font-weight:bold;padding:6px 0;margin-bottom:10px;letter-spacing:2px;">CANCELLED</div>
@endif

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Bill No:</td><td>{{ $charge->invoice_number }}</td>
	</tr>
	<tr>
		<td class="label">Contact:</td><td>{{ $patient?->mobile_number }}</td>
		<td class="label">Date:</td><td>{{ $charge->charge_date_time ? date('d-M-Y h:i A', strtotime($charge->charge_date_time)) : '' }}</td>
	</tr>
</table></div>

<div class="rpt-section">{{ $charge->charge_title }}</div>

<table class="rpt-table">
	@if($hasTax)
	<tr><th style="width:6%;">#</th><th>Description</th><th class="num" style="width:14%;">Amount (&#8377;)</th><th class="num" style="width:10%;">Tax %</th><th class="num" style="width:12%;">Tax (&#8377;)</th><th class="num" style="width:14%;">Total (&#8377;)</th></tr>
	@else
	<tr><th style="width:6%;">#</th><th>Description</th><th class="num" style="width:20%;">Amount (&#8377;)</th></tr>
	@endif
	@forelse($charge->details as $i => $detail)
	<tr>
		<td>{{ $i + 1 }}</td>
		<td>{{ $detail->charge?->charge_name }}</td>
		<td class="num">{{ number_format($detail->total - ($detail->tax_amount ?? 0), 2) }}</td>
		@if($hasTax)
		<td class="num">{{ ($detail->tax_percentage ?? 0) > 0 ? number_format($detail->tax_percentage, 2) . '%' : '-' }}</td>
		<td class="num">{{ ($detail->tax_amount ?? 0) > 0 ? number_format($detail->tax_amount, 2) : '-' }}</td>
		<td class="num">{{ number_format($detail->total, 2) }}</td>
		@endif
	</tr>
	@empty
	<tr><td colspan="{{ $hasTax ? 6 : 3 }}" class="center">{{ $charge->charge_title }}</td></tr>
	@endforelse
	@php $taxTotal = $charge->details->sum(fn($d) => $d->tax_amount ?? 0); @endphp
	<tr><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">Sub Total</td><td class="num">{{ number_format($charge->amount, 2) }}</td></tr>
	@if($charge->discount > 0)
	<tr><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">Discount</td><td class="num">- {{ number_format($charge->discount, 2) }}</td></tr>
	@endif
	@if($charge->addon_discount_amount > 0)
	<tr><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">Add. Discount</td><td class="num">- {{ number_format($charge->addon_discount_amount, 2) }}</td></tr>
	@endif
	@if($hasTax && $taxTotal > 0)
	<tr><td colspan="5" class="num">Tax</td><td class="num">{{ number_format($taxTotal, 2) }}</td></tr>
	@endif
	<tr class="rpt-group"><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">TOTAL</td><td class="num">{{ number_format($charge->total, 2) }}</td></tr>
	<tr><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">Paid Amount</td><td class="num">{{ number_format($charge->paid_amount, 2) }}</td></tr>
	@php $due = $charge->total - $charge->paid_amount; @endphp
	@if($due > 0)
	<tr><td colspan="{{ $hasTax ? 5 : 2 }}" class="num">Due</td><td class="num">{{ number_format($due, 2) }}</td></tr>
	@endif
</table>

<p class="rpt-line"><b>Payment Mode:</b> {{ $charge->payment_mode }}</p>
@endsection
