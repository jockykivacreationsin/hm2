{{-- Selected invoices (OPD/IPD/DCD). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: charges, patient (may be null), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Selected Invoices')
@section('report-title', 'SELECTED INVOICES')

@section('content')
@php $grandTotal = 0; $grandPaid = 0; @endphp

@if($patient)
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ $patient->uhid }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient->age){{ $patient->age }} Years @endif @if($patient->gender){{ $patient->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Contact:</td><td>{{ $patient->mobile_number }}</td>
	</tr>
</table></div>
@endif

@foreach($charges as $c)
	@php $grandTotal += $c->total; $grandPaid += $c->paid_amount; @endphp
	<div class="rpt-section">Invoice #{{ $c->invoice_number }} &mdash; {{ $c->charge_title }} &mdash; {{ $c->chargeDepartment?->department_name }}</div>
	<div class="rpt-info"><table>
		<tr>
			<td class="label">Date:</td><td>{{ $c->charge_date_time ? date('d-M-Y h:i A', strtotime($c->charge_date_time)) : '' }}</td>
			<td class="label">Mode:</td><td>{{ $c->payment_mode }}</td>
		</tr>
	</table></div>
	<table class="rpt-table">
		<tr>
			<th class="num">Amount (&#8377;)</th><th class="num">Discount (&#8377;)</th><th class="num">Total (&#8377;)</th>
			<th class="num">Paid (&#8377;)</th><th class="num">Due (&#8377;)</th>
		</tr>
		<tr>
			<td class="num">{{ number_format($c->amount, 2) }}</td>
			<td class="num">{{ number_format($c->discount, 2) }}</td>
			<td class="num">{{ number_format($c->total, 2) }}</td>
			<td class="num">{{ number_format($c->paid_amount, 2) }}</td>
			<td class="num">{{ number_format($c->total - $c->paid_amount, 2) }}</td>
		</tr>
	</table>
@endforeach

@if($charges->isEmpty())
	<div class="rpt-empty">No invoices found</div>
@endif

<table class="rpt-table" style="margin-top:15px;">
	<tr class="rpt-group"><td>Grand Total</td><td class="num" style="width:20%;">&#8377;{{ number_format($grandTotal, 2) }}</td></tr>
	<tr><td>Total Paid</td><td class="num">&#8377;{{ number_format($grandPaid, 2) }}</td></tr>
	<tr class="rpt-group"><td>Balance Due</td><td class="num">&#8377;{{ number_format($grandTotal - $grandPaid, 2) }}</td></tr>
</table>
@endsection
