@extends('layouts.app')
@section('title', 'TPA Companies')
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
					<div class="ipd-title">TPA Companies</div>
					@if($canNew)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addTPACompanyModal()"><i class="fa fa-plus"></i> New</a></li>
						</ul>
					</div>
					@endif
				</div>
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="data-tbl table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr2btns"></th>
									<th>Name</th>
									<th>Contact Person</th>
									<th>Address</th>
									<th>Contact Number</th>
									<th>Email</th>
								</tr>
							</thead>
							<tbody>
								@foreach($tpaProviders as $row)
									@php
										$tpa_id = (int) $row->id;
										$tpa_name = $row->tpa_name;
										$contact_person = $row->contact_person;
										$address = $row->address;
										$contact_number = $row->contact_number;
										$email = $row->email;
									@endphp
									<tr id="row_{{ $tpa_id }}">
										<td class="tr-sm-action-buttons center">
											@if($canDelete)
												<a href="javascript:void(0)" onclick="deleteTPACompany({{ $tpa_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
											@endif
											@if($canEdit)
												<a href="javascript:void(0)" onclick="editTPACompanyModal({{ $tpa_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
											@endif
										</td>
										<td>{{ $tpa_name }}</td>
										<td>{{ $contact_person }}</td>
										<td>{{ $address }}</td>
										<td>{{ $contact_number }}</td>
										<td>{{ $email }}</td>
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

<div class="modal fade" id="addTPACompanyModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add TPA</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_tpa_company_form">
				@csrf

				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Name <span class="req-star">*</span></label>
								<input type="text" name="tpa_name" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Contact Person <span class="req-star">*</span></label>
								<input type="text" name="contact_person" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Address <span class="req-star">*</span></label>
								<input type="text" name="address" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Contact Number <span class="req-star">*</span></label>
								<input type="text" name="contact_number" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Email <span class="req-star">*</span></label>
								<input type="email" name="email" class="form-control" required>
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

<div class="modal fade" id="editTPACompanyModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_tpa_company_form">
				@csrf
				@method('PUT')
				<input type="hidden" id="tpa_company_id" name="tpa_company_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Name <span class="req-star">*</span></label>
								<input type="text" id="tpa_name" name="tpa_name" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Contact Person <span class="req-star">*</span></label>
								<input type="text" id="contact_person" name="contact_person" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Address <span class="req-star">*</span></label>
								<input type="text" id="address" name="address" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Contact Number <span class="req-star">*</span></label>
								<input type="text" id="contact_number" name="contact_number" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Email <span class="req-star">*</span></label>
								<input type="email" id="email" name="email" class="form-control" required>
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
@endif

@endsection

@push('scripts')
@if($canAccess)
<script>
function addTPACompanyModal() {
	$("#add_tpa_company_form")[0].reset();
	$("#addTPACompanyModal").modal('show');
}

function editTPACompanyModal(id) {
	$("#edit_tpa_company_form")[0].reset();
	$("#editTPACompanyModal").modal('show');
	$.ajax({
		type: 'GET',
		url: "{{ route('master.tpa.show', ['id' => ':id']) }}".replace(':id', id),
		dataType: 'json',                   
		success: function(data) {
			$("#edit_tpa_company_form #tpa_company_id").val(id);
			$("#edit_tpa_company_form #tpa_name").val(data.tpa_name || '');
			$("#edit_tpa_company_form #contact_person").val(data.contact_person || '');
			$("#edit_tpa_company_form #address").val(data.address || '');
			$("#edit_tpa_company_form #contact_number").val(data.contact_number || '');
			$("#edit_tpa_company_form #email").val(data.email || '');
		} 
	});	
}

function deleteTPACompany(id) {
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
				type: 'DELETE',
				url: "{{ route('master.tpa.destroy', ['id' => ':id']) }}".replace(':id', id),
				headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
				dataType: 'json',                   
				success: function(data) {
					var r = (data.result || '').trim();
					if(r === 'OK') {
						$("#row_"+id).remove();
						Swal.fire({
							icon: "success",
							title: "Success!",
							text: "Deleted Successfully",
							showConfirmButton: false,
							timer: 1500
						});
					} else {
						Swal.fire({
							icon: "error",
							title: "Error!",
							text: "In use unable to delete",
							showConfirmButton: false,
							timer: 1500
						});
					}
				} 
			});	
		}
	});
}

$(document).ready(function() {
	$("#add_tpa_company_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		$.ajax({
			url: "{{ route('master.tpa.store') }}",
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#addTPACompanyModal").modal('hide');
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				}).then(function() {
					location.reload();
				});
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Error saving";
				Swal.fire({
					icon: "error",
					title: "Error",
					text: msg
				});
			}
		});
	});

	$("#edit_tpa_company_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		var tpaId = $("#edit_tpa_company_form #tpa_company_id").val();
		$.ajax({
			url: "{{ route('master.tpa.update', ['id' => ':id']) }}".replace(':id', tpaId),
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#editTPACompanyModal").modal('hide');
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				}).then(function() {
					location.reload();
				});
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Error updating";
				Swal.fire({
					icon: "error",
					title: "Error",
					text: msg
				});
			}
		});
	});
});
</script>
@endif
@endpush
