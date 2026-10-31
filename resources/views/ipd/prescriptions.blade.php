@extends('layouts.app')
@section('title', 'IPD Prescriptions')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-prescription-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Prescription</div>
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="{{ route('pdf.ipd.prescription', $ipd->id) }}" target="_blank"><i class="fas fa-file-prescription"></i> PDF</a></li>
                            @if($ps->hasPermission('b_ipd_new_prescription'))
                            <li><a href="javascript:void(0)" onclick="addIPDPrescriptionModal();"><i class="fa fa-plus"></i> New</a></li>
                            @endif
                        </ul>
                    </div>
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
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
                                        @if($ps->hasPermission('b_ipd_edit_prescription'))
                                        <a href="javascript:void(0)" onclick="editIPDPrescriptionModal({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                        @endif
                                        @if($ps->hasPermission('b_ipd_delete_prescription'))
                                        <a href="javascript:void(0)" onclick="deleteIPDPrescription({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
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
<div class="modal fade" id="addIPDPrescriptionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Prescriptions</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_prescription_form">
                @csrf
                <input type="hidden" name="save_ipd_prescription" value="Yes">
                <input type="hidden" id="ipd_id" name="ipd_id" value="{{ $ipd->id }}">
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
                                                <option value="{{ $doc->id }}" {{ $ipd->doctor_id == $doc->id ? 'selected' : '' }}>{{ $doc->staff_name }}</option>
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
                            <input type="hidden" id="ipd_pmnor" value="0">
                            <div class="form-group local-forms">
                                <label>Medicine Details <span class="req-star">*</span></label>
                            </div>
                            <div class="row">
                                <!-- Medicine -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="a_ipd_medicine_name" name="medicine_name" class="form-control select2">
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
                                        <input type="number" step="0.5" min="0.5" id="a_ipd_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>
                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="a_ipd_frequency" name="frequency" class="form-control">
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
                                        <select id="a_ipd_meal" name="meal" class="form-control">
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
                                        <input type="number" min="1" id="a_ipd_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>
                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7 add-medicine-brow">
                                    <div class="form-group local-forms flex-1">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="a_ipd_timing" name="timing[]" class="form-control select2" multiple>
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
                                        <select id="a_ipd_medical_condition_id" name="medical_condition_id" class="form-control select2">
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
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addIPDPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="ipd_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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
<div class="modal fade" id="editIPDPrescriptionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Prescriptions</h5>
                <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_prescription_form">
                @csrf
                <input type="hidden" name="update_ipd_prescription" value="Yes">
                <input type="hidden" id="ipd_prescription_id" name="ipd_prescription_id" value="">
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
                            <input type="hidden" id="e_ipd_pmnor" value="0">
                            <div class="form-group local-forms">
                                <label>Medicine Details <span class="req-star">*</span></label>
                            </div>
                            <div class="row">
                                <!-- Medicine -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="e_ipd_medicine_name" name="medicine_name" class="form-control select2">
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
                                        <input type="number" step="0.5" min="0.5" id="e_ipd_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>
                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="e_ipd_frequency" name="frequency" class="form-control">
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
                                        <select id="e_ipd_meal" name="meal" class="form-control">
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
                                        <input type="number" min="1" id="e_ipd_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>
                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7 add-medicine-brow">
                                    <div class="form-group local-forms flex-1">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="e_ipd_timing" name="timing[]" class="form-control select2" multiple>
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
                                        <select id="e_ipd_medical_condition_id" name="medical_condition_id" class="form-control select2">
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
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addEIPDPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_ipd_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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
    // Initialize datetimepicker flatpickr
    $(".datetimepicker").flatpickr({
        enableTime: true,
        dateFormat: "d-m-Y h:i K"
    });

    // Initialize Select2 in modals
    $('#addIPDPrescriptionModal').on('shown.bs.modal', function() {
        $('#addIPDPrescriptionModal .select2').select2({
            dropdownParent: $('#addIPDPrescriptionModal'),
            width: '100%'
        });
    });

    $('#editIPDPrescriptionModal').on('shown.bs.modal', function() {
        $('#editIPDPrescriptionModal .select2').select2({
            dropdownParent: $('#editIPDPrescriptionModal'),
            width: '100%'
        });
    });

    // Submit Add Prescription Form
    $("#add_ipd_prescription_form").on('submit', function(e) {
        e.preventDefault();
        var medicines_list = $('#ipd_prescription_medicine_tbl >tbody >tr').length;
        var pathology_tests = $("#a_pathology_tests").val() || [];
        var radiology_tests = $("#a_radiology_tests").val() || [];
        var procedures = $("#a_procedures").val() || [];
        if (pathology_tests.length == 0 && radiology_tests.length == 0 && procedures.length == 0 && medicines_list == 0) {
            Swal.fire({
                icon: "error",
                title: "Required!",
                text: "Please Enter Some Data (Tests, Procedures or Medicines)",
                showConfirmButton: false,
                timer: 2000
            });
            return;
        }

        var formData = new FormData(this);
        $.ajax({
            url: "{{ route('ipd.prescriptions.store', $ipd->id) }}",
            type: "POST",
            data: formData,
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
                setTimeout(function() {
                    location.reload();
                }, 1200);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Error saving prescription.";
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });

    // Submit Edit Prescription Form
    $("#edit_ipd_prescription_form").on('submit', function(e) {
        e.preventDefault();
        var medicines_list = $('#e_ipd_prescription_medicine_tbl >tbody >tr').length;
        var pathology_tests = $("#e_pathology_tests").val() || [];
        var radiology_tests = $("#e_radiology_tests").val() || [];
        var procedures = $("#e_procedures").val() || [];
        if (pathology_tests.length == 0 && radiology_tests.length == 0 && procedures.length == 0 && medicines_list == 0) {
            Swal.fire({
                icon: "error",
                title: "Required!",
                text: "Please Enter Some Data (Tests, Procedures or Medicines)",
                showConfirmButton: false,
                timer: 2000
            });
            return;
        }

        var rxId = $("#edit_ipd_prescription_form #ipd_prescription_id").val();
        var formData = new FormData(this);
        $.ajax({
            url: "{{ route('ipd.prescriptions.update', ['__ID__']) }}".replace('__ID__', rxId),
            type: "POST",
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: "Updated Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function() {
                    location.reload();
                }, 1200);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : "Error updating prescription.";
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
});

function addIPDPrescriptionModal() {
    $("#addIPDPrescriptionModal").modal('show');
}

function addIPDPrescriptionMedicine() {
    var pmnor = parseInt($("#ipd_pmnor").val()) || 0;
    
    var pharma_product_id = $("#a_ipd_medicine_name").val();
    var medicine_name = $("#a_ipd_medicine_name option:selected").text();
    var dose_quantity = $("#a_ipd_dose_quantity").val();
    var frequency_type_id = $("#a_ipd_frequency").val();
    var frequency_text = $("#a_ipd_frequency option:selected").text();
    var timing_ids = $("#a_ipd_timing").val() || [];
    var timing_text = $("#a_ipd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
    var meal_relation_id = $("#a_ipd_meal").val();
    var meal_text = $("#a_ipd_meal option:selected").text();
    var duration_days = $("#a_ipd_duration_days").val();
    var medical_condition_id = $("#a_ipd_medical_condition_id").val();
    var condition_text = medical_condition_id ? $("#a_ipd_medical_condition_id option:selected").text() : 'Not Specified';
    
    // Validation
    if (!pharma_product_id || !dose_quantity || !frequency_type_id || timing_ids.length === 0 || !duration_days) {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
            showConfirmButton: false,
            timer: 2000
        });
        return false;
    }
    
    pmnor = pmnor + 1;
    $("#ipd_pmnor").val(pmnor);
    
    var timing_hidden_inputs = '';
    for (var i = 0; i < timing_ids.length; i++) {
        timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
    }
    
    $("#ipd_prescription_medicine_tbl tbody").append(
        '<tr id="ipd_pmr_' + pmnor + '">' +
            '<td><a href="javascript:void(0)" onclick="removeIPDPrescriptionMedicine(' + pmnor + ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
            '<td>' + medicine_name + '<input type="hidden" name="pharma_product_id_value[]" value="' + pharma_product_id + '"></td>' +
            '<td>' + dose_quantity + '<input type="hidden" name="dose_quantity_value[]" value="' + dose_quantity + '"></td>' +
            '<td>' + frequency_text + '<input type="hidden" name="frequency_type_id_value[]" value="' + frequency_type_id + '"></td>' +
            '<td>' + timing_text + timing_hidden_inputs + '</td>' +
            '<td>' + meal_text + '<input type="hidden" name="meal_relation_id_value[]" value="' + meal_relation_id + '"></td>' +
            '<td>' + duration_days + ' days<input type="hidden" name="duration_days_value[]" value="' + duration_days + '"></td>' +
            '<td>' + condition_text + '<input type="hidden" name="medical_condition_id_value[]" value="' + (medical_condition_id || '') + '"></td>' +
        '</tr>'
    );
    
    // Reset inputs
    $("#a_ipd_medicine_name").val('').trigger('change');
    $("#a_ipd_dose_quantity").val('1');
    $("#a_ipd_timing").val(null).trigger('change');
    $("#a_ipd_duration_days").val('5');
    $("#a_ipd_medical_condition_id").val('').trigger('change');
    $("#a_ipd_medicine_name").focus();
}

function removeIPDPrescriptionMedicine(nor) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to remove this medicine!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes'
    }).then(function(result) {
        if (result.isConfirmed) {
            $("#ipd_pmr_" + nor).remove();
        }
    });
}

