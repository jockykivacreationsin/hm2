@extends('layouts.app')
@section('title', 'Sale Search')
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Sale Search</div>
    <div class="tbl-filter-box">
      <div class="fields-flex-row">
        <div class="local-forms"><label>From</label><input type="text" id="ss_from" class="form-control flatpickr-date" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="local-forms"><label>To</label><input type="text" id="ss_to" class="form-control flatpickr-date" value="{{ now()->format('Y-m-d') }}"></div>
      </div>
      <div class="local-forms">
        <label>Module</label>
        <select id="ss_module" class="form-control" style="width:110px;">
          <option value="">All</option>
          <option value="OPD">OPD</option><option value="IPD">IPD</option>
          <option value="DCD">DCD</option><option value="OTHERS">Others</option>
        </select>
      </div>
      <div class="tbl-top-buttons">
        <ul>
          <li><a href="javascript:void(0)" onclick="loadSearch()"><i class="fa fa-search"></i></a></li>
          <li><a href="javascript:void(0)" onclick="printSearch()"><i class="fa fa-print"></i> Print</a></li>
        </ul>
      </div>
    </div>
  </div>
  <div class="sm-table mt-10">
    <table id="ss_tbl" class="table table-bordered table-sm table-text-sm data-tbl">
      <thead>
        <tr><th class="no-sort tr1btn"></th><th>Date</th><th>Module</th><th>Patient</th><th>Title</th><th>Dept</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Mode</th></tr>
      </thead>
      <tbody id="ss_tbody"><tr><td colspan="10" class="text-center text-muted">Search above to load results.</td></tr></tbody>
    </table>
  </div>
</div></div>
@endsection
@push('scripts')
<script>
flatpickr('.flatpickr-date',{dateFormat:'Y-m-d'});
function loadSearch(){
    showLoading();
    $.get('{{ route('sale-bills.data') }}',{from_date:$('#ss_from').val(),to_date:$('#ss_to').val(),module:$('#ss_module').val(),length:500,start:0},function(r){
        Swal.close();
        var rows='';
        (r.data||[]).forEach(function(row){
            rows+='<tr>'
                +'<td><a href="{{ url('pdf/receipt') }}/'+row.id+'" target="_blank" title="Receipt"><i class="fas fa-receipt"></i></a></td>'
                +'<td>'+row.charge_date_time+'</td><td>'+row.module+'</td><td>'+row.patient_name+'</td>'
                +'<td>'+row.charge_title+'</td><td>'+row.department+'</td>'
                +'<td class="text-end">'+row.total+'</td><td class="text-end">'+row.paid_amount+'</td>'
                +'<td class="text-end">'+row.due+'</td><td>'+row.payment_mode+'</td></tr>';
        });
        $('#ss_tbody').html(rows||'<tr><td colspan="10" class="text-center text-muted">No records</td></tr>');
    }).fail(function(){Swal.close();showError('Server error');});
}
function printSearch(){
    var url='{{ route('pdf.sale-bills') }}?from_date='+$('#ss_from').val()+'&to_date='+$('#ss_to').val()+'&module='+$('#ss_module').val();
    window.open(url,'_blank');
}
</script>
@endpush
