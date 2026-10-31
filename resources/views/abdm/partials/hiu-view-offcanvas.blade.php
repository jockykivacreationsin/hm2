<!-- ABDM M3/M4 (HIU side) - full-width offcanvas showing a patient's fetched
     ABDM health records, driven entirely client-side by
     abdmHiuOpenViewOffcanvas()/abdmHiuViewRecordsInit() in assets/js/app.js
     (no iframe/separate page load - those functions run directly in this
     page's own context and just target the element IDs below). Shared by
     opd-visits.php ("Fetch Records" state of abdmHiuButtonClick()),
     ipd-visits.php, dcd-visits.php, and
     consent-requests.php ("VIEW" on a GRANTED consent) - include this
     partial once on any page that needs it. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="abdmHiuViewOffcanvas" style="width:100%;" data-bs-scroll="true">
	<div class="offcanvas-header row-bottom-border">
		<h5 class="offcanvas-title" id="abdm_hiu_view_title">ABDM Health Records</h5>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
	<div class="offcanvas-body">
		<div id="abdm_hiu_view_abha_line" class="mb-10" style="font-size:13px; color:#666;"></div>

		<div id="abdm_hiu_progress"></div>

		<div id="abdm_hiu_records_layout" class="row mt-10" style="display:none;">
			<div class="col-lg-4">
				<h6>Received Records</h6>
				<div id="abdm_hiu_records_list"></div>
			</div>
			<div class="col-lg-8">
				<h6>Detail</h6>
				<div id="abdm_hiu_record_detail" class="text-muted">Select a record from the list to view its contents.</div>
			</div>
		</div>
	</div>
</div>
<!-- Legacy alias for backward compatibility -->
<div id="abdmHiuOffcanvas" style="display:none;"></div>

@push('scripts')
<script>
window.openAbdmHiuView = function (abhaAddress) {
    if (!abhaAddress) {
        Swal.fire({
            icon: 'warning',
            title: 'ABHA Required',
            text: 'This patient has no ABHA address on file.'
        });
        return;
    }
    $.ajax({
        url: '{{ route('abdm.hiu.patients.search') }}',
        type: 'GET',
        data: { abha_address: abhaAddress },
        dataType: 'json',
        success: function(resp) {
            if (resp && resp.found && resp.patient && resp.patient.id) {
                abdmHiuOpenViewOffcanvas(resp.patient.id);
            } else {
                var offcanvasEl = document.getElementById('abdmHiuViewOffcanvas');
                if (offcanvasEl) {
                    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
                    offcanvas.show();
                    $('#abdm_hiu_view_title').text('ABDM Health Records');
                    $('#abdm_hiu_view_abha_line').html('ABHA Address: <b>' + $('<div>').text(abhaAddress).html() + '</b>');
                    $('#abdm_hiu_progress').html('<div class="alert alert-warning">No local patient is linked to this ABHA address.</div>');
                }
            }
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Could not contact server to look up ABHA.' });
        }
    });
};
</script>
@endpush
