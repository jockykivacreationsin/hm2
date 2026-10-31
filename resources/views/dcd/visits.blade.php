@extends('layouts.app')
@section('title', 'DCD Visits')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('dcd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-prescription-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Visits</div>
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" id="abdm_hiu_pull_btn" onclick="abdmHiuButtonClick(this, {{ $patientId }})" data-hiu-state="{{ $abdmInitialState ?? 'checking' }}" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top" title="Pull this patient's health records from ABDM (any facility, via their ABHA)" data-bs-original-title="Pull this patient's health records from ABDM (any facility, via their ABHA)">{{ $abdmInitialBtnText ?? 'Checking ABDM...' }}</a></li>
                        </ul>
                    </div>
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr1btn"></th>
                                    <th>Type</th>
                                    <th>Date Time</th>
                                    <th>Doctor</th>
                                    <th>Symptoms</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($visits as $v)
                                <tr>
                                    <td class="tr-sm-action-buttons center">
                                        <a href="javascript:void(0)" onclick="visitDetailModal({{ $v['id'] }}, '{{ $v['type'] }}')" data-bs-toggle="tooltip" data-bs-placement="top" title="Detail"><i class="fas fa-list"></i></a>
                                    </td>
                                    <td>{!! $v['type_display'] !!}</td>
                                    <td>{{ $v['date_time'] }}</td>
                                    <td>{{ $v['doctor'] }}</td>
                                    <td>{{ $v['symptoms'] }}</td>
                                    <td>{{ $v['remarks'] }}</td>
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

<div class="modal fade" id="visitDetailModal">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body">
                <div id="visit-detail">
                    
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@include('abdm.partials.hiu-view-offcanvas')

<!-- Request Records From Abha modal -->
<div class="modal fade" id="hiuRequestConsentModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consent Request Form</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="hiu_consent_request_form">
                <div class="modal-body">
                    <div id="hiu_consent_request_form_error" class="text-danger mb-10" style="display:none;"></div>

                    <div class="form-group local-forms">
                        <label>Purpose of request</label>
                        <select id="hiu_consent_purpose_code" class="form-control">
                            <option value="CAREMGT">Care Management</option>
                            <option value="BTG">Break the Glass</option>
                            <option value="PUBHLTH">Public Health</option>
                            <option value="HPAYMT">Healthcare Payment</option>
                            <option value="DSRCH">Disease Specific Healthcare Research</option>
                            <option value="PATRQT">Self Requested</option>
                        </select>
                    </div>

                    <label>Health Info duration</label>
                    <div class="row">
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms">
                                <select id="hiu_consent_duration_preset" class="form-control" onchange="applyHiuConsentDurationPreset()">
                                    <option value="all">All</option>
                                    <option value="7">Last 7 Days</option>
                                    <option value="30">Last 1 Month</option>
                                    <option value="90">Last 3 Months</option>
                                    <option value="180" selected>Last 6 Months</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms">
                                <label class="fs-12">From</label>
                                <input type="text" id="hiu_consent_from_date" class="form-control datepicker" disabled>
                            </div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <div class="form-group local-forms">
                                <label class="fs-12">To</label>
                                <input type="text" id="hiu_consent_to_date" class="form-control datepicker" disabled>
                            </div>
                        </div>
                    </div>

                    <label>Health Info type</label>
                    <div class="row mb-15">
                        <div class="col-12 col-sm-6">
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_OPConsultation" class="hiu-consent-hi-type" value="OPConsultation" checked><span>OP Consultation</span></label></div>
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_DischargeSummary" class="hiu-consent-hi-type" value="DischargeSummary"><span>Discharge Summary</span></label></div>
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_ImmunizationRecord" class="hiu-consent-hi-type" value="ImmunizationRecord" checked><span>Immunization Record</span></label></div>
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_WellnessRecord" class="hiu-consent-hi-type" value="WellnessRecord"><span>Wellness Record</span></label></div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_DiagnosticReport" class="hiu-consent-hi-type" value="DiagnosticReport"><span>Diagnostic Report</span></label></div>
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_Prescription" class="hiu-consent-hi-type" value="Prescription" checked><span>Prescription</span></label></div>
                            <div class="mb-10"><label class="checkbox-label"><input type="checkbox" id="hiu_hi_HealthDocumentRecord" class="hiu-consent-hi-type" value="HealthDocumentRecord"><span>Health Document Record</span></label></div>
                        </div>
                    </div>

                    <div class="form-group local-forms">
                        <label>Consent Expiry</label>
                        <input type="text" id="hiu_consent_expiry" class="form-control datetimepicker">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="hiu_submit_consent_request_btn" class="btn btn-primary" onclick="submitHiuConsentRequest()">Request Consent</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function visitDetailModal(id, type) {
    $("#visitDetailModal").modal('show');
    $("#visit-detail").html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted">Loading visit details...</div></div>');
    $.ajax({ 
        type: 'POST',
        url: "{{ route('dcd.visits.detail') }}",
        data: {
            _token: "{{ csrf_token() }}",
            id: id, 
            type: type
        },                       
        dataType: 'html',                   
        success: function(html) {
            $("#visit-detail").html(html);
        },
        error: function(xhr) {
            $("#visit-detail").html('<div class="alert alert-danger m-3">Failed to load visit details.</div>');
        }
    });
}

function applyHiuConsentDurationPreset() {
    if (typeof applyDurationPreset === 'function') {
        applyDurationPreset('#hiu_consent_duration_preset', '#hiu_consent_from_date', '#hiu_consent_to_date');
        return;
    }
    var preset = $("#hiu_consent_duration_preset").val();
    var $from = $("#hiu_consent_from_date");
    var $to = $("#hiu_consent_to_date");
    var today = new Date();

    if (preset === 'custom') {
        $from.prop('disabled', false);
        $to.prop('disabled', false);
        return;
    }

    $from.prop('disabled', true);
    $to.prop('disabled', true);

    var formatDate = function(d) {
        var day = ("0" + d.getDate()).slice(-2);
        var month = ("0" + (d.getMonth() + 1)).slice(-2);
        return day + "-" + month + "-" + d.getFullYear();
    };

    $to.val(formatDate(today));

    if (preset === 'all') {
        $from.val('01-01-2000');
    } else {
        var days = parseInt(preset, 10) || 180;
        var pastDate = new Date();
        pastDate.setDate(today.getDate() - days);
        $from.val(formatDate(pastDate));
    }
}

function submitHiuConsentRequest() {
    var types = [];
    $(".hiu-consent-hi-type:checked").each(function() {
        types.push($(this).val());
    });

    if (types.length === 0) {
        $("#hiu_consent_request_form_error").text("Please select at least one health information type.").show();
        return;
    }

    $("#hiu_consent_request_form_error").hide();
    var $btn = $("#hiu_submit_consent_request_btn");
    var origText = $btn.text();
    $btn.prop('disabled', true).text("Submitting...");

    $.ajax({
        type: 'POST',
        url: "{{ route('abdm.hiu.patients.consent-requests.store', $dcd->patient_id) }}",
        data: {
            _token: "{{ csrf_token() }}",
            purpose_code: $("#hiu_consent_purpose_code").val(),
            from_date: $("#hiu_consent_from_date").val(),
            to_date: $("#hiu_consent_to_date").val(),
            'hi_types[]': types,
            expiry: $("#hiu_consent_expiry").val()
        },
        traditional: true,
        dataType: 'json',
        success: function(resp) {
            $btn.prop('disabled', false).text(origText);
            if (resp && resp.success) {
                $("#hiuRequestConsentModal").modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Consent Request Sent',
                    text: 'The patient will be asked to approve this in their ABHA app.',
                    timer: 2500,
                    showConfirmButton: false
                });
                if (typeof abdmHiuRefreshButton === 'function') {
                    abdmHiuRefreshButton(document.getElementById('abdm_hiu_pull_btn'), {{ $patientId }});
                }
            } else {
                $("#hiu_consent_request_form_error").text((resp && (resp.error || resp.message)) || "Failed to submit request.").show();
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).text(origText);
            var err = xhr.responseJSON && (xhr.responseJSON.error || xhr.responseJSON.message) ? (xhr.responseJSON.error || xhr.responseJSON.message) : "Error submitting request.";
            $("#hiu_consent_request_form_error").text(err).show();
        }
    });
}

