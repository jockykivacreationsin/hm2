{{-- IPD discharge bill (legacy rpt-ipd-discharge-bill.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::dischargeBill), settings --}}
@extends('pdf.layouts.standard')

@php $row = $d['ipd']; $hidePaid = $d['hide_paid']; @endphp

@section('title', 'IPD Bill - ' . ($row->patient_name ?? ''))
@section('report-title', 'IPD BILL')

@section('content')
@include('pdf.partials.ipd-bill-patient', ['row' => $row, 'hidePaid' => $hidePaid, 'dateLabel' => 'Date:', 'dateValue' => now()->format('d-M-Y')])

<div class="rpt-section">Charges</div>
@if($d['charges']->isNotEmpty())
{{-- Code column only for non-category-1 patients; Paid column only for category 1 (legacy rule). --}}
<table class="rpt-table">
	@if($hidePaid)
	<tr><th style="width:14%;">Date</th><th style="width:18%;">Code</th><th>Title</th><th class="num" style="width:16%;">Total (&#8377;)</th></tr>
	@else
	<tr><th style="width:14%;">Date</th><th>Title</th><th class="num" style="width:16%;">Total (&#8377;)</th><th class="num" style="width:16%;">Paid (&#8377;)</th></tr>
	@endif
	@foreach($d['charges'] as $c)
	@php $cCancelled = ($c->invoice_status ?? 'issued') === 'cancelled'; @endphp
	<tr>
		<td>{{ $c->charge_date_display }}</td>
		@if($hidePaid)<td>{{ $c->code_display }}</td>@endif
		<td>{{ $c->charge_title }}@if($cCancelled) <span style="color:#c00;font-size:9px;font-weight:bold;">[CANCELLED]</span>@endif</td>
		<td class="num">{{ number_format($c->total, 2) }}</td>
		@unless($hidePaid)<td class="num">{{ number_format($c->paid_amount, 2) }}</td>@endunless
	</tr>
	@endforeach
	<tr class="rpt-group">
		<td colspan="{{ $hidePaid ? 3 : 2 }}" class="num">Total</td>
		<td class="num">{{ number_format($d['total'], 2) }}</td>
		@unless($hidePaid)<td class="num">{{ number_format($d['total_paid'], 2) }}</td>@endunless
	</tr>
</table>
@else
<div class="rpt-empty">No charges found</div>
@endif

<table class="rpt-table" style="margin-top:15px;">
	<tr class="rpt-group"><td>Total</td><td class="num" style="width:20%;">&#8377;{{ number_format($d['total'], 2) }}</td></tr>
	@unless($hidePaid)
	<tr><td>Paid</td><td class="num">&#8377;{{ number_format($d['total_paid'], 2) }}</td></tr>
	<tr class="rpt-group"><td>Payable</td><td class="num">&#8377;{{ number_format($d['payable'], 2) }}</td></tr>
	@endunless
</table>
@endsection

@section('signature')
	@include('pdf.partials.bill-signature')
@endsection
