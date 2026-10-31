{{-- IPD detailed discharge bill (legacy rpt-ipd-discharge-detailed-bill.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::dischargeDetailedBill), settings --}}
@extends('pdf.layouts.standard')

@php $row = $d['ipd']; $hidePaid = $d['hide_paid']; @endphp

@section('title', 'IPD Detailed Bill - ' . ($row->patient_name ?? ''))
@section('report-title', 'IPD DETAILED BILL')

@section('content')
@include('pdf.partials.ipd-bill-patient', ['row' => $row, 'hidePaid' => $hidePaid, 'dateLabel' => 'Printed on:', 'dateValue' => now()->format('d-M-Y')])

@forelse($d['blocks'] as $block)
	@php
		$charge     = $block['charge'];
		$cCancelled = ($charge->invoice_status ?? 'issued') === 'cancelled';
	@endphp
	<div class="rpt-section">
		{{ $charge->charge_date_display }} - {{ $charge->charge_title }} ({{ $charge->department_name }})
		@if($cCancelled) &nbsp;<span style="color:#c00;font-weight:bold;">[CANCELLED]</span>@endif
	</div>
	@if(count($block['items']) > 0)
	<table class="rpt-table">
		@if($hidePaid)
		<tr><th style="width:18%;">Code</th><th>Title</th><th class="num" style="width:16%;">Total (&#8377;)</th></tr>
		@else
		<tr><th>Title</th><th class="num" style="width:16%;">Total (&#8377;)</th><th class="num" style="width:16%;">Paid (&#8377;)</th></tr>
		@endif
		@foreach($block['items'] as $item)
		<tr>
			@if($hidePaid)<td>{{ $item['line']->charge_code }}</td>@endif
			<td>{{ $item['line']->charge_name }}</td>
			<td class="num">{{ number_format($item['line']->total, 2) }}</td>
			@unless($hidePaid)<td class="num">{{ number_format($item['paid'], 2) }}</td>@endunless
		</tr>
		@endforeach
		<tr class="rpt-group">
			<td colspan="{{ $hidePaid ? 2 : 1 }}" class="num">Subtotal</td>
			<td class="num">{{ number_format($block['subtotal'], 2) }}</td>
			@unless($hidePaid)<td class="num">{{ number_format($block['subtotal_paid'], 2) }}</td>@endunless
		</tr>
	</table>
	@endif
@empty
	<div class="rpt-empty">No charges found</div>
@endforelse

<table class="rpt-table" style="margin-top:15px;">
	<tr class="rpt-group"><td>Grand Total</td><td class="num" style="width:20%;">&#8377;{{ number_format($d['grand_total'], 2) }}</td></tr>
	@unless($hidePaid)
	<tr><td>Paid</td><td class="num">&#8377;{{ number_format($d['grand_paid'], 2) }}</td></tr>
	<tr class="rpt-group"><td>Payable</td><td class="num">&#8377;{{ number_format($d['grand_total'] - $d['grand_paid'], 2) }}</td></tr>
	@endunless
</table>
@endsection

@section('signature')
	@include('pdf.partials.bill-signature')
@endsection
