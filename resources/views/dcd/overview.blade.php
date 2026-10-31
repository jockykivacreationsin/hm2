@extends('layouts.app')
@section('title', 'DCD Overview - ' . ($dcd->patient?->patient_name ?? ''))

@php
  $patientPhoto = !empty($dcd->patient?->photo) ? \App\Support\Uploads::url('patients', $dcd->patient->photo) : asset('assets/images/no_image.png');
  $doctorPhoto = !empty($dcd->doctor?->photo) ? \App\Support\Uploads::url('staffmembers', $dcd->doctor->photo) : asset('assets/images/no_image.png');
  $dcdDateTimeFormatted = $dcd->dcd_date_time ? date('d-m-Y h:i A', strtotime($dcd->dcd_date_time)) : '';
  $bedName = ($dcd->bed?->bed_name ?? '') . ($dcd->bed?->bedType?->bed_type ? '-' . $dcd->bed->bedType->bed_type : '');
@endphp

@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">
      @include('dcd.partials.menu')
    </div>
    <div class="card-body">
      <div class="row ipd-patient-overview-row">
        <div class="col-lg-6 ipd-patient-left-part">
          <div class="ipd-overview-left">
            <div class="ipd-patient-info-top-bar row-bottom-border">
              <div class="ipd-patient-name">
                <span class="patient-name">{{ $dcd->patient?->patient_name }}</span><span>({{ $dcd->patient_id }})</span> OPD ID: {{ $dcd->opd_id }}
              </div>
              <div class="ipd-patient-links">
                <ul>
                  @if($dcd->status != 'Discharged')
                    <li><a href="javascript:void(0)" onclick="editPatientModal({{ $dcd->patient_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Profile"><i class="fa fa-user" aria-hidden="true"></i></a></li>
                    @if($ps->hasPermission('b_dcd_to_ipd') && $dcd->status == 'New Admission')
                      <li><a id="dcd-to-ipd-btn" href="javascript:void(0)" onclick="createDCTTOIPDModal({{ $dcd->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Shift to IPD"><i class="fa-solid fa-bed-pulse"></i></a></li>
                    @endif
                    @if($ps->hasPermission('b_dcd_discharge_patient'))
                      <li><a href="{{ route('dcd.discharge', $dcd->id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Discharge Patient"><i class="fas fa-hospital-user"></i></a></li>
                    @endif
                  @endif
                  @if(!empty($dcd->patient?->abha_address))
                    {{-- Once everything is linked there is nothing to do in the modal, so only the status badge remains. --}}
                    <li>@unless($abdmFullyLinked)<a href="javascript:void(0)" onclick="openAbdmLinkModal(this)" data-bs-toggle="tooltip" data-bs-placement="top" title="ABDM HIP-Initiated Linking (Discharge Summary / Prescription / Diagnostic Report)">Link to ABDM</a>@endunless{!! $abdmBadge !!}</li>
                  @endif
                </ul>
              </div>
            </div>

            <div class="ipd-patient-personal-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-2">
                  <img class="fullwidth" src="{{ $patientPhoto }}" alt="{{ $dcd->patient?->patient_name }}">
                </div>
                <div class="col-lg-10">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">Guardian Name</span>{{ $dcd->patient?->guardian_name }}</li>
                    <li><span class="bold">Gender</span>{{ $dcd->patient?->gender }}</li>
                    <li><span class="bold">Age</span>{{ $dcd->patient?->age }} Yrs</li>
                    <li><span class="bold">Phone</span>{{ $dcd->patient?->mobile_number }}@if(!empty($dcd->patient?->alternate_mobile_number)), {{ $dcd->patient->alternate_mobile_number }}@endif</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="ipd-patient-admission-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-8">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">DC No</span>{{ $dcd->id }}</li>
                    <li><span class="bold">Admission Date</span>{{ $dcdDateTimeFormatted }}</li>
                    <li><span class="bold">Floor</span>{{ $dcd->bed?->floor?->floor }}</li>
                    <li><span class="bold">Ward </span>{{ $dcd->bed?->wardCategory?->ward_category }}</li>
                    <li><span class="bold">Bed</span>{{ $bedName }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Physical & Vital Signs</div>

              <!-- First Row - Flexbox -->
              <div style="display: flex; gap: 15px; margin-top: 10px;">
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Height</label>
                    <input type="text" id="height" class="form-control" value="{{ $height }}" placeholder="cm">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Weight</label>
                    <input type="text" id="weight" class="form-control" value="{{ $weight }}" placeholder="kg">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>BP (Systolic)</label>
                    <input type="number" id="bp_systolic" class="form-control" placeholder="120" min="40" max="250" value="{{ $bpSystolic }}">
                    <small class="text-muted">mmHg</small>
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>BP (Diastolic)</label>
                    <input type="number" id="bp_diastolic" class="form-control" placeholder="80" min="40" max="200" value="{{ $bpDiastolic }}">
                    <small class="text-muted">mmHg</small>
                  </div>
                </div>
              </div>

              <!-- Second Row - Flexbox -->
              <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Pulse</label>
                    <input type="text" id="pulse" class="form-control" value="{{ $pulse }}" placeholder="bpm">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Temperature</label>
                    <input type="text" id="temperature" class="form-control" value="{{ $temperature }}" placeholder="°F">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Respiration</label>
                    <input type="text" id="respiration" class="form-control" value="{{ $respiration }}" placeholder="breaths/min">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>SPO2</label>
                    <input type="text" id="spo2" class="form-control" value="{{ $spo2 }}" placeholder="%">
                  </div>
                </div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Symptoms</div>
              <textarea id="symptoms" class="form-control summernote">{{ \App\Support\SafeHtml::clean($symptoms, true) }}</textarea>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Finding</div>
              <textarea id="remarks" class="form-control summernote">{{ \App\Support\SafeHtml::clean($remarks, true) }}</textarea>
            </div>

            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
              @if($dcd->doctor)
                <div class="col-lg-6">
                  <div class="ipd-doctor-box">
                    <div class="ipd-patient-consultant-doctor-box row">
                      <div class="col-lg-4">
                        <img class="fullwidth" src="{{ $doctorPhoto }}" alt="{{ $dcd->doctor->staff_name }}">
                      </div>
                      <div class="col-lg-8">
                        <ul>
                          <li><span class="bold">{{ $dcd->doctor->staff_name }}</span></li>
                          <li>{{ $dcd->doctor->designation?->designation }}</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              @endif
            </div>

          </div>
        </div>

        <div class="col-lg-6">
          <div class="ipd-overview-right">
            {{-- Department Billing Progress Bars --}}
            <div class="ipd-billing-pb-boxes row">
              @foreach($dcdBillingSummary as $b)
                <div class="col-lg-6">
                  <div class="ipd-billing-pb-box row-bottom-border">
                    <div class="ipd-billing-pb-box-title">
                      <div class="ipd-billing-pb-title bold caps">
                        {{ $b['name'] }} PAYMENT/BILLING
                      </div>
                      <div class="ipd-billing-pb-icon">
                        <i class="{{ $b['icon'] }}"></i>
                      </div>
                    </div>

                    <div class="ipd-billing-pb-box-amount">
                      <div class="ipd-billing-pb-title">
                        {{ $b['percent'] }}%
                      </div>
                      <div class="ipd-billing-pb-amount">
                        Rs.{{ $b['paid'] }}/Rs.{{ $b['total'] }}
                      </div>
                    </div>
                    <div class="ipd-billing-pb-box-pbar">
                      <div class="progress progress-success progress-sm mb-10">
                        <div class="progress-bar" style="width: {{ $b['percent'] }}%"></div>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            {{-- Latest Prescription Medication --}}
            @if(!empty($latestMedicines) && count($latestMedicines) > 0)
              <div class="sm-table table-sm-bottom-border">
                <span class="table-title caps">MEDICATION</span>
                <div class="table-responsive">  
                  <table class="table table-bordered table-sm table-text-sm">
                    <thead>
                      <tr>
                        <th>Medicine</th>
                        <th>Dose</th>
                        <th>Frequency</th>
                        <th>Timing</th>
                        <th>Meal</th>
                        <th>Duration</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($latestMedicines as $med)
                        <tr>
                          <td>{{ $med->product_name }}</td>
                          <td>{{ $med->dose_quantity }}</td>
                          <td>{{ $med->frequency_display }}</td>
                          <td>{{ $med->timing_names }}</td>
                          <td>{{ $med->meal_name }}</td>
                          <td>{{ $med->duration_days }} days</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif

            {{-- All Prescriptions List --}}
            @if(!empty($prescriptions) && count($prescriptions) > 0)
              <div class="sm-table table-sm-bottom-border">
                <span class="table-title caps">Prescription</span>
                <div class="table-responsive">  
                  <table class="table table-bordered table-sm table-text-sm">
                    <thead>
                      <tr>
                        <th>Date Time</th>
                        <th>Doctor</th>
                        <th>Findings</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($prescriptions as $p)
                        <tr>
                          <td>{{ $p->prescription_date_time ? date('d-m-Y h:i A', strtotime($p->prescription_date_time)) : '' }}</td>
                          <td>{{ $p->doctor?->staff_name }}</td>
                          <td>{{ $p->findings }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif

          </div>
        </div>

      </div>
    </div>
  </div>

  @if($ps->hasPermission('b_dcd_save'))
    <div class="bottom-center-btns">
      <button class="btn btn-success btn-rounded" type="button" onclick="updateDcdOverview()">Save Changes</button>
    </div>
  @endif
</div>

{{-- Modals --}}
@include('abdm.partials.link-modal', ['moduleType' => 'dcd', 'moduleId' => $dcd->id, 'patientId' => $dcd->patient_id])

<div class="modal fade" id="createDCTTOIPDModal">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Shift To IPD</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <form id="create_dcd_to_ipd_form" action="{{ route('dcd.to-ipd.store', $dcd->id) }}" method="POST">
        @csrf
        <input type="hidden" name="save_dcd_to_ipd" value="Yes">
        <input type="hidden" name="dcd_id" value="{{ $dcd->id }}">
        <input type="hidden" id="patient_id" name="patient_id" value="{{ $dcd->patient_id }}">
        <input type="hidden" id="opd_id" name="opd_id" class="form-control" value="{{ $dcd->opd_id }}" required>
        <div class="modal-body">
          <div class="row">
            <div class="col-lg-12">
              <div class="row">
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>Admission Date <span class="req-star">*</span></label>
                    <input type="text" name="admission_date_time" class="form-control datetimepicker" value="{{ date('d-m-Y h:i a') }}" required>
                  </div>
                </div>
                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms">
                    <label>TPA </label>
                    <select class="form-control" id="a_tpa_provider_id" name="tpa_provider_id">
                      <option value="">Select TPA</option>
                      @foreach($tpaProviders as $tpa)
                        <option value="{{ $tpa->id }}">{{ e($tpa->tpa_name) }}</option>
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
                    <input type="number" name="tpa_credit_limit" class="form-control">
                  </div>
                </div>

                <div class="col-12 col-sm-12">
                  <div class="form-group local-forms mb-25">
                    <input type="hidden" id="selected_bed_id" name="bed_id" value="{{ $dcd->bed_id ?? 0 }}">
                    <div>
                      <a href="javascript:void(0)" class="btn btn-sm btn-outline-success" onclick="showBedsUIModal()">Change Bed</a><span id="selected_bed_name" class="ml-10">Bed: {{ $dcd->bed?->bed_name ?? 'No Bed Selected' }}</span>
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

<div class="modal fade" id="showBedsUIModal">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Beds</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <div class="modal-body nptop">
        <div id="beds-ui-box"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
window.dcdRoutes = Object.assign(window.dcdRoutes || {}, {
  toIpdShow: "{{ route('dcd.to-ipd.show', $dcd->id) }}"
});
$(document).ready(function(){
  // Initialize Summernote
  if (typeof $.fn.summernote !== 'undefined') {
    $('.summernote').summernote({
      height: 150,
      toolbar: [
        ['style', ['bold', 'italic', 'underline', 'clear']],
        ['font', ['strikethrough']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link']],
        ['view', ['codeview']]
      ]
    });
    setTimeout(function() {
      $('.summernote').each(function() {
        var content = $(this).summernote('code');
        if (content === '<p>0</p>' || content === '0' || content === '<p><br></p>0' || (content && content.trim() === '<p>0</p>') || (content && content.trim() === '0')) {
          $(this).summernote('code', '');
        }
      });
    }, 100);
  }

  // BP Validation - Systolic and Diastolic separate fields
  $('#bp_systolic, #bp_diastolic').on('blur', function() {
    var systolic = parseInt($('#bp_systolic').val()) || 0;
    var diastolic = parseInt($('#bp_diastolic').val()) || 0;
    
    // Only validate if both fields have values
    if (systolic > 0 && diastolic > 0) {
      if (systolic < 40 || systolic > 250) {
        alert('Systolic BP should be between 40-250 mmHg');
        $('#bp_systolic').focus();
        return false;
      }
      
      if (diastolic < 40 || diastolic > 200) {
        alert('Diastolic BP should be between 40-200 mmHg');
        $('#bp_diastolic').focus();
        return false;
      }
      
      // Systolic should be greater than diastolic
      if (systolic <= diastolic) {
        alert('Systolic BP should be greater than Diastolic BP');
        $('#bp_systolic').focus();
        return false;
      }
    }
  });

  // Auto-save on blur for vital signs
  $('#height, #weight, #bp_systolic, #bp_diastolic, #pulse, #temperature, #respiration, #spo2').on('blur', function() {
    updateDCDOverview();
  });

  // Auto-save on change for summernote fields
  $('#symptoms, #remarks').on('summernote.blur', function() {
    updateDCDOverview();
  });

  // Alias modal hide for Shift to IPD form
  $('#create_dcd_to_ipd_form').on('submit', function() {
    $('#createDCTTOIPDModal').modal('hide');
  });
});

function updateDCDOverview() {
  var height = $('#height').val();
  var weight = $('#weight').val();
  var bp_systolic = $('#bp_systolic').val();
  var bp_diastolic = $('#bp_diastolic').val();
  var pulse = $('#pulse').val();
  var temperature = $('#temperature').val();
  var respiration = $('#respiration').val();
  var spo2 = $('#spo2').val();
  var symptoms = (typeof $.fn.summernote !== 'undefined') ? $('#symptoms').summernote('code') : $('#symptoms').val();
  var remarks = (typeof $.fn.summernote !== 'undefined') ? $('#remarks').summernote('code') : $('#remarks').val();

  $.ajax({
    url: "{{ route('dcd.overview.update', $dcd->id) }}",
    type: 'PUT',
    data: {
      _token: "{{ csrf_token() }}",
      height: height,
      weight: weight,
      bp_systolic: bp_systolic,
      bp_diastolic: bp_diastolic,
      pulse: pulse,
      temperature: temperature,
      respiration: respiration,
      spo2: spo2,
      symptoms: symptoms,
      remarks: remarks
    },
    success: function(response) {
      console.log('DCD overview updated');
    },
    error: function(xhr, status, error) {
      console.error('Error updating DCD overview:', error);
    }
  });
}

function updateDcdOverview() {
    var value = function (id) {
        var $el = $('#' + id);
        return $el.data('summernote') ? ($el.summernote('isEmpty') ? '' : $el.summernote('code')) : $el.val();
    };
    $.ajax({
        type: 'POST',
        url: "{{ route('dcd.overview.update', $dcd->id) }}",
        data: {
            _method: 'PUT',
            symptoms: value('symptoms'),
            remarks: value('remarks'),
            height: $('#height').val(),
            weight: $('#weight').val(),
            bp_systolic: $('#bp_systolic').val(),
            bp_diastolic: $('#bp_diastolic').val(),
            pulse: $('#pulse').val(),
            temperature: $('#temperature').val(),
            respiration: $('#respiration').val(),
            spo2: $('#spo2').val()
        },
        dataType: 'json',
        success: function () { appForm.toast('success', 'Success!', 'Update Successfully'); },
        error: function (xhr) { appForm.toast('error', 'Error!', appForm.errorMessage(xhr, 'Failed to update.'), 2500); }
    });
}
</script>
@endpush
