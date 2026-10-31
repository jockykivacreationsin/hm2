@extends('layouts.app')
@section('title', 'DCD Prescriptions - ' . ($dcd->patient?->patient_name ?? ''))

@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">
      @include('dcd.partials.menu')
    </div>
    <div class="card-body">
      <div class="row ipd-patient-prescription-row">
        <div class="ipd-title-with-buttons row-bottom-border">
          <div class="ipd-title">Prescription</div>
          @if($newAllowed)
          <div class="ipd-buttons">
            <ul>
              <li><a href="javascript:void(0)" onclick="addDCDPrescriptionModal();"><i class="fa fa-plus"></i> New</a></li>
            </ul>
          </div>
          @endif
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
                    @if($editAllowed)
                    <a href="javascript:void(0)" onclick="editDCDPrescriptionModal({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                    @endif
                    @if($deleteAllowed)
                    <a href="javascript:void(0)" onclick="deleteDCDPrescription({{ $rx->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                    @endif
                  </td>
                  <td>{{ $rx->id }}</td>
                  <td>{{ \Carbon\Carbon::parse($rx->prescription_date_time)->format('d-m-Y h:i A') }}</td>
                  <td>{{ $rx->doctor?->staff_name }}</td>
                  <td>{{ $rx->findings }}</td>
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

