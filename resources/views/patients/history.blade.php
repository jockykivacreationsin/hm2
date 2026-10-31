@extends('layouts.app')

@section('title', 'Visits History')

@php
    // Legacy html_to_text(): the symptoms/remarks columns hold editor HTML, shown here as plain text.
    $plain = fn ($html) => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    $fmt   = fn ($dt) => $dt ? \Illuminate\Support\Carbon::parse($dt)->format('d-m-Y h:i A') : '';
@endphp

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-prescription-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Visits History &mdash; {{ $patient->patient_name }} ({{ $patient->uhid }})</div>
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="{{ route('patients.index') }}"><i class="fa fa-arrow-left"></i> Patients</a></li>
                        </ul>
                    </div>
                </div>
                <div class="sm-table mt-10">
                    <div class="table-responsive">
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Date Time</th>
                                    <th>Doctor</th>
                                    <th>Symptoms</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($opds as $opd)
                                    <tr>
                                        <td><a href="{{ route('opd.show', $opd->id) }}" data-bs-toggle="tooltip" title="OPD Dashboard">OPD {{ $opd->id }}</a></td>
                                        <td>{{ $fmt($opd->opd_date_time) }}</td>
                                        <td>{{ $opd->doctor->staff_name }}</td>
                                        <td>{{ $plain($opd->symptoms) }}</td>
                                        <td>{{ $plain($opd->remarks) }}</td>
                                    </tr>
                                    @if($opd->ipd)
                                        <tr>
                                            <td><b><a href="{{ route('ipd.show', $opd->ipd->id) }}" data-bs-toggle="tooltip" title="IPD Dashboard">IPD {{ $opd->ipd->id }}</a></b> OPD {{ $opd->id }}</td>
                                            <td>{{ $fmt($opd->ipd->admission_date_time) }}</td>
                                            <td>{{ $opd->doctor->staff_name }}</td>
                                            <td>{{ $plain($opd->symptoms) }}</td>
                                            <td>{{ $plain($opd->remarks) }}</td>
                                        </tr>
                                    @endif
                                    @if($opd->dcd)
                                        <tr>
                                            <td><b><a href="{{ route('dcd.show', $opd->dcd->id) }}" data-bs-toggle="tooltip" title="DCD Dashboard">DCD {{ $opd->dcd->id }}</a></b> OPD {{ $opd->id }}</td>
                                            <td>{{ $fmt($opd->dcd->dcd_date_time) }}</td>
                                            <td>{{ $opd->doctor->staff_name }}</td>
                                            <td>{{ $plain($opd->symptoms) }}</td>
                                            <td>{{ $plain($opd->remarks) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
