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
					@if($canNew ?? true)
						<div class="ipd-buttons">
							<ul>
								<li><a href="javascript:void(0)" onclick="addStaffMemberModal()"><i class="fa fa-plus"></i> New</a></li>
							</ul>
						</div>
					@endif
				</div>
				<div class="sm-table mt-10">
					<div class="table-responsive">
						<table id="staff_salary_setting_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr4btns"></th>
									<th>Name</th>
									<th>Designation</th>
									<th>Mobile Number</th>
									<th>Last Appraisal</th>
									<th>Days</th>
									<th>Current Salary</th>
								</tr>
							</thead>
							<tbody>
								@foreach($rows ?? [] as $row)
								<tr id="row_{{ $row->id }}">
									<td class="tr-sm-action-buttons center">@if($canDelete ?? true)<a href="javascript:void(0)" onclick="deleteStaffMember({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>@endif @if($canEdit ?? true)<a href="javascript:void(0)" onclick="editStaffMemberModal({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>@endif <a href="javascript:void(0)" onclick="updateStaffSalaryModal({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Update Salary"><i class="far fa-money-check-edit-alt"></i></a> <a href="javascript:void(0)" onclick="staffAppraisalDetailModal({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Appraisal Detail"><i class="far fa-search-dollar"></i></a></td>
									<td>{{ $row->staff_name }}</td>
									<td>{{ $row->designation }}</td>
									<td>{{ $row->mobile_number }}</td>
									<td id="last_appraisal_date_{{ $row->id }}">{{ $row->last_appraisal_date }}</td>
									<td>{{ $row->days_since_appraisal }}</td>
									<td id="current_salary_{{ $row->id }}">{{ $row->current_salary }}</td>
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

@include('staff.partials.member-modals', ['withSalary' => true])

<div class="modal fade" id="updateStaffSalaryModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Salary</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="update_staff_salary_form" action="{{ route('staff.salary.appraisal') }}" method="POST">
				<input type="hidden" id="salary_staff_member_id" name="staff_member_id" value="0">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<p>Current Salary: <span id="current_salary_print"></span></p>
								<input type="hidden" id="current_salary" name="current_salary" value="">
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Appraisal Date <span class="req-star">*</span></label>
								<input type="text" name="appraisal_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
							</div>
						</div>

						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>New Salary <span class="req-star">*</span></label>
								<input type="number" name="new_salary" class="form-control" min="0" required>
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

<div class="modal fade" id="staffAppraisalDetailModal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Appraisal Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-12 col-sm-12">
						<div id="staff_appraisal_detail">

						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button class="btn btn-secondary ml-10" type="button" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canView ?? true)
<script>
	var currentSalaryUrl = "{{ route('staff.current-salary', ['id' => '__ID__']) }}";
	var appraisalsUrl = "{{ route('staff.appraisals', ['id' => '__ID__']) }}";

	function updateStaffSalaryModal(id) {
		$("#updateStaffSalaryModal").modal('show');

		$.ajax({
			type: 'GET',
			url: currentSalaryUrl.replace('__ID__', id),
			dataType: 'json',
			success: function (data) {
				$("#update_staff_salary_form #salary_staff_member_id").val(id);
				$("#update_staff_salary_form #current_salary_print").text(data.current_salary);
				$("#update_staff_salary_form #current_salary").val(data.current_salary);
			}
		});
	}

	function staffAppraisalDetailModal(id) {
		$.ajax({
			type: 'GET',
			url: appraisalsUrl.replace('__ID__', id),
			dataType: 'json',
			success: function (data) {
				if (data.nor > 0) {
					$("#staffAppraisalDetailModal").modal('show');
					$("#staff_appraisal_detail").html(data.html);
				} else {
					Swal.fire({
						icon: "warning",
						title: "No Record!",
						text: "Appraisal Record Not Found",
						showConfirmButton: false,
						timer: 1500
					});
				}
			}
		});
	}

	$(document).ready(function () {
		$('#staff_salary_setting_tbl').DataTable({
			paging: false,
			ordering: false,
			info: false,
			searching: true,
			autoWidth: false,
			rowGroup: {
				dataSrc: 2
			},
			columnDefs: [
				{
					targets: 2,
					visible: false,
					searchable: false
				}
			]
		});

		$("#update_staff_salary_form").off('submit').on('submit', function (e) {
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
					form.reset();
					$("#updateStaffSalaryModal").modal('hide');
					Swal.fire({ icon: "success", title: "Saved!", text: "Saved Successfully.", showConfirmButton: false, timer: 1500 });
					$("#last_appraisal_date_" + data.id).html(data.last_appraisal_date);
					$("#current_salary_" + data.id).html(data.new_salary);
				},
				error: function (xhr) {
					var msg = (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors)
						? Object.values(xhr.responseJSON.errors).flat().join('\n') : 'Could not save the salary.';
					Swal.fire({ icon: "error", title: "Error", text: msg });
				}
			});
		});
	});
</script>
@endif
@endpush
