@extends('layouts.app')
@section('title', 'Ward Categories')
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
					<div class="ipd-title">Ward Categories</div>
					@if($canAdd ?? false)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addWardCategoryModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Ward Category</th>
								</tr>
							</thead>
							<tbody>
								@foreach($wardCategories as $wc)
								<tr>
									<td class="tr-sm-action-buttons center">
										@if($canEdit ?? false)
										<a href="javascript:void(0)" onclick="editWardCategoryModal({{ $wc->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
										@endif
									</td>
									<td>{{ $wc->ward_category }}</td>
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

<div class="modal fade" id="addWardCategoryModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Ward Category</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_ward_category_form">
				@csrf

				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Ward Category Name <span class="req-star">*</span></label>
								<input type="text" name="ward_category" class="form-control" required>
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

<div class="modal fade" id="editWardCategoryModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Ward Category</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_ward_category_form">
				@csrf

				<input type="hidden" id="ward_category_id" name="ward_category_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Ward Category Name <span class="req-star">*</span></label>
								<input type="text" id="ward_category" name="ward_category" class="form-control" required>
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
var WARD_CATEGORY_URL_TEMPLATE = @json(route('beds.ward-categories.show', ['id' => '__ID__']));
var WARD_CATEGORY_UPDATE_URL_TEMPLATE = @json(route('beds.ward-categories.update', ['id' => '__ID__']));
function wardCategoryUrl(id) {
	return WARD_CATEGORY_URL_TEMPLATE.replace('__ID__', id);
}
function wardCategoryUpdateUrl(id) {
	return WARD_CATEGORY_UPDATE_URL_TEMPLATE.replace('__ID__', id);
}

function addWardCategoryModal() {
	$("#add_ward_category_form")[0].reset();
	$("#addWardCategoryModal").modal('show');
}

function editWardCategoryModal(id) {
	$("#editWardCategoryModal").modal('show');
	$.getJSON(wardCategoryUrl(id), function(data) {
		$("#edit_ward_category_form #ward_category_id").val(id);
		$("#edit_ward_category_form #ward_category").val(data.ward_category);
	});
}

$("#add_ward_category_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: '{{ route("beds.ward-categories.store") }}',
		type: "POST",
		data: new FormData(form),
		contentType: false,
		cache: false,
		processData: false,
		dataType: 'json',
		success: function(data) {
			if (data.success !== false) {
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				});
				location.reload();
			} else {
				Swal.fire({
					icon: "error",
					title: "Error",
					text: data.message || "An error occurred."
				});
			}
		},
		error: function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "An error occurred.";
			Swal.fire({
				icon: "error",
				title: "Error",
				text: msg
			});
		},
		complete: function() {
			$btn.prop('disabled', false);
		}
	});
});

$("#edit_ward_category_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var id = $("#edit_ward_category_form #ward_category_id").val();
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: wardCategoryUpdateUrl(id),
		type: "POST",
		data: new FormData(form),
		contentType: false,
		cache: false,
		processData: false,
		dataType: 'json',
		success: function(data) {
			if (data.success !== false) {
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				});
				location.reload();
			} else {
				Swal.fire({
					icon: "error",
					title: "Error",
					text: data.message || "An error occurred."
				});
			}
		},
		error: function(xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : "An error occurred.";
			Swal.fire({
				icon: "error",
				title: "Error",
				text: msg
			});
		},
		complete: function() {
			$btn.prop('disabled', false);
		}
	});
});
</script>
@endpush
