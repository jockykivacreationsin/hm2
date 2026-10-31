@extends('layouts.app')
@section('title', 'OPD - OPD' . str_pad($opd->id, 7, '0', STR_PAD_LEFT))
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">
      @include('opd.partials.menu')
    </div>
    <div class="card-body">
      <div class="row ipd-patient-overview-row">
        {{-- Left Column: Patient Info, Vitals, Symptoms, Doctor --}}
        <div class="col-lg-6 ipd-patient-left-part">
          <div class="ipd-overview-left">
            <div class="ipd-patient-info-top-bar row-bottom-border">
              <div class="ipd-patient-name">
                <div><span class="patient-name">{{ $patient?->patient_name }}</span><span>({{ $patient_id }})</span></div>
              </div>
              <div class="ipd-patient-links">
                <ul>
                  @if(!empty($patient?->abha_address))
                    {{-- Once everything is linked there is nothing to do in the modal, so only the status badge remains. --}}
                    <li>@unless($abdmFullyLinked)<a href="javascript:void(0)" onclick="openAbdmLinkModal(this)" data-bs-toggle="tooltip" data-bs-placement="top" title="ABDM HIP-Initiated Linking">Link to ABDM</a>@endunless{!! $abdm_cc_badge !!}</li>
                  @endif
                  <li><a href="javascript:void(0)" onclick="viewPatientModal({{ $patient_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Profile"><i class="fa fa-user" aria-hidden="true"></i></a></li>
                  <li><a href="{{ route('pdf.opd.visit-summary', $opd->id) }}" target="_blank" data-bs-toggle="tooltip" data-bs-placement="top" title="Print Visit Summary (Prescription, Lab, Procedures, Charges)"><i class="fas fa-print" aria-hidden="true"></i></a>{!! $abdm_push_badge !!}</li>
                </ul>
              </div>
            </div>

            <div class="ipd-patient-personal-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-2">
                  @php
                    $photo = $patient?->photo ? \App\Support\Uploads::url('patients', $patient->photo) : asset('assets/images/no_image.png');
                  @endphp
                  <img class="fullwidth" src="{{ $photo }}" alt="{{ $patient?->patient_name }}">
                </div>
                <div class="col-lg-10">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">Gender</span>{{ $patient?->gender }}</li>
                    <li><span class="bold">Age</span>{{ (int) ($patient?->age ?? 0) }} Yrs</li>
                    <li><span class="bold">Phone</span>{{ $patient?->mobile_number }}@if(!empty($patient?->alternate_mobile_number)), {{ $patient->alternate_mobile_number }}@endif</li>
                    @if(!empty($patient?->abha_number))
                      <li><span class="bold">ABHA Number</span>{{ $patient->abha_number }}</li>
                    @endif
                    @if(!empty($patient?->abha_address))
                      <li><span class="bold">ABHA Address</span>{{ $patient->abha_address }}</li>
                    @endif
                  </ul>
                </div>
              </div>
            </div>

            <div class="ipd-patient-admission-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-8">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">IPD No</span>{{ $opd->id }}</li>
                    <li><span class="bold">OPD Date</span>{{ $opdDateTime }}</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Physical &amp; Vital Signs</div>
              
              <!-- First Row - Flexbox -->
              <div style="display: flex; gap: 15px; margin-top: 10px;">
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Height</label>
                    <input type="text" id="height" class="form-control" value="{{ $opd->height }}" placeholder="cm">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Weight</label>
                    <input type="text" id="weight" class="form-control" value="{{ $opd->weight }}" placeholder="kg">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>BP (Systolic)</label>
                    <input type="number" id="bp_systolic" class="form-control" placeholder="120" min="40" max="250" value="{{ $bp_systolic }}">
                    <small class="text-muted">mmHg</small>
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>BP (Diastolic)</label>
                    <input type="number" id="bp_diastolic" class="form-control" placeholder="80" min="40" max="200" value="{{ $bp_diastolic }}">
                    <small class="text-muted">mmHg</small>
                  </div>
                </div>
              </div>
              
              <!-- Second Row - Flexbox -->
              <div style="display: flex; gap: 15px;">
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Pulse</label>
                    <input type="text" id="pulse" class="form-control" value="{{ $opd->pulse }}" placeholder="bpm">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Temperature</label>
                    <input type="text" id="temperature" class="form-control" value="{{ $opd->temperature }}" placeholder="°F">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>Respiration</label>
                    <input type="text" id="respiration" class="form-control" value="{{ $opd->respiration }}" placeholder="breaths/min">
                  </div>
                </div>
                <div style="flex: 1;">
                  <div class="form-group local-forms">
                    <label>SPO2</label>
                    <input type="text" id="spo2" class="form-control" value="{{ $opd->spo2 }}" placeholder="%">
                  </div>
                </div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Symptoms</div>
              <textarea id="symptoms" class="form-control summernote">{{ \App\Support\SafeHtml::clean($opd->symptoms, true) }}</textarea>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Finding</div>
              <textarea id="remarks" class="form-control summernote">{{ \App\Support\SafeHtml::clean($opd->remarks, true) }}</textarea>
            </div>

            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
              <div class="title-with-small-buttons">
                <div class="bold caps">Consultant Doctor</div>
                <div class="ipd-patient-links">
                  <ul>
                    <li><a href="javascript:void(0)" onclick="addOPDDoctorModal()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Doctor"><i class="fas fa-plus"></i></a></li>
                  </ul>
                </div>
              </div>
              <div id="ipd_consultant_doctors" class="row">
                @foreach($doctors as $doc)
                  <div class="col-lg-6">
                    <div class="ipd-doctor-box">
                      <a class="ipd-remove-doctor-btn" href="javascript:void(0)" onclick="removeOPDDoctor({{ $doc->id }})"><i class="fas fa-times-circle"></i></a>
                      <div class="ipd-patient-consultant-doctor-box row">
                        <div class="col-lg-4">
                          @php
                            $docPhoto = $doc->photo ? \App\Support\Uploads::url('staffmembers', $doc->photo) : asset('assets/images/no_image.png');
                          @endphp
                          <img class="fullwidth" src="{{ $docPhoto }}" alt="{{ $doc->staff_name }}">
                        </div>
                        <div class="col-lg-8">
                          <ul>
                            <li><span class="bold">{{ $doc->staff_name }}</span></li>
                            <li>{{ $doc->designation }}</li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>

        {{-- Right Column: Billing Boxes, Medication, History --}}
        <div class="col-lg-6">
          <div class="ipd-overview-right">
            <div class="ipd-billing-pb-boxes row">
              @foreach($billingBoxes as $b)
                <div class="col-lg-6">
                  <div class="ipd-billing-pb-box row-bottom-border">
                    <div class="ipd-billing-pb-box-title">
                      <div class="ipd-billing-pb-title bold caps">
                        {{ $b->department_name }} PAYMENT/BILLING
                      </div>
                      <div class="ipd-billing-pb-icon">
                        <i class="{{ $b->icon }}"></i>
                      </div>
                    </div>

                    <div class="ipd-billing-pb-box-amount">
                      <div class="ipd-billing-pb-title">
                        {{ $b->percent }}%
                      </div>
                      <div class="ipd-billing-pb-amount">
                        Rs.{{ $b->paid }}/Rs.{{ $b->total }}
                      </div>
                    </div>
                    <div class="ipd-billing-pb-box-pbar">
                      <div class="progress progress-success progress-sm mb-10">
                        <div class="progress-bar" style="width: {{ $b->percent }}%"></div>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            @if($medicines->isNotEmpty())
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
                      @foreach($medicines as $med)
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

            @if(!empty($visitHistory))
              <div class="sm-table table-sm-bottom-border">
                <span class="table-title caps">History</span>
                <div class="table-responsive">
                  <table class="table table-bordered table-sm table-text-sm">
                    <thead>
                      <tr>
                        <th class="no-sort tr1btn"></th>
                        <th>Type</th>
                        <th>Date Time</th>
                        <th>Doctor</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($visitHistory as $vh)
                        <tr>
                          <td class="tr-sm-action-buttons center">
                            <a href="javascript:void(0)" onclick="visitDetailModal({{ $vh->id }}, '{{ $vh->type }}')" data-bs-toggle="tooltip" data-bs-placement="top" title="Detail"><i class="fas fa-list"></i></a>
                          </td>
                          <td>{{ $vh->label }}</td>
                          <td>{{ $vh->date_time }}</td>
                          <td>{{ $vh->doctor_name }}</td>
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

  @if($save)
    <div class="bottom-center-btns">
      <button class="btn btn-success btn-rounded" type="button" onclick="updateOPDOverview({{ $opd->id }});">Save Changes</button>
    </div>
  @endif
