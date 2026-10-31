@extends('layouts.app')
@section('title', 'Lab Investigations')
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Lab Investigations (All Modules)</div>
    <div class="tbl-filter-box">
      <form method="GET" action="{{ route('lab-results.all') }}" class="d-flex flex-wrap gap-2 align-items-end">
        <div class="local-forms"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
        <div class="local-forms"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
        <div class="local-forms"><label>Patient</label><input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Name..."></div>
        <div><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button></div>
      </form>
    </div>
  </div>
  <div class="sm-table mt-10">
    <table class="table table-bordered table-sm data-tbl">
      <thead>
        <tr><th>Module</th><th>Ref</th><th>Patient</th><th>Service</th><th>Dept</th><th>Date</th><th class="no-sort">Actions</th></tr>
      </thead>
      <tbody>
        @foreach($services as $s)
        <tr>
          <td><span class="badge {{ $s['module']==='OPD'?'bg-info':($s['module']==='IPD'?'bg-primary':'bg-success') }}">{{ $s['module'] }}</span></td>
          <td>{{ $s['ref'] }}</td>
          <td>{{ e($s['patient']) }}</td>
          <td>{{ e($s['title']) }}</td>
          <td>{{ e($s['dept']) }}</td>
          <td>{{ $s['date'] }}</td>
          <td><a href="{{ $s['lab_url'] }}" title="View Results"><i class="fas fa-vials"></i></a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div></div>
@endsection
