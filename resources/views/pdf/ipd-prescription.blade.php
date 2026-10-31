{{-- IPD prescription (legacy rpt-ipd-prescription-pdf.php). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: d (IpdReportData::prescription), settings --}}
@extends('pdf.layouts.standard')

@php
	$row = $d['ipd'];
	$doctorName = $row->doctor_name ?? 'N/A';
	$qualification = $row->qualification ?? '';
	// Date-only values (time 00:00:00) print without a time.
	$fmt = fn ($v) => $v ? date(str_ends_with((string) $v, '00:00:00') || strlen((string) $v) <= 10 ? 'd-M-Y' : 'd-M-Y h:i A', strtotime($v)) : '';
@endphp

@section('title', 'IPD Prescription - ' . ($row->patient_name ?? ''))
@section('report-title', 'IPD PRESCRIPTION')

@section('content')
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row->patient_name }}</td>
		<td class="label">IPD No:</td><td>{{ (int) $row->id }}/bmh/{{ $row->iyear }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $row->age }} Years / {{ $row->gender }}</td>
		<td class="label">Contact:</td><td>{{ $row->patient_mobile }}</td>
	</tr>
	<tr>
		<td class="label">Admission Date:</td><td>{{ $fmt($row->admission_date) }}</td>
		<td class="label">Discharge Date:</td><td>{{ !empty($row->discharge_date) ? $fmt($row->discharge_date) : 'Not Discharged' }}</td>
	</tr>
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $doctorName }}</td></tr>
</table></div>

<div class="rpt-section">1. Doctor Prescriptions (During Admission)</div>
@forelse($d['prescriptions'] as $rx)
	<p class="rpt-meta"><b>Prescribed on:</b> {{ $fmt($rx['row']->prescription_datetime) }}</p>
	@if($rx['medicines']->isNotEmpty())
		@include('pdf.partials.ipd-rx-table', ['rows' => $rx['medicines'], 'timingKey' => 'timing_names'])
	@endif
	@if(!empty($rx['row']->findings))
	<div class="rpt-note">
		<span class="rpt-note-title">Findings:</span><br>
		<span class="rpt-note-body">{!! nl2br(e($rx['row']->findings)) !!}</span>
	</div>
	@endif
@empty
	<div class="rpt-empty">No prescription records</div>
@endforelse

<div class="rpt-section">2. Treatment Chart (Nurse Station)</div>
@forelse($d['treatments'] as $tr)
	<p class="rpt-meta"><b>Treatment on:</b> {{ $fmt($tr['row']->treatment_datetime) }}</p>
	@if($tr['medicines']->isNotEmpty())
		@include('pdf.partials.ipd-rx-table', ['rows' => $tr['medicines'], 'timingKey' => 'timing_names'])
	@endif
@empty
	<div class="rpt-empty">No treatment chart records</div>
@endforelse

@if($d['is_discharged'])
	<div class="rpt-section">3. Medications Given at Discharge</div>
	@if($d['given']->isNotEmpty())
		@include('pdf.partials.ipd-rx-table', ['rows' => $d['given'], 'timingKey' => 'timing_display'])
	@else
		<div class="rpt-empty">No medications given at discharge</div>
	@endif

	<div class="rpt-section">4. Medications Advised on Discharge (To Continue)</div>
	@if($d['advised']->isNotEmpty())
		@include('pdf.partials.ipd-rx-table', ['rows' => $d['advised'], 'timingKey' => 'timing_names'])
	@else
		<div class="rpt-empty">No medications advised on discharge</div>
	@endif
@endif
@endsection

@section('signature')
	<p><strong>{{ $doctorName }}</strong></p>
	@if(!empty($qualification))<p>{{ $qualification }}</p>@endif
@endsection
