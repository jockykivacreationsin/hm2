@extends('layouts.app')
@section('title', 'DCD Lab Investigations')
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">@include('dcd.partials.menu')</div>
    <div class="card-body">
      @include('lab-results.partials.investigations', ['module' => 'dcd', 'entityId' => $dcd->id])
    </div>
  </div>
</div>
@endsection
