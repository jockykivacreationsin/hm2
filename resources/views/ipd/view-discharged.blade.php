@extends('layouts.app')
@section('title', 'Discharge Detail — ' . $ipd->ipd_ref)
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Discharge Record &mdash; {{ e($ipd->patient?->patient_name) }}</div>
    <div class="ipd-buttons"><ul>
      <li><a href="{{ route('ipd.discharged') }}" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></li>
      <li><a href="{{ route('pdf.ipd.discharge-summary', $ipd->id) }}" target="_blank"><i class="fas fa-print"></i> Discharge Summary</a></li>
      <li><a href="{{ route('pdf.ipd.discharge-bill', $ipd->id) }}" target="_blank"><i class="fas fa-receipt"></i> Bill</a></li>
      @if($ipd->discharge)
      <li><a href="{{ route('pdf.discharge.ipd', $ipd->discharge->id) }}" target="_blank" title="Discharge Card"><i class="fas fa-id-card"></i> Discharge Card</a></li>
      @endif
    </ul></div>
  </div>

  <div class="row mt-20">
    {{-- Patient Info --}}
    <div class="col-md-6 mb-3">
      <div class="card h-100"><div class="card-header fw-bold">Patient</div><div class="card-body">
        <p><b>Name:</b> {{ e($ipd->patient?->patient_name) }}</p>
        <p><b>UHID:</b> {{ $ipd->patient?->uhid }}</p>
        <p><b>Age/Gender:</b> {{ $ipd->patient?->age }} Yrs / {{ $ipd->patient?->gender }}</p>
        <p><b>Mobile:</b> {{ $ipd->patient?->mobile_number }}</p>
        <p><b>Address:</b> {{ e($ipd->patient?->address) }}</p>
      </div></div>
    </div>
    {{-- Admission Info --}}
    <div class="col-md-6 mb-3">
      <div class="card h-100"><div class="card-header fw-bold">Admission</div><div class="card-body">
        <p><b>IPD Ref:</b> {{ $ipd->ipd_ref }}</p>
        <p><b>Consultant:</b> {{ e($ipd->doctor?->staff_name) }}</p>
        <p><b>Admitted:</b> {{ $ipd->admission_date_time }}</p>
        <p><b>Discharged:</b> {{ $ipd->discharge?->discharge_date_time }}</p>
        <p><b>Ward/Bed:</b> {{ $ipd->bed?->wardCategory?->ward_category }} / {{ $ipd->bed?->bed_name }}</p>
      </div></div>
    </div>
    {{-- Discharge Details --}}
    <div class="col-12 mb-3">
      <div class="card"><div class="card-header fw-bold">Discharge Details</div><div class="card-body">
        <div class="row">
          <div class="col-md-6"><p><b>Diagnosis:</b> {{ e($ipd->discharge?->diagnosis) }}</p></div>
          <div class="col-md-6"><p><b>Condition on Discharge:</b> {{ e($ipd->discharge?->condition_on_discharge) }}</p></div>
          <div class="col-md-6"><p><b>Follow-up:</b> {{ e($ipd->discharge?->follow_up_detail) }}</p></div>
          <div class="col-md-6"><p><b>Urgent Care:</b> {{ e($ipd->discharge?->urgent_care_condition) }}</p></div>
          @if($ipd->discharge?->note)
          <div class="col-12"><p><b>Note:</b> {!! \App\Support\SafeHtml::clean($ipd->discharge->note) !!}</p></div>
          @endif
          <div class="col-md-4"><p><b>Approved:</b>
            <span class="badge {{ $ipd->discharge?->approved ? 'bg-success' : 'bg-warning text-dark' }}">
              {{ $ipd->discharge?->approved ? 'Yes' : 'Pending' }}
            </span>
          </p></div>
        </div>
      </div></div>
    </div>
  </div>
</div></div>
@endsection
