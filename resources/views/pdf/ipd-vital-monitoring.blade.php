{{-- IPD nursing care record / vital monitoring (legacy rpt-ipd-vital-monitoring.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::vitalMonitoring), settings --}}
@extends('pdf.layouts.standard')

@section('title', 'Nursing Care Record - ' . ($d['patient']->patient_name ?? ''))
@section('report-title', 'NURSING CARE RECORD')

@section('content')
@include('pdf.partials.ipd-chart-patient', ['p' => $d['patient']])

@forelse($d['days'] as $day)
	@php $r = $day['row']; @endphp
	<div class="rpt-section">{{ $r->day_display }} &mdash; {{ $r->ward_category }} - {{ $r->bed_name }}</div>
	<p class="rpt-meta">
		<b>Added By:</b> {{ $r->added_by }}
		@if(!empty($r->remarks)) &nbsp;&nbsp; <b>Remarks:</b> {{ $r->remarks }}@endif
	</p>
	<table class="rpt-table">
		<tr>
			<th>Time</th><th>BP</th><th>Pulse</th><th>Temp</th><th>R.R.</th>
			<th>SPO2</th><th>FBS</th><th>RBS</th><th>Intake</th><th>Output</th>
		</tr>
		@forelse($day['details'] as $v)
		<tr>
			<td>{{ $v->time_display }}</td><td>{{ $v->bp }}</td><td>{{ $v->pulse }}</td><td>{{ $v->temperature }}</td><td>{{ $v->respiration }}</td>
			<td>{{ $v->spo2 }}</td><td>{{ $v->fbs }}</td><td>{{ $v->rbs }}</td><td>{{ $v->intake }}</td><td>{{ $v->output }}</td>
		</tr>
		@empty
		<tr><td colspan="10" class="center">No vital signs recorded for this date.</td></tr>
		@endforelse
	</table>
@empty
	<div class="rpt-empty">No vital monitoring records found.</div>
@endforelse
@endsection
