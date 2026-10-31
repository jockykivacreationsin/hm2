@extends('layouts.app')
@section('title', strtoupper($module) . ' - Immunization')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @if($module === 'opd')
                @include('opd.partials.menu', ['opd' => $visit])
            @elseif($module === 'ipd')
                @include('ipd.partials.menu', ['ipd' => $visit])
            @else
                @include('dcd.partials.menu', ['dcd' => $visit])
            @endif
        </div>
        <div class="card-body">

            {{-- Upcoming recommendations banner (Gap #7: only 'due' recs that are not already
                 fulfilled by a record in the current page are shown; the query in the controller
                 already filters status='due', so fulfilled/cancelled recs are excluded) --}}
            @php
                $pendingRecs = $recommendations->filter(function ($rec) use ($records) {
                    // Hide if a completed record for this vaccine+dose already exists for this visit
                    return $records->where('vaccine_id', $rec->vaccine_id)
                                   ->where('dose_number', $rec->dose_number)
                                   ->where('status', 'completed')
                                   ->isEmpty();
                });
            @endphp
            @if($pendingRecs->isNotEmpty())
            <div class="alert alert-warning mt-10">
                <strong>Upcoming / Due Doses:</strong>
                @foreach($pendingRecs as $rec)
                    <span class="me-2">{{ $rec->vaccine?->vaccine_name }} — Dose {{ $rec->dose_number }}
                    (due {{ \Carbon\Carbon::parse($rec->due_date)->format('d-m-Y') }})</span>
                @endforeach
            </div>
            @endif

            <div class="ipd-title-with-buttons row-bottom-border">
                <div class="ipd-title">Immunization Records</div>
                @if($canAdd)
                <div class="ipd-buttons">
                    <ul>
                        <li><a href="javascript:void(0)" onclick="openAddImmunizationModal()"><i class="fa fa-plus"></i> New</a></li>
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
                                <th>Date &amp; Time</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Status</th>
                                <th>Route / Site</th>
                                <th>Lot No.</th>
                                <th>Performer</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $rec)
                            <tr id="imrow_{{ $rec->id }}">
                                <td class="tr-sm-action-buttons center">
                                    @if($canEdit)
                                    <a href="javascript:void(0)" onclick="editImmunizationModal({{ $rec->id }})" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-pencil"></i></a>
                                    @endif
                                    @if($canDelete)
                                    <a href="javascript:void(0)" onclick="deleteImmunizationRecord({{ $rec->id }})" data-bs-toggle="tooltip" title="Delete"><i class="fas fa-times"></i></a>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($rec->occurrence_date_time)->format('d-m-Y H:i') }}</td>
                                <td>{{ $rec->vaccine?->vaccine_name ?? '—' }}</td>
                                <td class="center">{{ $rec->dose_number }}</td>
                                <td>
                                    @if($rec->status === 'completed')
                                        <span class="badge bg-success">Completed</span>
                                    @elseif($rec->status === 'not-done')
                                        <span class="badge bg-warning text-dark">Not Done</span>
                                    @else
                                        <span class="badge bg-secondary">Entered in Error</span>
                                    @endif
                                </td>
                                <td>{{ $rec->route?->route_name ?? '' }}{{ $rec->site ? ' / '.$rec->site : '' }}</td>
                                <td>{{ $rec->lot_number }}</td>
                                <td>{{ $rec->performer?->staff_name ?? '' }}</td>
                                <td>{{ $rec->remarks }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="center">No immunization records.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit Modal --}}
