@extends('layouts.app')
@section('title', 'Medicines Issued — ' . e($patient->patient_name))
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Medicines Issued &mdash; {{ e($patient->patient_name) }}</div>
    <div class="ipd-buttons"><ul>
      <li><a href="{{ route('periodic-medicine.index') }}"><i class="fas fa-arrow-left"></i> Back</a></li>
    </ul></div>
  </div>
  <div class="sm-table mt-10">
    <table class="table table-bordered table-sm data-tbl">
      <thead>
        <tr><th>Issue Date</th><th>Next Date</th><th>Diseases</th><th>Delivery</th><th>Remarks</th><th>Medicines</th></tr>
      </thead>
      <tbody>
        @forelse($records as $r)
        @php
          $diseaseNames = collect(explode(',', $r->disease_ids))->filter()
              ->map(fn($id) => $diseases[(int)$id] ?? '')->implode(', ');
        @endphp
        <tr>
          <td>{{ $r->medicine_date_time }}</td>
          <td>{{ $r->next_medicine_date }}</td>
          <td>{{ $diseaseNames }}</td>
          <td>{{ $r->delivery }}</td>
          <td>{{ e($r->remarks) }}</td>
          <td>
            @foreach($r->medicines as $m)
              <span class="badge bg-light text-dark border">{{ e($m->medicine_name) }} {{ $m->dose_quantity }}</span>
            @endforeach
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div></div>
@endsection
