@extends('layouts.app')

@section('title', 'OPD Register')

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">OPD Register</div>
                    <div class="tbl-filter-box">
                        <div class="local-forms">
                            <label>Report <span class="req-star">*</span></label>
                            <select class="form-control" id="s_report_period">
                                <option value="Daily">Daily</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Periodic">Periodic</option>
                            </select>
                        </div>
                        <div id="report-period-daily" class="local-forms">
                            <label>Date <span class="req-star">*</span></label>
                            <input type="text" id="s_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
                        </div>
                        <div id="report-period-monthly" class="local-forms" style="display:none;">
                            <label>Month <span class="req-star">*</span></label>
                            <select class="form-control" id="s_month">
                                @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div id="report-period-periodic" class="fields-flex-row" style="display:none;">
                            <div class="local-forms">
                                <label>From <span class="req-star">*</span></label>
                                <input type="text" id="s_from_date" name="from_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
                            </div>
                            <div class="local-forms">
                                <label>To <span class="req-star">*</span></label>
                                <input type="text" id="s_to_date" name="to_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
                            </div>
                        </div>
                        <div class="local-forms">
                            <label>Source</label>
                            <select class="form-control" id="s_opd_source">
                                <option value="">All</option>
                                <option value="manual">Manual</option>
                                <option value="abha">Abha</option>
                            </select>
                        </div>
                        <div class="local-forms">
                            <label>Consultant</label>
                            <select class="form-control" id="s_consultant_doctor_id">
                                <option value="">All</option>
                                @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="tbl-top-buttons">
                            <ul>
                                <li><a id="search-opds-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
                                @if($canNewOpd)
                                <li><a href="javascript:void(0)" onclick="createOPDModal()"><i class="fa fa-plus"></i> New OPD</a></li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="sm-table mt-10">
                    <div class="table-responsive">
                        <table id="opds_tbl" class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr5btns"></th>
                                    <th>Token</th>
                                    <th>OPD ID</th>
                                    <th>Date Time</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Phone</th>
                                    <th>Source</th>
                                    <th class="no-sort small-switch">Consulted</th>
                                    <th class="no-sort">Consultant</th>
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

