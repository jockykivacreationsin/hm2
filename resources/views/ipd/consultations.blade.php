@extends('layouts.app')
@section('title', 'IPD Consultations')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Consultants</div>
                    @if($ps->hasPermission('b_ipd_consultation_add'))
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addIPDConsultationModal();"><i class="fa fa-plus"></i> Add Consultation</a></li>
                        </ul>
                    </div>
                    @endif
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>Date</th>
                                    <th>Consultant Doctor</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($consultations as $row)
                                    @php
                                        $consultation_id = $row->id;
                                        $consultation_date_time = $row->consultation_date_time ? \Carbon\Carbon::parse($row->consultation_date_time)->format('d-m-Y h:i A') : '';
                                        $staff_name = $row->doctor?->staff_name ?? '';
                                        $remarks = $row->remarks ?? '';
                                    @endphp
                                    <tr id="row_{{ $consultation_id }}">
                                        <td class="tr-sm-action-buttons center">
                                            @if($ps->hasPermission('b_ipd_consultation_edit'))
                                                <a href="javascript:void(0)" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit" onclick="editIPDConsultationModal({{ $consultation_id }})"><i class="fa fa-pencil"></i></a>
                                            @endif
                                            @if($ps->hasPermission('b_ipd_consultation_delete'))
                                                <a href="javascript:void(0)" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete" onclick="deleteIPDConsultation({{ $consultation_id }})"><i class="fa fa-times"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $consultation_date_time }}</td>
                                        <td>{{ $staff_name }}</td>
                                        <td>{{ $remarks }}</td>
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

<div class="modal fade" id="addIPDConsultationModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Consultation</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_consultation_form">
                @csrf
                <input type="hidden" name="ipd_id" value="{{ $ipd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="consultation_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control select2" id="a_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option value="">-- Select Doctor --</option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Remarks <span class="req-star">*</span></label>
                                        <textarea class="form-control local-forms" name="remarks"></textarea>
                                    </div>
                                </div>
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

<div class="modal fade" id="editIPDConsultationModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Consultation</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_consultation_form">
                @csrf
                <input type="hidden" id="ipd_consultation_id" name="ipd_consultation_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="consultation_date_time" name="consultation_date_time" class="form-control datetimepicker" value="" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control select2" id="e_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option value="">-- Select Doctor --</option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Remarks <span class="req-star">*</span></label>
                                        <textarea class="form-control local-forms" id="remarks" name="remarks"></textarea>
                                    </div>
                                </div>
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
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $(".datetimepicker").flatpickr({
        enableTime: true,
        dateFormat: "d-m-Y h:i K",
        time_24hr: false
    });

    // Form submit handlers (unbind legacy submit listeners from app.js to prevent double AJAX collision)
    $("#add_ipd_consultation_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('Wait...');

        $.ajax({
            url: "{{ route('ipd.consultations.store', $ipd->id) }}",
            type: "POST",
            data: new FormData(this),
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
                location.reload();
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(origText);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Failed to save. Please try again.";
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: err,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        });
    });

    $("#edit_ipd_consultation_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        var $btn = $(this).find('button[type="submit"]');
        var origText = $btn.html();
        $btn.prop('disabled', true).html('Wait...');
        var consultId = $("#edit_ipd_consultation_form #ipd_consultation_id").val();

        $.ajax({
            url: "{{ route('ipd.consultations.update', ['__ID__']) }}".replace('__ID__', consultId),
            type: "POST",
            data: new FormData(this),
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
                location.reload();
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(origText);
                var err = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Failed to save. Please try again.";
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: err,
                    showConfirmButton: false,
                    timer: 2000
                });
            }
        });
    });
});

function addIPDConsultationModal() {
    $("#addIPDConsultationModal").modal('show');
}

function editIPDConsultationModal(id) {
    $("#editIPDConsultationModal").modal('show');
    $.ajax({
        type: 'GET',
        url: "{{ route('ipd.consultations.get', ['__ID__']) }}".replace('__ID__', id),
        dataType: 'json',
        success: function(data) {
            $("#edit_ipd_consultation_form #ipd_consultation_id").val(id);
            if (data.doctor_id) {
                $("#edit_ipd_consultation_form #e_doctor_id").val(data.doctor_id).trigger('change');
            }
            $("#edit_ipd_consultation_form #consultation_date_time").val(data.consultation_date_time);
            $("#edit_ipd_consultation_form #remarks").val(data.remarks);
        }
    });
}

function deleteIPDConsultation(id) {
    Swal.fire({
        title: "Delete?",
        text: "Are you sure you want to delete!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: 'POST',
                url: "{{ route('ipd.consultations.destroy', ['__ID__']) }}".replace('__ID__', id),
                data: { _method: 'DELETE' },
                dataType: 'json',
                success: function(data) {
                    $("#row_" + id).remove();
                }
            });
        }
    });
}
</script>
@endpush
