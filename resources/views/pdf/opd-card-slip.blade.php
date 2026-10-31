{{-- OPD card slip (legacy rpt-receipt.php): hospital header + patient details on a half page, the rest is left blank
     for the doctor to write on. Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: opd, settings; hideSignature = true. --}}
@extends('pdf.layouts.standard')

@section('title', 'OPD Card - ' . ($opd->patient?->patient_name ?? ''))
@section('report-title', 'OPD CARD')

@section('content')
@php
	$patient = $opd->patient;
	$visitAt = $opd->opd_date_time ? strtotime($opd->opd_date_time) : null;
@endphp

<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $patient?->patient_name }}</td>
		<td class="label">OPD No:</td><td>{{ $opd->id }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>@if($patient?->age){{ $patient->age }} Years @endif @if($patient?->gender){{ $patient?->age ? '/ ' : '' }}{{ $patient->gender }}@endif</td>
		<td class="label">Date:</td><td>{{ $visitAt ? date('d-M-Y h:i A', $visitAt) : '' }}</td>
	</tr>
	<tr>
		<td class="label">Contact:</td><td colspan="3">{{ $patient?->mobile_number }}</td>
	</tr>
	@if(!empty($patient?->address))
	<tr><td class="label">Address:</td><td colspan="3">{{ $patient->address }}</td></tr>
	@endif
</table></div>
@endsection
