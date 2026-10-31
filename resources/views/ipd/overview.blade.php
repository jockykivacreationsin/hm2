@extends('layouts.app')
@section('title', 'IPD - ' . $ipd->ipd_ref)
@section('content')
@php
  $patient     = $ipd->patient;
  $discharged  = $ipd->status === 'Discharged';
  $canSave     = $ps->hasPermission('b_ipd_save');
  $photo       = $patient?->photo ? \App\Support\Uploads::url('patients', $patient->photo) : asset('assets/images/no_image.png');
  $admittedOn  = $ipd->admission_date_time ? \Illuminate\Support\Carbon::parse($ipd->admission_date_time)->format('d-m-Y h:i A') : '';
  $bedLabel    = trim(($ipd->bed?->bed_name ?? '').'-'.($ipd->bed?->bedType?->bed_type ?? ''), '-');
  $vitals      = $overview['vitals'];
  $credit      = $overview['credit'];
@endphp
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">@include('ipd.partials.menu')</div>
    <div class="card-body">
      <div class="row ipd-patient-overview-row">

        {{-- ───────── Left column ───────── --}}
        <div class="col-lg-6 ipd-patient-left-part">
          <div class="ipd-overview-left">

            <div class="ipd-patient-info-top-bar row-bottom-border">
              <div class="ipd-patient-name">
                <span class="patient-name">{{ $patient?->patient_name }}</span><span>({{ $ipd->patient_id }})</span> OPD ID: {{ $ipd->opd_id }}
              </div>
              <div class="ipd-patient-links">
                <ul>
                  @unless($discharged)
                    <li><a href="javascript:void(0)" onclick="editTpaModal()" data-bs-toggle="tooltip" data-bs-placement="top" title="Update TPA"><i class="fa-solid fa-id-card"></i></a></li>
                    @if($ps->hasPermission('b_ipd_change_bed'))
                      <li><a href="javascript:void(0)" onclick="openIpdChangeBed()" data-bs-toggle="tooltip" data-bs-placement="top" title="Change Bed"><i class="fas fa-bed"></i></a></li>
                    @endif
                    @if($ps->hasPermission('b_ipd_discharge_patient'))
                      <li><a href="{{ route('ipd.discharge', $ipd->id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Discharge Patient"><i class="fas fa-hospital-user"></i></a></li>
                    @endif
                  @endunless
                  @if($ps->hasPermission('im_ipd_patient_file'))
                    <li><a href="{{ route('ipd.patient-file', $ipd->id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="Patients File"><i class="fas fa-files-medical"></i></a></li>
                  @endif
                  @if(!empty($patient?->abha_address))
                    <li>
                      {{-- Once everything is linked there is nothing to do in the modal, so only the status badge remains. --}}
                      @unless($abdmFullyLinked)
                      <a href="javascript:void(0)" onclick="openAbdmLinkModal(this)" data-bs-toggle="tooltip" data-bs-placement="top" title="ABDM HIP-Initiated Linking">Link to ABDM</a>
                      @endunless
                      {!! $abdmBadge ?? '' !!}
                    </li>
                  @endif
                </ul>
              </div>
            </div>

            <div class="ipd-patient-personal-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-2"><img class="fullwidth" src="{{ $photo }}" alt=""></div>
                <div class="col-lg-10">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">Guardian Name</span>{{ $patient?->guardian_name }}</li>
                    <li><span class="bold">Gender</span>{{ $patient?->gender }}</li>
                    <li><span class="bold">Age</span>{{ $patient?->age }} Yrs</li>
                    <li><span class="bold">Phone</span>{{ $patient?->mobile_number }}@if(!empty($patient?->alternate_mobile_number)), {{ $patient->alternate_mobile_number }}@endif</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="ipd-patient-admission-bar row-bottom-border">
              <div class="row">
                <div class="col-lg-8">
                  <ul class="ipd-patient-personal-ul">
                    <li><span class="bold">IPD No</span>{{ $ipd->id }}</li>
                    <li><span class="bold">Admission Date</span>{{ $admittedOn }}</li>
                    <li><span class="bold">Floor</span>{{ $ipd->bed?->floor?->floor }}</li>
                    <li><span class="bold">Ward </span>{{ $ipd->bed?->wardCategory?->ward_category }}</li>
                    <li><span class="bold">Bed</span>{{ $bedLabel }}</li>
                  </ul>
                </div>
                <div class="col-lg-4">
                  <div id="credit-usage-pie-chart" style="height: 150px; width: 100%;"></div>
                </div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Physical &amp; Vital Signs</div>
              <div style="display: flex; gap: 15px; margin-top: 10px;">
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>Height</label>
                  <input type="text" id="height" class="form-control" value="{{ $vitals['height'] }}" placeholder="cm">
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>Weight</label>
                  <input type="text" id="weight" class="form-control" value="{{ $vitals['weight'] }}" placeholder="kg">
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>BP (Systolic)</label>
                  <input type="number" id="bp_systolic" class="form-control" placeholder="120" min="40" max="250" value="{{ $vitals['bp_systolic'] }}">
                  <small class="text-muted">mmHg</small>
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>BP (Diastolic)</label>
                  <input type="number" id="bp_diastolic" class="form-control" placeholder="80" min="40" max="200" value="{{ $vitals['bp_diastolic'] }}">
                  <small class="text-muted">mmHg</small>
                </div></div>
              </div>
              <div style="display: flex; gap: 15px;">
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>Pulse</label>
                  <input type="text" id="pulse" class="form-control" value="{{ $vitals['pulse'] }}" placeholder="bpm">
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>Temperature</label>
                  <input type="text" id="temperature" class="form-control" value="{{ $vitals['temperature'] }}" placeholder="°F">
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>Respiration</label>
                  <input type="text" id="respiration" class="form-control" value="{{ $vitals['respiration'] }}" placeholder="breaths/min">
                </div></div>
                <div style="flex: 1;"><div class="form-group local-forms">
                  <label>SPO2</label>
                  <input type="text" id="spo2" class="form-control" value="{{ $vitals['spo2'] }}" placeholder="%">
                </div></div>
              </div>
            </div>

            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Symptoms</div>
              <textarea id="symptoms" class="form-control summernote">{{ $overview['symptoms'] }}</textarea>
            </div>
            <div class="ipd-patient-tag-bar row-bottom-border">
              <div class="tag-title caps">Finding</div>
              <textarea id="remarks" class="form-control summernote">{{ $overview['findings'] }}</textarea>
            </div>

            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
              <div class="title-with-small-buttons">
                <div class="bold caps">Consultant Doctor</div>
                <div class="ipd-patient-links">
                  <ul><li><a href="javascript:void(0)" onclick="addDoctorModal()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Doctor"><i class="fas fa-plus"></i></a></li></ul>
                </div>
              </div>
              <div id="ipd_consultant_doctors" class="row">
                @foreach($ipdDoctors as $d)
                <div class="col-lg-6" id="ipddr_{{ $d->ipd_doctor_id }}">
                  <div class="ipd-doctor-box">
                    <a class="ipd-remove-doctor-btn" href="javascript:void(0)" onclick="removeDoctor({{ $d->ipd_doctor_id }})"><i class="fas fa-times-circle"></i></a>
                    <div class="ipd-patient-consultant-doctor-box row">
                      <div class="col-lg-4">
                        <img class="fullwidth" src="{{ $d->photo ? \App\Support\Uploads::url('staffmembers', $d->photo) : asset('assets/images/no_image.png') }}" alt="">
                      </div>
                      <div class="col-lg-8">
                        <ul>
                          <li><span class="bold">{{ $d->staff_name }}</span></li>
                          <li>{{ $d->designation }}</li>
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

        {{-- ───────── Right column ───────── --}}
        <div class="col-lg-6">
          <div class="ipd-overview-right">

            <div class="ipd-billing-pb-boxes row">
              @foreach($overview['billing'] as $b)
              <div class="col-lg-6">
                <div class="ipd-billing-pb-box row-bottom-border">
                  <div class="ipd-billing-pb-box-title">
                    <div class="ipd-billing-pb-title bold caps">{{ $b['name'] }} PAYMENT/BILLING</div>
                    <div class="ipd-billing-pb-icon"><i class="{{ $b['icon'] }}"></i></div>
                  </div>
                  <div class="ipd-billing-pb-box-amount">
                    <div class="ipd-billing-pb-title">{{ $b['percent'] }}%</div>
                    <div class="ipd-billing-pb-amount">Rs.{{ $b['paid'] }}/Rs.{{ $b['total'] }}</div>
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

            @if($overview['medications']->isNotEmpty())
            <div class="sm-table table-sm-bottom-border">
              <span class="table-title caps">MEDICATION</span>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-text-sm">
                  <thead><tr><th>Medicine</th><th>Dose</th><th>Frequency</th><th>Timing</th><th>Meal</th><th>Duration</th></tr></thead>
                  <tbody>
                    @foreach($overview['medications'] as $m)
                    <tr>
                      <td>{{ $m->product_name }}</td>
                      <td>{{ $m->dose_quantity }}</td>
                      <td>{{ $m->frequency }}</td>
                      <td>{{ $m->timing }}</td>
                      <td>{{ $m->meal }}</td>
                      <td>{{ $m->duration_days }} days</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
            @endif

            @if($overview['prescriptions']->isNotEmpty())
            <div class="sm-table table-sm-bottom-border">
              <span class="table-title caps">Prescription</span>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-text-sm">
                  <thead><tr><th>Date Time</th><th>Doctor</th><th>Findings</th></tr></thead>
                  <tbody>
                    @foreach($overview['prescriptions'] as $p)
                    <tr><td>{{ $p->when }}</td><td>{{ $p->staff_name }}</td><td>{{ $p->findings }}</td></tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
            @endif

            @if($overview['bedHistory']->isNotEmpty())
            <div class="sm-table table-sm-bottom-border">
              <span class="table-title caps">Bed History</span>
              <div class="table-responsive">
                <table class="table table-bordered table-sm table-text-sm">
                  <thead><tr><th>Bed</th><th>From</th><th>To</th><th>Days</th></tr></thead>
                  <tbody>
                    @foreach($overview['bedHistory'] as $bh)
                    <tr><td>{{ $bh->label }}</td><td>{{ $bh->from }}</td><td>{{ $bh->to }}</td><td>{{ $bh->days }}</td></tr>
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

  @if($canSave)
  <div class="bottom-center-btns">
    <button class="btn btn-success btn-rounded" type="button" onclick="saveIpdOverview()">Save Changes</button>
  </div>
  @endif