<!-- Create OPD Modal -->
<div class="modal fade" id="createOPDModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create OPD <span id="token_number" class="token-number">Token: Will be assigned on save</span></h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            {{-- Ids differ from the legacy page on purpose: public/assets/js/app.js is shared with the legacy site and
                 still binds #create_opd_form / #add-opd-patient-form to the old ajax.php endpoints. --}}
            <form id="opd-create-form">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-12 col-sm-12 flex-row-wb">
                                    <div class="form-group local-forms">
                                        <label>Patient Name / Mobile Number <span class="req-star">*</span></label>
                                        <select id="opd_patient_id" class="form-control" name="patient_id" required>
                                            <option></option>
                                        </select>
                                    </div>
                                    <button class="btn btn-info" type="button" onclick="addOPDPatientModal()"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                            <div id="opd_patient_info_box" class="row fg-bottom-border">
                                <div id="opd_patient_info" class="col-12 col-sm-12"></div>

                                {{-- Patient Identity of the EXISTING patient picked above --}}
                                <div id="opd_identity_box" class="col-12 col-sm-12" style="display:none;">
                                    <div class="identity-verification-box mb-15 p-15" style="border:1px solid #e6e6e6;border-radius:6px;">
                                        <label class="d-block mb-10"><b>Patient Identity <span class="text-muted">(Optional)</span></b></label>
                                        <div id="opd_identity_linked_summary" style="display:none;">
                                            <div class="identity-result mb-10">
                                                <i class="fa fa-check-circle text-success"></i> Linked &mdash;
                                                ABHA Number: <b id="opd_identity_linked_abha_number">-</b>,
                                                ABHA Address: <b id="opd_identity_linked_abha_address">-</b>
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm" onclick="showOPDIdentityForm()">Change / Re-verify</button>
                                        </div>
                                        <div id="opd_identity_not_linked" style="display:none;">
                                            <div class="text-muted mb-10"><i class="fa fa-info-circle"></i> No ABHA linked to this patient yet.</div>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="showOPDIdentityForm()">Verify / Link ABHA</button>
                                        </div>
                                        <div id="opd_identity_form" style="display:none;">
                                            @include('partials.abdm.identity-tabs', ['mode' => 'opd'])
                                            <div id="opd_identity_mismatch_banner" class="alert alert-warning mt-10" style="display:none;">
                                                <i class="fa fa-exclamation-triangle"></i> <span id="opd_identity_mismatch_text"></span>
                                            </div>
                                            <!-- Values captured from identity verification above, saved via saveOPDPatientIdentity() -->
                                            <input type="hidden" id="opd_new_patient_abha_number" value="">
                                            <input type="hidden" id="opd_new_patient_abha_address" value="">
                                            <input type="hidden" id="opd_new_patient_aadhaar_number" value="">
                                            <input type="hidden" id="opd_new_patient_date_of_birth" value="">
                                            <!-- ABDM's verified Name/Gender: synced onto the selected patient's own record on save, since ABDM's M2 "generate link token" call needs an exact match -->
                                            <input type="hidden" id="opd_new_patient_name" value="">
                                            <input type="hidden" id="opd_new_patient_gender" value="">
                                            <!-- Filled only if this patient has NO photo on file yet - see handleIdentityPhoto() -->
                                            <input type="hidden" id="opd_new_patient_photo" value="">
                                            <div class="mt-10">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="saveOPDPatientIdentity()"><i class="fa fa-save"></i> Save Identity</button>
                                                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelOPDIdentityForm()">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Mobile Number <span class="req-star">*</span></label>
                                        <input type="number" id="mobile_number" name="mobile_number" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-8">
                                    <div class="form-group local-forms">
                                        <label>Address <span class="req-star">*</span></label>
                                        <input type="text" id="address" name="address" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>OPD Date <span class="req-star">*</span></label>
                                        <input type="text" name="opd_date_time" id="opd_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i A') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Consultant Doctor <span class="req-star">*</span></label>
                                        <select class="form-control" id="opd_doctor_id" name="consultant_doctor_id" required>
                                            <option></option>
                                            @foreach($consultants as $doc)
                                            <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>OPD Charges <span class="req-star">*</span></label>
                                        <input type="number" min="0" id="opd_charges" name="opd_charges" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Payment Mode </label>
                                        <select class="form-control" id="payment_mode" name="payment_mode">
                                            <option></option>
                                            <option value="Cash">Cash</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="Credit Card">Credit Card</option>
                                        </select>
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

