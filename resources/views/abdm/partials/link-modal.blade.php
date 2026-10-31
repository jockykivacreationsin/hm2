{{--
  Shared "Link to ABDM" modal — HIP-initiated linking (M2 Step 1 link token, Step 2 care context). It ends at
  "Care context linked successfully": the M3 consent and M4 data push that follow are handled by the server on ABDM's
  callbacks and are not shown. One modal for OPD, IPD and DCD; the behaviour lives in
  public/assets/js/abdm-link-modal.js (port of the legacy openAbdmLinkModal / ...Ipd / ...Dcd flows).

  Usage: @include('abdm.partials.link-modal', ['moduleType' => 'opd', 'moduleId' => $opd->id, 'patientId' => $opd->patient_id])
  Must be placed INSIDE @section('content').
  The HIU offcanvas is in abdm.partials.hiu-view-offcanvas — include that separately.
--}}
@php
    $linkType = in_array($moduleType ?? 'opd', ['opd', 'ipd', 'dcd'], true) ? $moduleType : 'opd';
    $linkModuleId = (int) ($moduleId ?? 0);
    $linkConfig = [
        'type'                 => $linkType,
        'id'                   => $linkModuleId,
        'patientId'            => (int) ($patientId ?? 0),
        'urls'                 => [
            'tokenCheck'   => route('abdm.link-token.check'),
            'tokenRequest' => route('abdm.link-token.request'),
            'ccCheck'      => route("abdm.{$linkType}.care-context.check", $linkModuleId),
            'ccLink'       => route("abdm.{$linkType}.care-context.link", $linkModuleId),
        ],
        // Both steps expect ABDM's callback within the pending window.
        'pollMaxSeconds'       => (int) config('abdm.care_context_pending_timeout', 600),
        'pollIntervalMs'       => 5000,
    ];
@endphp

<div class="modal fade" id="abdmLinkModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Link to ABDM</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
        <div id="abdm_link_modal_body">
          <div class="text-center text-muted">Loading...</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  window.ABDM_LINK_MODAL = @json($linkConfig + ['csrf' => csrf_token()]);
</script>
<script src="{{ asset('assets/js/abdm-link-modal.js') }}?v={{ @filemtime(public_path('assets/js/abdm-link-modal.js')) }}"></script>
@endpush
