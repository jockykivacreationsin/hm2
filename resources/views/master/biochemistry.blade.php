@extends('layouts.app')
@section('title', 'Biochemistry Tests')
@section('content')
<div class="card-ipd"><div class="card-body">
    <div class="ipd-title-with-buttons row-bottom-border">
        <div class="ipd-title">Biochemistry Tests</div>
        <div class="tbl-top-buttons"><ul>
            <li><a href="javascript:void(0)" onclick="addUnitModal()"><i class="fa fa-plus"></i> Add Unit</a></li>
            <li><a href="javascript:void(0)" onclick="addTestModal()"><i class="fa fa-plus"></i> Add Test</a></li>
        </ul></div>
    </div>

    <div class="row mt-10">
        {{-- Units --}}
        <div class="col-md-4">
            <h6 class="mb-10"><strong>Units ({{ $units->count() }})</strong></h6>
            <table class="table table-sm table-bordered">
                <thead><tr><th></th><th>Unit Name</th><th>UCUM Code</th></tr></thead>
                <tbody>
                    @foreach($units as $u)
                    <tr id="unit_{{ $u->id }}">
                        <td><a href="javascript:void(0)" onclick="deleteUnit({{ $u->id }})" class="text-danger" title="Delete"><i class="fa fa-times"></i></a></td>
                        <td>{{ e($u->unit_name) }}</td>
                        <td><code>{{ $u->ucum_code }}</code></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Sub Tests --}}
        <div class="col-md-8">
            <h6 class="mb-10"><strong>Test Parameters ({{ $subTests->count() }})</strong></h6>
            <table id="subtests_tbl" class="table table-sm table-bordered">
                <thead><tr><th class="no-sort"></th><th>Test Name</th><th>Reference Range</th><th>Unit</th></tr></thead>
                <tbody>
                    @foreach($subTests as $sub)
                    <tr id="sub_{{ $sub->id }}">
                        <td>
                            <a href="javascript:void(0)" onclick="deleteSub({{ $sub->id }})" class="text-danger"><i class="fa fa-times"></i></a>
                        </td>
                        <td>{{ e($sub->sub_test_name) }}</td>
                        <td>{{ e($sub->reference_range) }}</td>
                        <td>{{ e($sub->unit?->unit_name) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div></div>

{{-- Add Test Modal --}}
<div class="modal fade" id="addTestModal"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>Add Biochemistry Test</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="add-test-form">@csrf
        <div class="modal-body">
            <div class="form-group local-forms"><label>Test Name *</label><input type="text" class="form-control" name="sub_test_name" required></div>
            <div class="form-group local-forms"><label>Reference Range</label><input type="text" class="form-control" name="reference_range" placeholder="e.g. 3.5-5.0"></div>
            <div class="form-group local-forms"><label>Unit</label>
                <select class="form-control" name="unit_id">
                    <option value="">Select unit...</option>
                    @foreach($units as $u)<option value="{{ $u->id }}">{{ e($u->unit_name) }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div></div></div>

{{-- Add Unit Modal --}}
<div class="modal fade" id="addUnitModal"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5>Add Unit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form id="add-unit-form">@csrf
        <div class="modal-body">
            <div class="form-group local-forms"><label>Unit Name *</label><input type="text" class="form-control" name="unit_name" required></div>
            <div class="form-group local-forms"><label>UCUM Code</label><input type="text" class="form-control" name="ucum_code" placeholder="e.g. mg/dL"></div>
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
$(document).ready(function(){ $('#subtests_tbl').DataTable({ pageLength:25 }); });
function addTestModal(){ $('#add-test-form')[0].reset(); $('#addTestModal').modal('show'); }
function addUnitModal(){ $('#add-unit-form')[0].reset(); $('#addUnitModal').modal('show'); }
$('#add-test-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('master.biochemistry.subtest.store') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success){ showSuccess(resp.message); location.reload(); } else showError(resp.message);
    });
});
$('#add-unit-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('master.biochemistry.unit.store') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success){ showSuccess(resp.message); location.reload(); } else showError(resp.message);
    });
});
function deleteUnit(id){
    Swal.fire({title:'Delete unit?',icon:'warning',showCancelButton:true}).then(r=>{
        if(r.isConfirmed) $.ajax({ url:'{{ url('master/biochemistry/unit') }}/'+id, type:'DELETE', data:{_token:'{{ csrf_token() }}'}, success:function(r){
            if(r.success){ showSuccess(r.message); $('#unit_'+id).remove(); } else showError(r.message);
        }});
    });
}
function deleteSub(id){
    Swal.fire({title:'Delete test?',icon:'warning',showCancelButton:true}).then(r=>{
        if(r.isConfirmed) $.ajax({ url:'{{ url('master/biochemistry/subtest') }}/'+id, type:'DELETE', data:{_token:'{{ csrf_token() }}'}, success:function(r){
            if(r.success){ showSuccess(r.message); $('#sub_'+id).remove(); } else showError(r.message);
        }});
    });
}
</script>
@endpush