<div class="modal fade" id="addDCDPrescriptionModal">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Prescriptions</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <form id="add_dcd_prescription_form" action="{{ route('dcd.prescriptions.store', $dcd->id) }}" method="POST">
        @csrf
        <input type="hidden" id="dcd_id" name="dcd_id" value="{{ $dcd->id }}">
        <div class="modal-body">
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <div class="col-12 col-sm-6">
                  <div class="form-group local-forms">
                    <label>Date Time <span class="req-star">*</span></label>
                    <input type="text" name="prescription_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                  </div>
                </div>
                <div class="col-12 col-sm-6">
                  <div class="form-group local-forms">
                    <label>Doctor <span class="req-star">*</span></label>
                    <select class="form-control select2" id="a_doctor_id" name="doctor_id" data-placeholder="Select Doctor" required>
                      <option></option>
                      @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Lab Tests</label>
                    <select class="form-control select2" id="a_pathology_tests" name="pathology_tests[]" multiple>
                      @foreach($pathologyTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Radiology Tests</label>
                    <select class="form-control select2" id="a_radiology_tests" name="radiology_tests[]" multiple>
                      @foreach($radiologyTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Procedures</label>
                    <select class="form-control select2" id="a_procedures" name="procedures[]" multiple>
                      @foreach($procedureTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
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
              <input type="hidden" id="dcd_pmnor" value="0">
              <div class="form-group local-forms">
                <label>Medicine Details <span class="req-star">*</span></label>
              </div>
              <div class="row">
                <!-- Medicine -->
                <div class="col-12 col-sm-5">
                  <div class="form-group local-forms">
                    <label>Medicine <span class="req-star">*</span></label>
                    <select id="a_dcd_medicine_name" name="medicine_name" class="form-control select2">
                      <option value="">-- Select Medicine --</option>
                      @foreach($pharmaProducts as $med)
                        @php
                          $display = $med->product_name;
                          if ($med->doseUnit?->unit_name) $display .= " - " . $med->doseUnit->unit_name;
                          if ($med->route?->route_name) $display .= " (" . $med->route->route_name . ")";
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
                    <input type="number" step="0.5" min="0.5" id="a_dcd_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                  </div>
                </div>
                <!-- Frequency -->
                <div class="col-12 col-sm-2">
                  <div class="form-group local-forms">
                    <label>Frequency <span class="req-star">*</span></label>
                    <select id="a_dcd_frequency" name="frequency" class="form-control">
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
                    <select id="a_dcd_meal" name="meal" class="form-control">
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
                    <input type="number" min="1" id="a_dcd_duration_days" name="duration_days" class="form-control" value="5">
                  </div>
                </div>
                <!-- Timing (Multiple Selection) -->
                <div class="col-12 col-sm-7 add-medicine-brow">
                  <div class="form-group local-forms flex-1">
                    <label>Timing <span class="req-star">*</span></label>
                    <select id="a_dcd_timing" name="timing[]" class="form-control select2" multiple>
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
                    <select id="a_dcd_medical_condition" name="medical_condition" class="form-control select2">
                      <option value="">-- Select Reason --</option>
                      @foreach($medicalConditions as $cond)
                        <option value="{{ $cond->id }}">{{ $cond->condition_name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group local-forms">
                    <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addDCDPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-12 col-sm-12">
                  <div class="table-responsive">  
                    <table id="dcd_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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

<div class="modal fade" id="editDCDPrescriptionModal">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Update Prescriptions</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <form id="edit_dcd_prescription_form" data-action-template="{{ route('dcd.prescriptions.update', ['rxId' => '__ID__']) }}" method="POST">
        @csrf
        
        <input type="hidden" id="dcd_prescription_id" name="dcd_prescription_id" value="">
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
                    <select class="form-control select2" id="e_doctor_id" name="doctor_id" data-placeholder="Select Doctor" required>
                      <option></option>
                      @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Lab Tests</label>
                    <select class="form-control select2" id="e_pathology_tests" name="pathology_tests[]" multiple>
                      @foreach($pathologyTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Radiology Tests</label>
                    <select class="form-control select2" id="e_radiology_tests" name="radiology_tests[]" multiple>
                      @foreach($radiologyTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Procedures</label>
                    <select class="form-control select2" id="e_procedures" name="procedures[]" multiple>
                      @foreach($procedureTests as $t)
                        <option value="{{ $t['id'] }}">{{ $t['text'] }}</option>
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
              <input type="hidden" id="e_dcd_pmnor" value="0">
              <div class="form-group local-forms">
                <label>Medicine Details <span class="req-star">*</span></label>
              </div>
              <div class="row">
                <!-- Medicine -->
                <div class="col-12 col-sm-5">
                  <div class="form-group local-forms">
                    <label>Medicine <span class="req-star">*</span></label>
                    <select id="e_dcd_medicine_name" name="medicine_name" class="form-control select2">
                      <option value="">-- Select Medicine --</option>
                      @foreach($pharmaProducts as $med)
                        @php
                          $display = $med->product_name;
                          if ($med->doseUnit?->unit_name) $display .= " - " . $med->doseUnit->unit_name;
                          if ($med->route?->route_name) $display .= " (" . $med->route->route_name . ")";
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
                    <input type="number" step="0.5" min="0.5" id="e_dcd_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                  </div>
                </div>
                <!-- Frequency -->
                <div class="col-12 col-sm-2">
                  <div class="form-group local-forms">
                    <label>Frequency <span class="req-star">*</span></label>
                    <select id="e_dcd_frequency" name="frequency" class="form-control">
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
                    <select id="e_dcd_meal" name="meal" class="form-control">
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
                    <input type="number" min="1" id="e_dcd_duration_days" name="duration_days" class="form-control" value="5">
                  </div>
                </div>
                <!-- Timing (Multiple Selection) -->
                <div class="col-12 col-sm-7 add-medicine-brow">
                  <div class="form-group local-forms flex-1">
                    <label>Timing <span class="req-star">*</span></label>
                    <select id="e_dcd_timing" name="timing[]" class="form-control select2" multiple>
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
                    <select id="e_dcd_medical_condition" name="medical_condition" class="form-control select2">
                      <option value="">-- Select Reason --</option>
                      @foreach($medicalConditions as $cond)
                        <option value="{{ $cond->id }}">{{ $cond->condition_name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group local-forms">
                    <button type="button" class="btn btn-sm btn-success add-medicine-btn" onclick="addEDCDPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-12 col-sm-12">
                  <div class="table-responsive">  
                    <table id="e_dcd_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
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
window.dcdRoutes = Object.assign(window.dcdRoutes || {}, {
    prescriptionGet: "{{ route('dcd.prescriptions.get', ['rxId' => '__ID__']) }}",
    prescriptionUpdate: "{{ route('dcd.prescriptions.update', ['rxId' => '__ID__']) }}",
    prescriptionDestroy: "{{ route('dcd.prescriptions.destroy', ['id' => '__ID__']) }}"
});

$(window).on("load", function() {
    if (typeof showDropDownPathologyTests === 'function') {
        showDropDownPathologyTests();
    }
    if (typeof showDropDownRadiologyTests === 'function') {
        showDropDownRadiologyTests();
    }
    if (typeof showDropDownProcedures === 'function') {
        showDropDownProcedures();
    }
});

$(document).ready(function() {
    if (typeof $.fn.select2 !== 'undefined') {
        // select.select2, not .select2: Select2's own wrapper <span> carries that class too, and initialising Select2 on
        // it builds a second dropdown on top of every field that is already set up (e.g. the Doctor lookup).
        // Inside a modal the dropdown is mounted on .modal-content, otherwise Bootstrap's focus trap blocks typing in
        // the search box (and a click on the body-mounted dropdown counts as outside the modal).
        $('select.select2').each(function () {
            var $modal = $(this).closest('.modal-content');
            $(this).select2({ width: '100%', dropdownParent: $modal.length ? $modal : undefined });
        });
    }
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datetimepicker', { enableTime: true, dateFormat: "d-m-Y h:i K" });
    }
});

// At least one test, procedure or medicine is needed to save a prescription.
    function dcdPrescriptionHasContent(prefix, medicineTable) {
        return ($('#' + prefix + 'pathology_tests').val() || []).length
            || ($('#' + prefix + 'radiology_tests').val() || []).length
            || ($('#' + prefix + 'procedures').val() || []).length
            || $(medicineTable + ' > tbody > tr').length;
    }

    appForm.bind('#add_dcd_prescription_form', {
        validate: function () { return dcdPrescriptionHasContent('a_', '#dcd_prescription_medicine_tbl') ? true : 'Please Enter Some Data'; }
    });

    appForm.bind('#edit_dcd_prescription_form', {
        method: 'PUT',
        url: function (form) { return appForm.fill($(form).data('actionTemplate'), $('#dcd_prescription_id').val()); },
        validate: function () { return dcdPrescriptionHasContent('e_', '#e_dcd_prescription_medicine_tbl') ? true : 'Please Enter Some Data'; }
    });
</script>
@endpush
