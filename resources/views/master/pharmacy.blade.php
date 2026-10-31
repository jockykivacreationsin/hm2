@extends('layouts.app')
@section('title', 'Pharmacy Products')
@section('content')
<div class="card-ipd"><div class="card-body">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Pharmacy Products</div>
    <div class="tbl-top-buttons"><ul>
      <li><a href="{{ route('master.pharmacy-categories') }}">Categories</a></li>
      <li><a href="javascript:void(0)" onclick="$('#addProductModal').modal('show')"><i class="fa fa-plus"></i> New</a></li>
    </ul></div>
  </div>
  <div class="sm-table mt-10">
    <table id="prod_tbl" class="table table-bordered table-sm">
      <thead><tr><th>#</th><th>Product Name</th><th>Category</th><th>Active</th></tr></thead>
      <tbody>
        @foreach($products as $prod)
        <tr>
          <td>{{ $prod->id }}</td>
          <td>{{ e($prod->product_name) }}</td>
          <td>{{ e($prod->category?->category_name) }}</td>
          <td>{{ $prod->is_active ? 'Yes' : 'No' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div></div>

<div class="modal fade" id="addProductModal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5>Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <form id="add-prod-form">@csrf
    <div class="modal-body">
      <div class="form-group local-forms"><label>Product Name *</label><input type="text" class="form-control" name="product_name" required></div>
      <div class="form-group local-forms"><label>Category *</label>
        <select class="form-control" name="category_id" required>
          <option value="">Select...</option>
          @foreach($categories as $cat)<option value="{{ $cat->id }}">{{ e($cat->category_name) }}</option>@endforeach
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div></div></div>
@endsection
@push('scripts')
<script>
$(document).ready(function(){ $('#prod_tbl').DataTable({ pageLength:25 }); });
$('#add-prod-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('master.pharmacy.store') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success){ showSuccess(resp.message); location.reload(); } else showError(resp.message);
    });
});
</script>
@endpush
