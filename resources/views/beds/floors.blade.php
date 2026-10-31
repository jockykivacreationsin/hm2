@extends('layouts.app')
@section('title', 'Floors')
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
					<div class="ipd-title">Floors</div>
					@if($canAdd ?? false)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addFloorModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Floor</th>
								</tr>
							</thead>
							<tbody>
								@foreach($floors as $floor)
								<tr>
									<td class="tr-sm-action-buttons center">
										@if($canFloorAreas ?? false)
										<a href="{{ route('beds.floor-areas') }}?id={{ $floor->id }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $floor->floor }}'s Areas"><i class="fas fa-list"></i></a>
										@endif
										@if($canEdit ?? false)
										<a href="javascript:void(0)" onclick="editFloorModal({{ $floor->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
										@endif
									</td>
									<td>{{ $floor->floor }}</td>
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

<div class="modal fade" id="addFloorModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Floor</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_floor_form">
				@csrf

				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor Name <span class="req-star">*</span></label>
								<input type="text" name="floor" class="form-control" required>
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

<div class="modal fade" id="editFloorModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Floor</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_floor_form">
				@csrf

				<input type="hidden" id="floor_id" name="floor_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor Name <span class="req-star">*</span></label>
								<input type="text" id="floor" name="floor" class="form-control" required>
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
var FLOOR_URL_TEMPLATE = @json(route('beds.floors.show', ['id' => '__ID__']));
var FLOOR_UPDATE_URL_TEMPLATE = @json(route('beds.floors.update', ['id' => '__ID__']));
function floorUrl(id) {
	return FLOOR_URL_TEMPLATE.replace('__ID__', id);
}
function floorUpdateUrl(id) {
	return FLOOR_UPDATE_URL_TEMPLATE.replace('__ID__', id);
}

function addFloorModal() {
	$("#add_floor_form")[0].reset();
	$("#addFloorModal").modal('show');
}

function editFloorModal(id) {
	$("#editFloorModal").modal('show');
	$.getJSON(floorUrl(id), function(data) {
		$("#edit_floor_form #floor_id").val(data.id || id);
		$("#edit_floor_form #floor").val(data.floor || '');
	});
}

$("#add_floor_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: '{{ route("beds.floors.store") }}',
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

$("#edit_floor_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var id = $("#edit_floor_form #floor_id").val();
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: floorUpdateUrl(id),
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
