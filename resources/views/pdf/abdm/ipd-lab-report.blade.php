{{-- ABDM FHIR attachment: IPD lab investigations. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: row (admission + patient, array), groups (list of ['date', 'charge_name', 'results']), settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'IPD Lab Investigations - ' . $row['patient_name'])
@section('report-title', 'LAB INVESTIGATIONS')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row['patient_name'] }}</td>
		<td class="label">IPD No:</td><td>{{ (int) $row['id'] }}/bmh/{{ $row['iyear'] }}</td>
	</tr>
	<tr>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($row['patient_id']) }}</td>
		<td class="label">Date:</td><td>{{ date('d-M-Y') }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row['age'] }} Years / {{ $row['gender'] }}</td>
		<td class="label">Contact:</td><td>{{ $row['mobile_number'] }}</td>
	</tr>
	<tr><td class="label">Address:</td><td colspan="3">{{ $row['address'] }}</td></tr>
</table></div>

@if($groups)
<table class="rpt-table">
	<tr><th>Test</th><th>Result</th><th>Reference Range</th><th>Unit</th></tr>
	@foreach($groups as $group)
	<tr class="rpt-group"><td colspan="4">{{ $group['date'] }} - {{ $group['charge_name'] }}</td></tr>
	@foreach($group['results'] as $result)
	<tr{!! (string) $result['highlighted'] === 'Yes' ? ' class="rpt-highlight"' : '' !!}>
		<td>{{ $result['sub_test_name'] }}</td>
		<td>{{ $result['test_value'] }}</td>
		<td>{{ $result['reference_range'] }}</td>
		<td>{{ $result['unit_name'] }}</td>
	</tr>
	@endforeach
	@endforeach
</table>
@else
<div class="rpt-empty">No lab results recorded for this admission</div>
@endif
@endsection
