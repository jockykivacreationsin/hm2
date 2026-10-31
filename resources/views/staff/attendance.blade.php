@extends('layouts.app')
@section('title', 'Staff Attendance')
@section('content')
@if(!($canView ?? false))
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
					<div class="ipd-title">Staff Attendance</div>
					<div class="tbl-filter-box">
						<div class="local-forms">
							<label>Attendance Date <span class="req-star">*</span></label>
							<input type="text" id="attendance_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
						</div>
						<div class="local-forms">
							<label>All Status <span class="req-star">*</span></label>
							<select id="attendance_status" class="form-control">
								<option value=""></option>
								<option value="Present">Present</option>
								<option value="Holiday">Holiday</option>
							</select>
						</div>
					</div>
				</div>
				<div class="sm-table mt-10">
					<form id="staff_attendance_form" action="{{ route('staff.attendance.store') }}" method="POST">
						<input type="hidden" name="user_id" value="{{ \App\Support\CurrentUser::id() }}">
						<input type="hidden" id="staff_attendance_date" name="attendance_date" value="{{ date('d-m-Y') }}">
						<div class="table-responsive">

							<table id="staff_attendance_tbl" class="table table-bordered table-sm table-text-sm">
								<thead>
									<tr>
										<th>Name</th>
										<th>Designation</th>
										<th style="width: 100px">Status</th>
										<th style="width: 100px">In Time</th>
										<th style="width: 100px">Out Time</th>
										<th>Remarks</th>
									</tr>
								</thead>
								<tbody>
									@foreach($staff as $row)
									<tr id="row_{{ $row->id }}">
										<td><input type="hidden" name="staff_id[]" value="{{ $row->id }}">{{ $row->staff_name }}</td>
										<td>{{ $row->designation }}</td>
										<td>
											<select name="status[]" class="fullwidth">
												<option value="Absent">Absent</option>
												<option value="Present">Present</option>
												<option value="Off">Off</option>
												<option value="Halfday">Halfday</option>
												<option value="Leave">Leave</option>
												<option value="Holiday">Holiday</option>
											</select>
										</td>
										<td><input name="check_in_time[]" style="width: 80px" type="text" class="tmpicker"></td>
										<td><input name="check_out_time[]" style="width: 80px" type="text" class="tmpicker"></td>
										<td><input name="remarks[]" type="text" class="fullwidth" placeholder="Remarks ..."></td>
									</tr>
									@endforeach
								</tbody>
							</table>
						</div>
						<div class="center mt-10"><button type="submit" class="btn btn-primary">Save Attendance</button></div>

					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
<script>
	// Replaces the legacy global helper of the same name (loads the saved attendance of a date into the sheet).
	function loadAttendance(attendance_date) {
		$("#staff_attendance_date").val(attendance_date);
		$('#attendance_status').val('');
		$.ajax({
			url: "{{ route('staff.attendance.data') }}",
			type: 'GET',
			dataType: 'json',
			data: { attendance_date: attendance_date },
			success: function (response) {
				if (response.pending_date && response.pending_date !== '') {
					Swal.fire({
						icon: 'warning',
						title: 'Pending Attendance!',
						text: 'You have pending attendance checkout from ' + response.pending_date + '. Loading that date now to complete.',
						confirmButtonText: 'OK'
					}).then(() => {
						$("#attendance_date").val(response.pending_date);
						loadAttendance(response.pending_date);
					});
					return;
				}

				$('.tmpicker').val('');
				$('select[name="status[]"]').val('Absent');
				$('input[name="remarks[]"]').val('');

				if (response.attendance && response.attendance.length > 0) {
					response.attendance.forEach(function (item) {
						var row = $('#row_' + item.staff_id);
						row.find('select[name="status[]"]').val(item.status);
						row.find('input[name="check_in_time[]"]').val(item.check_in_time);
						row.find('input[name="check_out_time[]"]').val(item.check_out_time);
						row.find('input[name="remarks[]"]').val(item.remarks);
					});
				}
			}
		});
	}

	$(document).ready(function () {
		$("#staff_attendance_form").off('submit').on('submit', function (e) {
			e.preventDefault();
			var form = this;
			var valid = true;
			var errorMessage = "";

			$('#staff_attendance_form tbody tr').each(function (index) {
				var status = $(this).find('select[name="status[]"]').val();
				var checkIn = $(this).find('input[name="check_in_time[]"]').val();

				if (status === "Present" && checkIn === "") {
					valid = false;
					errorMessage = "Check-in time is required for Present staff (row " + (index + 1) + ")";
					return false;
				}
			});

			if (!valid) {
				Swal.fire({ icon: "warning", title: "Validation Error", text: errorMessage });
				return false;
			}

			$.ajax({
				url: form.action,
				type: "POST",
				data: new FormData(form),
				contentType: false,
				cache: false,
				processData: false,
				dataType: 'json',
				success: function () {
					Swal.fire({ icon: "success", title: "Saved!", text: "Saved Successfully.", showConfirmButton: false, timer: 1500 });
				},
				error: function (xhr) {
					var msg = (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors)
						? Object.values(xhr.responseJSON.errors).flat().join('\n') : 'Could not save attendance.';
					Swal.fire({ icon: "error", title: "Error", text: msg });
				}
			});
		});

		$('#staff_attendance_tbl').DataTable({
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

		var initialDate = $('#attendance_date').val();
		loadAttendance(initialDate);

		$('#attendance_date').on('change', function () {
			loadAttendance($(this).val());
		});

		if ($('#staff_attendance_tbl').length) {
			$('select[name="status[]"]').on('change', function () {
				const $row = $(this).closest('tr');
				$row.find('input[name="check_in_time[]"]').val('');
				$row.find('input[name="check_out_time[]"]').val('');
				$row.find('input[name="remarks[]"]').val('');
			});
		}

		$('#attendance_status').on('change', function () {
			const selectedStatus = $(this).val();
			const selectedDate = $('#attendance_date').val();

			if (selectedStatus !== '' && selectedDate !== '') {
				$.ajax({
					url: "{{ route('staff.attendance.exists') }}",
					type: 'GET',
					dataType: 'json',
					data: { attendance_date: selectedDate },
					success: function (response) {
						if (response.exists) {
							Swal.fire({
								icon: 'warning',
								title: 'Attendance Already Exists',
								text: 'Bulk status cannot be applied as attendance has already been submitted for this date.',
							});

							$('#attendance_status').val('');
						} else {
							Swal.fire({
								title: 'Are you sure?',
								text: `This will change status for all staff to "${selectedStatus}".`,
								icon: 'warning',
								showCancelButton: true,
								confirmButtonText: 'Yes, change all',
								cancelButtonText: 'No, cancel'
							}).then((result) => {
								if (result.isConfirmed) {
									$('select[name="status[]"]').each(function () {
										$(this).val(selectedStatus).trigger('change');

										const row = $(this).closest('tr');
										row.find('input[name="check_in_time[]"]').val('');
										row.find('input[name="check_out_time[]"]').val('');
										row.find('input[name="remarks[]"]').val('');
									});
								} else {
									$('#attendance_status').val('');
								}
							});
						}
					}
				});
			}
		});
	});
</script>
@endpush
