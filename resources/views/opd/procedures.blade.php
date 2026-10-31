@extends('layouts.app')
@section('title', 'OPD Procedures')

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('opd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-prescription-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Procedures</div>
                    <div class="ipd-buttons">
                        <ul>
                            
                        </ul>
                    </div>
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr1btn"></th>
                                    <th>Date Time</th>
                                    <th>Title</th>
                                    <th>Detail</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($services as $svc)
                                    @php
                                        $id = $svc->id;
                                        $service_date_time = $svc->service_date_time ? \Carbon\Carbon::parse($svc->service_date_time)->format('d-m-Y h:i A') : '';
                                        $service_title = $svc->service_title;
                                        $charges = $svc->details->map(fn($d) => $d->charge?->charge_name ?? '')->filter()->implode(', ');
                                        $status = $svc->status;
                                    @endphp
                                    <tr>
                                        <td class="tr-sm-action-buttons center">
                                            <a href="javascript:void(0)" onclick="openOPDProcedureNoteModal({{ $id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Detail"><i class="fas fa-list"></i></a>
                                        </td>
                                        <td>{{ $service_date_time }}</td>
                                        <td>{{ $service_title }}</td>
                                        <td>{{ $charges }}</td>
                                        <td>{{ $status }}</td>
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

<div class="modal fade" id="opdProcedureNoteModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Procedure Note</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body">
                <input type="hidden" id="opd_procedure_note_service_id">
                <textarea id="opd_procedure_note_editor" class="form-control summernote"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" type="button" onclick="saveOPDProcedureNote()"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    if (typeof $.fn.summernote !== 'undefined') {
        $('#opd_procedure_note_editor').summernote({
            height: 250,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }
});

function openOPDProcedureNoteModal(id) {
    $("#opd_procedure_note_service_id").val(id);
    if (typeof $.fn.summernote !== 'undefined') {
        $("#opd_procedure_note_editor").summernote('code', '');
    } else {
        $("#opd_procedure_note_editor").val('');
    }
    $.ajax({
        type: 'GET',
        url: "{{ route('opd.procedure-note.get', ['svcId' => '__ID__']) }}".replace('__ID__', id),
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                if (typeof $.fn.summernote !== 'undefined') {
                    $("#opd_procedure_note_editor").summernote('code', resp.note || '');
                } else {
                    $("#opd_procedure_note_editor").val(resp.note || '');
                }
            }
            $("#opdProcedureNoteModal").modal('show');
        },
        error: function() {
            $("#opdProcedureNoteModal").modal('show');
        }
    });
}

function saveOPDProcedureNote() {
    var opd_service_id = $("#opd_procedure_note_service_id").val();
    var note = typeof $.fn.summernote !== 'undefined'
        ? $("#opd_procedure_note_editor").summernote('code')
        : $("#opd_procedure_note_editor").val();

    $.ajax({
        type: 'POST',
        url: "{{ route('opd.procedure-note.store', ['svcId' => '__ID__']) }}".replace('__ID__', opd_service_id),
        data: {
            _token: '{{ csrf_token() }}',
            opd_service_id: opd_service_id,
            note: note
        },
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                $("#opdProcedureNoteModal").modal('hide');
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "Saved Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                Swal.fire({ icon: "error", title: "Error", text: resp.message || 'Failed to save' });
            }
        },
        error: function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save';
            Swal.fire({ icon: "error", title: "Error", text: msg });
        }
    });
}
</script>
@endpush
