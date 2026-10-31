{{-- ABDM FHIR attachment: OPD lab + radiology investigations. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: visit (array), opdId, services (list, see OpdLabInvestigationsPdf::investigations()), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Lab Investigations - ' . ($visit['patient_name'] ?? ''))
@section('report-title', 'LAB INVESTIGATION REPORT')

@section('content')
@php
	$age = !empty($visit['date_of_birth']) ? floor((time() - strtotime($visit['date_of_birth'])) / 31556926) : null;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $visit['patient_name'] }}</td>
		<td class="label">OPD ID:</td><td>{{ $opdId }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($age !== null){{ $age }} Years @endif @if(!empty($visit['gender'])){{ $age !== null ? '/ ' : '' }}{{ $visit['gender'] }}@endif</td>
		<td class="label">Visit Date:</td><td>{{ date('d-M-Y', strtotime($visit['opd_date_time'])) }}</td>
	</tr>
	@if(!empty($visit['doctor_name']))
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $visit['doctor_name'] }}</td></tr>
	@endif
	@if(!empty($visit['patient_phone']))
	<tr><td class="label">Contact:</td><td colspan="3">{{ $visit['patient_phone'] }}</td></tr>
	@endif
</table></div>

@forelse($services as $svc)
	<div class="rpt-section">{{ $svc['date'] }} - {{ $svc['title'] }} ({{ $svc['charges'] }})</div>

	@if(!$svc['is_radiology'])
		@if(count($svc['groups']) > 0)
		<table class="rpt-table">
			<tr><th>Test</th><th>Result</th><th>Reference Range</th><th>Unit</th></tr>
			@foreach($svc['groups'] as $group)
			<tr class="rpt-group"><td colspan="4">{{ $group['name'] }}</td></tr>
			@foreach($group['rows'] as $r)
			<tr{!! strtolower((string) $r['highlighted']) === 'yes' ? ' class="rpt-highlight"' : '' !!}>
				<td>{{ $r['sub_test_name'] }}</td>
				<td>{{ $r['test_value'] }}</td>
				<td>{{ $r['reference_range'] }}</td>
				<td>{{ $r['unit_name'] }}</td>
			</tr>
			@endforeach
			@endforeach
		</table>
		@else
		<div class="rpt-empty">No detailed results entered yet</div>
		@endif
	@endif

	@if(!empty($svc['note']['note']))
	<div class="rpt-note">
		<span class="rpt-note-title">{{ $svc['is_radiology'] ? 'Radiology Report:' : 'Conclusion / Interpretation:' }}</span><br>
		<span class="rpt-note-body">{!! nl2br(e($svc['note']['note'])) !!}</span>
		@if(!empty($svc['note']['staff_name']))
		<div class="rpt-note-meta">By: {{ $svc['note']['staff_name'] }}@if(!empty($svc['note']['updated_on'])) on {{ date('d-m-Y h:i A', strtotime($svc['note']['updated_on'])) }}@endif</div>
		@endif
	</div>
	@endif
@empty
	<div class="rpt-empty">No lab or radiology investigations ordered for this visit</div>
@endforelse
@endsection
