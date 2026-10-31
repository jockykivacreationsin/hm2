@extends('layouts.app')

@section('title', $category_name !== '' ? 'Category: ' . $category_name : 'Pharmacy Category Products')

@section('content')
@if($canView)
@if($category_id > 0)
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Category: {{ $category_name }}</div>
					@if($canNew)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addPPCProductModal()"><i class="fa fa-plus"></i> New</a><a href="{{ route('master.pharmacy-categories') }}"><i class="fas fa-chevron-left"></i> Back</a></li>
						</ul>
					</div>
					@else
					<div class="ipd-buttons">
						<ul>
							<li><a href="{{ route('master.pharmacy-categories') }}"><i class="fas fa-chevron-left"></i> Back</a></li>
						</ul>
					</div>
					@endif
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="data-tbl table table-bordered table-sm table-text-sm" data-delete-url-template="{{ route('master.pharmacy-category-products.destroy', ':id') }}">
							<thead>
								<tr>
									<th class="no-sort tr2btns"></th>
									<th>Area</th>
								</tr>
							</thead>
							<tbody>
								@foreach($products as $row)
									@php
										$id = (int) $row->id;
										$product_name = $row->product_name;
									@endphp
									<tr id="row_{{ $id }}">
										<td class="tr-sm-action-buttons center">
											@if($canDelete)
											<a href="javascript:void(0)" onclick="delPPCProduct({{ $id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
											@endif
											@if($canEdit)
											<a href="javascript:void(0)" onclick="editPPCProductModal({{ $id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
											@endif
										</td>
										<td>{{ $product_name }}</td>
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

<div class="modal fade" id="addPPCProductModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Product</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_pharma_product_form" data-action="{{ route('master.pharmacy-category-products.store', $category_id) }}" data-method="POST">
				<input type="hidden" name="category_id" value="{{ $category_id }}">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Product Name <span class="req-star">*</span></label>
								<input type="text" name="product_name" class="form-control" required>
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

<div class="modal fade" id="editPPCProductModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Product</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_pharma_product_form" data-action-template="{{ route('master.pharmacy-category-products.update', ':id') }}" data-method="PUT" data-show-url-template="{{ route('master.pharmacy-category-products.show', ':id') }}">
				<input type="hidden" id="pharma_product_id" name="pharma_product_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Category <span class="req-star">*</span></label>
								<select class="form-control" id="e_pharma_product_category_id" name="pharma_product_category_id" data-lookup-url="{{ route('master.pharmacy-categories.options') }}" required>
								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Product Name <span class="req-star">*</span></label>
								<input type="text" id="product_name" name="product_name" class="form-control" required>
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
function editPPCProductModal(id) {
    var $form = $('#edit_pharma_product_form');
    appForm.edit('#edit_pharma_product_form', '#editPPCProductModal', appForm.fill($form.data('showUrlTemplate'), id), id, function (data) {
        $form.find('#pharma_product_id').val(id);
        $form.find('#product_name').val(data.product_name);
        appForm.setOption('#e_pharma_product_category_id', data.category_id, data.category_name);
    });
}

function delPPCProduct(id) {
    appForm.destroy(appForm.fill($('table[data-delete-url-template]').data('deleteUrlTemplate'), id), id);
}
</script>
@endpush