function addEIPDPrescriptionMedicine() {
    var pmnor = parseInt($("#e_ipd_pmnor").val()) || 0;
    
    var pharma_product_id = $("#e_ipd_medicine_name").val();
    var medicine_name = $("#e_ipd_medicine_name option:selected").text();
    var dose_quantity = $("#e_ipd_dose_quantity").val();
    var frequency_type_id = $("#e_ipd_frequency").val();
    var frequency_text = $("#e_ipd_frequency option:selected").text();
    var timing_ids = $("#e_ipd_timing").val() || [];
    var timing_text = $("#e_ipd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
    var meal_relation_id = $("#e_ipd_meal").val();
    var meal_text = $("#e_ipd_meal option:selected").text();
    var duration_days = $("#e_ipd_duration_days").val();
    var medical_condition_id = $("#e_ipd_medical_condition_id").val();
    var condition_text = medical_condition_id ? $("#e_ipd_medical_condition_id option:selected").text() : 'Not Specified';
    
    // Validation
    if (!pharma_product_id || !dose_quantity || !frequency_type_id || timing_ids.length === 0 || !duration_days) {
        Swal.fire({
            icon: "error",
            title: "Required!",
            text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
            showConfirmButton: false,
            timer: 2000
        });
        return false;
    }
    
    pmnor = pmnor + 1;
    $("#e_ipd_pmnor").val(pmnor);
    
    var timing_hidden_inputs = '';
    for (var i = 0; i < timing_ids.length; i++) {
        timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
    }
    
    $("#e_ipd_prescription_medicine_tbl tbody").append(
        '<tr id="e_ipd_pmr_' + pmnor + '">' +
            '<td><a href="javascript:void(0)" onclick="removeEIPDPrescriptionMedicine(' + pmnor + ')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
            '<td>' + medicine_name + '<input type="hidden" name="pharma_product_id_value[]" value="' + pharma_product_id + '"></td>' +
            '<td>' + dose_quantity + '<input type="hidden" name="dose_quantity_value[]" value="' + dose_quantity + '"></td>' +
            '<td>' + frequency_text + '<input type="hidden" name="frequency_type_id_value[]" value="' + frequency_type_id + '"></td>' +
            '<td>' + timing_text + timing_hidden_inputs + '</td>' +
            '<td>' + meal_text + '<input type="hidden" name="meal_relation_id_value[]" value="' + meal_relation_id + '"></td>' +
            '<td>' + duration_days + ' days<input type="hidden" name="duration_days_value[]" value="' + duration_days + '"></td>' +
            '<td>' + condition_text + '<input type="hidden" name="medical_condition_id_value[]" value="' + (medical_condition_id || '') + '"></td>' +
        '</tr>'
    );
    
    // Reset inputs
    $("#e_ipd_medicine_name").val('').trigger('change');
    $("#e_ipd_dose_quantity").val('1');
    $("#e_ipd_timing").val(null).trigger('change');
    $("#e_ipd_duration_days").val('5');
    $("#e_ipd_medical_condition_id").val('').trigger('change');
    $("#e_ipd_medicine_name").focus();
}

function removeEIPDPrescriptionMedicine(nor) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to remove this medicine!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes'
    }).then(function(result) {
        if (result.isConfirmed) {
            $("#e_ipd_pmr_" + nor).remove();
        }
    });
}

