@extends('layouts.app')
@section('title', 'User Roles')
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
                    <div class="ipd-title">User Roles</div>
                    @if($canAdd)
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addUserRoleModal()"><i class="fa fa-plus"></i> New</a></li>
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
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roles as $row)
                                @php
                                    $role_id = (int)$row->id;
                                    $role_name = $row->role;
                                @endphp
                                <tr>
                                    <td class="tr-sm-action-buttons center">
                                        @if($canPermissions)
                                            <a href="{{ route('roles.permissions', $role_id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Permissions"><i class="fas fa-user-unlock"></i></a>
                                        @endif
                                        @if($canEdit)
                                            <a href="javascript:void(0)" onclick="editUserRoleModal({{ $role_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                    </td>
                                    <td>{{ $role_name }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    @if ($roles instanceof \Illuminate\Pagination\LengthAwarePaginator && $roles->lastPage() > 1)
                    <div class="pagination-wrap mt-20">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                @if ($roles->currentPage() > 1)
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $roles->previousPageUrl() }}">Previous</a>
                                    </li>
                                @endif
                                
                                @php
                                    $page = $roles->currentPage();
                                    $total_pages = $roles->lastPage();
                                    $start_page = max(1, $page - 5);
                                    $end_page = min($total_pages, $page + 4);
                                @endphp
                                
                                @if ($start_page > 1)
                                    <li class="page-item"><a class="page-link" href="{{ $roles->url(1) }}">1</a></li>
                                    @if ($start_page > 2)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                @endif
                                
                                @for ($i = $start_page; $i <= $end_page; $i++)
                                    <li class="page-item {{ ($i == $page) ? 'active' : '' }}">
                                        <a class="page-link" href="{{ $roles->url($i) }}">{{ $i }}</a>
                                    </li>
                                @endfor
                                
                                @if ($end_page < $total_pages)
                                    @if ($end_page < $total_pages - 1)
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    @endif
                                    <li class="page-item"><a class="page-link" href="{{ $roles->url($total_pages) }}">{{ $total_pages }}</a></li>
                                @endif
                                
                                @if ($roles->hasMorePages())
                                    <li class="page-item">
                                        <a class="page-link" href="{{ $roles->nextPageUrl() }}">Next</a>
                                    </li>
                                @endif
                            </ul>
                            <div class="text-center mt-2">
                                <small>Page {{ $roles->currentPage() }} of {{ $roles->lastPage() }} (Total Records: {{ $roles->total() }})</small>
                            </div>
                        </nav>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserRoleModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_user_role_form" action="{{ route('roles.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Role <span class="req-star">*</span></label>
                                <input type="text" name="role" class="form-control" required>
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

<div class="modal fade" id="editUserRoleModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_user_role_form" action="" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="user_role_id" name="user_role_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Role <span class="req-star">*</span></label>
                                <input type="text" id="role" name="role" class="form-control" required>
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
function addUserRoleModal() {
    $('#add_user_role_form')[0].reset();
    $('#addUserRoleModal').modal('show');
}

var roleShowUrl = "{{ route('roles.show', ['id' => '__ID__']) }}";
var roleUpdateUrl = "{{ route('roles.update', ['id' => '__ID__']) }}";

function editUserRoleModal(id) {
    $('#editUserRoleModal').modal('show');
    $.ajax({
        type: 'GET',
        url: roleShowUrl.replace('__ID__', id),
        dataType: 'json',
        success: function(data) {
            $("#edit_user_role_form #user_role_id").val(id);
            $("#edit_user_role_form").attr('action', roleUpdateUrl.replace('__ID__', id));
            $("#edit_user_role_form #role").val(data.role || '');
        } 
    });	
}

$(document).ready(function() {
    $("#add_user_role_form").on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: this.action,
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "Saved Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error saving user role.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    $("#edit_user_role_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: this.action,
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "Saved Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function() {
                    location.reload();
                }, 1000);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error updating user role.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
});
</script>
@endif
@endpush