</div>

{{-- Modals --}}
<div class="modal fade" id="visitDetailModal">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <div class="modal-body">
        <div id="visit-detail"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addOPDDoctorModal">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Doctor</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <form id="add_opd_doctor_form">
        @csrf

        <div class="modal-body">
          <div class="row">
            <div class="col-12 col-sm-12">
              <div class="form-group local-forms">
                <label>Doctors <span class="req-star">*</span></label>
                <select class="form-control" id="a_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                  <option></option>
                </select>
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

@include('abdm.partials.link-modal', ['moduleType' => 'opd', 'moduleId' => $opd->id, 'patientId' => $patient_id])
@endsection

@push('scripts')
<script>
$(document).ready(function() {
  // Initialize Summernote for symptoms and remarks
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
    
    // Remove the "0" that appears on page load - run after initialization
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
      
      if (diastolic >= systolic) {
        alert('Warning: Diastolic BP is usually lower than Systolic BP. Please check values.');
        return false;
      }
    }
  });

  // Doctor Select2 in add doctor modal
  if (typeof $.fn.select2 !== 'undefined') {
    $('#a_doctor_id').select2({
      dropdownParent: $('#addOPDDoctorModal'),
      placeholder: "Doctor",
      minimumInputLength: 0,
      ajax: {
        url: "{{ route('opd.doctors.dropdown') }}",
        dataType: "json",
        type: "GET",
        delay: 250,
        data: function(params) {
          return { q: params.term };
        },
        processResults: function(data) {
          return {
            results: data
          };
        },
        cache: true
      }
    });
  }
});

