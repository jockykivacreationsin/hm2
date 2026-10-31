@extends('layouts.app')
@section('title', 'Collection Report')
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
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Collection Report</div>
					<div class="tbl-filter-box">

						<div class="local-forms">
							<label>Report <span class="req-star">*</span></label>
							<select class="form-control" id="s_report_period">
								<option value="Daily">Daily</option>
								<option value="Monthly">Monthly</option>
								<option value="Periodic">Periodic</option>
							</select>
						</div>
						<div id="report-period-daily" class="local-forms">
							<label>Date <span class="req-star">*</span></label>
							<input type="text" id="s_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
						</div>
						<div id="report-period-monthly" class="local-forms" style="display:none;">
							<label>Month <span class="req-star">*</span></label>
							<select class="form-control" id="s_month" data-lookup-url="{{ route('pickers.months') }}">
								<option>Month</option>
							</select>
						</div>
						<div id="report-period-periodic" class="fields-flex-row" style="display:none;">
							<div class="local-forms">
								<label>From <span class="req-star">*</span></label>
								<input type="text" id="s_from_date" name="from_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
							<div class="local-forms">
								<label>To <span class="req-star">*</span></label>
								<input type="text" id="s_to_date" name="to_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
						</div>
						<div class="local-forms">
							<label>Payment Mode <span class="req-star">*</span></label>
							<select class="form-control" id="s_payment_mode">
								<option value="">All</option>
								<option value="Cash">Cash</option>
								<option value="UPI">UPI</option>
								<option value="Debit Card">Debit Card</option>
								<option value="Credit Card">Credit Card</option>
							</select>
						</div>
						<div class="local-forms" style="width: 200px;">
							<label>Company / Department <span class="req-star">*</span></label>
							<select class="form-control" id="s_division_id">
								<option value="0">All</option>
								@foreach($divisions as $div)
									<option value="{{ $div->id }}">{{ $div->division_name }}</option>
								@endforeach
							</select>
						</div>
						<div class="local-forms">
							<label>Group By <span class="req-star">*</span></label>
							<select class="form-control" id="s_group_by">
								<option value="payment_mode">Payment Mode</option>
								<option value="department_name">Department</option>
							</select>
						</div>
						<div class="tbl-top-buttons">
							<ul>
								<li><a id="search-collection-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
								<li><a href="javascript:void(0)" onclick="pdfTbl('collections_tbl', 'collection-report', 'Collection Report')"><i class="fa fa-print"></i></a></li>
							</ul>
						</div>

					</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive1">  
						<table id="collections_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th>Department</th>
									<th>Payment Method</th>
									<th>Title</th>
									<th>Date Time</th>
									<th>Amount</th>
								</tr>
							</thead>
							<tbody>
							</tbody>
							<tfoot>
								<tr><th></th><th></th><th class="aright" colspan="2"></th><th class="aright"></th></tr>
							</tfoot>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal fade" id="collectionsTBLModal">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Staff</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<div class="modal-body">
				<table id="collections_tbl_print" class="table table-bordered table-sm table-text-sm">
					<thead>
						<tr>
							<th>Payment Method</th>
							<th>Type</th>
							<th>Date Time</th>
							<th>Title</th>
							<th>Amount</th>
						</tr>
					</thead>
					<tbody>

					</tbody>
				</table>
			</div>
			<div class="modal-footer">
				<button class="btn btn-primary ml-10" type="submit">Save</button>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canAccess)
