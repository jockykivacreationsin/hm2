{{--
  Lab investigation result entry (legacy {opd,ipd,dcd}-lab-investigation-result.php).
  Expects: $module, $serviceId, $service, $entity_id, $blocks (charge blocks with ->tests), $conclusion.
  Element ids (main_category_N, test-id-N-M, test-value-N-M, highlighted-N-M, mdnor_N, mnor, lab_conclusion) match legacy.
--}}
@extends('layouts.app')
@section('title', 'Lab Investigation Result')
@section('content')
<div class="themebody-wrap ipd-wrap">
  <div class="card-ipd">
    <div class="card-menu"></div>
    <div class="card-body">
      <div class="row ipd-patient-prescription-row">
        <div class="ipd-title-with-buttons row-bottom-border">
          <div class="ipd-title">{{ $module === 'dcd' ? 'DCD ' : '' }}Lab Investigation Result</div>
          <div class="ipd-buttons">
            <ul>
              <li><h5 id="saving_status"></h5></li>
              <li><a href="javascript:void(0)" onclick="saveLabResults();"><i class="fa fa-plus"></i> Save</a></li>
            </ul>
          </div>
        </div>
        <div class="sm-table mt-10">
          <div class="data">
            <input type="hidden" id="lab_investigation_id" name="lab_investigation_id" value="{{ $serviceId }}">
            <input type="hidden" id="{{ $module }}_id" name="{{ $module }}_id" value="{{ $entity_id }}">
            <input type="hidden" id="service_date_time" name="service_date_time" value="{{ $service->service_date_time }}">

            @foreach($blocks as $block)
              @php $mnor = $loop->iteration; @endphp
              <div style="color: #E94C3D;font-size: 18px; margin-top:20px;font-weight: bold;">{{ $block->charge_name }}</div><input id="main_category_{{ $mnor }}" type="hidden" value="{{ $block->detail_id }}">
              <table class="table-bordered table-sm table-text-sm">
                <tr>
                  @foreach($block->tests as $test)
                    @php $mdnor = $loop->iteration; @endphp
                    <td class="flex-td"><b style="margin-right:8px; text-transform: uppercase;">{{ $test->name }}</b><input id="test-id-{{ $mnor }}-{{ $mdnor }}" type="hidden" value="{{ $test->id }}"><input id="test-value-{{ $mnor }}-{{ $mdnor }}" type="text" maxlength="255" value="{{ $test->value }}" placeholder="{{ $test->reference_range }}"><input id="highlighted-{{ $mnor }}-{{ $mdnor }}" class="basic-checkbox" type="checkbox" value="Yes" {{ $test->highlighted ? 'checked' : '' }}></td>
                  @endforeach
                </tr>
              </table>
              <input type="hidden" id="mdnor_{{ $mnor }}" name="mdnor_{{ $mnor }}" value="{{ $block->tests->count() }}">
            @endforeach
            <input type="hidden" id="mnor" name="mnor" value="{{ $blocks->count() }}">

            <!-- Lab Investigation Conclusion -->
            <div style="margin-top: 30px;">
              <label style="color: #E94C3D; font-size: 16px; font-weight: bold; display: block; margin-bottom: 10px;">
                Conclusion / Interpretation
              </label>
              <textarea id="lab_conclusion" name="lab_conclusion" class="form-control" rows="5" placeholder="Enter overall conclusion/interpretation of the lab investigation results...">{{ $conclusion }}</textarea>
              <small class="text-muted">This conclusion will be included in the lab report and FHIR DiagnosticReport</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Replaces legacy save{,OPD,DCD}TestDetailedResults(): instead of one ajax call per cell followed by a
  // separate conclusion call, every cell and the conclusion are posted once and saved in a single transaction.
  function saveLabResults() {
    Swal.fire({
      title: "Save?",
      text: "Are you sure you want to save!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, save it!"
    }).then(function (result) {
      if (!result.isConfirmed) return;

      var cells = [];
      var mnor = parseInt($("#mnor").val(), 10) || 0;
      for (var a = 1; a <= mnor; a++) {
        var mdnor = parseInt($("#mdnor_" + a).val(), 10) || 0;
        var detailId = $("#main_category_" + a).val();
        for (var i = 1; i <= mdnor; i++) {
          cells.push({
            detail_id: detailId,
            test_id: $("#test-id-" + a + "-" + i).val(),
            test_value: $("#test-value-" + a + "-" + i).val(),
            highlighted: $("#highlighted-" + a + "-" + i).is(":checked") ? 1 : 0
          });
        }
      }

      $.ajax({
        type: 'POST',
        url: @json(route('lab-results.result.save', [$module, $serviceId])),
        data: {cells: cells, lab_conclusion: $("#lab_conclusion").val()},
        dataType: 'json',
        success: function () {
          $("#saving_status").html("Saved Successfully");
          Swal.fire({icon: "success", title: "Success!", text: "Saved Successfully", showConfirmButton: false, timer: 1500});
        },
        error: function (xhr) {
          var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not save. Please try again.';
          Swal.fire({icon: "error", title: "Error", text: msg});
        }
      });
    });
  }
</script>
@endpush
