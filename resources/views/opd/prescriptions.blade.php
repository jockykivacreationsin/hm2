@extends('layouts.app')
@section('title', 'OPD Prescriptions')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('opd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-prescription-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Prescription</div>
                    @if($ps->hasPermission('b_new_opd_prescription'))
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addOPDPrescriptionModal();"><i class="fa fa-plus"></i> New</a></li>
                        </ul>
                    </div>
                    @endif
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr3btns"></th>
                                    <th>ID</th>
                                    <th>Date Time</th>
                                    <th>Doctor</th>
                                    <th>Findings</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prescriptions as $rx)
                                <tr id="row_{{ $rx->id }}">
                                    <td class="tr-sm-action-buttons center">
                                        @if($ps->hasPermission('b_edit_opd_prescription'))
                                        <a href="javascript:void(0)" onclick="editOPDPrescriptionModal({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                        <a href="{{ route('pdf.opd.prescription', ['opdId' => $opd->id, 'prescription_id' => $rx->id]) }}" target="_blank" data-bs-toggle="tooltip" data-bs-placement="top" title="View PDF"><i class="fas fa-file-pdf"></i></a>
                                        @if($ps->hasPermission('b_delete_opd_prescription'))
                                        <a href="javascript:void(0)" onclick="deleteOPDPrescription({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                        @endif
                                    </td>
                                    <td>{{ $rx->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($rx->prescription_date_time)->format('d-m-Y h:i A') }}</td>
                                    <td>{{ $rx->doctor?->staff_name ?? '' }}</td>
                                    <td>{{ $rx->findings ?? '' }}</td>
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

