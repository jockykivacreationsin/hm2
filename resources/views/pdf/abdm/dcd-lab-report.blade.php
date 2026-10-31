{{-- ABDM FHIR attachment: DCD lab investigations. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: visit (dcd + patient, array), age (years or null), groups (list of ['title', 'rows']), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'DCD Lab Investigations - ' . $visit['patient_name'])
@section('report-title', 'LAB INVESTIGATIONS')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $visit['patient_name'] }}</td>
		<td class="label">DCD ID:</td><td>{{ (int) $visit['id'] }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($age !== null){{ $age }} Years @endif @if(!empty($visit['gender'])){{ $age !== null ? '/ ' : '' }}{{ $visit['gender'] }}@endif</td>
		<td class="label">Visit Date:</td><td>{{ date('d-M-Y h:i A', strtotime($visit['dcd_date_time'])) }}</td>
	</tr>
</table></div>

<table class="rpt-table">
	<tr><th>Test</th><th>Result</th><th>Reference Range</th><th>Unit</th></tr>
	@foreach($groups as $group)
	<tr class="rpt-group"><td colspan="4">{{ $group['title'] }}</td></tr>
	@foreach($group['rows'] as $row)
	<tr{!! $row['highlighted'] ? ' class="rpt-highlight"' : '' !!}>
		<td>{{ $row['name'] }}</td>
		<td>{{ $row['value'] }}</td>
		<td>{{ $row['range'] }}</td>
		<td>{{ $row['unit'] }}</td>
	</tr>
	@endforeach
	@endforeach
</table>
@endsection
