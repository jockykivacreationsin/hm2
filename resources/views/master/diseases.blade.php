@extends('layouts.app')
@section('title', 'Diseases')
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
                    <div class="ipd-title">Diseases</div>
                    @if($canAdd)
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addDiseaseModal()"><i class="fa fa-plus"></i> New</a></li>
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
                                    <th>Disease</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($diseases as $d)
                                @php
                                    $diseaseTitle = $d->disease_name . "'s Patients";
                                @endphp
                                <tr>
                                    <td class="tr-sm-action-buttons center">
                                        @if($canDiseasePatients)
                                            <a href="{{ route('master.disease-patients', $d->id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $diseaseTitle }}"><i class="fas fa-list"></i></a>
                                        @endif
                                        @if($canEdit)
                                            <a href="javascript:void(0)" onclick="editDiseaseModal({{ $d->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                    </td>
                                    <td>{{ $d->disease_name }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if ($diseases->hasPages())
                    <div class="pagination-wrap mt-20">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                @if (!$diseases->onFirstPage())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $diseases->previousPageUrl() }}">Previous</a>
                                    </li>
                                @endif
                                
                                @php
                                    $startPage = max(1, $diseases->currentPage() - 5);
                                    $endPage = min($diseases->lastPage(), $diseases->currentPage() + 4);
                                @endphp

                                @if ($startPage > 1)
                                    <li class="page-item"><a class="page-link" href="{{ $diseases->url(1) }}">1</a></li>
                                    @if ($startPage > 2)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                @endif

                                @for ($i = $startPage; $i <= $endPage; $i++)
                                    <li class="page-item {{ $i == $diseases->currentPage() ? 'active' : '' }}">
                                        <a class="page-link" href="{{ $diseases->url($i) }}">{{ $i }}</a>
                                    </li>
                                @endfor

                                @if ($endPage < $diseases->lastPage())
                                    @if ($endPage < $diseases->lastPage() - 1)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                    <li class="page-item"><a class="page-link" href="{{ $diseases->url($diseases->lastPage()) }}">{{ $diseases->lastPage() }}</a></li>
                                @endif

                                @if ($diseases->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $diseases->nextPageUrl() }}">Next</a>
                                    </li>
                                @endif
                            </ul>
                            <div class="text-center mt-2">
                                <small>Page {{ $diseases->currentPage() }} of {{ $diseases->lastPage() }} (Total Records: {{ $diseases->total() }})</small>
                            </div>
                        </nav>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDiseaseModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Disease</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_disease_form">
                @csrf

                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Disease Name <span class="req-star">*</span></label>
                                <input type="text" name="disease_name" class="form-control" required>
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

<div class="modal fade" id="editDiseaseModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Disease</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_disease_form">
                @csrf
                @method('PUT')
                <input type="hidden" id="disease_id" name="disease_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Disease Name <span class="req-star">*</span></label>
                                <input type="text" id="disease_name" name="disease_name" class="form-control" required>
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
@if($canAccess)
<script>
function addDiseaseModal() {
    if ($('#add_disease_form').length > 0) {
        $('#add_disease_form')[0].reset();
    }
    $('#addDiseaseModal').modal('show');
}

function editDiseaseModal(id) {
    if ($('#edit_disease_form').length > 0) {
        $('#edit_disease_form')[0].reset();
    }
    $('#editDiseaseModal').modal('show');
    $.ajax({
        type: 'GET',
        url: '{{ route('master.diseases.get', ['id' => ':id']) }}'.replace(':id', id),
        dataType: 'json',
        success: function(data) {
            $('#edit_disease_form #disease_id').val(data.id || id);
            $('#edit_disease_form #disease_name').val(data.disease_name || '');
        }
    });
}

$(document).ready(function() {
    $('#add_disease_form').off('submit').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: '{{ route('master.diseases.store') }}',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                if (resp && (resp.success || resp.disease_id)) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Saved Successfully.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to save disease.' });
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error saving disease.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    $('#edit_disease_form').off('submit').on('submit', function(e) {
        e.preventDefault();
        var id = $('#edit_disease_form #disease_id').val();
        var formData = new FormData(this);
        $.ajax({
            url: '{{ route('master.diseases.update', ['id' => ':id']) }}'.replace(':id', id),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function(resp) {
                if (resp && (resp.success || resp.disease_id)) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'Saved Successfully.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'Failed to update disease.' });
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error updating disease.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
});
</script>
@endif
@endpush
