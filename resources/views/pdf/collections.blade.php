{{-- Collections report (billing charges in a date range). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: charges, from, to, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Collections Report')
@section('report-title', 'COLLECTIONS REPORT')

@section('content')
@php
	$gAmt = 0; $gDisc = 0; $gTotal = 0; $gPaid = 0;
	$d = fn ($v) => $v ? date('d-m-Y', strtotime($v)) : '';
@endphp

<div class="rpt-info"><table>
	<tr><td class="label">Period:</td><td>{{ $d($from) }} to {{ $d($to) }}</td></tr>
</table></div>

<table class="rpt-table rpt-table-sm">
	<tr>
		<th style="width:4%;">#</th><th style="width:10%;">Date</th><th style="width:7%;">Module</th><th>Description</th><th style="width:13%;">Department</th>
		<th class="num">Amount</th><th class="num">Discount</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Due</th>
	</tr>
	@forelse($charges as $c)
	@php $gAmt += $c->amount; $gDisc += $c->discount; $gTotal += $c->total; $gPaid += $c->paid_amount; @endphp
	<tr>
		<td>{{ $loop->iteration }}</td>
		<td>{{ $c->charge_date_time ? date('d-m-Y', strtotime($c->charge_date_time)) : '' }}</td>
		<td>{{ $c->module }}</td>
		<td>{{ $c->charge_title }}</td>
		<td>{{ $c->chargeDepartment?->department_name ?? '' }}</td>
		<td class="num">{{ number_format($c->amount, 2) }}</td>
		<td class="num">{{ number_format($c->discount, 2) }}</td>
		<td class="num">{{ number_format($c->total, 2) }}</td>
		<td class="num">{{ number_format($c->paid_amount, 2) }}</td>
		<td class="num">{{ number_format($c->total - $c->paid_amount, 2) }}</td>
	</tr>
	@empty
	<tr><td colspan="10" class="center">No collections found for this period.</td></tr>
	@endforelse
	<tr class="rpt-group">
		<td colspan="5" class="num">Totals:</td>
		<td class="num">{{ number_format($gAmt, 2) }}</td>
		<td class="num">{{ number_format($gDisc, 2) }}</td>
		<td class="num">{{ number_format($gTotal, 2) }}</td>
		<td class="num">{{ number_format($gPaid, 2) }}</td>
		<td class="num">{{ number_format($gTotal - $gPaid, 2) }}</td>
	</tr>
</table>
@endsection
