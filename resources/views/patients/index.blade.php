@extends('layouts.app')

@section('title', 'Patients')

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Patients</div>
                    <div class="tbl-filter-box">
                        {{-- Category filter removed - automatically filtered by the logged-in user's category --}}
                        @if($canNew)
                        <div class="tbl-top-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="addPatientModal()"><i class="fa fa-plus"></i> New</a></li>
                            </ul>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="sm-table mt-10">
                    <div class="table-responsive">
                        <table id="patients_tbl" class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns">Actions</th>
                                    <th>UHID No</th>
                                    <th>Name</th>
                                    <th>Mobile Number</th>
                                    <th>Gender</th>
                                    <th>Created On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data is loaded via AJAX by DataTables -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Patient</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            {{-- Form ids differ from the shared public/assets/js/app.js bindings (#add-new-patient-form / #update-patient-form) on purpose. --}}
            <form id="patients-add-form" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="col-12 col-sm-12">
                        <div class="identity-verification-box mb-15 p-15" style="border:1px solid #e6e6e6;border-radius:6px;">
                            <label class="d-block mb-10"><b>Patient Identity <span class="text-muted">(Optional)</span></b></label>
                            @include('partials.abdm.identity-tabs', ['mode' => 'add', 'only' => 'tabs'])
                        </div>
                        @include('partials.abdm.identity-tabs', ['mode' => 'add', 'only' => 'download'])
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
                        <input type="hidden" id="new_patient_photo" name="photo_filename" value="">
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
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-3">
                            <div class="slim_image_box">
                                <input class="form-control" type="hidden" id="photo" name="photo" value="">
                                <div class="add-slim"
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

<!-- Update Patient Modal -->
<div class="modal fade" id="editPatientModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Patient</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="patients-edit-form" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="edit-patient-id" name="patient_id" value="0">
                    <input type="hidden" id="patient_id" name="patient_id" value="0">
                    <div class="col-12 col-sm-12">
                        <div class="identity-verification-box mb-15 p-15" style="border:1px solid #e6e6e6;border-radius:6px;">
                            <label class="d-block mb-10"><b>Patient Identity <span class="text-muted">(Optional)</span></b></label>
                            <div id="edit_identity_linked_summary" style="display:none;">
                                <div class="identity-result mb-10">
                                    <i class="fa fa-check-circle text-success"></i> Linked &mdash;
                                    ABHA Number: <b id="edit_identity_linked_abha_number">-</b>,
                                    ABHA Address: <b id="edit_identity_linked_abha_address">-</b>
                                </div>
                                <button type="button" class="btn btn-success btn-sm" onclick="showEditIdentityForm()">Change / Re-verify</button>
                            </div>
                            <div id="edit_identity_form">
                                @include('partials.abdm.identity-tabs', ['mode' => 'edit', 'only' => 'tabs'])
                            </div>
                        </div>
                        @include('partials.abdm.identity-tabs', ['mode' => 'edit', 'only' => 'download'])
                        <div id="edit_identity_mismatch_banner" class="alert alert-warning mt-10" style="display:none;">
                            <i class="fa fa-exclamation-triangle"></i> <span id="edit_identity_mismatch_text"></span>
                        </div>
                        <!-- Values captured from identity verification above, submitted with the patient update -->
                        <input type="hidden" id="edit_new_patient_abha_number" name="abha_number" value="">
                        <input type="hidden" id="edit_new_patient_abha_address" name="abha_address" value="">
                        <input type="hidden" id="edit_new_patient_aadhaar_number" name="aadhaar_number" value="">
                        <input type="hidden" id="edit_new_patient_date_of_birth" name="date_of_birth" value="">
                        <input type="hidden" id="edit_new_patient_photo" name="photo_filename" value="">
                    </div>
                    <div class="row mt-10">
                        <div class="col-12 col-sm-9">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Patient Name <span class="req-star">*</span></label>
                                        <input type="text" id="edit_patient_name" name="patient_name" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Guardian Name</label>
                                        <input type="text" id="edit_guardian_name" name="guardian_name" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Gender <span class="req-star">*</span></label>
                                        <select id="edit_gender" name="gender" class="form-control" required>
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
                                        <input type="number" min="0" id="edit_age" name="age" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Blood Group</label>
                                        <select class="form-control" id="edit_blood_group" name="blood_group">
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
                                        <input type="number" id="edit_mobile_number" name="mobile_number" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Alernate Mobile Number</label>
                                        <input type="number" id="edit_alternate_mobile_number" name="alternate_mobile_number" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Email</label>
                                        <input type="email" id="edit_email" name="email" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-3">
                            <input class="form-control" type="hidden" id="edit_photo" name="photo" value="">
                            <div id="edit_slim_wrap">
                                <div class="slim"
                                    data-label="Upload Photo"
                                    data-ratio="1:1"
                                    data-will-remove="epRemove">
                                    <input type="file" name="slim[]" capture />
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="row">
                                <!-- ESM/Service fields - shown based on the patient's category -->
                                <div id="edit_esm_fields_container" style="display: none;" class="col-12 col-sm-12">
                                    <div class="row">
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>ESM Name</label>
                                                <input type="text" id="edit_esm_name" name="esm_name" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Card Number</label>
                                                <input type="text" id="edit_card_number" name="card_number" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Service Number</label>
                                                <input type="text" id="edit_service_number" name="service_number" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-group local-forms">
                                                <label>Claim ID</label>
                                                <input type="text" id="edit_claim_id" name="claim_id" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Address <span class="req-star">*</span></label>
                                        <input type="text" id="edit_address" name="address" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="submit">Update</button>
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
@endsection

@push('scripts')
<script>
    var IDENTITY_CFG = {
        forms:  { add: '#patients-add-form',  edit: '#patients-edit-form' },
        modals: { add: '#addPatientModal',    edit: '#editPatientModal' }
    };
</script>
@include('partials.abdm.identity-scripts')
<script>
    var patientsTable; // DataTable instance
    var PATIENT_URLS = {
        data:   @json(route('patients.data')),
        history: @json(route('patients.history', ['id' => '__ID__'])),
        store:  @json(route('patients.store')),
        show:   @json(route('patients.show', ['id' => '__ID__'])),
        update: @json(route('patients.update', ['id' => '__ID__']))
    };
    var PATIENT_PHOTO_DIR = @json(\App\Support\Uploads::baseUrl('patients'));
    var NO_IMAGE = @json(asset('assets/images/no_image.png'));

    function patientUrl(key, id) { return PATIENT_URLS[key].replace('__ID__', encodeURIComponent(id)); }

    function patientErrorMessage(xhr) {
        var j = xhr && xhr.responseJSON;
        if (j && j.errors) { return Object.values(j.errors).flat().join('\n'); }
        if (j && (j.message || j.error)) { return j.message || j.error; }
        if (xhr && xhr.status === 419) { return 'Your session has expired. Please reload the page and try again.'; }
        return 'An error occurred.';
    }

    $(document).ready(function () {
        patientsTable = $('#patients_tbl').DataTable({
            processing: true,
            serverSide: true,
            scrollX: false,
            autoWidth: false,
            ajax: { url: PATIENT_URLS.data, type: 'GET' },
            columns: [
                {
                    data: 'id', orderable: false,
                    render: function (data) {
                        return '<div class="tr-sm-action-buttons center">'
                            + '@if($canHistory)<a href="' + PATIENT_URLS.history.replace('__ID__', data) + '" data-bs-toggle="tooltip" title="View History"><i class="fa-solid fa-user-clock"></i></a>@endif'
                            + '@if($canEdit)<a href="javascript:void(0)" onclick="editPatientModal(' + data + ');" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-pencil"></i></a>@endif'
                            + '</div>';
                    }
                },
                { data: 'uhid' },
                { data: 'patient_name' },
                { data: 'mobile_number' },
                { data: 'gender' },
                { data: 'created_on' }
            ],
            order: [[2, 'asc']],   // default sort by name
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                search: 'Search Patients:',
                lengthMenu: 'Show _MENU_ patients',
                info: 'Showing _START_ to _END_ of _TOTAL_ patients',
                infoEmpty: 'No patients found',
                infoFiltered: '(filtered from _MAX_ total patients)',
                zeroRecords: 'No matching patients found',
                emptyTable: 'No patients in database',
                paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
            }
        });

        // ── Add Patient ──
        $('#patients-add-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: PATIENT_URLS.store,
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
                patientsTable.ajax.reload(null, false);
                form.reset();
                $('#addPatientModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Added!', text: 'Patient Added Successfully.', showConfirmButton: false, timer: 1500 });
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Could Not Save Patient', text: patientErrorMessage(xhr), showConfirmButton: true });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        // ── Update Patient ──
        $('#patients-edit-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            var $btn = $(form).find('button[type="submit"]').prop('disabled', true);
            $.ajax({
                url: patientUrl('update', $('#patient_id').val()),
                type: 'POST',
                data: new FormData(form),
                contentType: false,
                processData: false,
                dataType: 'json'
            }).done(function (resp) {
                if (!resp || !resp.success) {
                    Swal.fire({ icon: 'error', title: 'Could Not Update Patient', text: (resp && resp.message) || 'Please try again.', showConfirmButton: true });
                    return;
                }
                patientsTable.ajax.reload(null, false);
                form.reset();
                $('#editPatientModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Updated!', text: 'Updated Successfully.', showConfirmButton: false, timer: 1500 });
            }).fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'Could Not Update Patient', text: patientErrorMessage(xhr), showConfirmButton: true });
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    });

    function abdmResetIdentity(prefix) {
        resetIdentitySection(prefix, prefix === 'edit');
    }

    // Init Add-Patient Slim: no service endpoint — base64 output is captured on confirm/transform and sent with the form.
    $('.add-slim').slim({
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

    function epRemove(data, remove) {
        remove();
        $("#edit_photo").val('');
    }

    $('#addPatientModal').on('show.bs.modal', function () {
        $('#photo').val('');
    });

    // addPatientModal() / editPatientModal() are also defined in the shared app.js; these versions replace them.
    function addPatientModal() {
        $('#patients-add-form')[0].reset();
        $('.add-slim').slim('destroy');
        $('.add-slim').slim({
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
        $('#photo').val('');
        resetIdentitySection('add');
        $('#addPatientModal').modal('show');
    }

    // Opens the Update Patient modal. $onLoaded(patient) runs once the form is filled - used by "Use This Existing
    // Patient Instead" to apply a freshly verified identity onto the matched patient.
    function editPatientModal(id, onLoaded) {
        $.ajax({ url: patientUrl('show', id), type: 'GET', dataType: 'json' }).done(function (resp) {
            if (!resp || !resp.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load patient data.' });
                return;
            }
            var p = resp.patient;
            var data = p;
            $('#patients-edit-form')[0].reset();
            resetIdentitySection('edit', true);

            $('#patient_id').val(id);
            $('#edit-patient-id').val(id);
            $('#edit_patient_name').val(data.patient_name || '');
            $('#edit_guardian_name').val(data.guardian_name || '');
            $('#edit_gender').val(data.gender || '');
            $('#edit_age').val(data.age);
            $('#edit_blood_group').val(data.blood_group || '');
            $('#edit_mobile_number').val(data.mobile_number || '');
            $('#edit_alternate_mobile_number').val(data.alternate_mobile_number || '');
            $('#edit_email').val(data.email || '');
            $('#edit_esm_name').val(data.esm_name || '');
            $('#edit_card_number').val(data.card_number || '');
            $('#edit_service_number').val(data.service_number || '');
            $('#edit_claim_id').val(data.claim_id || '');
            $('#edit_address').val(data.address || '');

            // ESM fields depend on the patient's own category.
            var categoryId = parseInt(data.patient_category_id, 10);
            $('#edit_esm_fields_container').toggle(!!categoryId && categoryId !== 1);

            // Load existing photo into Slim (no service endpoint — base64 output sent with the form on Update).
            editingPatientHasPhoto = !!data.photo;
            $('#edit_photo').val(data.photo || '');
            var $editSlim = $('#edit_slim_wrap .slim');
            $editSlim.slim('destroy');
            $editSlim.slim({
                ratio: '1:1',
                label: 'Upload Photo',
                didInit: function() {
                    if (data.photo_url) {
                        this.load(data.photo_url, function(error) {
                            if (error) { console.warn('Slim load error:', error); }
                        });
                    }
                },
                didConfirm: function(slimData) {
                    if (slimData.output && slimData.output.image) {
                        $('#edit_photo').val(slimData.output.image.toDataURL('image/jpeg', 0.85));
                    }
                },
                didTransform: function(slimData) {
                    if (slimData.output && slimData.output.image) {
                        $('#edit_photo').val(slimData.output.image.toDataURL('image/jpeg', 0.85));
                    }
                },
                willRemove: epRemove
            });

            // Patient Identity (ABDM): prefill the hidden fields from the patient's current DB values, and show the
            // ABHA read-only if already linked, so editing a patient never forces re-verification. The date of birth
            // is only ever populated from ABDM - prefilling keeps a re-save from wiping it.
            var abhaNumber = data.abha_number || '';
            var abhaAddress = data.abha_address || '';
            $('#edit_new_patient_abha_number').val(abhaNumber);
            $('#edit_new_patient_abha_address').val(abhaAddress);
            $('#edit_new_patient_aadhaar_number').val(data.aadhaar_number || '');
            $('#edit_new_patient_date_of_birth').val(p.date_of_birth || '');
            if (abhaNumber || abhaAddress) {
                $('#edit_identity_linked_abha_number').text(abhaNumber || '-');
                $('#edit_identity_linked_abha_address').text(abhaAddress || '-');
                $('#edit_identity_linked_summary').show();
                $('#edit_identity_form').hide();
                // Lock name + mobile — ABHA-verified identity fields must not be changed.
                // Use readonly (not disabled) so the values are still included in FormData.
                $('#edit_patient_name').prop('readonly', true);
                $('#edit_mobile_number').prop('readonly', true);
            } else {
                $('#edit_identity_linked_summary').hide();
                $('#edit_identity_form').show();
                // Unlock in case we are re-opening a patient who had ABHA removed
                $('#edit_patient_name').prop('readonly', false);
                $('#edit_mobile_number').prop('readonly', false);
            }

            $('#editPatientModal').modal('show');

            if (typeof onLoaded === 'function') { onLoaded(data); }
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: patientErrorMessage(xhr) });
        });
    }

    // "Use This Existing Patient Instead" (ABDM M1 returning-patient match): close Add, open the matched patient's
    // Update modal and carry the identity just verified over to it.
    function useExistingPatientMatch() {
        var match = lastIdentityMatch.add;
        if (!match) { return; }

        var pendingIdentity = {
            abha_number: $('#new_patient_abha_number').val(),
            abha_address: $('#new_patient_abha_address').val(),
            aadhaar_number: $('#new_patient_aadhaar_number').val(),
            date_of_birth: $('#new_patient_date_of_birth').val()
        };
        // Captured BEFORE hiding the Add modal - its hidden.bs.modal handler clears these.
        var pendingXToken = verifiedXToken.add;
        var pendingIdentitySource = verifiedIdentitySource.add;

        $('#addPatientModal').modal('hide');
        editPatientModal(match.id, function () {
            var existingAbhaNumber = $('#edit_new_patient_abha_number').val();
            var existingAbhaAddress = $('#edit_new_patient_abha_address').val();
            var existingAadhaarNumber = $('#edit_new_patient_aadhaar_number').val();

            // A real conflict only exists if this patient already has a DIFFERENT ABHA/Aadhaar on file.
            var hasConflict =
                (existingAbhaNumber && pendingIdentity.abha_number && existingAbhaNumber !== pendingIdentity.abha_number) ||
                (existingAbhaAddress && pendingIdentity.abha_address && existingAbhaAddress !== pendingIdentity.abha_address) ||
                (existingAadhaarNumber && pendingIdentity.aadhaar_number && existingAadhaarNumber !== pendingIdentity.aadhaar_number);
            var hasExistingIdentity = existingAbhaNumber || existingAadhaarNumber;

            if (hasConflict) {
                Swal.fire({ icon: 'warning', title: 'Already Linked', text: 'This patient already has a different ABHA/Aadhaar on file. Please review manually before changing it.', showConfirmButton: true });
                return;
            }

            if (!hasExistingIdentity) {
                // Nothing linked yet on this patient - apply the freshly verified identity.
                if (pendingIdentity.abha_number) { $('#edit_new_patient_abha_number').val(pendingIdentity.abha_number); }
                if (pendingIdentity.abha_address) { $('#edit_new_patient_abha_address').val(pendingIdentity.abha_address); }
                if (pendingIdentity.aadhaar_number) { $('#edit_new_patient_aadhaar_number').val(pendingIdentity.aadhaar_number); }
                if (pendingIdentity.date_of_birth) { $('#edit_new_patient_date_of_birth').val(pendingIdentity.date_of_birth); }
                verifiedIdentitySource.edit = pendingIdentitySource;
                // Same verified identity, same X-token - offer the card download here too instead of forcing a re-verify.
                showAbhaCardDownloadOption('edit', pendingXToken);
                if (pendingIdentity.abha_number || pendingIdentity.abha_address) {
                    $('#edit_identity_linked_abha_number').text(pendingIdentity.abha_number || '-');
                    $('#edit_identity_linked_abha_address').text(pendingIdentity.abha_address || '-');
                    $('#edit_identity_linked_summary').show();
                    $('#edit_identity_form').hide();
                }
                Swal.fire({ icon: 'info', title: 'Linked', text: 'The verified ABHA has been attached to this existing patient. Review the details and click Update to save.', showConfirmButton: true });
            } else {
                // Already linked, and it's the SAME identity just verified - nothing to change.
                Swal.fire({ icon: 'success', title: 'Already Linked', text: 'This patient already has this ABHA/Aadhaar on file - no changes needed. Review the details and click Update to save any other changes.', showConfirmButton: true });
            }
        });
    }
</script>
@endpush
