@extends('layouts.app')
@section('title', strtoupper($module) . ' - Patient Documents')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @if($module === 'opd')
                @include('opd.partials.menu', ['opd' => $visit])
            @elseif($module === 'ipd')
                @include('ipd.partials.menu', ['ipd' => $visit])
            @else
                @include('dcd.partials.menu', ['dcd' => $visit])
            @endif
        </div>
        <div class="card-body">
            @if($module === 'opd')
                @include('patient-documents.index', ['patientId' => $patientId, 'opdId' => $id])
            @elseif($module === 'ipd')
                @include('patient-documents.index', ['patientId' => $patientId, 'ipdId' => $id])
            @else
                @include('patient-documents.index', ['patientId' => $patientId, 'dcdId' => $id])
            @endif
        </div>
    </div>
</div>
@endsection