// User-feedback on clicking button when pending approval
$(document).on('click', '#abdm_hiu_pull_btn', function() {
    var state = $(this).data('hiuState') || $(this).attr('data-hiu-state');
    var text = $(this).text().trim();
    if (state === 'pending' || text === 'Patient Approval Pending') {
        Swal.fire({
            icon: 'info',
            title: 'Checking Approval Status',
            text: 'Checking with ABDM if patient has approved consent on their ABHA app...',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
});

// Fallback for abdmHiuButtonClick if app.js did not bind it
if (typeof window.abdmHiuButtonClick !== 'function') {
    window.abdmHiuButtonClick = function(btnEl, patientId) {
        var $btn = $(btnEl);
        var state = $btn.data('hiuState') || $btn.attr('data-hiu-state');
        @if($dcd->patient?->abha_address)
            if (state === 'pending' || $btn.text().trim() === 'Patient Approval Pending') {
                if (typeof abdmHiuRefreshButton === 'function') {
                    abdmHiuRefreshButton(btnEl, patientId);
                }
                return;
            }
            if (typeof openAbdmHiuView === 'function') {
                openAbdmHiuView('{{ $dcd->patient->abha_address }}');
            } else {
                applyHiuConsentDurationPreset();
                $('#hiuRequestConsentModal').modal('show');
            }
        @else
            Swal.fire({
                icon: 'warning',
                title: 'Abha Details Not Provided',
                text: "This patient has no ABHA Number/Address on file - unable to fetch records until their ABHA identity is linked."
            });
        @endif
    };
}

$(document).ready(function() {
    applyHiuConsentDurationPreset();
    if (typeof abdmHiuRefreshButton === 'function') {
        abdmHiuRefreshButton(document.getElementById('abdm_hiu_pull_btn'), {{ $patientId }});
    }
});
</script>
@endpush
