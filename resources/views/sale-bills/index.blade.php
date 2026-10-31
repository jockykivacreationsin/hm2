@extends('layouts.app')
@section('title', 'Sale Bill')
@section('content')

@if(!$canAccess)
<div class="themebody-wrap">
	<div class="no-access">
		<i class="fa-duotone fa-shield-halved"></i>
		<p>Access Denied</p>
	</div>
</div>
@else
<div class="themebody-wrap ipd-wrap">

	<div class="card-ipd">
		
		<div class="card-body">
			<div class="ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Sale Bill</div>
					<div class="tbl-filter-box">
						<div class="fields-flex-row">
							<div class="local-forms">
								<label>From <span class="req-star">*</span></label>
								<input type="text" id="s_from_date" name="from_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
							<div class="local-forms">
								<label>To <span class="req-star">*</span></label>
								<input type="text" id="s_to_date" name="to_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
						</div>
						<div class="tbl-top-buttons">
							<ul>
								<li><a id="search-sale-bill-ungrouped-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
								<li><a href="javascript:void(0)" onclick="viewSaleBills();"><i class="fa fa-print"></i> View / Print</a></li>
								<li style="display: flex;"><input type="text" id="invoice_number_two" name="invoice_number_two" class="in_value_field" value=""> <a href="javascript:void(0)" onclick="resetINTwo();"><i class="fas fa-repeat"></i> Reset</a></li>
							</ul>
						</div>
					</div>

				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table id="sale_bills_ungrouped_tbl" class="table table-bordered table-sm table-text-sm" data-url="{{ route('sale-bills.data') }}">
							<thead>
								<tr>
									<th>No</th>
									<th>Date Time</th>
									<th>Department</th>
									<th>Title</th>
									<th>Total</th>
									<th>Payment Mode</th>
								</tr>
							</thead>
							<tbody>
								
							</tbody>
							<tfoot>
								<tr>
									<th></th>
									<th></th>
									<th></th>
									<th></th>
									<th class="aright"></th>
									<th></th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canAccess)
<script>
	function viewSaleBills() {
		var from_date = $("#s_from_date").val();
		var to_date = $("#s_to_date").val();
		window.open('{{ route('pdf.sale-bills') }}?from_date=' + encodeURIComponent(from_date) + '&to_date=' + encodeURIComponent(to_date), '_blank');
	}

	function resetINTwo() {
		var start_date = $("#s_from_date").val();
		var end_date = $("#s_to_date").val();
		var invoice_number_two = $("#invoice_number_two").val();
		if(invoice_number_two == '') {
			Swal.fire({
				icon: "error",
				title: "Required!",
				text: "Please enter start value",
				showConfirmButton: false,
				timer: 1500
			});
			return;
		}
		$.ajax({ 
			type: 'POST',
			url: '{{ route('sale-bills.reset-invoice-number-two') }}',                  
			data: {
				_token: '{{ csrf_token() }}',

				start_date: start_date,
				end_date: end_date,
				invoice_number_two: invoice_number_two
			},                       
			dataType: 'json',                   
			success: function(data) {
				if (typeof dtsalebillsungrouped !== 'undefined' && dtsalebillsungrouped) {
					dtsalebillsungrouped.draw();
				}
			} 
		});
	}

	$(document).ready(function() {
		if ($(".datepicker").length > 0 && typeof flatpickr !== 'undefined') {
			$(".datepicker").flatpickr({
				enableTime: false,
				dateFormat: "d-m-Y"
			});
		}
	});
</script>
@endif
@endpush
