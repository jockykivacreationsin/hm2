@extends('layouts.app')
@section('title', 'HR Departments & Designations')
@section('content')
<div class="card-ipd"><div class="card-body">
    <div class="ipd-title-with-buttons row-bottom-border">
        <div class="ipd-title">HR Departments & Designations</div>
    </div>
    <div class="row mt-20">
        {{-- Departments --}}
        <div class="col-md-6">
            <div class="d-flex justify-content-between mb-10">
                <h6><strong>Departments ({{ $departments->count() }})</strong></h6>
                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#addDeptModal" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus"></i> Add
                </a>
            </div>
            <table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Department Name</th></tr></thead>
                <tbody>
                    @foreach($departments as $d)
                    <tr><td>{{ $d->id }}</td><td>{{ e($d->department) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Designations --}}
        <div class="col-md-6">
            <div class="d-flex justify-content-between mb-10">
                <h6><strong>Designations ({{ $designations->count() }})</strong></h6>
                <a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#addDesModal" class="btn btn-sm btn-primary">
                    <i class="fa fa-plus"></i> Add
                </a>
            </div>
            <table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Designation</th></tr></thead>
                <tbody>
                    @foreach($designations as $d)
                    <tr><td>{{ $d->id }}</td><td>{{ e($d->designation) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div></div>

<div class="modal fade" id="addDeptModal"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>Add Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="add-dept-form">@csrf
        <div class="modal-body">
            <div class="form-group local-forms"><label>Department Name *</label><input type="text" class="form-control" name="department" required></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div></div></div>

<div class="modal fade" id="addDesModal"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>Add Designation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="add-des-form">@csrf
        <div class="modal-body">
            <div class="form-group local-forms"><label>Designation *</label><input type="text" class="form-control" name="designation" required></div>
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
$('#add-dept-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('master.hr-departments.store') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success){ showSuccess(resp.message); location.reload(); } else showError(resp.message);
    });
});
$('#add-des-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('master.designations.store') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success){ showSuccess(resp.message); location.reload(); } else showError(resp.message);
    });
});
</script>
@endpush
