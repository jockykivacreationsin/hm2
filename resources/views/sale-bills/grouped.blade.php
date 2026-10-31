@extends('layouts.app')
@section('title', 'Sale Bills (Grouped)')
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Sale Bills</div>
    <div class="tbl-filter-box">
      <div class="fields-flex-row">
        <div class="local-forms"><label>From</label><input type="text" id="sg_from" class="form-control flatpickr-date" value="{{ now()->format('Y-m-d') }}"></div>
        <div class="local-forms"><label>To</label><input type="text" id="sg_to" class="form-control flatpickr-date" value="{{ now()->format('Y-m-d') }}"></div>
      </div>
      <div class="tbl-top-buttons">
        <ul>
          <li><a href="javascript:void(0)" onclick="loadGrouped()"><i class="fa fa-search"></i></a></li>
          <li><a href="javascript:void(0)" onclick="printGrouped()"><i class="fa fa-print"></i> Print</a></li>
        </ul>
      </div>
    </div>
  </div>
  <div id="grouped-result" class="mt-10"><p class="text-muted">Select dates and search.</p></div>
</div></div>
@endsection
@push('scripts')
<script>
flatpickr('.flatpickr-date',{dateFormat:'Y-m-d'});
function loadGrouped(){
    showLoading();
    $.get('{{ route('sale-bills.data') }}',{from_date:$('#sg_from').val(),to_date:$('#sg_to').val(),length:1000,start:0},function(r){
        Swal.close();
        if(!r.data){showError('Error');return;}
        // Group by department
        var groups={};
        r.data.forEach(function(row){
            var dept=row.department||'General';
            if(!groups[dept]) groups[dept]={rows:[],total:0,paid:0};
            groups[dept].rows.push(row);
            groups[dept].total+=parseFloat(row.total||0);
            groups[dept].paid+=parseFloat(row.paid_amount||0);
        });
        var html='';
        Object.keys(groups).forEach(function(dept){
            var g=groups[dept];
            html+='<h6 class="mt-20 mb-5 fw-bold border-bottom pb-1">'+dept+'</h6>';
            html+='<table class="table table-bordered table-sm table-text-sm mb-2"><thead><tr>'
                +'<th>Date</th><th>Module</th><th>Title</th><th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Due</th><th>Mode</th>'
                +'</tr></thead><tbody>';
            g.rows.forEach(function(row){
                html+='<tr><td>'+row.charge_date_time+'</td><td>'+row.module+'</td><td>'+row.charge_title+'</td>'
                    +'<td class="text-end">'+row.total+'</td><td class="text-end">'+row.paid_amount+'</td>'
                    +'<td class="text-end">'+row.due+'</td><td>'+row.payment_mode+'</td></tr>';
            });
            html+='</tbody><tfoot><tr class="fw-bold bg-light"><td colspan="3">Subtotal</td>'
                +'<td class="text-end">'+g.total.toFixed(2)+'</td><td class="text-end">'+g.paid.toFixed(2)+'</td>'
                +'<td class="text-end">'+(g.total-g.paid).toFixed(2)+'</td><td></td></tr></tfoot></table>';
        });
        $('#grouped-result').html(html||'<p class="text-muted">No records found.</p>');
    }).fail(function(){Swal.close();showError('Server error');});
}
function printGrouped(){
    var url='{{ route('pdf.sale-bills') }}?from_date='+$('#sg_from').val()+'&to_date='+$('#sg_to').val();
    window.open(url,'_blank');
}
</script>
@endpush
