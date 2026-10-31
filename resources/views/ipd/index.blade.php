@extends('layouts.app')

@section('title', 'IPD - In Patient')

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">IPD Register</div>
                    <div class="tbl-filter-box">
                        <div class="local-forms">
                            <label>Status</label>
                            {{-- Ids differ from the legacy page on purpose: public/assets/js/app.js is shared with the legacy site. --}}
                            <select class="form-control" id="s_ipd_status">
                                <option value="New Admission">New Admission</option>
                                <option value="Discharge Approval">Discharge Approval</option>
                                <option value="Discharged">Discharged</option>
                                <option value="All">All</option>
                            </select>
                        </div>
                        @if($canNew)
                        <div class="tbl-top-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="newIpdModal()"><i class="fa fa-plus"></i> New Admission</a></li>
                            </ul>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="sm-table mt-10">
                    <div class="table-responsive">
                        <table id="ipds_tbl" class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr3btns">Actions</th>
                                    <th>Admission Date</th>
                                    <th>IPD No</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Phone</th>
                                    <th>Bed</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New IPD Admission Modal -->
<div class="modal fade" id="newIpdModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create IPD</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="ipd-create-form">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-12 col-sm-12 flex-row-wb">
                                    <div class="form-group local-forms">
                                        <label>Enter OPD ID <span class="req-star">*</span></label>
                                        <input type="number" id="ipd_opd_id" name="opd_id" class="form-control" min="1" required>
                                    </div>
                                    <button class="btn btn-info" type="button" onclick="lookupIpdOpd()"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                            <div id="ipd_patient_info_box" class="row fg-bottom-border" style="display:none">
                                <div id="ipd_patient_info" class="col-12 col-sm-12"></div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Mobile Number <span class="req-star">*</span></label>
                                        <input type="text" id="ipd_mobile_number" name="mobile_number" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-8">
                                    <div class="form-group local-forms">
                                        <label>Address <span class="req-star">*</span></label>
                                        <input type="text" id="ipd_address" name="address" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-12 mt-20">
                                <div class="form-group local-forms">
                                    <label>Previous Medical Note</label>
                                    <textarea name="previous_medical_note" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Admission Date <span class="req-star">*</span></label>
                                        <input type="text" id="ipd_admission_date_time" name="admission_date_time" class="form-control datetimepicker" value="{{ $now }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>TPA</label>
                                        <select class="form-control" id="ipd_tpa_provider_id" name="tpa_provider_id">
                                            <option value=""></option>
                                            @foreach($tpaProviders as $tpa)
                                            <option value="{{ $tpa->id }}">{{ $tpa->tpa_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>TPA Number</label>
                                        <input type="text" name="tpa_number" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Credit Limit Rs.</label>
                                        <input type="number" name="tpa_credit_limit" class="form-control" min="0">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Consultant Doctor <span class="req-star">*</span></label>
                                        <select class="form-control" id="ipd_doctor_id" name="consultant_doctor_id" required>
                                            <option value=""></option>
                                            @foreach($consultants as $doc)
                                            <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-25">
                                        <input type="hidden" id="ipd_bed_id" name="bed_id" value="0">
                                        <div>
                                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" onclick="showIpdBedPicker()">Choose Bed</a>
                                            <span id="ipd_bed_name" class="ml-10">No Bed Selected</span>
                                        </div>
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

<!-- Bed picker -->
<div class="modal fade" id="ipdBedPickerModal">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Beds</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body nptop"><div id="ipd-bed-picker-box"></div></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var IPD_URLS = {
        data:       @json(route('ipd.data')),
        opdPatient: @json(route('ipd.opd-patient', ['opdId' => '__ID__'])),
        bedPicker:  @json(route('ipd.bed-picker')),
        store:      @json(route('ipd.store')),
        show:       @json(route('ipd.show', ['id' => '__ID__'])),
        file:       @json(route('ipd.patient-file', ['id' => '__ID__']))
    };
    var IPD_OPD_PARAM = {{ $opdIdParam }};
    var ipdsTable;

    function escHtml(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    $(document).ready(function () {
        ipdsTable = $('#ipds_tbl').DataTable({
            processing: true,
            serverSide: true,
            scrollX: false,
            autoWidth: false,
            ajax: {
                url: IPD_URLS.data,
                type: 'GET',
                data: function (d) { d.filter_ipd_status = $('#s_ipd_status').val(); }
            },
            columns: [
                {
                    data: 'id', orderable: false,
                    render: function (id) {
                        return '<div class="tr-sm-action-buttons center">'
                            + '<a href="' + IPD_URLS.show.replace('__ID__', id) + '" data-bs-toggle="tooltip" title="IPD Dashboard"><i class="fas fa-list"></i></a> '
                            + '@if($canPatientFile)<a href="' + IPD_URLS.file.replace('__ID__', id) + '" data-bs-toggle="tooltip" title="Patients File"><i class="fas fa-files-medical"></i></a>@endif'
                            + '</div>';
                    }
                },
                { data: 'admission_date_time' },
                { data: 'id' },
                { data: 'patient_name' },
                { data: 'gender' },
                { data: 'age' },
                { data: 'mobile_number' },
                { data: 'bed' }
            ],
            order: [[2, 'desc']],
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                search: 'Search IPD:',
                lengthMenu: 'Show _MENU_ records',
                info: 'Showing _START_ to _END_ of _TOTAL_ IPD records',
                infoEmpty: 'No IPD records found',
                infoFiltered: '(filtered from _MAX_ total records)',
                zeroRecords: 'No matching IPD records found',
                emptyTable: 'No IPD records in database'
            }
        });

        $('#s_ipd_status').on('change', function () { ipdsTable.ajax.reload(); });

        $('#ipd_tpa_provider_id').select2({ dropdownParent: $('#newIpdModal'), placeholder: 'TPA', allowClear: true });
        $('#ipd_doctor_id').select2({ dropdownParent: $('#newIpdModal'), placeholder: 'Doctor' });

        $('#ipd-create-form').on('submit', saveIpd);

        // ?opd_id=N: open the modal and pre-fill the patient (legacy behaviour).
        if (IPD_OPD_PARAM > 0) {
            newIpdModal();
            $('#ipd_opd_id').val(IPD_OPD_PARAM);
            setTimeout(lookupIpdOpd, 500);
        }
    });

    function newIpdModal() { $('#newIpdModal').modal('show'); }

    function lookupIpdOpd() {
        var opdId = parseInt($('#ipd_opd_id').val(), 10);
        if (!opdId) {
            Swal.fire({ icon: 'error', title: 'Required!', text: 'Please Enter OPD ID', showConfirmButton: false, timer: 1500 });
            return;
        }
        $.getJSON(IPD_URLS.opdPatient.replace('__ID__', opdId), function (r) {
            if (r.already_admitted) {
                Swal.fire({ icon: 'error', title: 'Exist!', text: 'IPD Already Exist Please Check OPD ID', showConfirmButton: false, timer: 1500 });
                return;
            }
            if (!r.found) {
                Swal.fire({ icon: 'error', title: 'No Record!', text: 'Please Check Entered OPD ID', showConfirmButton: false, timer: 1500 });
                return;
            }
            var p = r.patient;
            $('#ipd_patient_info').html(
                '<div class="ipd-pdwi-box"><div class="box-left f1-mr10">'
                + '<p class="ipd-pd-name">' + escHtml(p.name) + ' (' + escHtml(p.id) + ')</p>'
                + '<p class="ipd-pd-l2 mt-10"><span><i class="fas fa-venus-mars"></i>' + escHtml(p.gender) + '</span>'
                + '<span><i class="fas fa-tint"></i>' + escHtml(p.blood_group) + '</span>'
                + '<span><i class="fas fa-hourglass-half"></i>' + escHtml(p.age) + '</span></p></div>'
                + '<div class="ipd-pimg"><img class="w100" src="' + escHtml(p.photo) + '"></div></div>');
            $('#ipd_mobile_number').val(p.mobile_number);
            $('#ipd_address').val(p.address);
            $('#ipd_patient_info_box').css('display', 'flex');
        }).fail(function () { showError('Could not look up the OPD ID.'); });
    }

    function showIpdBedPicker() {
        $('#ipdBedPickerModal').modal('show');
        $('#ipd-bed-picker-box').html('<p class="text-center text-muted">Loading beds...</p>');
        $.getJSON(IPD_URLS.bedPicker, function (r) { $('#ipd-bed-picker-box').html(r.html); });
    }

    function chooseIpdBed(id, name) {
        $('#ipd_bed_id').val(id);
        $('#ipd_bed_name').text('Selected: ' + name);
        $('#ipdBedPickerModal').modal('hide');
    }

    function saveIpd(e) {
        e.preventDefault();
        if ($('#ipd_bed_id').val() === '0') {
            Swal.fire({ icon: 'error', title: 'Required!', text: 'Please choose a bed', showConfirmButton: false, timer: 1500 });
            return;
        }
        if ($('#ipd_patient_info_box').css('display') === 'none') {
            Swal.fire({ icon: 'error', title: 'Required!', text: 'Please enter the OPD ID and load the patient', showConfirmButton: false, timer: 1500 });
            return;
        }
        showLoading();
        $.ajax({
            url: IPD_URLS.store,
            type: 'POST',
            data: $(this).serialize(),
            success: function (resp) {
                Swal.close();
                if (resp.success) {
                    $('#newIpdModal').modal('hide');
                    window.location.href = IPD_URLS.show.replace('__ID__', resp.ipd_id);
                } else {
                    showError(resp.message || 'Error.');
                }
            },
            error: function (xhr) {
                Swal.close();
                var errors = xhr.responseJSON && xhr.responseJSON.errors;
                showError(errors ? Object.values(errors).flat().join('\n') : ((xhr.responseJSON && xhr.responseJSON.message) || 'An error occurred.'));
            }
        });
    }
</script>
@endpush
