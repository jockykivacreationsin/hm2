{{--
  Patient Documents tab — AJAX list + upload modal + secure download.
  Usage: @include('patient-documents.index', ['patientId' => $patient->id, 'opdId' => $opd->id])
  Set any of opdId / ipdId / dcdId to scope uploads to the current visit; patientId is always required.
--}}
<div id="patient_docs_section">
    <div class="ipd-title-with-buttons row-bottom-border mb-2">
        <span class="ipd-sub-title">Patient Documents</span>
        @if(!isset($permissions) || $permissions->hasPermission('b_document_upload'))
        <button type="button" class="btn btn-primary btn-sm ms-auto" onclick="openUploadDocModal()">
            <i class="fa fa-upload"></i> Upload Document
        </button>
        @endif
    </div>

    <div id="patient_docs_list">
        <div class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>
    </div>
</div>

{{-- Upload Modal --}}
<div class="modal fade" id="uploadDocModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Document</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="upload_doc_form" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patientId ?? 0 }}">
                @if(!empty($opdId))<input type="hidden" name="opd_id" value="{{ $opdId }}">@endif
                @if(!empty($ipdId))<input type="hidden" name="ipd_id" value="{{ $ipdId }}">@endif
                @if(!empty($dcdId))<input type="hidden" name="dcd_id" value="{{ $dcdId }}">@endif
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group local-forms">
                                <label>Document Type</label>
                                <select name="document_type_id" id="doc_type_id" class="form-control select2">
                                    <option value="">-- Select --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group local-forms">
                                <label>Title</label>
                                <input type="text" name="title" class="form-control" placeholder="Document title (optional)">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group local-forms">
                                <label>Document Date <span class="req-star">*</span></label>
                                <input type="text" name="document_date" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group local-forms">
                                <label>File <span class="req-star">*</span></label>
                                <input type="file" name="document_file" id="doc_file_input" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx" required>
                                <small class="text-muted">PDF, images, Word, Excel — max 20 MB</small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group local-forms">
                                <label>Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="upload_doc_btn">Upload</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var _patientId = {{ (int) ($patientId ?? 0) }};
    var _listUrl   = "{{ route('patient-documents.index') }}";
    var _storeUrl  = "{{ route('patient-documents.store') }}";
    var _deleteUrl = "{{ route('patient-documents.destroy', ['id' => '__ID__']) }}";
    var _dlUrl     = "{{ route('patient-documents.download', ['id' => '__ID__']) }}";
    var _typesUrl  = "{{ route('patient-documents.types') }}";
    var _csrfToken = "{{ csrf_token() }}";
    var _canDelete = {{ (!isset($permissions) || $permissions->hasPermission('b_document_delete')) ? 'true' : 'false' }};

    function loadDocs() {
        $.get(_listUrl, {patient_id: _patientId}, function (res) {
            var $list = $('#patient_docs_list');
            if (!res.success || !res.data.length) {
                $list.html('<div class="rpt-empty">No documents uploaded yet.</div>');
                return;
            }
            var rows = res.data.map(function (d, i) {
                var del = _canDelete
                    ? '<a href="javascript:void(0)" class="small-action-btn text-danger" onclick="deleteDoc(' + d.id + ')" title="Delete"><i class="fas fa-times"></i></a>'
                    : '';
                return '<tr>'
                    + '<td>' + (i + 1) + '</td>'
                    + '<td>' + (d.document_date || '') + '</td>'
                    + '<td>' + (d.type_name || '-') + '</td>'
                    + '<td>' + (d.title || d.original_file_name || '') + '</td>'
                    + '<td>' + (d.file_size ? Math.round(d.file_size / 1024) + ' KB' : '') + '</td>'
                    + '<td>'
                    + '<a href="' + _dlUrl.replace('__ID__', d.id) + '" class="small-action-btn" target="_blank" title="Download"><i class="fas fa-download"></i></a> '
                    + del
                    + '</td>'
                    + '</tr>';
            }).join('');
            $list.html('<table class="table table-sm table-bordered table-text-sm"><thead><tr>'
                + '<th style="width:4%">#</th><th style="width:12%">Date</th><th style="width:18%">Type</th>'
                + '<th>Title / File</th><th style="width:10%">Size</th><th style="width:10%">Actions</th>'
                + '</tr></thead><tbody>' + rows + '</tbody></table>');
        });
    }

    window.openUploadDocModal = function () {
        $('#upload_doc_form')[0].reset();
        // Populate document types once
        if ($('#doc_type_id option').length <= 1) {
            $.get(_typesUrl, function (res) {
                if (res.success) {
                    res.data.forEach(function (t) {
                        $('#doc_type_id').append('<option value="' + t.id + '">' + t.type_name + '</option>');
                    });
                }
            });
        }
        $('#uploadDocModal').modal('show');
    };

    window.deleteDoc = function (id) {
        Swal.fire({
            title: 'Delete this document?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            $.ajax({
                url: _deleteUrl.replace('__ID__', id),
                type: 'DELETE',
                data: {_token: _csrfToken},
                success: function (res) {
                    if (res && res.success) {
                        loadDocs();
                        if (typeof appForm !== 'undefined') appForm.toast('success', 'Deleted', res.message);
                    } else {
                        Swal.fire('Error', (res && res.message) ? res.message : 'Could not delete document.', 'error');
                    }
                }
            });
        });
    };

    $('#upload_doc_form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#upload_doc_btn');
        $btn.prop('disabled', true).text('Uploading...');
        var fd = new FormData(this);
        $.ajax({
            url: _storeUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false).text('Upload');
                if (res && res.success) {
                    $('#uploadDocModal').modal('hide');
                    loadDocs();
                    if (typeof appForm !== 'undefined') appForm.toast('success', 'Uploaded', res.message);
                } else {
                    alert((res && res.message) ? res.message : 'Upload failed.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Upload');
                alert('Upload failed. Please try again.');
            }
        });
    });

    // Load on page ready
    $(document).ready(function () { loadDocs(); });
})();
</script>
