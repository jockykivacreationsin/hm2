@extends('layouts.app')
@section('title', 'IPD Treatment')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu', ['menu' => 'pf'])
        </div>
        <div class="card-body">
            <div class="row ipd-patient-treatment-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Treatment</div>
                    @if($new)
                        <div class="ipd-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="addIPDTreatmentModal();"><i class="fa fa-plus"></i> New</a></li>
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
                                    <th>Ward</th>
                                    <th>Date Time</th>
                                    <th>Prescribed By</th>
                                    <th>Added By</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($treatments as $row)
                                    <tr id="row_{{ $row->id }}">
                                        <td class="tr-sm-action-buttons center">
                                            @if($edit)
                                                <a href="javascript:void(0)" onclick="editIPDTreatmentModal({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                            @endif
                                            @if($delete)
                                                <a href="javascript:void(0)" onclick="deleteIPDTreatment({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $row->ward_category }} - {{ $row->bed_name }}</td>
                                        <td>{{ $row->treatment_date_time }}</td>
                                        <td>{{ $row->prescribed_by }}</td>
                                        <td>{{ $row->added_by }}</td>
                                        <td>{{ $row->remarks }}</td>
                                    </tr>
                                    <tr id="drow_{{ $row->id }}">
                                        <td></td>
                                        <td colspan="5">
                                            <table class="table table-bordered table-sm table-text-sm">
                                                <thead>
                                                    <tr>
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
                                                    @foreach($row->details as $tdrow)
                                                        <tr>
                                                            <td>{{ $tdrow->product_name }}</td>
                                                            <td>{{ $tdrow->dose_quantity }}</td>
                                                            <td>{{ $tdrow->frequency_display }}</td>
                                                            <td>{{ $tdrow->timing_names }}</td>
                                                            <td>{{ $tdrow->meal_name }}</td>
                                                            <td>{{ $tdrow->duration_days }} days</td>
                                                            <td>{{ $tdrow->condition_name ?: 'Not Specified' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bottom-center-btns">
        <a class="btn btn-success btn-rounded" target="_blank" href="{{ route('pdf.ipd.treatment', ['ipdId' => $ipd->id]) }}">Print</a>
    </div>
</div>

<div class="modal fade" id="addIPDTreatmentModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Treatments</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_treatment_form">
                <input type="hidden" id="ipd_id" name="ipd_id" value="{{ $ipd->id }}">
                <input type="hidden" name="bed_id" value="{{ $ipd->bed_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="treatment_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control select2" id="a_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option></option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-0">
                                        <label>Remarks</label>
                                        <input type="text" name="note" class="form-control">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="treatment_pmnor" value="0">
                            <div class="form-group local-forms">
                                <label>Medicine Details <span class="req-star">*</span></label>
                            </div>
                            
                            <div class="row">
                                <!-- Medicine -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="a_treatment_medicine_name" name="medicine_name" class="form-control select2">
                                            <option value="">-- Select Medicine --</option>
                                            @foreach($medicines as $med)
                                                @php
                                                    $display = $med->product_name;
                                                    if($med->unit_name) $display .= " - " . $med->unit_name;
                                                    if($med->route_name) $display .= " (" . $med->route_name . ")";
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
                                        <input type="number" step="0.5" min="0.5" id="a_treatment_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>

                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="a_treatment_frequency" name="frequency" class="form-control">
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
                                        <select id="a_treatment_meal" name="meal" class="form-control">
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
                                        <input type="number" min="1" id="a_treatment_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>

                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7">
                                    <div class="form-group local-forms">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="a_treatment_timing" name="timing[]" class="form-control select2" multiple>
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
                                        <select id="a_treatment_medical_condition_id" name="medical_condition_id" class="form-control select2">
                                            <option value="">-- Select Reason --</option>
                                            @foreach($conditions as $condition)
                                                @php
                                                    $cdisplay = $condition->condition_name;
                                                    if($condition->category) $cdisplay .= " [" . $condition->category . "]";
                                                @endphp
                                                <option value="{{ $condition->id }}">{{ $cdisplay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group local-forms">
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addTreatmentMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="treatment_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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

<div class="modal fade" id="editIPDTreatmentModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Treatments</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_treatment_form">
                <input type="hidden" id="ipd_treatment_id" name="ipd_treatment_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="treatment_date_time" name="treatment_date_time" class="form-control datetimepicker" value="" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control select2" id="e_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option></option>
                                            @foreach($doctors as $doc)
                                                <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-0">
                                        <label>Remarks</label>
                                        <input type="text" id="note" name="note" class="form-control">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="e_treatment_pmnor" value="0">
                            <div class="form-group local-forms">
                                <label>Medicine Details <span class="req-star">*</span></label>
                            </div>
                            
                            <div class="row">
                                <!-- Medicine -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="e_treatment_medicine_name" name="medicine_name" class="form-control select2">
                                            <option value="">-- Select Medicine --</option>
                                            @foreach($medicines as $med)
                                                @php
                                                    $display = $med->product_name;
                                                    if($med->unit_name) $display .= " - " . $med->unit_name;
                                                    if($med->route_name) $display .= " (" . $med->route_name . ")";
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
                                        <input type="number" step="0.5" min="0.5" id="e_treatment_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>

                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="e_treatment_frequency" name="frequency" class="form-control">
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
                                        <select id="e_treatment_meal" name="meal" class="form-control">
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
                                        <input type="number" min="1" id="e_treatment_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>

                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-7">
                                    <div class="form-group local-forms">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="e_treatment_timing" name="timing[]" class="form-control select2" multiple>
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
                                        <select id="e_treatment_medical_condition_id" name="medical_condition_id" class="form-control select2">
                                            <option value="">-- Select Reason --</option>
                                            @foreach($conditions as $condition)
                                                @php
                                                    $cdisplay = $condition->condition_name;
                                                    if($condition->category) $cdisplay .= " [" . $condition->category . "]";
                                                @endphp
                                                <option value="{{ $condition->id }}">{{ $cdisplay }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group local-forms">
                                        <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addETreatmentMedicine()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_treatment_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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
@include('ipd.partials.ajax-helpers')
<script>
var IPD_TREATMENT_URLS = {
    store:   @json(route('ipd.treatments.store', $ipd->id)),
    get:     @json(route('ipd.treatments.get', ['id' => '__ID__'])),
    update:  @json(route('ipd.treatments.update', ['id' => '__ID__'])),
    destroy: @json(route('ipd.treatments.destroy', ['id' => '__ID__']))
};

$(document).ready(function() {
    ipdBindForm('#add_ipd_treatment_form', {
        url: IPD_TREATMENT_URLS.store,
        requiredRows: '#treatment_medicine_tbl > tbody > tr'
    });
    ipdBindForm('#edit_ipd_treatment_form', {
        url: function () { return ipdUrl(IPD_TREATMENT_URLS.update, $('#edit_ipd_treatment_form #ipd_treatment_id').val()); },
        method: 'PUT',
        requiredRows: '#e_treatment_medicine_tbl > tbody > tr'
    });

    $('#addIPDTreatmentModal').on('shown.bs.modal', function () {
        $('#a_doctor_id').select2({ dropdownParent: $('#addIPDTreatmentModal'), width: '100%', placeholder: "Doctor" });
        $('#a_treatment_medicine_name').select2({ dropdownParent: $('#addIPDTreatmentModal'), width: '100%', placeholder: "-- Select Medicine --" });
        $('#a_treatment_timing').select2({ dropdownParent: $('#addIPDTreatmentModal'), width: '100%', placeholder: "Timing" });
        $('#a_treatment_medical_condition_id').select2({ dropdownParent: $('#addIPDTreatmentModal'), width: '100%', placeholder: "-- Select Reason --" });
    });
    $('#editIPDTreatmentModal').on('shown.bs.modal', function () {
        $('#e_doctor_id').select2({ dropdownParent: $('#editIPDTreatmentModal'), width: '100%', placeholder: "Doctor" });
        $('#e_treatment_medicine_name').select2({ dropdownParent: $('#editIPDTreatmentModal'), width: '100%', placeholder: "-- Select Medicine --" });
        $('#e_treatment_timing').select2({ dropdownParent: $('#editIPDTreatmentModal'), width: '100%', placeholder: "Timing" });
        $('#e_treatment_medical_condition_id').select2({ dropdownParent: $('#editIPDTreatmentModal'), width: '100%', placeholder: "-- Select Reason --" });
    });
});

function editIPDTreatmentModal(id) {
    $("#editIPDTreatmentModal").modal('show');
    ipdFetch(ipdUrl(IPD_TREATMENT_URLS.get, id), function (data) {
        $("#edit_ipd_treatment_form #ipd_treatment_id").val(data.row.id);
        $("#edit_ipd_treatment_form #treatment_date_time").val(data.row.treatment_date_time);
        $("#edit_ipd_treatment_form #e_doctor_id").html('<option value="' + data.row.doctor_id + '" selected>' + $('<div>').text(data.row.staff_name).html() + '</option>');
        $("#edit_ipd_treatment_form #note").val(data.row.remarks);
        $("#edit_ipd_treatment_form #e_treatment_pmnor").val(data.nor);
        $("#e_treatment_medicine_tbl tbody").html(data.table_html);
    });
}

function deleteIPDTreatment(id) {
    ipdConfirmDelete(ipdUrl(IPD_TREATMENT_URLS.destroy, id), id);
}
</script>
@endpush
