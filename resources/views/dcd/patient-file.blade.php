@extends('layouts.app')
@section('title', 'DCD Patient File - ' . ($dcd->patient?->patient_name ?? ''))

@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu">
      @include('dcd.partials.menu')
    </div>
    <form id="dcd-patient-file-form" action="{{ route('dcd.patient-file.save', $dcd->id) }}" method="POST">
      @csrf
      <input type="hidden" name="dcd_id" value="{{ $dcd->id }}">
      <div class="card-body">
        <div class="row ipd-patient-treatment-row">
          <div class="ipd-title-with-buttons row-bottom-border">
            <div class="ipd-title">File</div>
          </div>  
          <div class="mt-20">
            <div class="row">
              <div class="col-12 col-md-6">
                <div class="form-group local-forms">
                  <label>Chief Complaints</label>
                  <textarea id="chief_complaints" name="chief_complaints" class="form-control summernote">{{ \App\Support\SafeHtml::clean($file?->chief_complaints, true) }}</textarea>
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
                  <label>MLC</label>
                  <select class="form-control select2" id="mlc" name="mlc">
                    <option></option>
                    <option value="Yes" {{ ($file?->mlc == 'Yes') ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ ($file?->mlc == 'No') ? 'selected' : '' }}>No</option>
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>MLC No</label>
                  <input type="text" id="mlc_number" name="mlc_number" class="form-control" value="{{ $file?->mlc_number }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Is Referred</label>
                  <select class="form-control select2" id="referred_from_other_hospital" name="referred_from_other_hospital">
                    <option></option>
                    <option value="Yes" {{ ($file?->referred_from_other_hospital == 'Yes') ? 'selected' : '' }}>Yes</option>
                    <option value="No" {{ ($file?->referred_from_other_hospital == 'No') ? 'selected' : '' }}>No</option>
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>From Hospital</label>
                  <input type="text" id="referred_from_hospital_name" name="referred_from_hospital_name" class="form-control" value="{{ $file?->referred_from_hospital_name }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Mode</label>
                  <select class="form-control select2" id="mode" name="mode">
                    <option></option>
                    <option value="Stretcher" {{ ($file?->mode == 'Stretcher') ? 'selected' : '' }}>Stretcher</option>
                    <option value="Wheelchair" {{ ($file?->mode == 'Wheelchair') ? 'selected' : '' }}>Wheelchair</option>
                    <option value="Walking" {{ ($file?->mode == 'Walking') ? 'selected' : '' }}>Walking</option>
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>History Giver By</label>
                  <input type="text" id="history_given_by" name="history_given_by" class="form-control" value="{{ $file?->history_given_by }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Identification Marks</label>
                  <input type="text" id="identification_marks" name="identification_marks" class="form-control" value="{{ $file?->identification_marks }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Arrival Time</label>
                  <input type="text" id="emergency_arrival_time" name="emergency_arrival_time" class="form-control timepicker" value="{{ $file?->emergency_arrival_time }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Allergies</label>
                  <input type="text" id="allergies" name="allergies" class="form-control" value="{{ $file?->allergies }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>GCS</label>
                  <input type="text" id="gcs" name="gcs" class="form-control" value="{{ $file?->gcs }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Pallor</label>
                  <input type="text" id="pallor" name="pallor" class="form-control" value="{{ $file?->pallor }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Pain Score</label>
                  <select class="form-control select2" id="pain_score" name="pain_score">
                    <option></option>
                    <option value="No Hurt" {{ ($file?->pain_score == 'No Hurt') ? 'selected' : '' }}>No Hurt</option>
                    <option value="Hurts Little Bit" {{ ($file?->pain_score == 'Hurts Little Bit') ? 'selected' : '' }}>Hurts Little Bit</option>
                    <option value="Hurts Little More" {{ ($file?->pain_score == 'Hurts Little More') ? 'selected' : '' }}>Hurts Little More</option>
                    <option value="Hurts Even More" {{ ($file?->pain_score == 'Hurts Even More') ? 'selected' : '' }}>Hurts Even More</option>
                    <option value="Hurts Whole Lot" {{ ($file?->pain_score == 'Hurts Whole Lot') ? 'selected' : '' }}>Hurts Whole Lot</option>
                    <option value="Hurts Wrost" {{ ($file?->pain_score == 'Hurts Wrost' || $file?->pain_score == 'Hurts Worst') ? 'selected' : '' }}>Hurts Wrost</option>
                  </select>
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>CNS</label>
                  <input type="text" id="cns" name="cns" class="form-control" value="{{ $file?->cns }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>RS</label>
                  <input type="text" id="rs" name="rs" class="form-control" value="{{ $file?->rs }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>CVS</label>
                  <input type="text" id="cvs" name="cvs" class="form-control" value="{{ $file?->cvs }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Per Abdomen</label>
                  <input type="text" id="per_abdomen" name="per_abdomen" class="form-control" value="{{ $file?->per_abdomen }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>F Menstural History</label>
                  <input type="text" id="female_menstural_history" name="female_menstural_history" class="form-control" value="{{ $file?->female_menstural_history }}">
                </div>
              </div>
              <div class="col-12 col-md-2">
                <div class="form-group local-forms">
                  <label>Provisional Diagnosis</label>
                  <input type="text" id="provisional_diagnosis" name="provisional_diagnosis" class="form-control" value="{{ $file?->provisional_diagnosis }}">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      @if($ps->hasPermission('b_dcd_file_save'))
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
window.dcdRoutes = Object.assign(window.dcdRoutes || {}, {
    patientFileShow: "{{ route('dcd.patient-file.show', $dcd->id) }}"
});
$(document).ready(function(){
    if (typeof $.fn.summernote !== 'undefined') {
        $('.summernote').summernote({
            height: 120,
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ],
            callbacks: {
                onChange: function(contents) {
                    $(this).val($(this).summernote('isEmpty') ? '' : contents);
                },
                onPaste: function(e) {
                    var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                    e.preventDefault();
                    document.execCommand('insertText', false, bufferText);
                }
            }
        });
    }
    if (typeof $.fn.select2 !== 'undefined') {
        // select.select2: Select2's own wrapper <span> also has the class "select2" (see dcd/prescriptions.blade.php).
        $('select.select2').select2({ width: '100%' });
    }
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.timepicker', { enableTime: true, noCalendar: true, dateFormat: "h:i K" });
    }
    if (typeof getDCDPatientFile === 'function') {
        getDCDPatientFile({{ $dcd->id }});
    }

    // Saved over AJAX (the endpoint answers with JSON), so the result is shown as a swal instead of a raw JSON page.
    $('#dcd-patient-file-form').off('submit').on('submit', function(e) {
        e.preventDefault();
        if (typeof $.fn.summernote !== 'undefined') {
            $(this).find('.summernote').each(function() {
                if ($(this).data('summernote')) {
                    $(this).val($(this).summernote('isEmpty') ? '' : $(this).summernote('code'));
                }
            });
        }

        showLoading();
        $.ajax({
            url: this.action,
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            cache: false,
            processData: false,
            dataType: 'json',
            success: function(data) {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: data.message || 'Saved Successfully.',
                    showConfirmButton: false,
                    timer: 1500
                });
            },
            error: function(xhr) {
                Swal.close();
                var errors = xhr.responseJSON && xhr.responseJSON.errors;
                var first = errors ? Object.values(errors)[0][0] : null;
                showError(first || (xhr.responseJSON && xhr.responseJSON.message) || 'Error saving patient file.');
            }
        });
    });
});
</script>
@endpush
