@extends('layouts.app')
@section('title', 'OPD Report')
@section('content')
<div class="card-ipd"><div class="card-body">
    <div class="ipd-title-with-buttons row-bottom-border">
        <div class="ipd-title">OPD Report</div>
    </div>
    <p class="mt-20 text-muted">OPD reporting module — use the OPD Register for date-range filtering.</p>
    <a href="{{ route('opd.index') }}" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Go to OPD Register</a>
</div></div>
@endsection
