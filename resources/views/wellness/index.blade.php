@extends('layouts.app')
@section('title', strtoupper($module) . ' - Wellness Records')
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
            <div class="ipd-title-with-buttons row-bottom-border">
                <div class="ipd-title">Wellness Records</div>
                @if($canSave)
                <div class="ipd-buttons">
                    <ul>
                        <li><a href="javascript:void(0)" onclick="openAddWellnessModal()"><i class="fa fa-plus"></i> New</a></li>
                    </ul>
                </div>
                @endif
            </div>

            <div class="sm-table mt-10">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-text-sm" id="wellness_records_tbl">
                        <thead>
                            <tr>
                                <th class="no-sort tr2btns"></th>
                                <th>Date &amp; Time</th>
                                <th>Observations</th>
                                <th>Remarks</th>
                                <th>Added By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                            <tr id="wrow_{{ $record->id }}">
                                <td class="tr-sm-action-buttons center">
                                    @if($canSave)
                                    <a href="javascript:void(0)" onclick="editWellnessModal({{ $record->id }})" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-pencil"></i></a>
                                    @endif
                                    @if($canDelete)
                                    <a href="javascript:void(0)" onclick="deleteWellnessRecord({{ $record->id }})" data-bs-toggle="tooltip" title="Delete"><i class="fas fa-times"></i></a>
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($record->recorded_at)->format('d-m-Y H:i') }}</td>
                                <td>
                                    @foreach($record->observations as $obs)
                                        <span class="me-2 text-nowrap">
                                            <span class="text-muted small">{{ $obs->type?->display_name ?? 'Type#'.$obs->type_id }}:</span>
                                            <strong>{{ $obs->value_numeric ?? $obs->value_text ?? '—' }}</strong>
                                            <span class="text-muted small">{{ $obs->type?->unit_display ?? '' }}</span>
                                        </span>
                                    @endforeach
                                </td>
                                <td>{{ $record->remarks }}</td>
                                <td>{{ optional(\App\Models\StaffMember::find($record->user_id))->staff_name }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="center">No wellness records recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add / Edit modal --}}
<div class="modal fade" id="wellnessModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wellnessModalTitle">New Wellness Record</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body">
                <input type="hidden" id="wellness_record_id" value="">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <div class="form-group local-forms">
                            <label>Date &amp; Time <span class="req-star">*</span></label>
                            <input type="text" id="w_recorded_at" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group local-forms">
                            <label>Remarks</label>
                            <input type="text" id="w_remarks" class="form-control">
                        </div>
                    </div>
                </div>

                @foreach($observationTypes as $category => $types)
                <div class="mb-10">
                    <strong>{{ ucwords(str_replace('-', ' ', $category)) }}</strong>
                </div>
                <div class="row">
                    @foreach($types as $type)
                    <div class="col-md-2 col-sm-4">
                        <div class="form-group local-forms">
                            <label>{{ $type->display_name }}</label>
                            @if($type->value_type === 'text')
                            <input type="text"
                                   class="form-control wellness-obs-input"
                                   data-type-id="{{ $type->id }}"
                                   data-value-type="text"
                                   placeholder="{{ $type->unit_display ?? '' }}"
                                   id="wobs_{{ $type->id }}">
                            @else
                            <input type="number" step="any"
                                   class="form-control wellness-obs-input"
                                   data-type-id="{{ $type->id }}"
                                   data-value-type="numeric"
                                   placeholder="{{ $type->unit_display ?? '' }}"
                                   id="wobs_{{ $type->id }}">
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="button" onclick="saveWellnessRecord()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var _wStoreUrl  = "{{ route($module . '.wellness.store', ['id' => $visitId]) }}";
var _wShowUrl   = "{{ route('wellness.show', ['id' => '__ID__']) }}";
var _wUpdateUrl = "{{ route('wellness.update', ['id' => '__ID__']) }}";
var _wDeleteUrl = "{{ route('wellness.destroy', ['id' => '__ID__']) }}";
var _csrfToken  = $('meta[name="csrf-token"]').attr('content');

function openAddWellnessModal() {
    $('#wellnessModalTitle').text('New Wellness Record');
    $('#wellness_record_id').val('');
    $('#w_recorded_at').val("{{ date('d-m-Y h:i a') }}");
    $('#w_remarks').val('');
    $('.wellness-obs-input').val('');
    $('#wellnessModal').modal('show');
}

function editWellnessModal(id) {
    $.get(_wShowUrl.replace('__ID__', id), function(res) {
        if (!res.success) return;
        var r = res.record;
        $('#wellnessModalTitle').text('Edit Wellness Record');
        $('#wellness_record_id').val(r.id);
        if (r.recorded_at) {
            var d = new Date(r.recorded_at.replace(' ', 'T'));
            var dd   = String(d.getDate()).padStart(2,'0');
            var mm   = String(d.getMonth()+1).padStart(2,'0');
            var yyyy = d.getFullYear();
            var hh   = String(d.getHours()).padStart(2,'0');
            var min  = String(d.getMinutes()).padStart(2,'0');
            var ampm = d.getHours() >= 12 ? 'pm' : 'am';
            var h12  = d.getHours() % 12 || 12;
            $('#w_recorded_at').val(dd+'-'+mm+'-'+yyyy+' '+String(h12).padStart(2,'0')+':'+min+' '+ampm);
        } else {
            $('#w_recorded_at').val('');
        }
        $('#w_remarks').val(r.remarks || '');
        // Clear all first
        $('.wellness-obs-input').val('');
        // Populate observed values
        if (r.observations) {
            $.each(r.observations, function(i, obs) {
                var $inp = $('#wobs_' + obs.type_id);
                if ($inp.length) {
                    $inp.val(obs.value_numeric !== null ? obs.value_numeric : (obs.value_text || ''));
                }
            });
        }
        $('#wellnessModal').modal('show');
    });
}

function saveWellnessRecord() {
    var id = $('#wellness_record_id').val();
    var observations = [];
    $('.wellness-obs-input').each(function() {
        var val = $(this).val();
        if (val === '' || val === null) return;
        var typeId = $(this).data('type-id');
        var valueType = $(this).data('value-type');
        var obs = { type_id: typeId };
        if (valueType === 'text') { obs.value_text = val; }
        else { obs.value_numeric = val; }
        observations.push(obs);
    });

    if (observations.length === 0) {
        appForm.toast('error', 'Validation', 'Please enter at least one observation value before saving.');
        return;
    }

    var payload = {
        _token:      _csrfToken,
        recorded_at: $('#w_recorded_at').val(),
        remarks:     $('#w_remarks').val(),
        observations: observations
    };

    var url    = id ? _wUpdateUrl.replace('__ID__', id) : _wStoreUrl;
    var method = id ? 'PUT' : 'POST';

    $.ajax({
        url: url, type: method, data: JSON.stringify(payload),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': _csrfToken },
        success: function(res) {
            if (res && res.success) {
                appForm.toast('success', 'Saved!', res.message || 'Saved.');
                $('#wellnessModal').modal('hide');
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

function deleteWellnessRecord(id) {
    Swal.fire({
        title: 'Delete wellness record?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: _wDeleteUrl.replace('__ID__', id), type: 'DELETE',
            data: { _token: _csrfToken },
            success: function(res) {
                if (res && res.success) {
                    appForm.toast('success', 'Deleted', res.message || 'Deleted.');
                    $('#wrow_' + id).remove();
                } else {
                    appForm.toast('error', 'Error', (res && res.message) ? res.message : 'Could not delete.');
                }
            }
        });
    });
}
</script>
@endsection
