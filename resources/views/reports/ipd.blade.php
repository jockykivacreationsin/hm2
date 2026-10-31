@extends('layouts.app')
@section('title', 'IPD Report')
@section('content')
<div class="card-ipd"><div class="card-body">
    <div class="ipd-title-with-buttons row-bottom-border">
        <div class="ipd-title">IPD Report</div>
    </div>
    <p class="mt-20 text-muted">IPD reporting module — use the IPD Register for detailed records.</p>
    <a href="{{ route('ipd.index') }}" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Go to IPD Register</a>
</div></div>
@endsection