<!-- Add Patient Modal (from the Create OPD patient picker) -->
<div class="modal fade" id="addOPDPatientModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Patient</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="opd-add-patient-form" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="col-12 col-sm-12">
                        <div class="identity-verification-box mb-15 p-15" style="border:1px solid #e6e6e6;border-radius:6px;">
                            <label class="d-block mb-10"><b>Patient Identity <span class="text-muted">(Optional)</span></b></label>
                            @include('partials.abdm.identity-tabs', ['mode' => 'add'])
                            <div id="identity_match_banner" class="alert alert-warning mt-10" style="display:none;">
                                <div id="identity_match_text"></div>
                                <button type="button" class="btn btn-sm btn-primary mt-10" onclick="useExistingPatientMatch()">Use This Existing Patient Instead</button>
                                <button type="button" id="identity_match_different_person_btn" class="btn btn-sm btn-success mt-10" onclick="dismissIdentityMatchBanner()">This Is a Different Person</button>
                            </div>
                            <!-- Values captured from identity verification above, submitted with the patient -->
                            <input type="hidden" id="new_patient_abha_number" name="abha_number" value="">
                            <input type="hidden" id="new_patient_abha_address" name="abha_address" value="">
                            <input type="hidden" id="new_patient_aadhaar_number" name="aadhaar_number" value="">
                            <input type="hidden" id="new_patient_date_of_birth" name="date_of_birth" value="">
                            <!-- Bare filename of the ABDM KYC photo saved by the identity flow (validated server-side) -->
                            <input type="hidden" id="new_patient_photo" name="photo_filename" value="">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-sm-9">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Patient Name <span class="req-star">*</span></label>
                                        <input type="text" name="patient_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Guardian Name</label>
                                        <input type="text" name="guardian_name" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Gender <span class="req-star">*</span></label>
                                        <select name="gender" class="form-control" required>
                                            <option></option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Age <span class="req-star">*</span></label>
                                        <input type="number" min="0" name="age" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Blood Group</label>
                                        <select class="form-control" name="blood_group">
                                            <option></option>
                                            @foreach($bloodGroups as $bg)
                                            <option value="{{ $bg }}">{{ $bg }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Mobile Number <span class="req-star">*</span></label>
                                        <input type="number" name="mobile_number" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Alernate Mobile Number</label>
                                        <input type="number" name="alternate_mobile_number" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-3">
                            <div class="slim_image_box">
                                <input class="form-control" type="hidden" id="photo" name="photo" value="">
                                <div class="opd-add-slim"
                                    data-label="Upload Image"
                                    data-ratio="1:1"
                                    data-size="300,300">
                                    <input type="file" name="slim[]" capture />
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="row">
                                <!-- ESM/Service fields - shown only when the logged-in patient category is > 1 -->
                                <div id="esm_fields_container" style="display: {{ $showEsmFields ? 'block' : 'none' }};" class="col-12 col-sm-12">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>ESM Name</label>
                                                <input type="text" name="esm_name" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Card Number</label>
                                                <input type="text" name="card_number" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Service Number</label>
                                                <input type="text" name="service_number" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Claim ID</label>
                                                <input type="text" name="claim_id" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Address <span class="req-star">*</span></label>
                                        <input type="text" name="address" class="form-control" required>
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

<!-- ABDM ABHA-creation Terms & Conditions (settings.abha_creation_terms) -->
<div class="modal fade" id="abhaTermsModal" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ $abhaTerms['title'] }}</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
                <ol>
                    @foreach($abhaTerms['items'] as $term)
                    <li class="mb-10">{{ $term }}</li>
                    @endforeach
                </ol>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary ml-10" onclick="acceptAbhaTerms()">Accept</button>
            </div>
        </div>
    </div>
</div>

<!-- Collect Payment (ABHA QR-scan visits with no payment recorded yet) -->
<div class="modal fade" id="collectOPDPaymentModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Collect Payment</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="collect-opd-payment-form">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="collect_opd_id" name="opd_id">
                    <div class="form-group local-forms">
                        <label>Payment Mode <span class="req-star">*</span></label>
                        <select class="form-control" id="collect_payment_mode" name="payment_mode" required>
                            <option value=""></option>
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    <div class="form-group local-forms">
                        <label>Amount <span class="req-star">*</span></label>
                        <input type="number" min="0" step="0.01" class="form-control" id="collect_opd_charges" name="opd_charges" required>
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
    var IDENTITY_CFG = { forms: { add: '#opd-add-patient-form' }, modals: { add: '#addOPDPatientModal' } };
