@extends('layouts.app')

@section('title', 'Pharma Product Categories')

@section('content')
@if($canView)
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Pharma Product Categories</div>
					@if($canNew)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addPharmaProductCategoryModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Pharma Product Category</th>
								</tr>
							</thead>
							<tbody>
								@foreach($categories as $row)
									@php
										$category_id = (int) $row->id;
										$category_name = $row->category_name;
									@endphp
									<tr>
										<td class="tr-sm-action-buttons center">
											@if($canList)
											<a href="{{ route('master.pharmacy-category-products', $category_id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $category_name }}'s Products"><i class="fas fa-list"></i></a>
											@endif
											@if($canEdit)
											<a href="javascript:void(0)" onclick="editPharmaProductCategoryModal({{ $category_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
											@endif
										</td>
										<td>{{ $category_name }}</td>
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

<div class="modal fade" id="addPharmaProductCategoryModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_pharma_product_category_form" data-action="{{ route('master.pharmacy-categories.store') }}" data-method="POST">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Category Name <span class="req-star">*</span></label>
								<input type="text" name="category_name" class="form-control" required>
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

<div class="modal fade" id="editPharmaProductCategoryModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_pharma_product_category_form" data-action-template="{{ route('master.pharmacy-categories.update', ':id') }}" data-method="PUT" data-show-url-template="{{ route('master.pharmacy-categories.show', ':id') }}">
				<input type="hidden" id="pharma_product_category_id" name="pharma_product_category_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Category Name <span class="req-star">*</span></label>
								<input type="text" id="category_name" name="category_name" class="form-control" required>
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
@else
<div class="themebody-wrap">
	<div class="no-access">
		<i class="fa-duotone fa-shield-halved"></i>
		<p>Access Denied</p>
	</div>
</div>
@endif
@endsection

@push('scripts')
<script>
function editPharmaProductCategoryModal(id) {
    var $form = $('#edit_pharma_product_category_form');
    appForm.edit('#edit_pharma_product_category_form', '#editPharmaProductCategoryModal', appForm.fill($form.data('showUrlTemplate'), id), id, function (data) {
        $form.find('#pharma_product_category_id').val(id);
        $form.find('#category_name').val(data.category_name);
    });
}
</script>
@endpush
