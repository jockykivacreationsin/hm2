{{--
  Lab result entry form body — rendered server-side and injected into the
  OPD lab-investigations fullscreen modal via AJAX GET .../form-partial.
  Markup is intentionally identical to lab-results/result.blade.php so the
  same element IDs, classes and save logic work in both contexts.
--}}
<input type="hidden" id="lab_investigation_id" value="{{ $serviceId }}">
<input type="hidden" id="mnor" value="{{ $blocks->count() }}">

@foreach($blocks as $block)
  @php $mnor = $loop->iteration; @endphp
  <div style="color:#E94C3D; font-size:18px; margin-top:20px; font-weight:bold;">{{ $block->charge_name }}</div>
  <input id="main_category_{{ $mnor }}" type="hidden" value="{{ $block->detail_id }}">
  <table class="table-bordered table-sm table-text-sm">
    <tr>
      @foreach($block->tests as $test)
        @php $mdnor = $loop->iteration; @endphp
        <td class="flex-td">
          <b style="margin-right:8px; text-transform:uppercase;">{{ $test->name }}</b>
          <input id="test-id-{{ $mnor }}-{{ $mdnor }}" type="hidden" value="{{ $test->id }}">
          <input id="test-value-{{ $mnor }}-{{ $mdnor }}" type="text" maxlength="255" value="{{ $test->value }}" placeholder="{{ $test->reference_range }}">
          <input id="highlighted-{{ $mnor }}-{{ $mdnor }}" class="basic-checkbox" type="checkbox" value="Yes" {{ $test->highlighted ? 'checked' : '' }}>
        </td>
      @endforeach
    </tr>
  </table>
  <input type="hidden" id="mdnor_{{ $mnor }}" value="{{ $block->tests->count() }}">
@endforeach

<div style="margin-top:30px;">
  <label style="color:#E94C3D; font-size:16px; font-weight:bold; display:block; margin-bottom:10px;">
    Conclusion / Interpretation
  </label>
  <textarea id="lab_conclusion" class="form-control" rows="5" placeholder="Enter overall conclusion/interpretation of the lab investigation results...">{{ $conclusion }}</textarea>
  <small class="text-muted">This conclusion will be included in the lab report and FHIR DiagnosticReport</small>
</div>