function editIPDPrescriptionModal(id) {
    $.ajax({
        type: 'GET',
        url: "{{ route('ipd.prescriptions.get', ['__ID__']) }}".replace('__ID__', id),
        dataType: 'json',
        success: function(data) {
            var row = data.row || data.data;
            $("#edit_ipd_prescription_form #ipd_prescription_id").val(row.id);
            $("#edit_ipd_prescription_form #prescription_date_time").val(row.prescription_date_time);
            $("#edit_ipd_prescription_form #e_doctor_id").val(row.doctor_id).trigger('change');
            
            var pathology_tests = row.pathology_tests || '';
            if (pathology_tests) {
                var pathology_tests_array = pathology_tests.split(',');
                $("#edit_ipd_prescription_form #e_pathology_tests").val(pathology_tests_array).trigger('change');
            } else {
                $("#edit_ipd_prescription_form #e_pathology_tests").val(null).trigger('change');
            }

            var radiology_tests = row.radiology_tests || '';
            if (radiology_tests) {
                var radiology_tests_array = radiology_tests.split(',');
                $("#edit_ipd_prescription_form #e_radiology_tests").val(radiology_tests_array).trigger('change');
            } else {
                $("#edit_ipd_prescription_form #e_radiology_tests").val(null).trigger('change');
            }

            var procedures = row.procedures || '';
            if (procedures) {
                var procedures_array = procedures.split(',');
                $("#edit_ipd_prescription_form #e_procedures").val(procedures_array).trigger('change');
            } else {
                $("#edit_ipd_prescription_form #e_procedures").val(null).trigger('change');
            }

            $("#edit_ipd_prescription_form #findings").val(row.findings || '');
            $("#edit_ipd_prescription_form #e_ipd_pmnor").val(data.nor || 0);
            $("#e_ipd_prescription_medicine_tbl tbody").html(data.table_html || '');
            
            $("#editIPDPrescriptionModal").modal('show');
        },
        error: function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load prescription details.' });
        }
    });
}

function deleteIPDPrescription(id) {
    Swal.fire({
        title: "Delete?",
        text: "Are you sure you want to delete!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                type: 'DELETE',
                url: "{{ route('ipd.prescriptions.destroy', ['__ID__']) }}".replace('__ID__', id),
                data: { _token: '{{ csrf_token() }}' },
                dataType: 'json',
                success: function(data) {
                    $("#row_" + id).remove();
                    Swal.fire({
                        icon: "success",
                        title: "Deleted!",
                        text: "Prescription deleted successfully.",
                        showConfirmButton: false,
                        timer: 1500
                    });
                },
                error: function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to delete prescription.' });
                }
            });
        }
    });
}
</script>
@endpush