</script>
@include('partials.abdm.identity-scripts')
@include('opd.partials.identity-scripts')
<script>
    var OPD_URLS = {
        patientSearch:  @json(route('opd.patient-autocomplete')),
        patientDetails: @json(route('opd.patient', ['id' => '__ID__'])),
        store:          @json(route('opd.store')),
        collectPayment: @json(route('opd.collect-payment', ['id' => '__ID__'])),
        status:         @json(route('opd.status', ['id' => '__ID__'])),
        patientStore:   @json(route('patients.store')),
        ipd:            @json(route('ipd.index')),
        dcd:            @json(route('dcd.index'))
    };

    function opdUrl(key, id) { return OPD_URLS[key].replace('__ID__', encodeURIComponent(id)); }

    function opdErrorMessage(xhr, fallback) {
        var j = xhr && xhr.responseJSON;
        if (j && j.errors) { return Object.values(j.errors).flat().join('\n'); }
        if (j && (j.message || j.error)) { return j.message || j.error; }
        if (xhr && xhr.status === 419) { return 'Your session has expired. Please reload the page and try again.'; }
        return fallback || 'Could not reach the server. Please try again.';
    }

    $(document).ready(function () {
        // ── OPD register (server-side DataTable). Rebuilt on every search because rows are grouped by consultant only
        //    when the Consultant filter is "All". ──
        function initOpdsTable() {
            if ($.fn.DataTable.isDataTable('#opds_tbl')) {
                $('#opds_tbl').DataTable().destroy();
            }

            var consultantDoctorId = $('#s_consultant_doctor_id').val();
            var exportColumns = { columns: 'th:not(:first-child)' };
            var config = {
                dom: 'Blfrtip',
                pageLength: 100,
                processing: true,
                serverSide: true,
                autoWidth: false,
                serverMethod: 'post',
                ajax: {
                    url: @json(route('opd.data')),
                    data: function (data) {
                        data.report_period = $('#s_report_period').val();
                        data.search_date = $('#s_date').val();
                        data.search_month = $('#s_month').val();
                        data.from_date = $('#s_from_date').val();
                        data.to_date = $('#s_to_date').val();
                        data.opd_source = $('#s_opd_source').val();
                        data.consultant_doctor_id = consultantDoctorId;
                    }
                },
                columns: [
                    { data: 'action' }, { data: 'token_number' }, { data: 'id' }, { data: 'opd_date_time' },
                    { data: 'patient_name' }, { data: 'gender' }, { data: 'age' }, { data: 'mobile_number' },
                    { data: 'source' }, { data: 'status_switch' }, { data: 'consultant_name' }
                ],
                columnDefs: [
                    { className: 'tr-sm-action-buttons', targets: [0] },
                    { className: 'small-switch', targets: [9] },
                    { targets: [10], visible: false }
                ],
                buttons: [
                    { extend: 'excel', text: 'Excel', className: 'buttons-html5', exportOptions: exportColumns },
                    { extend: 'pdf', text: 'PDF', className: 'buttons-html5', exportOptions: exportColumns },
                    { extend: 'print', text: 'Print', className: 'buttons-html5', exportOptions: exportColumns }
                ],
                initComplete: function () { $('[data-bs-toggle="tooltip"]').tooltip(); },
                drawCallback: function () { $('[data-bs-toggle="tooltip"]').tooltip(); }
            };
            // Group by consultant only for "All"; the server orders rows by doctor so same-doctor rows are contiguous.
            if (consultantDoctorId === '') {
                config.rowGroup = { dataSrc: 'consultant_name' };
            }
            $('#opds_tbl').DataTable(config);
        }

        initOpdsTable();
        $('#search-opds-btn').off('click').on('click', initOpdsTable);
        $('#s_consultant_doctor_id').off('change').on('change', initOpdsTable);

        // ── Patient picker: name / mobile / id search; "no result" offers to add a new patient ──
        var lastSearchTerm = '';
        $('#opd_patient_id').select2({
            dropdownParent: $('#createOPDModal'),
            placeholder: 'Patient',
            ajax: {
                url: OPD_URLS.patientSearch,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) { return { results: data.results }; },
                cache: true
            },
            language: {
                noResults: function () {
                    return '<span class="add-patient-link" style="cursor: pointer; color: #0c4079; text-decoration: underline;">No patient found. Click here or press Enter to add new patient.</span>';
                }
            },
            escapeMarkup: function (markup) { return markup; }
        });

        $('#opd_patient_id').on('select2:open', function () {
            $(this).data('select2').$dropdown.find('.select2-search__field').on('input', function () {
                lastSearchTerm = $(this).val();
            });
            setTimeout(function () {
                $('.select2-results').off('click', '.add-patient-link').on('click', '.add-patient-link', function (event) {
                    event.stopPropagation();
                    $('#opd_patient_id').select2('close');
                    addOPDPatientModal(/^\d{10}$/.test(lastSearchTerm) ? lastSearchTerm : undefined);
                });
            }, 100);
        });

        // Enter on "No results" with a 10-digit mobile opens Add Patient with that mobile prefilled.
        $('#opd_patient_id').on('select2:close', function () {
            var term = lastSearchTerm;
            if (term && /^\d{10}$/.test(term) && !$(this).val()) {
                var hasResults = $(this).data('select2').$dropdown.find('.select2-results__option').not('.select2-results__message').length > 0;
                if (!hasResults) {
                    setTimeout(function () { addOPDPatientModal(term); }, 200);
                }
            }
        });

        $('#opd_patient_id').on('change', function () {
            var id = $(this).val();
            if (id) { showOPDExistingPatient(id); }
        });

        $('#opd_doctor_id').select2({ dropdownParent: $('#createOPDModal'), placeholder: 'Doctor' });

        // ── Save OPD ──
        $('#opd-create-form').on('submit', function (e) {
            e.preventDefault();
            var charges = parseFloat($('#opd_charges').val());
            if (charges > 0 && $('#payment_mode').val() === '') {
                Swal.fire({ icon: 'error', title: 'Required!', text: 'Please Select Payment Mode', showConfirmButton: false, timer: 1500 });
                return;
            }
            var $btn = $(this).find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: OPD_URLS.store,
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    Swal.fire({ icon: 'error', title: 'Error!', text: (resp && resp.message) || 'Could not save the OPD visit.', showConfirmButton: true });
                    return;
                }
                $('#createOPDModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'OPD Created Successfully. Token Number: ' + resp.token, showConfirmButton: false, timer: 2000 });
                if ($.fn.DataTable.isDataTable('#opds_tbl')) { $('#opds_tbl').DataTable().ajax.reload(null, false); }
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Error!', text: opdErrorMessage(xhr), showConfirmButton: true });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ── Add Patient (from the Create OPD picker) ──
        $('#opd-add-patient-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: OPD_URLS.patientStore,
                type: 'POST',
                data: new FormData(form),
                contentType: false,
                processData: false,
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    Swal.fire({ icon: 'error', title: 'Could Not Save Patient', text: (resp && resp.message) || 'Please try again.', showConfirmButton: true });
                    return;
                }
                var p = resp.patient;
                var mobile = $(form).find('[name="mobile_number"]').val();
                var label = p.name + ' ' + mobile + ' ' + p.id;
                $('#opd_patient_id').append(new Option(label, p.id, true, true)).trigger('change');
                form.reset();
                resetOPDPatientPhoto();
                $('#addOPDPatientModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Added!', text: 'Patient Added Successfully.', showConfirmButton: false, timer: 1500 });
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Could Not Save Patient', text: opdErrorMessage(xhr), showConfirmButton: true });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ── Collect Payment ──
        $('#collect-opd-payment-form').on('submit', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: opdUrl('collectPayment', $('#collect_opd_id').val()),
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (resp) {
                if (resp && resp.success) {
                    $('#collectOPDPaymentModal').modal('hide');
                    if ($.fn.DataTable.isDataTable('#opds_tbl')) { $('#opds_tbl').DataTable().ajax.reload(null, false); }
                    Swal.fire({ icon: 'success', title: 'Payment collected', showConfirmButton: false, timer: 1200 });
                } else {
                    Swal.fire({ icon: 'error', title: 'Could not save payment', text: (resp && resp.error) ? resp.error : 'Please try again.' });
                }
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Could not save payment', text: opdErrorMessage(xhr) });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // Inline New/Consulted toggle - the table is server-side rendered, so this is delegated.
        $(document).on('change', '.opd-status-switch', function () {
            var $chk = $(this);
            var opdId = $chk.data('opd-id');
            var newStatus = $chk.is(':checked') ? 'Consulted' : 'New';
            $chk.prop('disabled', true);
            $.ajax({
                url: opdUrl('status', opdId),
                type: 'POST',
                data: { status: newStatus },
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    $chk.prop('checked', !$chk.is(':checked'));
                    Swal.fire({ icon: 'error', title: 'Could not update status', text: (resp && resp.error) ? resp.error : 'Please try again.' });
                }
            }).fail(function (xhr) {
                $chk.prop('checked', !$chk.is(':checked'));
                Swal.fire({ icon: 'error', title: 'Could not update status', text: opdErrorMessage(xhr) });
            }).always(function () {
                $chk.prop('disabled', false);
            });
        });
    });

    // The Create OPD / Add Patient openers and the patient lookup are redefined here (they also exist in the shared,
    // legacy app.js, where they talk to ajax.php).
    function createOPDModal() {
        var form = document.getElementById('opd-create-form');
        form.reset();
        $('#opd_patient_id').val(null).trigger('change.select2');
        $('#opd_doctor_id').val(null).trigger('change.select2');
        $('#opd_patient_info').empty();
        opdSelectedPatient = null;
        resetOPDIdentitySection();
        $('#opd_identity_box').hide();
        var dt = document.getElementById('opd_date_time');
        if (dt && dt._flatpickr) { dt._flatpickr.setDate(new Date(), false); }
        // Token is generated server-side during save to prevent a race between concurrent users.
        $('#token_number').html('Token: Will be assigned on save');
        $('#createOPDModal').modal('show');
    }

    // Slim cropper for OPD Add Patient: base64 output captured on confirm/transform and sent with the form.
    function initOpdAddSlim() {
        $('.opd-add-slim').slim({
            ratio: '1:1',
            size: '300,300',
            label: 'Upload Image',
            didConfirm: function(data) {
                if (data.output && data.output.image) {
                    $('#photo').val(data.output.image.toDataURL('image/jpeg', 0.85));
                }
            },
            didTransform: function(data) {
                if (data.output && data.output.image) {
                    $('#photo').val(data.output.image.toDataURL('image/jpeg', 0.85));
                }
            },
            willRemove: function(data, remove) {
                remove();
                $('#photo').val('');
            }
        });
    }
    initOpdAddSlim();

    // form.reset() does not touch the cropper widget or its hidden filename, so clear both.
    function resetOPDPatientPhoto() {
        $('#photo').val('');
        try {
            $('.opd-add-slim').slim('destroy');
            initOpdAddSlim();
        } catch (e) { /* cropper not initialised yet */ }
    }

    function addOPDPatientModal(mobileNumber) {
        $('#opd-add-patient-form')[0].reset();
        resetOPDPatientPhoto();
        if (mobileNumber) {
            $("#addOPDPatientModal input[name='mobile_number']").val(mobileNumber);
        }
        $('#addOPDPatientModal').modal('show');
    }

    function showOPDExistingPatient(patientId) {
        $.ajax({
            type: 'GET',
            url: opdUrl('patientDetails', patientId),
            dataType: 'json'
        }).done(function (data) {
            $('#opd_patient_info_box').css('display', 'flex');
            $('#opd_patient_info').html(data.html);
            $('#mobile_number').val(data.mobile_number);
            $('#address').val(data.address);

            opdSelectedPatient = data;
            resetOPDIdentitySection();
            renderOPDPatientIdentitySummary(data);
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Could not load patient', text: opdErrorMessage(xhr) });
        });
    }

    function showAdmitPatientDialog(opdId) {
        Swal.fire({
            title: 'Admit Patient',
            text: 'Choose admission type:',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'IPD',
            denyButtonText: 'Day Care',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0c4079',
            denyButtonColor: '#6f42c1',
            cancelButtonColor: '#6c757d'
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = OPD_URLS.ipd + '?opd_id=' + encodeURIComponent(opdId);
            } else if (result.isDenied) {
                window.location.href = OPD_URLS.dcd + '?opd_id=' + encodeURIComponent(opdId);
            }
        });
    }

    // Collect Payment - shown only on OPD visits with no payment recorded yet (the ABHA QR-scan flow never
    // collects money at scan time).
    function collectOPDPaymentModal(opdId, currentCharges) {
        $('#collect_opd_id').val(opdId);
        $('#collect_payment_mode').val('');
        $('#collect_opd_charges').val(currentCharges > 0 ? currentCharges : '');
        $('#collectOPDPaymentModal').modal('show');
    }
</script>
@endpush
