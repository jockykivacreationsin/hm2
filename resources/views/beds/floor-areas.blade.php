@extends('layouts.app')
@section('title', isset($currentFloor) ? 'Areas: ' . $currentFloor->floor : 'Floor Areas')
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
					<div class="ipd-title">{{ isset($currentFloor) ? 'Areas: ' . $currentFloor->floor : 'Floor Areas' }}</div>
					<div class="ipd-buttons">
						<ul>
							<li>
								@if($canAdd ?? false)
								<a href="javascript:void(0)" onclick="addFloorAreaModal()"><i class="fa fa-plus"></i> New</a>
								@endif
								<a href="{{ route('beds.floors') }}"><i class="fas fa-chevron-left"></i> Back</a>
							</li>
						</ul>
					</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="data-tbl table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr2btns"></th>
									@if(!isset($currentFloor))
									<th>Floor</th>
									@endif
									<th>Area</th>
								</tr>
							</thead>
							<tbody>
								@foreach($floorAreas as $area)
								<tr>
									<td class="tr-sm-action-buttons center">
										@if($canEdit ?? false)
										<a href="javascript:void(0)" onclick="editFloorAreaModal({{ $area->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
										@endif
									</td>
									@if(!isset($currentFloor))
									<td>{{ $area->floor?->floor }}</td>
									@endif
									<td>{{ $area->area }}</td>
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

<div class="modal fade" id="addFloorAreaModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Floor Area</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_floor_area_form">
				@csrf

				@if(isset($currentFloor))
				<input type="hidden" name="floor_id" value="{{ $currentFloor->id }}">
				@endif
				<div class="modal-body">
					<div class="row">
						@if(!isset($currentFloor))
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor <span class="req-star">*</span></label>
								<select class="form-control" name="floor_id" required>
									@foreach($floors as $f)
									<option value="{{ $f->id }}">{{ $f->floor }}</option>
									@endforeach
								</select>
							</div>
						</div>
						@endif
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Area Name <span class="req-star">*</span></label>
								<input type="text" name="area" class="form-control" required>
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

<div class="modal fade" id="editFloorAreaModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Floor Area</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_floor_area_form">
				@csrf

				<input type="hidden" id="floor_area_id" name="floor_area_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor <span class="req-star">*</span></label>
								<select class="form-control" id="e_floor_id" name="floor_id" required>
									@foreach($floors as $f)
									<option value="{{ $f->id }}">{{ $f->floor }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Area Name <span class="req-star">*</span></label>
								<input type="text" id="area" name="area" class="form-control" required>
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
var FLOOR_AREA_URL_TEMPLATE = @json(route('beds.floor-areas.show', ['id' => '__ID__']));
var FLOOR_AREA_UPDATE_URL_TEMPLATE = @json(route('beds.floor-areas.update', ['id' => '__ID__']));

function addFloorAreaModal() {
	$("#add_floor_area_form")[0].reset();
	$("#addFloorAreaModal").modal('show');
}

function editFloorAreaModal(id) {
	$("#editFloorAreaModal").modal('show');
	$.getJSON(FLOOR_AREA_URL_TEMPLATE.replace('__ID__', id), function(data) {
		$("#edit_floor_area_form #floor_area_id").val(id);
		$("#edit_floor_area_form #area").val(data.area);
		if ($("#edit_floor_area_form #e_floor_id option[value='" + data.floor_id + "']").length > 0) {
			$("#edit_floor_area_form #e_floor_id").val(data.floor_id);
		} else {
			$("#edit_floor_area_form #e_floor_id").html('<option value="'+data.floor_id+'" selected>'+(data.floor || '')+'</option>');
		}
	});
}

$("#add_floor_area_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: '{{ route("beds.floor-areas.store") }}',
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

$("#edit_floor_area_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var id = $("#edit_floor_area_form #floor_area_id").val();
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: FLOOR_AREA_UPDATE_URL_TEMPLATE.replace('__ID__', id),
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
