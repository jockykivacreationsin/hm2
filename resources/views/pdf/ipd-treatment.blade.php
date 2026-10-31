{{-- IPD treatment chart (legacy rpt-ipd-treatment.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::treatmentChart), settings --}}
@extends('pdf.layouts.standard')

@section('title', 'Treatment Chart - ' . ($d['patient']->patient_name ?? ''))
@section('report-title', 'TREATMENT CHART')

@section('content')
@include('pdf.partials.ipd-chart-patient', ['p' => $d['patient'], 'consultant' => $d['consultant'] ?? null])

@forelse($d['treatments'] as $t)
	@php $r = $t['row']; @endphp
	<div class="rpt-section">{{ $r->treatment_dt ? date('d-M-Y h:i A', strtotime($r->treatment_dt)) : '' }} &mdash; {{ $r->ward_category }} - {{ $r->bed_name }}</div>
	<p class="rpt-meta">
		<b>Prescribed By:</b> {{ $r->prescribed_by }} &nbsp;&nbsp;
		<b>Added By:</b> {{ $r->added_by }}
		@if(!empty($r->remarks)) &nbsp;&nbsp; <b>Remarks:</b> {{ $r->remarks }}@endif
	</p>
	<table class="rpt-table">
		<tr>
			<th style="width:22%;">Medicine</th><th style="width:8%;">Dose</th><th style="width:11%;">Frequency</th>
			<th style="width:17%;">Timing</th><th style="width:14%;">Meal</th><th style="width:10%;">Duration</th><th>Reason</th>
		</tr>
		@forelse($t['details'] as $m)
		<tr>
			<td>{{ $m->product_name }}</td>
			<td class="center">{{ $m->dose_quantity }}</td>
			<td class="center">{{ $m->frequency_display }}</td>
			<td>{{ $m->timing_names }}</td>
			<td>{{ $m->meal_name }}</td>
			<td class="center">{{ $m->duration_days }} days</td>
			<td>{{ !empty($m->condition_name) ? $m->condition_name : '-' }}</td>
		</tr>
		@empty
		<tr><td colspan="7" class="center">No medicines prescribed in this treatment entry.</td></tr>
		@endforelse
	</table>
@empty
	<div class="rpt-empty">No treatment records found.</div>
@endforelse
@endsection
