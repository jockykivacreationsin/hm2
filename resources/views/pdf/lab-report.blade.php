{{--
  Lab/radiology investigation report for OPD, IPD and DCD (legacy: rpt-opd-lab-investigations.php).
  Standard layout + report-standard.css. Vars: patient, idLabel, idValue, dateLabel, visitDate, doctor, services, settings.
--}}
@extends('pdf.layouts.standard')

@section('title', 'Lab Investigations - ' . ($patient?->patient_name ?? ''))
@section('report-title', 'LAB INVESTIGATION REPORT')

@section('content')
@php
	$dob = $patient?->date_of_birth;
	$age = !empty($dob) ? floor((time() - strtotime($dob)) / 31556926) : null;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">{{ $idLabel }}</td><td>{{ $idValue }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($age !== null){{ $age }} Years @endif @if(!empty($patient?->gender)){{ $age !== null ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">{{ $dateLabel }}</td><td>{{ $visitDate ? date('d-M-Y', strtotime($visitDate)) : '' }}</td>
	</tr>
	@if(!empty($doctor))
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $doctor }}</td></tr>
	@endif
	@if(!empty($patient?->mobile_number))
	<tr><td class="label">Contact:</td><td colspan="3">{{ $patient->mobile_number }}</td></tr>
	@endif
</table></div>

@forelse($services as $svc)
	<div class="rpt-section">{{ date('d-m-Y h:i A', strtotime($svc['date'])) }} - {{ $svc['title'] }} ({{ $svc['charge_names'] }})</div>

	@if(!$svc['is_radiology'])
		@php $groups = array_values(array_filter($svc['details'], fn ($d) => count($d['results']) > 0)); @endphp
		@if(count($groups) > 0)
		<table class="rpt-table">
			<tr><th>Test</th><th>Result</th><th>Reference Range</th><th>Unit</th></tr>
			@foreach($groups as $group)
			<tr class="rpt-group"><td colspan="4">{{ $group['charge_name'] }}</td></tr>
			@foreach($group['results'] as $row)
			<tr{!! $row['highlighted'] ? ' class="rpt-highlight"' : '' !!}>
				<td>{{ $row['sub_test_name'] }}</td>
				<td>{{ $row['test_value'] }}</td>
				<td>{{ $row['reference_range'] }}</td>
				<td>{{ $row['unit'] }}</td>
			</tr>
			@endforeach
			@endforeach
		</table>
		@else
		<div class="rpt-empty">No detailed results entered yet</div>
		@endif
	@endif

	@if(!empty($svc['note']))
	<div class="rpt-note">
		<span class="rpt-note-title">{{ $svc['note_title'] }}</span><br>
		<span class="rpt-note-body">{!! nl2br(e($svc['note'])) !!}</span>
		@if(!empty($svc['note_by']))
		<div class="rpt-note-meta">By: {{ $svc['note_by'] }}@if(!empty($svc['note_date'])) on {{ date('d-m-Y h:i A', strtotime($svc['note_date'])) }}@endif</div>
		@endif
	</div>
	@endif
@empty
	<div class="rpt-empty">No lab or radiology investigations ordered for this visit</div>
@endforelse
@endsection