$(document).ready(function() {
  // off() first: public/assets/js/app.js binds the same form to its own submit handler.
  $("#add_opd_doctor_form").off('submit').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
      url: "{{ route('opd.doctors.add', $opd->id) }}",
      type: "POST",
      data: new FormData(this),
      contentType: false,
      cache: false,
      processData: false,
      dataType: 'json',
      success: function() {
        $("#add_opd_doctor_form")[0].reset();
        $("#addOPDDoctorModal").modal('hide');
        Swal.fire({ icon: "success", title: "Saved!", text: "Saved Successfully.", showConfirmButton: false, timer: 1500 });
        location.reload();
      },
      error: function(xhr) {
        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to add the doctor.';
        Swal.fire({ icon: "error", title: "Error", text: msg });
      }
    });
  });
});

function removeOPDDoctor(id) {
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
        url: "{{ route('opd.doctors.remove', ['id' => '__ID__']) }}".replace('__ID__', id),
        data: { _token: "{{ csrf_token() }}" },
        dataType: 'json',
        success: function() { location.reload(); },
        error: function() { Swal.fire({ icon: "error", title: "Error", text: "Failed to remove the doctor." }); }
      });
    }
  });
}

function updateOPDOverview(opd_id) {
  var symptoms = $("#symptoms").data('summernote') ? ($("#symptoms").summernote('isEmpty') ? '' : $("#symptoms").summernote('code')) : $("#symptoms").val();
  var remarks = $("#remarks").data('summernote') ? ($("#remarks").summernote('isEmpty') ? '' : $("#remarks").summernote('code')) : $("#remarks").val();

  $.ajax({
    type: 'POST',
    url: "{{ route('opd.update-overview', ['id' => '__ID__']) }}".replace('__ID__', opd_id),
    data: {
      _token: "{{ csrf_token() }}",
      symptoms: symptoms,
      remarks: remarks,
      height: $("#height").val(),
      weight: $("#weight").val(),
      bp_systolic: $("#bp_systolic").val(),
      bp_diastolic: $("#bp_diastolic").val(),
      pulse: $("#pulse").val(),
      temperature: $("#temperature").val(),
      respiration: $("#respiration").val(),
      spo2: $("#spo2").val()
    },
    dataType: 'json',
    success: function() {
      Swal.fire({ icon: "success", title: "Success!", text: "Update Successfully", showConfirmButton: false, timer: 1500 });
    },
    error: function(xhr) {
      var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to update the overview.';
      Swal.fire({ icon: "error", title: "Error", text: msg });
    }
  });
}

function visitDetailModal(id, type) {
  $("#visitDetailModal").modal('show');
  $("#visit-detail").html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
  $.ajax({
    type: 'POST',
    url: "{{ route('opd.visits.detail') }}",
    data: { _token: "{{ csrf_token() }}", id: id, type: type },
    dataType: 'html',
    success: function(html) { $("#visit-detail").html(html); },
    error: function() { $("#visit-detail").html('<div class="alert alert-danger m-3">Failed to load visit details.</div>'); }
  });
}

function viewPatientModal(id) {
  if (typeof editPatientModal === 'function') {
    editPatientModal(id);
  } else {
    window.location.href = "{{ url('patients') }}?search_patient_id=" + id;
  }
}
</script>
@endpush
