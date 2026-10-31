{{-- Sale bills report (billing charges in a date range, optional module). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: charges, from, to, module, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Sale Bills Report')
@section('report-title', 'SALE BILLS REPORT')

@section('content')
@php
	$gTotal = 0; $gPaid = 0;
	$d = fn ($v) => $v ? date('d-m-Y', strtotime($v)) : '';
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Period:</td><td>{{ $d($from) }} to {{ $d($to) }}</td>
		@if($module)<td class="label">Module:</td><td>{{ $module }}</td>@endif
	</tr>
</table></div>

<table class="rpt-table rpt-table-sm">
	<tr>
		<th style="width:4%;">#</th><th style="width:11%;">Date</th><th style="width:8%;">Module</th><th>Title</th><th style="width:14%;">Department</th>
		<th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th><th style="width:9%;">Mode</th>
	</tr>
	@forelse($charges as $c)
	@php $gTotal += $c->total; $gPaid += $c->paid_amount; @endphp
	<tr>
		<td>{{ $loop->iteration }}</td>
		<td>{{ $c->charge_date_time ? date('d-m-Y', strtotime($c->charge_date_time)) : '' }}</td>
		<td>{{ $c->module }}</td>
		<td>{{ $c->charge_title }}</td>
		<td>{{ $c->chargeDepartment?->department_name ?? '' }}</td>
		<td class="num">{{ number_format($c->total, 2) }}</td>
		<td class="num">{{ number_format($c->paid_amount, 2) }}</td>
		<td class="num">{{ number_format($c->total - $c->paid_amount, 2) }}</td>
		<td>{{ $c->payment_mode }}</td>
	</tr>
	@empty
	<tr><td colspan="9" class="center">No sale bills found for this period.</td></tr>
	@endforelse
	<tr class="rpt-group">
		<td colspan="5" class="num">Totals:</td>
		<td class="num">{{ number_format($gTotal, 2) }}</td>
		<td class="num">{{ number_format($gPaid, 2) }}</td>
		<td class="num">{{ number_format($gTotal - $gPaid, 2) }}</td>
		<td></td>
	</tr>
</table>
@endsection
