@extends('layouts.app')
@section('title', 'Biochemistry Units')
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
					<div class="ipd-title">Biochemistry Units</div>
					@if($canAdd)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addBUnitModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Unit</th>
								</tr>
							</thead>
							<tbody>
								@foreach($units as $row)
								<tr id="row_{{ $row->id }}">
									<td class="tr-sm-action-buttons center">
										@if($canDelete)
										<a href="javascript:void(0)" onclick="deleteBUnit({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
										@endif
										@if($canEdit)
										<a href="javascript:void(0)" onclick="editBUnitModal({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
										@endif
									</td>
									<td>{{ $row->unit_name }}</td>
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

<div class="modal fade" id="addBUnitModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Unit</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_bunit_form">
				@csrf

				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Unit Name <span class="req-star">*</span></label>
								<input type="text" name="unit_name" class="form-control" required>
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

<div class="modal fade" id="editBUnitModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Unit</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_bunit_form">
				@csrf
				@method('PUT')
				<input type="hidden" id="unit_id" name="unit_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Unit Name <span class="req-star">*</span></label>
								<input type="text" id="unit_name" name="unit_name" class="form-control" required>
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
<script>
function addBUnitModal() {
	$("#add_bunit_form")[0].reset();
	$("#addBUnitModal").modal('show');
}

function editBUnitModal(id) {
	$("#edit_bunit_form")[0].reset();
	$("#editBUnitModal").modal('show');
	$.ajax({
		type: 'GET',
		url: "{{ route('master.biochemistry-units.show', ['id' => ':id']) }}".replace(':id', id),
		dataType: 'json',                   
		success: function(data) {
			$("#edit_bunit_form #unit_id").val(id);
			$("#edit_bunit_form #unit_name").val(data.unit_name || '');
		} 
	});	
}

function deleteBUnit(id) {
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
				url: "{{ route('master.biochemistry-units.destroy', ['id' => ':id']) }}".replace(':id', id),
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
	$("#add_bunit_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		$.ajax({
			url: "{{ route('master.biochemistry-units.store') }}",
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#addBUnitModal").modal('hide');
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

	$("#edit_bunit_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		var unitId = $("#edit_bunit_form #unit_id").val();
		$.ajax({
			url: "{{ route('master.biochemistry-units.update', ['id' => ':id']) }}".replace(':id', unitId),
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#editBUnitModal").modal('hide');
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
@endpush