<div class="modal fade" id="immunizationModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="immunizationModalTitle">New Immunization Record</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body">
                <input type="hidden" id="imm_record_id" value="">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group local-forms">
                            <label>Vaccine <span class="req-star">*</span></label>
                            <select id="imm_vaccine_id" class="form-control" required>
                                <option value=""></option>
                                @foreach($vaccines as $v)
                                <option value="{{ $v->id }}" data-doses="{{ $v->total_doses }}">{{ $v->vaccine_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group local-forms">
                            <label>Dose # <span class="req-star">*</span></label>
                            <input type="number" id="imm_dose_number" class="form-control" value="1" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group local-forms">
                            <label>Status <span class="req-star">*</span></label>
                            <select id="imm_status" class="form-control" required>
                                <option value="completed">Completed</option>
                                <option value="not-done">Not Done</option>
                                <option value="entered-in-error">Entered in Error</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group local-forms">
                            <label>Date &amp; Time <span class="req-star">*</span></label>
                            <input type="text" id="imm_occurrence_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Route</label>
                            <select id="imm_route_id" class="form-control">
                                <option value=""></option>
                                @foreach($routes as $r)
                                <option value="{{ $r->id }}">{{ $r->route_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Site</label>
                            <input type="text" id="imm_site" class="form-control" placeholder="e.g. Left upper arm">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Performer</label>
                            <select id="imm_performer_staff_id" class="form-control">
                                <option value=""></option>
                                @foreach($staff as $s)
                                <option value="{{ $s->id }}">{{ $s->staff_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Lot Number</label>
                            <input type="text" id="imm_lot_number" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Expiry Date</label>
                            <input type="text" id="imm_expiry_date" class="form-control datepicker">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Manufacturer</label>
                            <input type="text" id="imm_manufacturer" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Brand Name</label>
                            <input type="text" id="imm_brand_name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group local-forms">
                            <label>Remarks</label>
                            <input type="text" id="imm_remarks" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="button" onclick="saveImmunizationRecord()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var _immStoreUrl  = "{{ route($module . '.immunization.store', ['id' => $visitId]) }}";
var _immShowUrl   = "{{ route('immunization.show',  ['id' => '__ID__']) }}";
var _immUpdateUrl = "{{ route('immunization.update',['id' => '__ID__']) }}";
var _immDeleteUrl = "{{ route('immunization.destroy',['id' => '__ID__']) }}";
var _csrfToken    = $('meta[name="csrf-token"]').attr('content');

function openAddImmunizationModal() {
    $('#immunizationModalTitle').text('New Immunization Record');
    $('#imm_record_id').val('');
    $('#imm_vaccine_id').val('');
    $('#imm_dose_number').val(1);
    $('#imm_status').val('completed');
    // Compute "now" fresh each time the modal opens — not the server render time
    var now = new Date();
    var dd   = String(now.getDate()).padStart(2, '0');
    var mm   = String(now.getMonth() + 1).padStart(2, '0');
    var yyyy = now.getFullYear();
    var h    = now.getHours();
    var min  = String(now.getMinutes()).padStart(2, '0');
    var ampm = h >= 12 ? 'pm' : 'am';
    var h12  = String(h % 12 || 12).padStart(2, '0');
    $('#imm_occurrence_date_time').val(dd + '-' + mm + '-' + yyyy + ' ' + h12 + ':' + min + ' ' + ampm);
    $('#imm_route_id, #imm_performer_staff_id').val('');
    $('#imm_site, #imm_lot_number, #imm_manufacturer, #imm_brand_name, #imm_remarks, #imm_expiry_date').val('');
    $('#immunizationModal').modal('show');
}

function _fmtDateTime(str) {
    if (!str) return '';
    var d = new Date(str.replace(' ', 'T'));
    var dd   = String(d.getDate()).padStart(2, '0');
    var mm   = String(d.getMonth() + 1).padStart(2, '0');
    var yyyy = d.getFullYear();
    var h    = d.getHours();
    var min  = String(d.getMinutes()).padStart(2, '0');
    var ampm = h >= 12 ? 'pm' : 'am';
    var h12  = String(h % 12 || 12).padStart(2, '0');
    return dd + '-' + mm + '-' + yyyy + ' ' + h12 + ':' + min + ' ' + ampm;
}

function _fmtDate(str) {
    if (!str) return '';
    var p = str.split('-'); // Y-m-d
    return p[2] + '-' + p[1] + '-' + p[0];
}

function editImmunizationModal(id) {
    $.get(_immShowUrl.replace('__ID__', id), function(res) {
        if (!res.success) return;
        var r = res.record;
        $('#immunizationModalTitle').text('Edit Immunization Record');
        $('#imm_record_id').val(r.id);
        $('#imm_vaccine_id').val(r.vaccine_id);
        $('#imm_dose_number').val(r.dose_number);
        $('#imm_status').val(r.status);
        $('#imm_occurrence_date_time').val(_fmtDateTime(r.occurrence_date_time));
        $('#imm_route_id').val(r.route_id || '');
        $('#imm_site').val(r.site || '');
        $('#imm_performer_staff_id').val(r.performer_staff_id || '');
        $('#imm_lot_number').val(r.lot_number || '');
        $('#imm_expiry_date').val(_fmtDate(r.expiry_date));
        $('#imm_manufacturer').val(r.manufacturer || '');
        $('#imm_brand_name').val(r.brand_name || '');
        $('#imm_remarks').val(r.remarks || '');
        $('#immunizationModal').modal('show');
    });
}

function saveImmunizationRecord() {
    var id = $('#imm_record_id').val();

    // Client-side required field validation
    if (!$('#imm_vaccine_id').val()) {
        appForm.toast('error', 'Validation', 'Please select a vaccine.');
        return;
    }
    if (!$('#imm_dose_number').val() || parseInt($('#imm_dose_number').val()) < 1) {
        appForm.toast('error', 'Validation', 'Dose number must be 1 or greater.');
        return;
    }
    if (!$('#imm_occurrence_date_time').val()) {
        appForm.toast('error', 'Validation', 'Date & Time is required.');
        return;
    }

    var payload = {
        _token:                 _csrfToken,
        vaccine_id:             $('#imm_vaccine_id').val(),
        dose_number:            $('#imm_dose_number').val(),
        status:                 $('#imm_status').val(),
        occurrence_date_time:   $('#imm_occurrence_date_time').val(),
        route_id:               $('#imm_route_id').val() || null,
        site:                   $('#imm_site').val() || null,
        performer_staff_id:     $('#imm_performer_staff_id').val() || null,
        lot_number:             $('#imm_lot_number').val() || null,
        expiry_date:            $('#imm_expiry_date').val() || null,
        manufacturer:           $('#imm_manufacturer').val() || null,
        brand_name:             $('#imm_brand_name').val() || null,
        remarks:                $('#imm_remarks').val() || null,
    };

    var url    = id ? _immUpdateUrl.replace('__ID__', id) : _immStoreUrl;
    var method = id ? 'PUT' : 'POST';

    $.ajax({
        url: url, type: method,
        data: JSON.stringify(payload),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': _csrfToken },
        success: function(res) {
            if (res && res.success) {
                appForm.toast('success', 'Saved!', res.message || 'Saved.');
                $('#immunizationModal').modal('hide');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                appForm.toast('error', 'Error', (res && res.message) ? res.message : 'Could not save.');
            }
        },
        error: function(xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server error.';
            appForm.toast('error', 'Error', msg);
        }
    });
}

function deleteImmunizationRecord(id) {
    Swal.fire({
        title: 'Delete Record?',
        text: 'This will also update related dose recommendations. This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: _immDeleteUrl.replace('__ID__', id), type: 'DELETE',
            data: { _token: _csrfToken },
            success: function(res) {
                if (res && res.success) {
                    appForm.toast('success', 'Deleted', res.message || 'Deleted.');
                    $('#imrow_' + id).remove();
                } else {
                    appForm.toast('error', 'Error', (res && res.message) ? res.message : 'Could not delete.');
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server error.';
                appForm.toast('error', 'Error', msg);
            }
        });
    });
}
</script>
@endsection