</div>

{{-- Add Doctor --}}
<div class="modal fade" id="addDoctorModal"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Doctor</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a></div>
  <form id="add-doctor-form">@csrf
    <div class="modal-body">
      <div class="form-group local-forms">
        <label>Doctors <span class="req-star">*</span></label>
        <select name="doctor_id" class="form-control select2" required>
          <option value="">Select Doctor</option>
          @foreach($allDoctors as $doc)
          <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary ml-10" type="submit">Save</button></div>
  </form>
</div></div></div>

{{-- Change Bed --}}
<div class="modal fade" id="changeIpdBedModal">
  <div class="modal-dialog modal-fullscreen"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">Beds</h5>
      <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
    </div>
    <div class="modal-body nptop"><div id="ipd-change-bed-box"></div></div>
    <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
  </div></div>
</div>

{{-- TPA --}}
<div class="modal fade" id="tpaEditModal"><div class="modal-dialog modal-sm"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">TPA</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a></div>
  <form id="tpa-edit-form">@csrf
    <div class="modal-body">
      <div class="row">
        <div class="col-12">
          <div class="form-group local-forms">
            <label>TPA </label>
            <select name="tpa_provider_id" class="form-control select2" id="tpa_provider_sel">
              <option value="">— None —</option>
              @foreach(\App\Models\TpaProvider::orderBy('tpa_name')->get() as $tpa)
              <option value="{{ $tpa->id }}" {{ $ipd->tpa_provider_id == $tpa->id ? 'selected' : '' }}>{{ $tpa->tpa_name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="col-12 col-sm-6">
          <div class="form-group local-forms">
            <label>TPA Number</label>
            <input type="text" name="tpa_number" class="form-control" value="{{ $ipd->tpa_number }}">
          </div>
        </div>
        <div class="col-12 col-sm-6">
          <div class="form-group local-forms">
            <label>Credit Limit Rs.</label>
            <input type="number" min="0" step="1" name="tpa_credit_limit" class="form-control" value="{{ $ipd->tpa_credit_limit ?? 0 }}">
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary ml-10" type="submit">Save</button></div>
  </form>
</div></div></div>

@include('abdm.partials.link-modal', ['moduleType' => 'ipd', 'moduleId' => $ipd->id, 'patientId' => $ipd->patient_id])
@include('abdm.partials.hiu-view-offcanvas')

@endsection
@push('scripts')
<script>
const IPD_OVERVIEW = {
    csrf:      @json(csrf_token()),
    overview:  @json(route('ipd.overview.update', $ipd->id)),
    tpa:       @json(route('ipd.tpa.update', $ipd->id)),
    addDoctor: @json(route('ipd.doctor.add', $ipd->id)),
    removeDoctor: @json(route('ipd.doctor.remove', ['__ID__'])),
    bedPicker: @json(route('ipd.bed-picker')),
    changeBed: @json(route('ipd.bed.change', $ipd->id)),
    credit:    @json($credit),
};

function ipdAjaxError(xhr) {
    Swal.close();
    var errors = xhr.responseJSON && xhr.responseJSON.errors;
    var msg = errors ? Object.values(errors)[0][0] : (xhr.responseJSON && xhr.responseJSON.message) || 'Server error';
    showError(msg);
}

// ── Credit limit pie (legacy showUsageChart) ──
$(window).on('load', function () {
    var c = IPD_OVERVIEW.credit;
    $('#credit-usage-pie-chart').CanvasJSChart({
        title: { text: 'Credit Limit: ' + c.limit },
        subtitles: [{ text: 'Usage: ' + c.used + ' Balance:' + c.balance }],
        animationEnabled: true,
        data: [{
            type: 'pie',
            startAngle: 40,
            toolTipContent: '<b>{label}</b>: {y}%',
            showInLegend: 'false',
            legendText: '{label}',
            indexLabelFontSize: 10,
            indexLabel: '{label} - {y}%',
            dataPoints: [
                { y: c.balance_pct, label: 'Balance' },
                { y: c.used_pct, label: 'Used' }
            ]
        }]
    });
});

// ── Save Changes (vitals, symptoms, findings -> OPD row) ──
function editorHtml(selector) {
    var $el = $(selector);
    if (typeof $.fn.summernote !== 'undefined' && ($el.data('summernote') || $el.next('.note-editor').length)) {
        return $el.summernote('isEmpty') ? '' : $el.summernote('code');
    }
    return $el.val();
}

function saveIpdOverview() {
    var sys = $('#bp_systolic').val(), dia = $('#bp_diastolic').val();
    if (sys !== '' && (sys < 40 || sys > 250)) { showError('Systolic BP must be between 40 and 250 mmHg.'); return; }
    if (dia !== '' && (dia < 40 || dia > 200)) { showError('Diastolic BP must be between 40 and 200 mmHg.'); return; }
    if (sys !== '' && dia !== '' && parseInt(dia, 10) >= parseInt(sys, 10)) { showError('Diastolic BP must be lower than systolic BP.'); return; }

    showLoading();
    $.post(IPD_OVERVIEW.overview, {
        _token: IPD_OVERVIEW.csrf,
        height: $('#height').val(), weight: $('#weight').val(),
        bp_systolic: sys, bp_diastolic: dia,
        pulse: $('#pulse').val(), temperature: $('#temperature').val(),
        respiration: $('#respiration').val(), spo2: $('#spo2').val(),
        symptoms: editorHtml('#symptoms'), remarks: editorHtml('#remarks')
    }, function (r) {
        Swal.close();
        if (r.success) showSuccess(r.message); else showError(r.message || 'Failed');
    }).fail(ipdAjaxError);
}

// ── TPA ──
function editTpaModal() { $('#tpaEditModal').modal('show'); }
$('#tpa-edit-form').on('submit', function (e) {
    e.preventDefault(); showLoading();
    $.post(IPD_OVERVIEW.tpa, $(this).serialize(), function (r) {
        Swal.close();
        if (r.success) { showSuccess(r.message); setTimeout(function () { location.reload(); }, 1000); }
        else showError(r.message || 'Failed');
    }).fail(ipdAjaxError);
});

// ── Consultant doctors ──
function addDoctorModal() { $('#addDoctorModal').modal('show'); }
$('#add-doctor-form').on('submit', function (e) {
    e.preventDefault(); showLoading();
    $.post(IPD_OVERVIEW.addDoctor, $(this).serialize(), function (r) {
        Swal.close();
        if (r.success) { showSuccess(r.message); $('#addDoctorModal').modal('hide'); setTimeout(function () { location.reload(); }, 800); }
        else showError(r.message || 'Failed');
    }).fail(ipdAjaxError);
});

function removeDoctor(ipdDoctorId) {
    Swal.fire({ title: 'Remove doctor?', icon: 'question', showCancelButton: true }).then(function (res) {
        if (!res.isConfirmed) return;
        showLoading();
        $.ajax({
            url: IPD_OVERVIEW.removeDoctor.replace('__ID__', ipdDoctorId), type: 'DELETE', data: { _token: IPD_OVERVIEW.csrf },
            success: function (r) {
                Swal.close();
                if (r.success) { showSuccess(r.message); $('#ipddr_' + ipdDoctorId).remove(); } else showError(r.message);
            },
            error: ipdAjaxError
        });
    });
}

// ── Change bed (the shared bed picker calls chooseIpdBed) ──
function openIpdChangeBed() {
    $('#changeIpdBedModal').modal('show');
    $('#ipd-change-bed-box').html('<p class="text-center text-muted">Loading beds...</p>');
    $.getJSON(IPD_OVERVIEW.bedPicker, function (r) { $('#ipd-change-bed-box').html(r.html); })
        .fail(function () { $('#ipd-change-bed-box').html('<p class="text-center text-danger">Could not load beds.</p>'); });
}

function chooseIpdBed(id, name) {
    Swal.fire({
        title: 'Change?', text: 'Move the patient to bed ' + name + '?', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Yes, change it!'
    }).then(function (res) {
        if (!res.isConfirmed) return;
        showLoading();
        $.post(IPD_OVERVIEW.changeBed, { _token: IPD_OVERVIEW.csrf, new_bed_id: id }, function (r) {
            Swal.close();
            if (r.success) { showSuccess(r.message); setTimeout(function () { location.reload(); }, 1000); }
            else showError(r.message || 'Failed');
        }).fail(ipdAjaxError);
    });
}
</script>
@endpush
