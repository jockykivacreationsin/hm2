@extends('layouts.app')
@section('title', 'Staff Salary')
@section('content')
@if(!($canView ?? true))
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
					<div class="ipd-title">Staff Salary</div>
					<div class="tbl-filter-box">
						<div class="local-forms">
							<label>Salary Month <span class="req-star">*</span></label>
							<select class="form-control" id="salary_month">
								@for($m = 1; $m <= 12; $m++)
									<option value="{{ $m }}" @selected($m == date('n'))>{{ date('F', mktime(0, 0, 0, $m, 10)) }}</option>
								@endfor
							</select>
						</div>

						<div class="local-forms">
							<label>Year <span class="req-star">*</span></label>
							<select class="form-control" id="salary_year">
								@for($y = date('Y') - 3; $y <= date('Y') + 1; $y++)
									<option value="{{ $y }}" @selected($y == date('Y'))>{{ $y }}</option>
								@endfor
							</select>
						</div>
					</div>

				</div>
				<div class="sm-table mt-10">

					<div class="table-responsive">

						<table id="staff_salary_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th>Name</th>
									<th>Designation</th>
									<th style="width: 100px">Current Salary</th>
									<th style="width: 100px">Working/Off</th>
									<th style="width: 100px">Absent/Leave</th>
									<th style="width: 100px">Total Time</th>
									<th>Salary</th>
									<th>Paid</th>
								</tr>
							</thead>
							<tbody>

							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="staffSalaryPaymentModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Salary Payment</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="staff_salary_payment_form" action="{{ route('staff.salary.payment') }}" method="POST">
				<input type="hidden" id="staff_id" name="staff_id" value="0">
				<input type="hidden" id="amount" name="amount" value="0">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Salary Date Time <span class="req-star">*</span></label>
								<input type="text" name="salary_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i A') }}">
							</div>
						</div>

						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Payment <span class="req-star">*</span></label>
								<select name="payment_mode" class="form-control" required>
									<option value=""></option>
									<option value="Cash">Cash</option>
									<option value="Netbanking">Netbanking</option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Remarks </label>
								<input type="text" name="remarks" class="form-control">
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="salaryPaymentDetailModal">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Payment Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-12 col-sm-12">
						<div id="salary_payment_detail">

						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">

			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canView ?? true)
<script>
	function staffSalaryPaymentModal(id, amount) {
		$("#staffSalaryPaymentModal").modal('show');
		$("#staff_salary_payment_form #staff_id").val(id);
		$("#staff_salary_payment_form #amount").val(amount);
		$('select[name="payment_mode"]').focus();
	}

	function viewSalaryPaymentDetail(id) {
		$("#salaryPaymentDetailModal").modal('show');
		$("#salary_payment_detail").html('');
		$.ajax({
			type: 'GET',
			url: "{{ route('staff.salary.payment.detail', ['id' => '__ID__']) }}".replace('__ID__', id),
			dataType: 'json',
			success: function (data) {
				$("#salary_payment_detail").html(data.html);
			}
		});
	}

	function loadSalaryData() {
		const month = $('#salary_month').val();
		const year = $('#salary_year').val();

		$.get("{{ route('staff.salary.list') }}", { month: month, year: year }, function (html) {
			if ($.fn.DataTable.isDataTable('#staff_salary_tbl')) {
				$('#staff_salary_tbl').DataTable().destroy();
			}

			$('#staff_salary_tbl tbody').html(html);

			$('#staff_salary_tbl').DataTable({
				paging: false,
				ordering: false,
				info: false,
				searching: true,
				autoWidth: false,
				rowGroup: {
					dataSrc: 1
				},
				columnDefs: [
					{
						targets: 1,
						visible: false,
						searchable: false
					}
				]
			});
		});
	}

	$(function () {
		loadSalaryData();
		$('#salary_month, #salary_year').change(loadSalaryData);

		$("#staff_salary_payment_form").off('submit').on('submit', function (e) {
			e.preventDefault();
			var form = this;
			$.ajax({
				url: form.action,
				type: "POST",
				data: new FormData(form),
				contentType: false,
				cache: false,
				processData: false,
				dataType: 'json',
				success: function (data) {
					$("#staffSalaryPaymentModal").modal('hide');
					form.reset();
					Swal.fire({
						icon: "success",
						title: "Saved!",
						text: "Saved Successfully.",
						showConfirmButton: false,
						timer: 1500
					});
					var l = "<a href='javascript:void(0)' onclick='viewSalaryPaymentDetail(" + data.staff_payment_id + ");'>View</a>";
					$("#isp_" + data.staff_id).html(l);
				},
				error: function (xhr) {
					var msg = (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors)
						? Object.values(xhr.responseJSON.errors).flat().join('\n') : 'Could not save the payment.';
					Swal.fire({ icon: "error", title: "Error", text: msg });
				}
			});
		});
	});
</script>
@endif
@endpush
