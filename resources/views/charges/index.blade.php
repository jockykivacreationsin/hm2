@extends('layouts.app')
@section('title', 'Charges')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Charges</div>
                    <div class="tbl-filter-box">
                        <div class="local-forms">
                            <label>Department</label>
                            <select class="form-control" id="filter_department">
                                <option value="">All</option>
                                @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="local-forms">
                            <label>Charge Category</label>
                            <select class="form-control" id="filter_charge_category" style="max-width: 200px;">
                                <option value="">All</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->charge_category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="tbl-top-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="addChargeModal()" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> New</a></li>
                            </ul>
                        </div>
                    </div>
                </div>  
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table id="charges_tbl" class="table table-bordered table-sm table-text-sm" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>Charge Code</th>
                                    <th>Charge Name</th>
                                    <th>Department</th>
                                    <th>Charge Category</th>
                                    <th style="width: 80px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Charge Modal -->
<div class="modal fade" id="addChargeModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Charge</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_charge_form">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Code</label>
                                <input type="text" name="charge_code" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Name <span class="req-star">*</span></label>
                                <input type="text" name="charge_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Department <span class="req-star">*</span></label>
                                <select class="form-control" id="a_charge_department_id" name="charge_department_id" required>
                                    <option value="">Select Department...</option>
                                    @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Category <span class="req-star">*</span></label>
                                <select class="form-control" id="a_charge_category_id" name="charge_category_id" required>
                                    <option value="">Select Category...</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" data-dept="{{ $cat->charge_department_id }}">{{ $cat->charge_category }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @foreach($patientCategories as $pc)
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Amount ({{ $pc->patient_category_name }}) <span class="req-star">*</span></label>
                                <input type="hidden" name="patient_category_id[]" value="{{ $pc->id }}">
                                <input type="number" step="any" name="amount[]" class="form-control" value="0" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Charge Modal -->
<div class="modal fade" id="editChargeModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Charge</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_charge_form">
                @csrf
                <input type="hidden" id="edit_charge_id" name="charge_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Code</label>
                                <input type="text" id="edit_charge_code" name="charge_code" class="form-control">
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Name <span class="req-star">*</span></label>
                                <input type="text" id="edit_charge_name" name="charge_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Department <span class="req-star">*</span></label>
                                <select class="form-control" id="edit_charge_department_id" name="charge_department_id" required>
                                    <option value="">Select Department...</option>
                                    @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Category <span class="req-star">*</span></label>
                                <select class="form-control" id="edit_charge_category_id" name="charge_category_id" required>
                                    <option value="">Select Category...</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" data-dept="{{ $cat->charge_department_id }}">{{ $cat->charge_category }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @foreach($patientCategories as $pc)
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Amount ({{ $pc->patient_category_name }}) <span class="req-star">*</span></label>
                                <input type="hidden" name="patient_category_id[]" value="{{ $pc->id }}">
                                <input type="number" step="any" id="edit_amount_{{ $pc->id }}" name="amount[]" class="form-control" value="0" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var chargesTable;

$(document).ready(function() {
    chargesTable = $('#charges_tbl').DataTable({
        dom: 'Blfrtip',
        pageLength: 100,
        processing: true,
        serverSide: true,
        autoWidth: false,
        ajax: {
            url: '{{ route("charges.data") }}',
            type: 'POST',
            data: function(d) {
                d._token = '{{ csrf_token() }}';
                d.filter_department = $('#filter_department').val();
                d.filter_charge_category = $('#filter_charge_category').val();
            }
        },
        columns: [
            { data: 'action', orderable: false, className: 'tr-sm-action-buttons center' },
            { data: 'charge_code' },
            { data: 'charge_name' },
            { data: 'department_name' },
            { data: 'charge_category' },
            { data: 'amount', className: 'text-end' }
        ],
        buttons: ['excel', 'pdf', 'print']
    });

    $('#filter_department').on('change', function() {
        var deptId = $(this).val();
        loadChargeCategoriesByDepartment(deptId, '#filter_charge_category');
        chargesTable.draw();
    });

    $('#filter_charge_category').on('change', function() {
        chargesTable.draw();
    });

    $('#a_charge_department_id').on('change', function() {
        loadChargeCategoriesByDepartment($(this).val(), '#a_charge_category_id');
    });

    $('#edit_charge_department_id').on('change', function() {
        loadChargeCategoriesByDepartment($(this).val(), '#edit_charge_category_id');
    });
});

