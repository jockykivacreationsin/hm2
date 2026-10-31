{{-- Single charge receipt (OPD/IPD/DCD). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: charge, patient, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Receipt - ' . ($charge->invoice_number_two ?? ''))
@section('report-title', 'PAYMENT RECEIPT')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient?->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Receipt No:</td><td>{{ $charge->invoice_number_two }}</td>
		<td class="label">Date:</td><td>{{ $charge->charge_date_time ? date('d-M-Y h:i A', strtotime($charge->charge_date_time)) : '' }}</td>
	</tr>
	<tr><td class="label">Description:</td><td colspan="3">{{ $charge->charge_title }}</td></tr>
</table></div>

<table class="rpt-table">
	<tr><th style="width:6%;">#</th><th>Description</th><th class="num" style="width:20%;">Amount (&#8377;)</th></tr>
	@forelse($charge->details as $i => $d)
	<tr><td>{{ $i + 1 }}</td><td>{{ $d->charge?->charge_name }}</td><td class="num">{{ number_format($d->total, 2) }}</td></tr>
	@empty
	<tr><td colspan="3" class="center">{{ $charge->charge_title }}</td></tr>
	@endforelse
	<tr><td colspan="2" class="num">Amount</td><td class="num">{{ number_format($charge->amount, 2) }}</td></tr>
	@if($charge->discount > 0)<tr><td colspan="2" class="num">Discount</td><td class="num">- {{ number_format($charge->discount, 2) }}</td></tr>@endif
	<tr class="rpt-group"><td colspan="2" class="num">Total</td><td class="num">{{ number_format($charge->total, 2) }}</td></tr>
	<tr><td colspan="2" class="num">Paid</td><td class="num">{{ number_format($charge->paid_amount, 2) }}</td></tr>
	@php $due = $charge->total - $charge->paid_amount; @endphp
	@if($due > 0.001)<tr><td colspan="2" class="num">Due</td><td class="num">{{ number_format($due, 2) }}</td></tr>@endif
</table>

@foreach($charge->payments as $pmt)
<p class="rpt-line">Payment: &#8377;{{ number_format($pmt->amount, 2) }} via {{ $pmt->payment_mode }} on {{ $pmt->payment_date_time ? date('d-m-Y h:i A', strtotime($pmt->payment_date_time)) : '' }}</p>
@endforeach
@endsection
