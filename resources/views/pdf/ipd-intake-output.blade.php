{{-- IPD intake / output chart (legacy rpt-ipd-intake-output.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::intakeOutput), settings --}}
@extends('pdf.layouts.standard')

@section('title', 'Intake - Output Chart - ' . ($d['patient']->patient_name ?? ''))
@section('report-title', 'INTAKE - OUTPUT CHART')

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
			<th>Time</th><th>IV Fluids</th><th>Oral</th><th>Urine</th><th>Stool</th><th>Vomiting</th><th>RT/ASP</th><th>Drain</th>
		</tr>
		@forelse($day['details'] as $io)
		<tr>
			<td>{{ $io->time_display }}</td><td>{{ $io->iv_fluids }}</td><td>{{ $io->oral }}</td><td>{{ $io->urine }}</td>
			<td>{{ $io->stool }}</td><td>{{ $io->vomiting }}</td><td>{{ $io->rt_asp }}</td><td>{{ $io->drain }}</td>
		</tr>
		@empty
		<tr><td colspan="8" class="center">No intake/output recorded for this date.</td></tr>
		@endforelse
	</table>
@empty
	<div class="rpt-empty">No intake/output records found.</div>
@endforelse
@endsection