function loadChargeCategoriesByDepartment(deptId, targetSelector) {
    var url = '{{ url("charges/categories-by-dept") }}' + (deptId ? '/' + deptId : '');
    $.get(url, function(data) {
        var opts = '<option value="">All</option>';
        if (targetSelector.indexOf('filter') === -1) {
            opts = '<option value="">Select Category...</option>';
        }
        $.each(data, function(i, cat) {
            opts += '<option value="' + cat.id + '">' + cat.charge_category + '</option>';
        });
        $(targetSelector).html(opts);
    });
}

function updateChargeCode(chargeId, chargeCode) {
    $.post('{{ route("charges.update-code") }}', {
        _token: '{{ csrf_token() }}',
        charge_id: chargeId,
        charge_code: chargeCode
    });
}

function addChargeModal() {
    $('#add_charge_form')[0].reset();
    $('#addChargeModal').modal('show');
}

function editChargeModal(id) {
    $.get('{{ url("charges") }}/' + id, function(data) {
        $('#editChargeModal').modal('show');
        $('#edit_charge_id').val(data.id);
        $('#edit_charge_code').val(data.charge_code);
        $('#edit_charge_name').val(data.charge_name);
        $('#edit_charge_department_id').val(data.charge_department_id);
        
        loadChargeCategoriesByDepartment(data.charge_department_id, '#edit_charge_category_id');
        setTimeout(function() {
            $('#edit_charge_category_id').val(data.charge_category_id);
        }, 200);

        if (data.amounts) {
            $.each(data.amounts, function(pcId, val) {
                $('#edit_amount_' + pcId).val(val);
            });
        }
    });
}

$('#add_charge_form').on('submit', function(e) {
    e.preventDefault();
    $.post('{{ route("charges.store") }}', $(this).serialize(), function(resp) {
        if (resp.success) {
            Swal.fire({
                icon: "success",
                title: "Success!",
                text: resp.message || "Saved Successfully",
                showConfirmButton: false,
                timer: 1500
            });
            $('#addChargeModal').modal('hide');
            chargesTable.draw();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'An error occurred.' });
        }
    }).fail(function(xhr) {
        var msg = xhr.responseJSON ? xhr.responseJSON.message : "An error occurred.";
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
    });
});

$('#edit_charge_form').on('submit', function(e) {
    e.preventDefault();
    var id = $('#edit_charge_id').val();
    $.post('{{ url("charges") }}/' + id, $(this).serialize(), function(resp) {
        if (resp.success) {
            Swal.fire({
                icon: "success",
                title: "Success!",
                text: resp.message || "Saved Successfully",
                showConfirmButton: false,
                timer: 1500
            });
            $('#editChargeModal').modal('hide');
            chargesTable.draw();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'An error occurred.' });
        }
    }).fail(function(xhr) {
        var msg = xhr.responseJSON ? xhr.responseJSON.message : "An error occurred.";
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
    });
});

function deleteCharge(id) {
    Swal.fire({
        title: 'Delete charge?',
        text: 'Are you sure you want to delete this charge?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then(function(r) {
        if (r.isConfirmed) {
            $.ajax({
                url: '{{ url("charges") }}/' + id,
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Deleted!",
                            text: resp.message || "Deleted successfully",
                            showConfirmButton: false,
                            timer: 1500
                        });
                        chargesTable.draw();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: resp.message || 'An error occurred.' });
                    }
                }
            });
        }
    });
}
</script>
@endpush
