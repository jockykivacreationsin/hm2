@extends('layouts.app')
@section('title', 'Beds')
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
					<div class="ipd-title">Beds</div>
					@if($canAdd ?? false)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addBedModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Bed Name</th>
									<th>Floor</th>
									<th>Area</th>
									<th>Ward</th>
									<th>Type</th>
								</tr>
							</thead>
							<tbody>
								@foreach($beds as $bed)
								<tr>
									<td class="tr-sm-action-buttons center">
										@if($canCopy ?? false)
										<a href="javascript:void(0)" onclick="copyBedModal({{ $bed->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Copy"><i class="far fa-copy"></i></a>
										@endif
										@if($canEdit ?? false)
										<a href="javascript:void(0)" onclick="editBedModal({{ $bed->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
										@endif
									</td>
									<td>{{ $bed->bed_name }}</td>
									<td>{{ $bed->floor?->floor }}</td>
									<td>{{ $bed->floorArea?->area }}</td>
									<td>{{ $bed->wardCategory?->ward_category }}</td>
									<td>{{ $bed->bedType?->bed_type }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>

					@if(method_exists($beds, 'hasPages') && $beds->hasPages())
					<div class="pagination-wrap mt-20">
						<nav aria-label="Page navigation">
							<ul class="pagination justify-content-center">
								@if($beds->onFirstPage())
								@else
									<li class="page-item">
										<a class="page-link" href="{{ $beds->previousPageUrl() }}">Previous</a>
									</li>
								@endif

								@php
									$currentPage = $beds->currentPage();
									$lastPage = $beds->lastPage();
									$start = max(1, $currentPage - 5);
									$end = min($lastPage, $currentPage + 4);
								@endphp

								@if($start > 1)
									<li class="page-item"><a class="page-link" href="{{ $beds->url(1) }}">1</a></li>
									@if($start > 2)
										<li class="page-item disabled"><span class="page-link">...</span></li>
									@endif
								@endif

								@for($i = $start; $i <= $end; $i++)
									<li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
										<a class="page-link" href="{{ $beds->url($i) }}">{{ $i }}</a>
									</li>
								@endfor

								@if($end < $lastPage)
									@if($end < $lastPage - 1)
										<li class="page-item disabled"><span class="page-link">...</span></li>
									@endif
									<li class="page-item"><a class="page-link" href="{{ $beds->url($lastPage) }}">{{ $lastPage }}</a></li>
								@endif

								@if($beds->hasMorePages())
									<li class="page-item">
										<a class="page-link" href="{{ $beds->nextPageUrl() }}">Next</a>
									</li>
								@endif
							</ul>
							<div class="text-center mt-2">
								<small>Page {{ $currentPage }} of {{ $lastPage }} (Total Records: {{ $beds->total() }})</small>
							</div>
						</nav>
					</div>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Add Bed Modal -->
<div class="modal fade" id="addBedModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Bed</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_bed_form">
				@csrf

				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Bed Name <span class="req-star">*</span></label>
								<input type="text" name="bed_name" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor <span class="req-star">*</span></label>
								<select class="form-control" id="a_floor_id" name="floor_id" required>
									<option></option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Area <span class="req-star">*</span></label>
								<select class="form-control" id="a_floor_area_id" name="floor_area_id" required>
									<option></option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Ward Category <span class="req-star">*</span></label>
								<select class="form-control" id="a_ward_category_id" name="ward_category_id" required>
									<option></option>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Bed Type <span class="req-star">*</span></label>
								<select class="form-control" id="a_bed_type_id" name="bed_type_id" required>
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

<!-- Edit Bed Modal -->
<div class="modal fade" id="editBedModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Bed</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_bed_form">
				@csrf

				<input type="hidden" id="bed_id" name="bed_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Bed Name <span class="req-star">*</span></label>
								<input type="text" id="bed_name" name="bed_name" class="form-control" required>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Floor <span class="req-star">*</span></label>
								<select class="form-control" id="e_floor_id" name="floor_id" required>
									
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Area <span class="req-star">*</span></label>
								<select class="form-control" id="e_floor_area_id" name="floor_area_id" required>
									
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Ward Category <span class="req-star">*</span></label>
								<select class="form-control" id="e_ward_category_id" name="ward_category_id" required>
									
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Bed Type <span class="req-star">*</span></label>
								<select class="form-control" id="e_bed_type_id" name="bed_type_id" required>
									
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

<!-- Copy Bed Modal -->
<div class="modal fade" id="copyBedModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Copy Bed</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="copy_bed_form">
				@csrf

				<input type="hidden" id="c_floor_id" name="floor_id" value="">
				<input type="hidden" id="c_floor_area_id" name="floor_area_id" value="">
				<input type="hidden" id="c_ward_category_id" name="ward_category_id" value="">
				<input type="hidden" id="c_bed_type_id" name="bed_type_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Bed Name <span class="req-star">*</span></label>
								<input type="text" name="bed_name" id="copy_bed_name" class="form-control" required>
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
var BED_URL_TEMPLATE = @json(route('beds.show', ['id' => '__ID__']));
var BED_UPDATE_URL_TEMPLATE = @json(route('beds.update', ['id' => '__ID__']));

function addBedModal() {
	$('#add_bed_form')[0].reset();
	$('#add_bed_form select').val(null).trigger('change');
	$('#addBedModal').modal('show');
}

function editBedModal(id) {
	$('#editBedModal').modal('show');
	$.getJSON(BED_URL_TEMPLATE.replace('__ID__', id), function(data) {
		$("#edit_bed_form #bed_id").val(id);
		$("#edit_bed_form #bed_name").val(data.bed_name);

		$("#edit_bed_form #e_floor_id").html('<option value="'+data.floor_id+'" selected>'+(data.floor || '')+'</option>').trigger('change');
		$("#edit_bed_form #e_floor_area_id").html('<option value="'+data.floor_area_id+'" selected>'+(data.area || '')+'</option>').trigger('change');
		$("#edit_bed_form #e_ward_category_id").html('<option value="'+data.ward_category_id+'" selected>'+(data.ward_category || '')+'</option>').trigger('change');
		$("#edit_bed_form #e_bed_type_id").html('<option value="'+data.bed_type_id+'" selected>'+(data.bed_type || '')+'</option>').trigger('change');
	});
}

function copyBedModal(id) {
	$('#copyBedModal').modal('show');
	$('#copy_bed_form')[0].reset();
	$.getJSON(BED_URL_TEMPLATE.replace('__ID__', id), function(data) {
		$("#copy_bed_form #c_floor_id").val(data.floor_id);
		$("#copy_bed_form #c_floor_area_id").val(data.floor_area_id);
		$("#copy_bed_form #c_ward_category_id").val(data.ward_category_id);
		$("#copy_bed_form #c_bed_type_id").val(data.bed_type_id);
		$("#copy_bed_form input[name='bed_name']").val(data.bed_name ? data.bed_name + ' (Copy)' : '');
	});
}

// Select2 sources for the Add / Update Bed modals.
function initBedSelect2(selector, placeholder, url, extraParams) {
	var $el = $(selector);
	if ($el.hasClass('select2-hidden-accessible')) {
		$el.select2('destroy');
	}
	$el.select2({
		placeholder: placeholder,
		ajax: {
			url: url,
			dataType: 'json',
			type: 'GET',
			delay: 250,
			data: function(params) {
				return $.extend({ q: params.term }, extraParams ? extraParams() : {});
			},
			processResults: function(data) {
				return { results: data };
			},
			cache: true
		}
	});
}

$(document).ready(function() {
	var floorsUrl = @json(route('beds.dropdowns.floors'));
	var areasUrl  = @json(route('beds.dropdowns.floor-areas'));
	var wardsUrl  = @json(route('beds.dropdowns.ward-categories'));
	var typesUrl  = @json(route('beds.dropdowns.bed-types'));

	initBedSelect2('#a_floor_id', 'Floor', floorsUrl);
	initBedSelect2('#e_floor_id', 'Floor', floorsUrl);
	initBedSelect2('#a_floor_area_id', 'Area', areasUrl, function() { return { floor_id: $('#a_floor_id').val() }; });
	initBedSelect2('#e_floor_area_id', 'Area', areasUrl, function() { return { floor_id: $('#e_floor_id').val() }; });
	initBedSelect2('#a_ward_category_id', 'Ward Category', wardsUrl);
	initBedSelect2('#e_ward_category_id', 'Ward Category', wardsUrl);
	initBedSelect2('#a_bed_type_id', 'Bed Type', typesUrl);
	initBedSelect2('#e_bed_type_id', 'Bed Type', typesUrl);

	$('#e_floor_id').on('select2:select', function () {
		$('#e_floor_area_id').val(null).empty().trigger('change');
	});
	$('#a_floor_id').on('select2:select', function () {
		$('#a_floor_area_id').val(null).empty().trigger('change');
	});
});

$("#add_bed_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: '{{ route("beds.store") }}',
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

$("#edit_bed_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var id = $("#edit_bed_form #bed_id").val();
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: BED_UPDATE_URL_TEMPLATE.replace('__ID__', id),
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

$("#copy_bed_form").on('submit', function(e) {
	e.preventDefault();
	var form = this;
	var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
	$.ajax({
		url: '{{ route("beds.copy") }}',
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
