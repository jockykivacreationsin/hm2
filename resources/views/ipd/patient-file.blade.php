@extends('layouts.app')
@section('title', 'IPD Patient File')
@section('content')

@php
  $substanceAbuse = collect(explode(',', $file?->substance_abuse ?? ''))->filter()->all();
  $generalExam    = collect(explode(',', $file?->general_examination ?? ''))->filter()->all();
@endphp

<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">
      @include('ipd.partials.menu', ['menu' => 'pf'])
    </div>
    <form id="ipd-patient-file-form">
      @csrf
      <input type="hidden" name="ipd_id" value="{{ $ipd->id }}">
      <div class="card-body">
        <div class="row ipd-patient-treatment-row">
          <div class="ipd-title-with-buttons row-bottom-border">
            <div class="ipd-title">File</div>
            <div class="ipd-buttons">
              <ul>
                <li><a href="{{ route('ipd.show', $ipd->id) }}"><i class="fa fa-th"></i> IPD Dashboard</a></li>
                <li><a href="{{ route('pdf.ipd.patients-file', $ipd->id) }}" target="_blank"><i class="fa fa-print"></i> Print</a></li>
              </ul>
            </div>
          </div>	
          <div class="mt-20">
            <div class="row">
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Relative Name</label>
                  <input type="text" id="relative_name" name="relative_name" class="form-control" value="{{ $file?->relative_name }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Relationship</label>
                  <select class="form-control select2" id="relationship" name="relationship">
                    <option></option>
                    @foreach(['Father', 'Mother', 'Brother', 'Sister', 'Cousin', 'Other'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->relationship === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Witness Name</label>
                  <input type="text" id="witness_name" name="witness_name" class="form-control" value="{{ $file?->witness_name }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Admission Type</label>
                  <select class="form-control select2" id="admission_type" name="admission_type">
                    <option></option>
                    @foreach(['Direct Admission', 'Shifted From Emergency'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->admission_type === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Patient Condition</label>
                  <select class="form-control select2" id="patient_condition" name="patient_condition">
                    <option></option>
                    @foreach(['Walking', 'Wheelchair', 'Stretcher'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->patient_condition === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>General Condition</label>
                  <select class="form-control select2" id="general_condition" name="general_condition">
                    <option></option>
                    @foreach(['Good', 'Fair', 'Poor'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->general_condition === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-12">
                <div class="form-group local-forms">
                  <label>Source of History</label>
                  <input type="text" id="source_of_history" name="source_of_history" class="form-control" value="{{ $file?->source_of_history }}">
                </div>
              </div>
              <div class="col-12 col-md-12">
                <div class="form-group local-forms">
                  <label>Chief Complaints</label>
                  <textarea id="chief_complaints" name="chief_complaints" class="form-control summernote">{{ \App\Support\SafeHtml::clean($file?->chief_complaints, true) }}</textarea>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group local-forms">
                  <label>History of Present Illness</label>
                  <textarea id="history_of_present_illness" name="history_of_present_illness" class="form-control summernote">{{ \App\Support\SafeHtml::clean($file?->history_of_present_illness, true) }}</textarea>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-group local-forms">
                  <label>Past Medical History</label>
                  <textarea id="past_medical_history" name="past_medical_history" class="form-control summernote">{{ \App\Support\SafeHtml::clean($file?->past_medical_history, true) }}</textarea>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Pain Score</label>
                  <select class="form-control select2" id="pain_score" name="pain_score">
                    <option></option>
                    @foreach(['No Hurt', 'Hurts Little Bit', 'Hurts Little More', 'Hurts Even More', 'Hurts Whole Lot', 'Hurts Wrost'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->pain_score === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-5">
                <div class="form-group local-forms">
                  <label>Pain Location</label>
                  <input type="text" id="pain_location" name="pain_location" class="form-control" value="{{ $file?->pain_location }}">
                </div>
              </div>
              <div class="col-12 col-md-5">
                <div class="form-group local-forms">
                  <label>Intervention</label>
                  <input type="text" id="intervention" name="intervention" class="form-control" value="{{ $file?->intervention }}">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="form-group local-forms">
                  <label>Family H/O</label>
                  <input type="text" id="family_ho" name="family_ho" class="form-control" value="{{ $file?->family_ho }}">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="form-group local-forms">
                  <label>Current Medication</label>
                  <input type="text" id="current_medication" name="current_medication" class="form-control" value="{{ $file?->current_medication }}">
                </div>
              </div>
              <div class="col-12 col-md-4">
                <div class="form-group local-forms">
                  <label>Allergies</label>
                  <input type="text" id="allergies" name="allergies" class="form-control" value="{{ $file?->allergies }}">
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="form-group local-forms">
                  <label>H/O of Blood Transfusion</label>
                  <input type="text" id="ho_blood_transfusion" name="ho_blood_transfusion" class="form-control" value="{{ $file?->ho_blood_transfusion }}">
                </div>
              </div>
              
              <div class="col-12 col-md-3">
                <div class="form-group local-forms">
                  <label>Substance Abuse</label>
                  <select class="form-control select2" id="substance_abuse" name="substance_abuse[]" multiple>
                    @foreach(['Tobacco', 'Smoking', 'Alchohol', 'Drug'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $substanceAbuse) ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="form-group local-forms">
                  <label>Nutritional Status</label>
                  <select class="form-control select2" id="nutritional_status" name="nutritional_status">
                    <option></option>
                    @foreach(['Cachectic', 'Thin', 'Average', 'Obese'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->nutritional_status === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="form-group local-forms">
                  <label>General Examination</label>
                  <select class="form-control select2" id="general_examination" name="general_examination[]" multiple>
                    @foreach(['Normal', 'Palior', 'Lectrus', 'Cyanosis', 'Clubbing', 'Oedema', 'Lymphadenopathy'] as $opt)
                    <option value="{{ $opt }}" {{ in_array($opt, $generalExam) ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-12">
                <div class="row">
                  <div class="col-6 col-md-6">
                    <div class="row">
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Systemic Examination</label>
                          <textarea id="systemic_examination" name="systemic_examination" class="form-control summernote">{{ \App\Support\SafeHtml::clean($file?->systemic_examination, true) }}</textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-6 col-md-6">
                    <div class="row">
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Mental Status</label>
                          <select class="form-control select2" id="cns_system_mental_status" name="cns_system_mental_status">
                            <option></option>
                            @foreach(['Alert', 'Awake', 'Confused', 'Lethargic', 'Agitated'] as $opt)
                            <option value="{{ $opt }}" {{ $file?->cns_system_mental_status === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Sensory Examination</label>
                          <select class="form-control select2" id="cns_system_sensory_examination" name="cns_system_sensory_examination">
                            <option></option>
                            @foreach(['Intact', 'Impaired'] as $opt)
                            <option value="{{ $opt }}" {{ $file?->cns_system_sensory_examination === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Motor Exam. Tone</label>
                          <select class="form-control select2" id="cns_system_motor_examination_tone" name="cns_system_motor_examination_tone">
                            <option></option>
                            @foreach(['Normal', 'Hypotonic', 'Hypertonic'] as $opt)
                            <option value="{{ $opt }}" {{ $file?->cns_system_motor_examination_tone === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Reflexes</label>
                          <select class="form-control select2" id="cns_system_reflexes" name="cns_system_reflexes">
                            <option></option>
                            @foreach(['Absent (-)', 'Depressed (+)', 'Normal (++)', 'Brisk (+++) & Brisk with Clonus (+++)'] as $opt)
                            <option value="{{ $opt }}" {{ $file?->cns_system_reflexes === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                      <div class="col-12 col-md-12">
                        <div class="form-group local-forms">
                          <label>Power</label>
                          <select class="form-control select2" id="cns_system_power" name="cns_system_power">
                            <option></option>
                            @foreach([
                              '(0) No Power',
                              '(1) Flicker or Contraction',
                              '(2) Movement with Gravity Eliminated',
                              '(3) Movement Against Gravity',
                              '(4) Movement Against Gravity & Resistance',
                              '(5) Normal Power'
                            ] as $opt)
                            <option value="{{ $opt }}" {{ $file?->cns_system_power === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="col-12 col-md-9">
                <div class="form-group local-forms">
                  <label>Provisional Diagnosis</label>
                  <input type="text" id="provisional_diagnosis" name="provisional_diagnosis" class="form-control" value="{{ $file?->provisional_diagnosis }}">
                </div>
              </div>
              <div class="col-12 col-md-3">
                <div class="form-group local-forms">
                  <label>Diet Plan</label>
                  <select class="form-control select2" id="diet_plan" name="diet_plan">
                    <option></option>
                    @foreach(['Normal', 'Semisolid', 'NBM', 'RT Feeding', 'Diabetic Diet', 'Renal Diet', 'Plan of Care', 'Desired Goals of Treatment'] as $opt)
                    <option value="{{ $opt }}" {{ $file?->diet_plan === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @if($ps->hasPermission('b_ipd_patient_file_save'))
      <div class="bottom-center-btns mb-20">
        <button class="btn btn-success btn-rounded" type="submit">Save</button>
      </div>
      @endif
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function decode(str) {
    if (!str) return '';
    let txt = document.createElement("textarea");
    txt.innerHTML = str;
    return txt.value;
}

function initSummernoteEditors() {
    if (typeof $.fn.summernote !== 'undefined') {
        $('#chief_complaints, #history_of_present_illness, #past_medical_history, #systemic_examination, .summernote').each(function() {
            var $el = $(this);
            if (!$el.data('summernote')) {
                $el.summernote({
                    height: $el.attr('id') === 'systemic_examination' ? 186 : 250,
                    callbacks: {
                        onChange: function(contents) {
                            $el.val($el.summernote('isEmpty') ? '' : contents);
                        },
                        onPaste: function(e) {
                            var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                            e.preventDefault();
                            document.execCommand('insertText', false, bufferText);
                        }
                    }
                });
            }
        });
    }
}

function syncSummernote() {
    if (typeof $.fn.summernote !== 'undefined') {
        $('#chief_complaints, #history_of_present_illness, #past_medical_history, #systemic_examination, .summernote').each(function() {
            if ($(this).data('summernote')) {
                var code = $(this).summernote('isEmpty') ? '' : $(this).summernote('code');
                $(this).val(code);
            }
        });
    }
}

window.getIPDPatientFile = function(ipd_id) {
    $.ajax({
        type: 'GET',
        url: '{{ route("ipd.patient-file.data", ["__ID__"]) }}'.replace('__ID__', ipd_id),
        dataType: 'json',
        success: function(data) {
            if (data != null) {
                $("#relative_name").val(data.relative_name || '');
                $('#relationship').val(data.relationship || '').trigger('change');
                $("#witness_name").val(data.witness_name || '');
                $("#admission_type").val(data.admission_type || '').trigger('change');
                $("#patient_condition").val(data.patient_condition || '').trigger('change');
                $("#source_of_history").val(data.source_of_history || '');
                $("#general_condition").val(data.general_condition || '').trigger('change');

                initSummernoteEditors();
                if ($("#chief_complaints").data('summernote')) {
                    $("#chief_complaints").summernote('code', decode(data.chief_complaints || ''));
                }
                $("#chief_complaints").val(data.chief_complaints || '');

                if ($("#history_of_present_illness").data('summernote')) {
                    $("#history_of_present_illness").summernote('code', decode(data.history_of_present_illness || ''));
                }
                $("#history_of_present_illness").val(data.history_of_present_illness || '');

                if ($("#past_medical_history").data('summernote')) {
                    $("#past_medical_history").summernote('code', decode(data.past_medical_history || ''));
                }
                $("#past_medical_history").val(data.past_medical_history || '');

                $("#pain_score").val(data.pain_score || '').trigger('change');
                $("#pain_location").val(data.pain_location || '');
                $("#intervention").val(data.intervention || '');
                $("#family_ho").val(data.family_ho || '');
                $("#current_medication").val(data.current_medication || '');
                $("#allergies").val(data.allergies || '');
                $("#ho_blood_transfusion").val(data.ho_blood_transfusion || '');

                var substance_abuse = data.substance_abuse || '';
                var substance_abuse_array = substance_abuse ? substance_abuse.split(',') : [];
                $("#substance_abuse").val(substance_abuse_array).trigger('change');

                $("#nutritional_status").val(data.nutritional_status || '').trigger('change');

                var general_examination = data.general_examination || '';
                var general_examination_array = general_examination ? general_examination.split(',') : [];
                $("#general_examination").val(general_examination_array).trigger('change');

                if ($("#systemic_examination").data('summernote')) {
                    $("#systemic_examination").summernote('code', decode(data.systemic_examination || ''));
                }
                $("#systemic_examination").val(data.systemic_examination || '');

                $("#cns_system_mental_status").val(data.cns_system_mental_status || '').trigger('change');
                $("#cns_system_sensory_examination").val(data.cns_system_sensory_examination || '').trigger('change');
                $("#cns_system_motor_examination_tone").val(data.cns_system_motor_examination_tone || '').trigger('change');
                $("#cns_system_reflexes").val(data.cns_system_reflexes || '').trigger('change');
                $("#cns_system_power").val(data.cns_system_power || '').trigger('change');
                $("#provisional_diagnosis").val(data.provisional_diagnosis || '');
                $("#diet_plan").val(data.diet_plan || '').trigger('change');
            }
        }
    });
};

$(document).ready(function() {
    if (typeof $.fn.select2 !== 'undefined') {
        $('#ipd-patient-file-form .select2').select2({
            width: '100%',
            placeholder: "Select",
            allowClear: true
        });
    }
    initSummernoteEditors();

    $("#ipd-patient-file-form").off('submit').on('submit', function(e) {
        e.preventDefault();
        syncSummernote();

        showLoading();
        var formData = new FormData(this);

        $.ajax({
            url: '{{ route("ipd.patient-file.save", $ipd->id) }}',
            type: 'POST',
            data: formData,
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.close();
                Swal.fire({
                    icon: "success",
                    title: "Saved!",
                    text: data.message || "Saved Successfully.",
                    showConfirmButton: false,
                    timer: 1500
                });
            },
            error: function(xhr) {
                Swal.close();
                showError(xhr.responseJSON?.message || 'Error saving patient file.');
            }
        });
    });
});

$(window).on("load", function() {
    getIPDPatientFile({{ $ipd->id }});
});
</script>
@endpush