<script>
	function createPDF1() {
		var element = document.getElementById('collections_tbl_print');
		if (element && typeof html2pdf !== 'undefined') {
			html2pdf().from(element).set({
				margin: 0.2,
				html2canvas: { scale: 0.2 },
				jsPDF: {orientation: 'portrait', unit: 'in', format: 'a4', compressPDF: true}
			}).save('invoice.pdf');
		}
	}

	function pdfTbl(table_id, filename, title) {
		var thead = $('#' + table_id + ' thead').html() || '';
		var tbody = $('#' + table_id + ' tbody').html() || '';
		var tfoot = $('#' + table_id + ' tfoot').html() || '';

		if (!tbody || tbody.trim() === '' || tbody.indexOf('dataTables_empty') !== -1) {
			Swal.fire({
				icon: 'info',
				title: 'No Data',
				text: 'There is no collection data to print for the selected period.',
				timer: 2000,
				showConfirmButton: false
			});
			return;
		}

		if (typeof showLoading === 'function') {
			showLoading();
		}

		$.ajax({ 
			type: 'POST',
			url: '{{ route('reports.create-table-pdf') }}',                  
			data: {
				_token: '{{ csrf_token() }}',

				thead: thead,
				tbody: tbody,
				tfoot: tfoot,
				filename: filename,
				title: title
			},                       
			dataType: 'json',                   
			success: function(data) {
				if (typeof Swal !== 'undefined' && Swal.close) {
					Swal.close();
				}
				if (data && data.fn) {
					window.open(data.fn, '_blank');
				} else {
					Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to generate PDF.' });
				}
			},
			error: function() {
				if (typeof Swal !== 'undefined' && Swal.close) {
					Swal.close();
				}
				Swal.fire({
					icon: 'error',
					title: 'Error',
					text: 'Could not generate PDF. Please try again.'
				});
			}
		});
	}

	var dtcollections;
	function showCollectionsTBL() {
		if (typeof $.fn.DataTable === 'undefined' || $('#collections_tbl').length === 0) {
			return;
		}

		var groupDataSrc = $("#s_group_by").val() === 'payment_mode'
			? ['payment_mode', 'department_name']
			: ['department_name', 'payment_mode'];

		if ($.fn.DataTable.isDataTable('#collections_tbl')) {
			if (dtcollections) {
				dtcollections.destroy();
			} else {
				$('#collections_tbl').DataTable().destroy();
			}
		}

		dtcollections = $('#collections_tbl').DataTable({
			dom: 'Blfrtip',
			pageLength: 200,
			processing: true,
			serverSide: true,
			autoWidth: false,
			serverMethod: 'post',
			ajax: {
				url: '{{ route('reports.collections.data') }}',
				data: function(data){
					data._token = '{{ csrf_token() }}';

					data.report_period = $('#s_report_period').val();
					data.search_date = $('#s_date').val();
					data.search_month = $('#s_month').val();
					data.from_date = $('#s_from_date').val();
					data.to_date = $('#s_to_date').val();
					data.payment_mode = $('#s_payment_mode').val();
					data.division_id = $('#s_division_id').val();
				}
			},
			columns: [
				{ data: 'department_name' }, 
				{ data: 'payment_mode' }, 
				{ data: 'charge_title' },
				{ data: 'charge_date_time' }, 
				{ data: 'paid_amount' },
			],
			order: [
				[0, 'asc'],
				[1, 'asc']
			],
			rowGroup: {
				dataSrc: groupDataSrc,
				endRender: function ( rows, group ) {
					var gtotal = rows
						.data()
						.pluck('paid_amount')
						.reduce(function (a, b) {
							return a + (typeof b === 'string' ? b.replace(/[^\d.]/g, '') * 1 : Number(b) || 0);
						}, 0);

					return $('<tr/>').append( '<th class="aright" colspan="2">'+group+' Total </th><th class="aright">'+gtotal+'</th>' );
				}
			},
			columnDefs: [
				{ targets: [0, 1], visible: false },
				{ className: 'td-number', targets: [4] }
			],
			buttons: [
				{
					extend: 'excel',
					text: 'Excel',
					className: 'buttons-html5',
					exportOptions: {
						modifer: {
							page: 'all',
							search: 'none'
						},
						columns: 'th:not(:first-child)'
					}
				},
				{
					extend: 'pdf',
					text: 'PDF',
					className: 'buttons-html5',
					exportOptions: {
						columns: 'th:not(:first-child)'
					},
				},
				{
					extend: 'print',
					text: 'Print',
					className: 'buttons-html5',
					exportOptions: {
						columns: 'th:not(:first-child)'
					}
				}
			],
			footerCallback: function ( row, data, start, end, display ) {
				var api = this.api();
				var intVal = function ( i ) {
					return typeof i === 'string' ?
						i.replace(/[\$,]/g, '') * 1 :
						typeof i === 'number' ? i : 0;
				};

				var allTotal = api
					.column( 4 )
					.data()
					.reduce( function (a, b) {
						return intVal(a) + intVal(b);
					}, 0 );

				$( api.column( 2 ).footer() ).html('All Total');
				$( api.column( 4 ).footer() ).html(allTotal);
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

		$('#s_report_period').on('change', function (e) {
			var x = this.value;
			if(x == 'Daily')
			{
				$("#report-period-daily").show();
				$("#report-period-monthly").hide();
				$("#report-period-periodic").hide();
			}
			if(x == 'Monthly')
			{
				$("#report-period-daily").hide();
				$("#report-period-monthly").show();
				$("#report-period-periodic").hide();
			}
			if(x == 'Periodic')
			{
				$("#report-period-daily").hide();
				$("#report-period-monthly").hide();
				$("#report-period-periodic").css('display', 'flex');
			}
		});

		$('#search-collection-btn').off('click').on('click', function(e) {
			e.preventDefault();
			showCollectionsTBL();
		});

		showCollectionsTBL();
	});
</script>
@endif
@endpush
