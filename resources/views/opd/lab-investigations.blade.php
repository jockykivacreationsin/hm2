@extends('layouts.app')
@section('title', 'OPD Lab Investigations')
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">@include('opd.partials.menu')</div>
    <div class="card-body">
      @include('lab-results.partials.investigations', ['module' => 'opd', 'entityId' => $opd->id])
    </div>
  </div>
</div>
@endsection
