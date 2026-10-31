{{--
  Lab Investigations list shared by OPD / IPD / DCD (legacy {opd,ipd,dcd}-lab-investigations.php).
  Expects: $module ('opd'|'ipd'|'dcd'), $entityId, $services (LabResultService::investigations()).
--}}
@php
  // Legacy differences between the three pages (print button, action column width class, detail modal/table ids).
  // All modules now use tr2btns — Add Detail opens an in-page fullscreen modal for all three.
  $isOpd     = $module === 'opd';
  $actionTh  = 'tr2btns';
  $detModal  = ['opd' => 'viewOPDLabInvestigationDetailModal', 'ipd' => 'viewTestDetailModal', 'dcd' => 'viewDCDLabInvestigationDetailModal'][$module];
  $detTable  = $module === 'ipd' ? 'lab_test_detail_tbl' : 'lab_service_detail_tbl';
  $detTableClass = $module === 'ipd' ? 'table table-bordered table-sm' : 'table table-bordered table-sm table-text-sm';
@endphp
<div class="row ipd-patient-prescription-row">
  <div class="ipd-title-with-buttons row-bottom-border">
    <div class="ipd-title">Lab Investigations</div>
    <div class="ipd-buttons">
      <ul>
        <li>
          @if($isOpd)
          <a target="_blank" href="{{ route('pdf.opd.lab-report', $entityId) }}"><i class="fas fa-file-pdf"></i> PDF</a>
          @else
          <a target="_blank" href="{{ route("pdf.{$module}.lab-report", $entityId) }}"><i class="fa fa-print"></i> Print Detail</a>
          @endif
        </li>
      </ul>
    </div>
  </div>
  <div class="sm-table mt-10">
    <div class="table-responsive">
      <table class="data-tbl table table-bordered table-sm table-text-sm">
        <thead>
          <tr>
            <th class="no-sort {{ $actionTh }}"></th>
            <th>Date Time</th>
            <th>Lab</th>
            <th>Title</th>
            <th>Detail</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($services as $row)
          <tr>
            <td class="tr-sm-action-buttons center">
              @if($row->is_radiology)
              <a onclick="labOpenRadiologyNote({{ $row->id }})" href="javascript:void(0)" data-bs-toggle="tooltip" data-bs-placement="top" title="Radiology Notes"><i class="fas fa-list"></i></a>
              @else
              <a onclick="labOpenResultModal({{ $row->id }})" href="javascript:void(0)" data-bs-toggle="tooltip" data-bs-placement="top" title="Add / Edit Detail"><i class="fas fa-plus"></i></a>
              <a onclick="labViewDetail({{ $row->id }})" href="javascript:void(0)" data-bs-toggle="tooltip" data-bs-placement="top" title="View Detail"><i class="fas fa-list"></i></a>
              @endif
            </td>
            <td>{{ $row->service_date_time_fmt }}</td>
            <td>{{ $row->department_name }}</td>
            <td>{{ $row->service_title }}</td>
            <td>{{ $row->charges }}</td>
            <td>{{ $row->status }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="{{ $module }}RadiologyNoteModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Radiology Notes</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <div class="modal-body">
        <input type="hidden" id="{{ $module }}_radiology_note_service_id">
        <textarea id="{{ $module }}_radiology_note_editor" class="form-control summernote"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="button" onclick="labSaveRadiologyNote()"><i class="fa fa-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="{{ $detModal }}">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-lg-12">
            <div class="row">
              <div class="col-12 col-sm-12">
                <div class="table-responsive">
                  <table id="{{ $detTable }}" class="{{ $detTableClass }}"></table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

{{-- Result entry modal — shared by OPD / IPD / DCD --}}
<div class="modal fade" id="{{ $module }}LabResultModal" tabindex="-1">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-vials me-1"></i> Lab Investigation Result</h5>
        <a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
      </div>
      <div class="modal-body" id="{{ $module }}LabResultModalBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="button" onclick="labSaveResults()"><i class="fa fa-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  // Radiology notes and result entry modal — shared by OPD / IPD / DCD
  var labModule = @json($module);
  var labBase   = "{{ url('lab-results') }}/" + labModule + "/service/";
  var _labModalId     = '#' + labModule + 'LabResultModal';
  var _labModalBodyId = '#' + labModule + 'LabResultModalBody';
  var _labResultServiceId = 0;

  // Opens the result entry modal; fetches server-rendered form HTML into the modal body.
  function labOpenResultModal(id) {
    _labResultServiceId = id;
    $(_labModalBodyId).html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
    var $modal = $(_labModalId);
    $modal.modal('show');
    $modal.on('shown.bs.modal.focusFix', function () {
      $modal.trigger('focus');
      $modal.off('shown.bs.modal.focusFix');
    });
    $.ajax({
      type: 'GET',
      url: labBase + id + '/form-partial',
      success: function (html) {
        $(_labModalBodyId).html(html);
      },
      error: function () {
        $(_labModalBodyId).html('<div class="alert alert-danger">Could not load the form. Please try again.</div>');
      }
    });
  }

  // Collects inputs from the modal body and POSTs to the save endpoint.
  function labSaveResults() {
    var cells = [];
    var mnor = parseInt($('#mnor').val(), 10) || 0;
    for (var a = 1; a <= mnor; a++) {
      var mdnor    = parseInt($('#mdnor_' + a).val(), 10) || 0;
      var detailId = $('#main_category_' + a).val();
      for (var i = 1; i <= mdnor; i++) {
        cells.push({
          detail_id:   detailId,
          test_id:     $('#test-id-'    + a + '-' + i).val(),
          test_value:  $('#test-value-' + a + '-' + i).val(),
          highlighted: $('#highlighted-'+ a + '-' + i).is(':checked') ? 1 : 0
        });
      }
    }

    $.ajax({
      type: 'POST',
      url: labBase + _labResultServiceId,
      data: { cells: cells, lab_conclusion: $('#lab_conclusion').val() },
      dataType: 'json',
      success: function (res) {
        if (res && res.success) {
          $(_labModalId).modal('hide');
          Swal.fire({ icon: 'success', title: 'Saved!', text: res.message || 'Saved successfully.', showConfirmButton: false, timer: 1500 });
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: (res && res.message) ? res.message : 'Could not save.' });
        }
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Server error.';
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
      }
    });
  }

  function labOpenRadiologyNote(id) {
    var modalId = "#" + labModule + "RadiologyNoteModal", editor = "#" + labModule + "_radiology_note_editor";
    $("#" + labModule + "_radiology_note_service_id").val(id);
    $(editor).summernote('code', '');
    $.ajax({
      type: 'GET', url: labBase + id + "/radiology-note", dataType: 'json',
      success: function (resp) { if (resp.success) $(editor).summernote('code', resp.note || ''); $(modalId).modal('show'); },
      error: function () { $(modalId).modal('show'); }
    });
  }

  function labSaveRadiologyNote() {
    var id = $("#" + labModule + "_radiology_note_service_id").val();
    var note = $("#" + labModule + "_radiology_note_editor").summernote('code');
    $.ajax({
      type: 'POST', url: labBase + id + "/radiology-note", data: {note: note}, dataType: 'json',
      success: function (resp) {
        if (resp.success) {
          $("#" + labModule + "RadiologyNoteModal").modal('hide');
          Swal.fire({icon: 'success', title: 'Saved!', text: 'Saved Successfully.', showConfirmButton: false, timer: 1500});
        } else {
          Swal.fire({icon: 'error', title: 'Error', text: resp.message || 'Failed to save'});
        }
      },
      error: function () { Swal.fire({icon: 'error', title: 'Error', text: 'Could not reach the server. Please try again.'}); }
    });
  }

  // "View Detail": saved results (value + unit, bold when flagged, reference range) - replaces view{,OPD,DCD}TestDetail()
  function labViewDetail(id) {
    $("#{{ $detModal }}").modal('show');
    $.ajax({
      type: 'GET', url: labBase + id + "/detail", dataType: 'html',
      success: function (html) { $("#{{ $detTable }}").html(html); }
    });
  }

  // Legacy function name aliases
  window.openOPDRadiologyNoteModal = labOpenRadiologyNote;
  window.saveOPDRadiologyNote = labSaveRadiologyNote;
  window.viewOPDTestDetail = labViewDetail;
  window.openIPDRadiologyNoteModal = labOpenRadiologyNote;
  window.saveIPDRadiologyNote = labSaveRadiologyNote;
  window.viewTestDetail = labViewDetail;
  window.openDCDRadiologyNoteModal = labOpenRadiologyNote;
  window.saveDCDRadiologyNote = labSaveRadiologyNote;
  window.viewDCDTestDetail = labViewDetail;
</script>
@endpush