{{-- Add Prescription Modal --}}
<div class="modal fade" id="addOPDPrescriptionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Prescriptions</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_opd_prescription_form" data-action="{{ route('opd.prescriptions.store', $opd->id) }}" data-method="POST" novalidate>
                @csrf
                <input type="hidden" id="opd_id" name="opd_id" value="{{ $opd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="prescription_date_time" class="form-control datetimepicker" value="{{ now()->format('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control select2" id="a_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option value="">-- Select Doctor --</option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}" {{ ($opd->doctor_id == $doc->id) ? 'selected' : '' }}>{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Lab Tests</label>
                                        <select class="form-control select2" id="a_pathology_tests" data-lookup-url="{{ route('lab-results.dropdown.pathology-tests') }}" name="pathology_tests[]" multiple>
                                            @foreach($pathologyCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Radiology Tests</label>
                                        <select class="form-control select2" id="a_radiology_tests" data-lookup-url="{{ route('lab-results.dropdown.radiology-tests') }}" name="radiology_tests[]" multiple>
                                            @foreach($radiologyCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Procedures</label>
                                        <select class="form-control select2" id="a_procedures" data-lookup-url="{{ route('lab-results.dropdown.procedures') }}" name="procedures[]" multiple>
                                            @foreach($procedureCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-0">
                                        <label>Findings</label>
                                        <input type="text" name="findings" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="pmnor" value="0">
                            <h6 class="mb-10">Medicine Details</h6>
                            <div class="row">
                                <!-- Medicine Selection from pharma_products -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="a_pharma_product_id" name="pharma_product_id" class="form-control select2">
                                            <option value="">-- Select Medicine --</option>
                                            @foreach($medicines as $med)
                                                @php
                                                    $display = $med->product_name;
                                                    if ($med->doseUnit?->unit_name) $display .= ' - ' . $med->doseUnit->unit_name;
                                                    if ($med->route?->route_name) $display .= ' (' . $med->route->route_name . ')';
                                                @endphp
                                                <option value="{{ $med->id }}">{{ $display }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dose Quantity -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Quantity <span class="req-star">*</span></label>
                                        <input type="number" step="0.5" min="0.5" id="a_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>

                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="a_frequency_type_id" name="frequency_type_id" class="form-control">
                                            @foreach($frequencies as $freq)
                                                <option value="{{ $freq->id }}">{{ $freq->display_text }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Meal Relation -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Meal</label>
                                        <select id="a_meal_relation_id" name="meal_relation_id" class="form-control">
                                            @foreach($meals as $meal)
                                                <option value="{{ $meal->id }}">{{ $meal->relation_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Duration Days -->
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>Days <span class="req-star">*</span></label>
                                        <input type="number" min="1" id="a_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7">
                                    <div class="form-group local-forms">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="a_timing_ids" name="timing_ids[]" class="form-control select2" multiple required>
                                            @foreach($timings as $timing)
                                                <option value="{{ $timing->id }}">{{ $timing->timing_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Reason/Diagnosis -->
                                <div class="col-12 col-sm-5 add-medicine-brow">
                                    <div class="form-group local-forms flex-1">
                                        <label>Reason/Diagnosis (Why?)</label>
                                        <select id="a_medical_condition_id" name="medical_condition_id" class="form-control select2">
                                            <option value="">-- Select Reason --</option>
                                            @foreach($conditions as $cond)
                                                @php
                                                    $condDisplay = $cond->condition_name;
                                                    if ($cond->category) $condDisplay .= ' [' . $cond->category . ']';
                                                @endphp
                                                <option value="{{ $cond->id }}">{{ $condDisplay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group local-forms">
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Medicine</th>
                                                    <th>Dose</th>
                                                    <th>Frequency</th>
                                                    <th>Timing</th>
                                                    <th>Meal</th>
                                                    <th>Duration</th>
                                                    <th>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group local-forms">
                                        <label>Follow-up Detail</label>
                                        <input type="text" id="a_follow_up_detail" name="follow_up_detail" class="form-control" placeholder="Follow-up instructions or next visit details">
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

{{-- Edit Prescription Modal --}}
<div class="modal fade" id="editOPDPrescriptionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Prescriptions</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_opd_prescription_form" novalidate>
                @csrf
                <input type="hidden" id="opd_prescription_id" name="opd_prescription_id" value="">
                <input type="hidden" id="e_opd_id" name="opd_id" value="{{ $opd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="prescription_date_time" name="prescription_date_time" class="form-control datetimepicker" value="" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
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
                                        <label>Lab Tests</label>
                                        <select class="form-control select2" id="e_pathology_tests" data-lookup-url="{{ route('lab-results.dropdown.pathology-tests') }}" name="pathology_tests[]" multiple>
                                            @foreach($pathologyCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Radiology Tests</label>
                                        <select class="form-control select2" id="e_radiology_tests" data-lookup-url="{{ route('lab-results.dropdown.radiology-tests') }}" name="radiology_tests[]" multiple>
                                            @foreach($radiologyCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Procedures</label>
                                        <select class="form-control select2" id="e_procedures" data-lookup-url="{{ route('lab-results.dropdown.procedures') }}" name="procedures[]" multiple>
                                            @foreach($procedureCharges as $c)
                                                <option value="{{ $c->id }}">{{ $c->charge_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-0">
                                        <label>Findings</label>
                                        <input type="text" id="findings" name="findings" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="epmnor" value="0">
                            <h6 class="mb-10">Medicine Details</h6>
                            <div class="row">
                                <!-- Medicine Selection from pharma_products -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="e_pharma_product_id" name="pharma_product_id" class="form-control select2">
                                            <option value="">-- Select Medicine --</option>
                                            @foreach($medicines as $med)
                                                @php
                                                    $display = $med->product_name;
                                                    if ($med->doseUnit?->unit_name) $display .= ' - ' . $med->doseUnit->unit_name;
                                                    if ($med->route?->route_name) $display .= ' (' . $med->route->route_name . ')';
                                                @endphp
                                                <option value="{{ $med->id }}">{{ $display }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dose Quantity -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Quantity <span class="req-star">*</span></label>
                                        <input type="number" step="0.5" min="0.5" id="e_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>

                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="e_frequency_type_id" name="frequency_type_id" class="form-control">
                                            @foreach($frequencies as $freq)
                                                <option value="{{ $freq->id }}">{{ $freq->display_text }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Meal Relation -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Meal</label>
                                        <select id="e_meal_relation_id" name="meal_relation_id" class="form-control">
                                            @foreach($meals as $meal)
                                                <option value="{{ $meal->id }}">{{ $meal->relation_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Duration Days -->
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>Days <span class="req-star">*</span></label>
                                        <input type="number" min="1" id="e_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7">
                                    <div class="form-group local-forms">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="e_timing_ids" name="timing_ids[]" class="form-control select2" multiple required>
                                            @foreach($timings as $timing)
                                                <option value="{{ $timing->id }}">{{ $timing->timing_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Reason/Diagnosis -->
                                <div class="col-12 col-sm-5 add-medicine-brow">
                                    <div class="form-group local-forms flex-1">
                                        <label>Reason/Diagnosis (Why?)</label>
                                        <select id="e_medical_condition_id" name="medical_condition_id" class="form-control select2">
                                            <option value="">-- Select Reason --</option>
                                            @foreach($conditions as $cond)
                                                @php
                                                    $condDisplay = $cond->condition_name;
                                                    if ($cond->category) $condDisplay .= ' [' . $cond->category . ']';
                                                @endphp
                                                <option value="{{ $cond->id }}">{{ $condDisplay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group local-forms">
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addEPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Medicine</th>
                                                    <th>Dose</th>
                                                    <th>Frequency</th>
                                                    <th>Timing</th>
                                                    <th>Meal</th>
                                                    <th>Duration</th>
                                                    <th>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <div class="form-group local-forms">
                                        <label>Follow-up Detail</label>
                                        <input type="text" id="e_follow_up_detail" name="follow_up_detail" class="form-control" placeholder="Follow-up instructions or next visit details">
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
        if (typeof showDropDownPathologyTests === 'function') {
            showDropDownPathologyTests();
        }
        if (typeof showDropDownRadiologyTests === 'function') {
            showDropDownRadiologyTests();
        }
        if (typeof showDropDownProcedures === 'function') {
            showDropDownProcedures();
        }

        // Initialize select2 for medicine and timing dropdowns
        $('#a_pharma_product_id, #e_pharma_product_id').select2({
            placeholder: 'Search medicine...',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#addOPDPrescriptionModal').is(':visible') ? $('#addOPDPrescriptionModal') : undefined
        });

        $('#a_timing_ids').select2({
            placeholder: 'Select timing (e.g., Morning + Evening)',
            closeOnSelect: false,
            width: '100%'
        });

        $('#e_timing_ids').select2({
            placeholder: 'Select timing (e.g., Morning + Evening)',
            closeOnSelect: false,
            width: '100%'
        });

        $('#a_medical_condition_id, #e_medical_condition_id').select2({
            placeholder: 'Search diagnosis...',
            allowClear: true,
            width: '100%'
        });

        // Add Modal Select2 dropdownParent configuration
        $('#addOPDPrescriptionModal').on('shown.bs.modal', function () {
            $('#a_pharma_product_id').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
            $('#a_timing_ids').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%', closeOnSelect: false });
            $('#a_medical_condition_id').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
            $('#a_pathology_tests').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
            $('#a_radiology_tests').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
            $('#a_procedures').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
            $('#a_doctor_id').select2({ dropdownParent: $('#addOPDPrescriptionModal'), width: '100%' });
        });

        // Edit Modal Select2 dropdownParent configuration
        $('#editOPDPrescriptionModal').on('shown.bs.modal', function () {
            $('#e_pharma_product_id').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
            $('#e_timing_ids').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%', closeOnSelect: false });
            $('#e_medical_condition_id').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
            $('#e_pathology_tests').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
            $('#e_radiology_tests').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
            $('#e_procedures').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
            $('#e_doctor_id').select2({ dropdownParent: $('#editOPDPrescriptionModal'), width: '100%' });
        });
    });

    // Add prescription function fallback if not in app.js
    if (typeof addOPDPrescriptionModal !== 'function') {
        function addOPDPrescriptionModal() {
            $('#addOPDPrescriptionModal').modal('show');
        }
    }

    // Add prescription medicine fallback
    if (typeof addPrescriptionMedicine !== 'function') {
        function addPrescriptionMedicine() {
            var pharma_product_id = $('#a_pharma_product_id').val();
            var pharma_product_text = $('#a_pharma_product_id option:selected').text();
            var dose_quantity = $('#a_dose_quantity').val();
            var frequency_type_id = $('#a_frequency_type_id').val();
            var frequency_text = $('#a_frequency_type_id option:selected').text();
            var meal_relation_id = $('#a_meal_relation_id').val();
            var meal_text = $('#a_meal_relation_id option:selected').text();
            var duration_days = $('#a_duration_days').val();
            var timing_ids = $('#a_timing_ids').val();
            var timing_text = $('#a_timing_ids option:selected').map(function() { return $(this).text(); }).get().join(' + ');
            var medical_condition_id = $('#a_medical_condition_id').val();
            var condition_text = medical_condition_id ? $('#a_medical_condition_id option:selected').text() : 'Not Specified';

            if (!pharma_product_id || !dose_quantity || !frequency_type_id || !duration_days || !timing_ids || timing_ids.length === 0) {
                Swal.fire({
                    icon: "error",
                    title: "Required!",
                    text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
                    showConfirmButton: false,
                    timer: 2000
                });
                return;
            }

            var rowNum = parseInt($('#pmnor').val()) + 1;
            $('#pmnor').val(rowNum);

            var timing_hidden = '';
            if (timing_ids && timing_ids.length > 0) {
                timing_ids.forEach(function(tid) {
                    timing_hidden += '<input type="hidden" name="timing_ids_' + rowNum + '[]" value="' + tid + '">';
                });
            }

            var html = '<tr id="pmr_' + rowNum + '">';
            html += '<td><a href="javascript:void(0)" onclick="removePrescriptionMedicine(' + rowNum + ')"><i class="fas fa-times"></i></a></td>';
            html += '<td>' + pharma_product_text + '<input type="hidden" name="pharma_product_id_value[]" value="' + pharma_product_id + '"></td>';
            html += '<td>' + dose_quantity + '<input type="hidden" name="dose_quantity_value[]" value="' + dose_quantity + '"></td>';
            html += '<td>' + frequency_text + '<input type="hidden" name="frequency_type_id_value[]" value="' + frequency_type_id + '"></td>';
            html += '<td>' + timing_text + timing_hidden + '</td>';
            html += '<td>' + meal_text + '<input type="hidden" name="meal_relation_id_value[]" value="' + meal_relation_id + '"></td>';
            html += '<td>' + duration_days + ' days<input type="hidden" name="duration_days_value[]" value="' + duration_days + '"></td>';
            html += '<td>' + condition_text + '<input type="hidden" name="medical_condition_id_value[]" value="' + (medical_condition_id || '') + '"></td>';
            html += '</tr>';

            $('#prescription_medicine_tbl tbody').append(html);

            $('#a_pharma_product_id').val('').trigger('change');
            $('#a_dose_quantity').val('1');
            $('#a_duration_days').val('5');
            $('#a_timing_ids').val(null).trigger('change');
            $('#a_medical_condition_id').val('').trigger('change');
        }
    }

    if (typeof removePrescriptionMedicine !== 'function') {
        function removePrescriptionMedicine(rowNum) {
            $('#pmr_' + rowNum).remove();
        }
    }

    if (typeof addEPrescriptionMedicine !== 'function') {
        function addEPrescriptionMedicine() {
            var pharma_product_id = $('#e_pharma_product_id').val();
            var pharma_product_text = $('#e_pharma_product_id option:selected').text();
            var dose_quantity = $('#e_dose_quantity').val();
            var frequency_type_id = $('#e_frequency_type_id').val();
            var frequency_text = $('#e_frequency_type_id option:selected').text();
            var meal_relation_id = $('#e_meal_relation_id').val();
            var meal_text = $('#e_meal_relation_id option:selected').text();
            var duration_days = $('#e_duration_days').val();
            var timing_ids = $('#e_timing_ids').val();
            var timing_text = $('#e_timing_ids option:selected').map(function() { return $(this).text(); }).get().join(' + ');
            var medical_condition_id = $('#e_medical_condition_id').val();
            var condition_text = medical_condition_id ? $('#e_medical_condition_id option:selected').text() : 'Not Specified';

            if (!pharma_product_id || !dose_quantity || !frequency_type_id || !duration_days || !timing_ids || timing_ids.length === 0) {
                Swal.fire({
                    icon: "error",
                    title: "Required!",
                    text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
                    showConfirmButton: false,
                    timer: 2000
                });
                return;
            }

            var rowNum = parseInt($('#epmnor').val()) + 1;
            $('#epmnor').val(rowNum);

            var timing_hidden = '';
            if (timing_ids && timing_ids.length > 0) {
                timing_ids.forEach(function(tid) {
                    timing_hidden += '<input type="hidden" name="timing_ids_' + rowNum + '[]" value="' + tid + '">';
                });
            }

            var html = '<tr id="epmr_' + rowNum + '">';
            html += '<td><a href="javascript:void(0)" onclick="removeEPrescriptionMedicine(' + rowNum + ')"><i class="fas fa-times"></i></a></td>';
            html += '<td>' + pharma_product_text + '<input type="hidden" name="pharma_product_id_value[]" value="' + pharma_product_id + '"></td>';
            html += '<td>' + dose_quantity + '<input type="hidden" name="dose_quantity_value[]" value="' + dose_quantity + '"></td>';
            html += '<td>' + frequency_text + '<input type="hidden" name="frequency_type_id_value[]" value="' + frequency_type_id + '"></td>';
            html += '<td>' + timing_text + timing_hidden + '</td>';
            html += '<td>' + meal_text + '<input type="hidden" name="meal_relation_id_value[]" value="' + meal_relation_id + '"></td>';
            html += '<td>' + duration_days + ' days<input type="hidden" name="duration_days_value[]" value="' + duration_days + '"></td>';
            html += '<td>' + condition_text + '<input type="hidden" name="medical_condition_id_value[]" value="' + (medical_condition_id || '') + '"></td>';
            html += '</tr>';

            $('#e_prescription_medicine_tbl tbody').append(html);

            $('#e_pharma_product_id').val('').trigger('change');
            $('#e_dose_quantity').val('1');
            $('#e_duration_days').val('5');
            $('#e_timing_ids').val(null).trigger('change');
            $('#e_medical_condition_id').val('').trigger('change');
        }
    }

    if (typeof removeEPrescriptionMedicine !== 'function') {
        function removeEPrescriptionMedicine(rowNum) {
            $('#epmr_' + rowNum).remove();
        }
    }

/* appForm:wired */
appForm.bind('#add_opd_prescription_form', {
        validate: function () { return (($('#a_pathology_tests').val() || []).length || ($('#a_radiology_tests').val() || []).length || ($('#a_procedures').val() || []).length || $('#prescription_medicine_tbl > tbody > tr').length) ? true : 'Please Enter Some Data'; }
    });
    appForm.bind('#edit_opd_prescription_form', {
        method: 'PUT',
        url: function () { return "{{ route('opd.prescriptions.update', ['rxId' => '__ID__']) }}".replace('__ID__', $('#opd_prescription_id').val()); },
        validate: function () { return (($('#e_pathology_tests').val() || []).length || ($('#e_radiology_tests').val() || []).length || ($('#e_procedures').val() || []).length || $('#e_prescription_medicine_tbl > tbody > tr').length) ? true : 'Please Enter Some Data'; }
    });
</script>
@endpush
