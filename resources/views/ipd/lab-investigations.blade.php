@extends('layouts.app')
@section('title', 'IPD Lab Investigations')
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">@include('ipd.partials.menu')</div>
    <div class="card-body">
      @include('lab-results.partials.investigations', ['module' => 'ipd', 'entityId' => $ipd->id])
    </div>
  </div>
</div>
@endsection
