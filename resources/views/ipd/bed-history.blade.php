@extends('layouts.app')
@section('title', 'IPD Bed History')
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">@include('ipd.partials.menu')</div>
    <div class="card-body">
      <div class="row ipd-patient-prescription-row">
        <div class="ipd-title-with-buttons row-bottom-border">
          <div class="ipd-title">Bed History</div>
          <div class="ipd-buttons">
            <ul>
              
            </ul>
          </div>
        </div>	
        <div class="sm-table mt-10">
          <div class="table-responsive">  
            <table id="bed_history_tbl" class="table table-bordered table-sm table-text-sm">
              <thead>
                <tr>
                  <th>Bed</th>
                  <th>Floor</th>
                  <th>Ward</th>
                  <th>Type</th>
                  <th>From</th>
                  <th>To</th>
                  <th>Days</th>
                </tr>
              </thead>
              <tbody>
                @foreach($bedHistory as $bh)
                <tr>
                  <td>{{ $bh->bed_name }}</td>
                  <td>{{ $bh->floor }}</td>
                  <td>{{ $bh->ward_category }}</td>
                  <td>{{ $bh->bed_type }}</td>
                  <td>{{ $bh->from }}</td>
                  <td>{{ $bh->to }}</td>
                  <td>{{ $bh->days }}</td>
                </tr>
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

@push('scripts')
<script>
$(window).on("load", function() {
    if ($.fn.DataTable.isDataTable('#bed_history_tbl')) {
        $('#bed_history_tbl').DataTable().destroy();
    }
    $('#bed_history_tbl').DataTable({
        "pageLength": 100,
        "ordering": false,
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'excel',
                text: 'Excel',
                className: 'buttons-html5',
                exportOptions: {
                    columns: 'th:not(:first-child)'
                }
            },
            {
                extend: 'pdf',
                text: 'PDF',
                className: 'buttons-html5',
                exportOptions: {
                    columns: 'th:not(:first-child)'
                }
            },
            {
                extend: 'print',
                text: 'Print',
                className: 'buttons-html5',
                exportOptions: {
                    columns: 'th:not(:first-child)'
                }
            }
        ]
    });
});
</script>
@endpush
