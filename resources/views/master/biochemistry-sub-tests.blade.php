@extends('layouts.app')
@section('title', 'Sub Tests')
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
					<div class="ipd-title">Sub Tests</div>
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addSubTestModal()"><i class="fa fa-plus"></i> New</a><a href="{{ route('master.biochemistry-tests') }}"><i class="fas fa-chevron-left"></i> Back</a></li>
						</ul>
					</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="data-tbl table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr2btns"></th>
									<th>Sub Test Name</th>
									<th>Reference Range</th>
									<th>Unit</th>
								</tr>
							</thead>
							<tbody>
								@foreach($subTests as $row)
								<tr id="row_{{ $row->id }}">
									<td class="tr-sm-action-buttons center">
										<a href="javascript:void(0)" onclick="deleteSubTest({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
										<a href="javascript:void(0)" onclick="editSubTestModal({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
									</td>
									<td>{{ $row->sub_test_name }}</td>
									<td>{{ $row->reference_range }}</td>
									<td>{{ $row->unit?->unit_name ?? '' }}</td>
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

<div class="modal fade" id="addSubTestModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Sub Test</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_sub_test_form">
				@csrf

				<input type="hidden" name="main_category_id" value="{{ intval($charge_id) }}">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Sub Test Name <span class="req-star">*</span></label>
								<input type="text" name="sub_test_name" class="form-control" required>
							</div>
							<div class="form-group local-forms">
								<label>Reference Range <span class="req-star">*</span></label>
								<input type="text" name="reference_range" class="form-control" required>
							</div>
							<div class="form-group local-forms">
								<label>Unit <span class="req-star">*</span></label>
								<select class="form-control" id="a_bunit_id" name="bunit_id" required style="width: 100%;">
									<option></option>
								</select>
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

<div class="modal fade" id="editSubTestModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_sub_test_form">
				@csrf
				@method('PUT')
				<input type="hidden" id="sub_test_id" name="sub_test_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Sub Test Name <span class="req-star">*</span></label>
								<input type="text" id="sub_test_name" name="sub_test_name" class="form-control" required>
							</div>
							<div class="form-group local-forms">
								<label>Reference Range <span class="req-star">*</span></label>
								<input type="text" id="reference_range" name="reference_range" class="form-control" required>
							</div>
							<div class="form-group local-forms">
								<label>Unit <span class="req-star">*</span></label>
								<select class="form-control" id="e_bunit_id" name="bunit_id" required style="width: 100%;">
									<option></option>
								</select>
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
function addSubTestModal() {
	$("#add_sub_test_form")[0].reset();
	if ($.fn.select2) {
		$('#a_bunit_id').val(null).trigger('change');
	}
	$("#addSubTestModal").modal('show');
}

function editSubTestModal(id) {
	$("#edit_sub_test_form")[0].reset();
	$("#editSubTestModal").modal('show');
	$.ajax({ 
		type: 'GET',
		url: "{{ route('master.biochemistry-sub-tests.show', ['id' => ':id']) }}".replace(':id', id),
		dataType: 'json',
		success: function(data) {
			$("#edit_sub_test_form #sub_test_id").val(id);
			$("#edit_sub_test_form #sub_test_name").val(data.sub_test_name || '');
			$("#edit_sub_test_form #reference_range").val(data.reference_range || '');
			$("#edit_sub_test_form #e_bunit_id").html('<option value="'+data.unit_id+'" selected>'+(data.unit_name || '')+'</option>');
			if ($.fn.select2) {
				$("#edit_sub_test_form #e_bunit_id").trigger('change');
			}
		} 
	});	
}

function deleteSubTest(id) {
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
				url: "{{ route('master.biochemistry-sub-tests.destroy', ['id' => ':id']) }}".replace(':id', id),
				headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
				dataType: 'json',
				success: function(data) {
					$("#row_"+id).remove();
					Swal.fire({
						icon: "success",
						title: "Success!",
						text: "Deleted Successfully",
						showConfirmButton: false,
						timer: 1500
					});
				} 
			});	
		}
	});
}

$(document).ready(function() {
	if ($.fn.select2) {
		$('#a_bunit_id').select2({
			dropdownParent: $('#addSubTestModal'),
			placeholder: "Unit",
			minimumInputLength: 0,
			ajax: {
				url: "{{ route('master.biochemistry-units.options') }}",
				dataType: "json",
				type: "GET",
				delay: 250,
				data: function(params){
					return {
						q: params.term || '',
					};
				},
				processResults: function(data){
					return {
						results: data
					};
				},
				cache: true
			}
		});

		$('#e_bunit_id').select2({
			dropdownParent: $('#editSubTestModal'),
			placeholder: "Unit",
			minimumInputLength: 0,
			ajax: {
				url: "{{ route('master.biochemistry-units.options') }}",
				dataType: "json",
				type: "GET",
				delay: 250,
				data: function(params){
					return {
						q: params.term || '',
					};
				},
				processResults: function(data){
					return {
						results: data
					};
				},
				cache: true
			}
		});
	}

	$("#add_sub_test_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		$.ajax({
			url: "{{ route('master.biochemistry-sub-tests.store') }}",
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#addSubTestModal").modal('hide');
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

	$("#edit_sub_test_form").on('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);
		var subId = $("#edit_sub_test_form #sub_test_id").val();
		$.ajax({
			url: "{{ route('master.biochemistry-sub-tests.update', ['id' => ':id']) }}".replace(':id', subId),
			type: "POST",
			data: formData,
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function(data) {
				$("#editSubTestModal").modal('hide');
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
