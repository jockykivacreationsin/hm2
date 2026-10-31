/**
 * "Link to ABDM" modal (HIP-initiated linking, M2 Step 1 link token + Step 2 care context).
 *
 * Port of the legacy bmhabdm app.js flow behind openAbdmLinkModal / openAbdmLinkModalIpd / openAbdmLinkModalDcd:
 * one implementation for OPD, IPD and DCD, driven by window.ABDM_LINK_MODAL (see abdm/partials/link-modal.blade.php):
 *
 *   { type: 'opd'|'ipd'|'dcd', id, patientId, csrf,
 *     urls: { tokenCheck, tokenRequest, ccCheck, ccLink },
 *     pollMaxSeconds, pollIntervalMs }
 *
 * The flow runs by itself: it checks the link token (requesting one when none exists and no cooldown is running),
 * then links the care context, and stops at "Care context linked successfully". What follows (the patient's consent,
 * ABDM's data request and the health-data push) is driven by ABDM callbacks that the server handles on its own, so
 * it is deliberately not shown here. The flow only pauses for a cooldown (ABDM rate-limit protection) or a genuine
 * error. Everything that comes from the server is HTML-escaped.
 */
(function (window, $) {
  'use strict';

  var C = window.ABDM_LINK_MODAL;
  if (!C || !$) { return; }

  var POLL_MS = C.pollIntervalMs || 5000;
  var POLL_STEP = POLL_MS / 1000;
  var POLL_MAX = C.pollMaxSeconds || 600;             // token / care context: ABDM's async callback is expected in seconds
  var TYPE = C.type;
  var $modal;

  /* Wording that differs per module (legacy kept three near-identical copies of this). */
  var HI_LABELS = {
    OPConsultation:       'OP Consultation',
    DischargeSummary:     'Discharge Summary',
    Prescription:         'Prescription',
    DiagnosticReport:     'Diagnostic Report',
    Invoice:              'Invoice',
    ImmunizationRecord:   'Immunization Record',
    WellnessRecord:       'Wellness Record',
    HealthDocumentRecord: 'Health Document Record'
  };
  var NO_DATA_TEXT = {
    opd: 'This OPD visit has no clinical data, prescriptions, lab results, invoices, immunization, wellness, or health documents. Please add data to the visit before linking to ABDM.',
    ipd: 'This IPD admission has no discharge summary, prescriptions, lab results, invoices, immunization, wellness, or health documents. Please add data before linking to ABDM.',
    dcd: 'This DCD visit has no discharge summary, prescriptions, lab results, invoices, immunization, wellness, or health documents. Please add data before linking to ABDM.'
  };


  /* ── state: one timer per concern, never shared between steps ── */
  var tokenPoll = null, tokenPollElapsed = 0, tokenCooldown = null;
  var ccPoll = null, ccPollElapsed = 0, ccCooldown = null;
  var ccAutoTriggered = false;
  var ccLinkInProgress = false;
  var linkedChanged = false; // a care context was linked during this opening: the page behind it needs a refresh on close
  var openCount = 0; // bumped on every open/close so a late response from a previous session is ignored

  /* ── helpers ── */
  function esc(s) { return s === null || s === undefined ? '' : $('<div>').text(String(s)).html(); }
  function body(html) { $('#abdm_link_modal_body').html(html); }
  function spinner(msg) { return '<i class="fa fa-spinner fa-spin"></i> ' + msg; }
  function csrf() { return C.csrf || $('meta[name="csrf-token"]').attr('content') || ''; }


  function formatRemaining(seconds) {
    if (seconds === null || seconds === undefined || isNaN(seconds)) { return ''; }
    seconds = Math.abs(Math.round(seconds));
    var days = Math.floor(seconds / 86400);
    var hours = Math.floor((seconds % 86400) / 3600);
    if (days > 0) { return days + 'd ' + hours + 'h'; }
    var minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) { return hours + 'h ' + minutes + 'm'; }
    var secs = seconds % 60;
    if (minutes > 0) { return minutes + 'm ' + secs + 's'; }
    return secs + 's';
  }

  function post(url, data) {
    return $.ajax({ url: url, type: 'POST', data: $.extend({ _token: csrf() }, data || {}), dataType: 'json' });
  }


  function clearTimer(t) { if (t) { clearInterval(t); } return null; }
  function clearTokenCooldown() { tokenCooldown = clearTimer(tokenCooldown); }
  function clearTokenPoll() { tokenPoll = clearTimer(tokenPoll); tokenPollElapsed = 0; }
  function clearCcCooldown() { ccCooldown = clearTimer(ccCooldown); }
  function clearCcPoll() { ccPoll = clearTimer(ccPoll); ccPollElapsed = 0; }
  function clearAll() {
    clearTokenCooldown(); clearTokenPoll(); clearCcCooldown(); clearCcPoll();
  }

  /* ================================================================
     Entry point - the "Link to ABDM" link on the OPD / IPD / DCD overview
     ================================================================ */
  window.openAbdmLinkModal = function (triggerEl) {
    // A tooltip on the trigger does not hide by itself when a modal opens over it.
    if (triggerEl && triggerEl.nodeType === 1 && $.fn.tooltip) { $(triggerEl).tooltip('hide'); }
    clearAll();
    openCount++;
    ccAutoTriggered = false;
    ccLinkInProgress = false;
    linkedChanged = false;
    body('<div class="text-center text-muted">Checking existing link token...</div>');
    $('#abdmLinkModal').modal('show');
    checkLinkToken();
  };

  $(document).on('hidden.bs.modal', '#abdmLinkModal', function () {
    clearAll();
    openCount++;
    ccAutoTriggered = false;
    ccLinkInProgress = false;
    // The overview's status badge and its "Link to ABDM" button depend on what was just linked.
    if (linkedChanged) { window.location.reload(); }
  });

  /* ================================================================
     STEP 1 - link token (abdm_link_tokens)
     ================================================================ */
  function checkLinkToken() {
    var session = openCount;
    post(C.urls.tokenCheck, { patient_id: C.patientId })
      .done(function (resp) { if (session === openCount) { renderStep1(resp); } })
      .fail(function () {
        if (session === openCount) { body('<div class="alert alert-danger">Could not reach the server. Please try again.</div>'); }
      });
  }

  // ABDM's generate-token call is asynchronous: the real token arrives later via a server-to-server callback.
  // Polling check is a cheap local DB read (no call to ABDM).
  function pollForLinkToken() {
    clearTokenPoll();
    var session = openCount;
    tokenPoll = setInterval(function () {
      tokenPollElapsed += POLL_STEP;
      post(C.urls.tokenCheck, { patient_id: C.patientId }).done(function (resp) {
        if (session !== openCount) { return; }
        if (!resp.success || !resp.pending || resp.existing || tokenPollElapsed >= POLL_MAX) {
          clearTokenPoll();
          renderStep1(resp);
        }
      }); // a transient network error is not worth interrupting staff over - the next tick retries
    }, POLL_MS);
  }

  function renderStep1(resp) {
    clearTokenCooldown();
    if (!resp.success) {
      body('<div class="alert alert-danger">' + esc(resp.error || 'Something went wrong') + '</div>');
      return;
    }

    var html = '<div class="alert alert-warning" style="font-size:13px;">Make sure the ABHA Address, ABHA Number, Patient Name, Gender and Year of Birth on this record exactly match the patient\'s actual ABHA record before linking - ABDM will reject the request otherwise.</div>';

    if (resp.existing) {
      html += '<div class="alert alert-success"><b>A valid link token already exists</b><br>'
        + 'ABHA Address: ' + esc(resp.abha_address) + '<br>'
        + 'Issued: ' + esc(resp.issued_at) + ' UTC<br>'
        + 'Expires: ' + esc(resp.expires_at) + ' UTC (' + formatRemaining(resp.seconds_remaining) + ' remaining)</div>';
    } else {
      html += '<div class="alert alert-info">No valid link token found for this patient yet.</div>';
    }

    // A request from an earlier opening is still awaiting ABDM's callback: wait for it instead of offering a
    // button that ABDM would reject as a duplicate in-flight request.
    if (resp.pending && !resp.existing) {
      html += '<div class="alert alert-info">' + spinner('Waiting for ABDM to confirm a previously requested link token... this usually takes just a few seconds.') + '</div>';
      body(html);
      pollForLinkToken();
      return;
    }

    var hasCooldown = !!(resp.cooldown_seconds_remaining && resp.cooldown_seconds_remaining > 0);
    if (hasCooldown) {
      // ABDM's actual stored error from the failed attempt, not a paraphrase.
      var reason = resp.cooldown_error_message || (resp.cooldown_is_duplicate
        ? 'ABDM already has an active link token for this ABHA address'
        : 'the previous attempt failed');
      html += '<div class="alert alert-warning" style="font-size:13px;"><i class="fa fa-clock"></i> ' + esc(reason)
        + ' - repeated attempts can get ABDM to block this facility\'s requests for up to 24 hours.</div>';
    }

    if (!resp.existing) {
      html += '<button type="button" id="abdm_link_token_btn" class="btn btn-primary"' + (hasCooldown ? ' disabled' : '') + '>Get Linking Token</button>';
    }
    html += '<div id="abdm_link_token_result" class="mt-10"></div>';
    body(html);

    if (resp.existing) {
      showCareContextSection();
    } else if (hasCooldown) {
      runButtonCooldown($('#abdm_link_token_btn'), resp.cooldown_seconds_remaining, 'Get Linking Token', 'token');
    } else {
      // Auto-chain: no click needed. Only a cooldown or a genuine error pauses the flow.
      requestLinkToken($('#abdm_link_token_btn'));
    }
  }

  // Keeps a button disabled with a live "Wait Xm Ys..." label and re-enables it once the cooldown has passed.
  function runButtonCooldown($el, seconds, label, kind) {
    if (!$el.length) { return; }
    var remaining = Math.round(seconds);
    $el.prop('disabled', true).text('Wait ' + formatRemaining(remaining) + '...');
    var timer = setInterval(function () {
      remaining -= 1;
      if (remaining <= 0) {
        if (kind === 'token') { clearTokenCooldown(); } else { clearCcCooldown(); }
        $el.prop('disabled', false).text(label);
        return;
      }
      $el.text('Wait ' + formatRemaining(remaining) + '...');
    }, 1000);
    if (kind === 'token') { clearTokenCooldown(); tokenCooldown = timer; } else { clearCcCooldown(); ccCooldown = timer; }
  }

  $(document).on('click', '#abdm_link_token_btn', function () { requestLinkToken($(this)); });

  function requestLinkToken($btn) {
    var session = openCount;
    var label = $btn.length ? $btn.text() : 'Get Linking Token';
    // Disabled immediately so a double click cannot fire a second real request to ABDM.
    $btn.prop('disabled', true);
    $('#abdm_link_token_result').html('<div class="text-muted">Requesting link token from ABDM...</div>');

    post(C.urls.tokenRequest, { patient_id: C.patientId }).done(function (resp) {
      if (session !== openCount) { return; }
      var $result = $('#abdm_link_token_result');
      if (resp.success) {
        if (resp.pending) {
          // Normal outcome: the request was accepted and the token arrives by callback. Keep polling, and do not
          // leave the button clickable (a duplicate in-flight request would be rejected).
          $btn.remove();
          $result.html('<div class="alert alert-info">' + spinner(esc(resp.message || 'Request accepted by ABDM - waiting for confirmation.')) + '</div>');
          pollForLinkToken();
          return;
        }
        var r = resp.token_row || {};
        $btn.remove();
        $result.html('<div class="alert alert-success"><b>Link token generated successfully.</b>'
          + (r.issued_at ? '<br>Issued: ' + esc(r.issued_at) + ' UTC' : '')
          + (r.expires_at ? '<br>Expires: ' + esc(r.expires_at) + ' UTC' : '') + '</div>');
        showCareContextSection();
        return;
      }

      if (resp.pending) {
        // An earlier request is still awaiting the callback: too soon to ask again, not a failure.
        $btn.remove();
        $result.html('<div class="alert alert-info">' + spinner(esc(resp.error)) + '</div>');
        pollForLinkToken();
        return;
      }

      $result.html('<div class="alert alert-danger">' + esc(resp.error || 'Failed to generate link token') + '</div>');
      if (resp.cooldown_seconds_remaining && $btn.length) {
        // A real call to ABDM failed: count down before another attempt is allowed (ABDM's rate limit).
        runButtonCooldown($btn, resp.cooldown_seconds_remaining, label, 'token');
      } else {
        // Purely local validation error: no ABDM call was made, safe to retry at once.
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      if (session !== openCount) { return; }
      $btn.prop('disabled', false);
      $('#abdm_link_token_result').html('<div class="alert alert-danger">Could not reach the server. Please try again.</div>');
    });
  }

  /* ================================================================
     STEP 2 - link the care context(s) of this OPD visit / IPD admission / DCD record
     ================================================================ */
  function showCareContextSection() {
    if ($('#abdm_care_context_section').length) { return; } // never duplicate on a rapid reopen / repeated callback
    $('#abdm_link_modal_body').append('<hr><div id="abdm_care_context_section"><div class="text-center text-muted">Checking care context...</div></div>');
    checkCareContext();
  }

  function checkCareContext() {
    var session = openCount;
    post(C.urls.ccCheck)
      .done(function (resp) { if (session === openCount) { renderCareContext(resp); } })
      .fail(function () {
        if (session !== openCount) { return; }
        var $s = $('#abdm_care_context_section');
        if ($s.length) { $s.html('<div class="alert alert-danger">Could not reach the server. Please try again.</div>'); }
      });
  }

  // ABDM's add-care-context call is asynchronous too: poll until the on_carecontext callback has settled it.
  function pollForCareContext() {
    clearCcPoll();
    var session = openCount;
    ccPoll = setInterval(function () {
      ccPollElapsed += POLL_STEP;
      post(C.urls.ccCheck).done(function (resp) {
        if (session !== openCount) { return; }
        if (!resp.success || !resp.pending || resp.existing || ccPollElapsed >= POLL_MAX) {
          clearCcPoll();
          if (resp.success && resp.existing) { linkedChanged = true; }
          renderCareContext(resp);
        }
      });
    }, POLL_MS);
  }

  function hiLabel(hiType) { return HI_LABELS[hiType] || hiType; }

  function renderCareContext(resp) {
    clearCcCooldown();
    var $s = $('#abdm_care_context_section');
    if (!$s.length) { return; }

    if (!resp.success) {
      $s.html('<div class="alert alert-danger">' + esc(resp.error || 'Something went wrong') + '</div>');
      return;
    }
    if (!resp.has_link_token) {
      $s.html('<div class="text-muted" style="font-size:13px;">Step 2 (Link Care Context) will be available once a valid link token exists.</div>');
      return;
    }

    var html = '<h6>Step 2: Link Care Context</h6>';
    html += '<div class="text-muted" style="font-size:12px;">Care Context Ref: <b>' + esc(resp.care_context_reference_number)
      + '</b><br>Display: <b>' + esc(resp.care_context_display) + '</b></div>';

    // HI types already linked (several can be linked while others are still outstanding).
    var seen = {}, linked = [];
    $.each(resp.linked_hi_types || [], function (i, ht) {
      if (ht.status === 'linked' && !seen[ht.hi_type]) { seen[ht.hi_type] = true; linked.push(ht); }
    });
    if (linked.length) {
      html += '<div style="font-size:12px; margin-top:8px;"><b>Linked HI Types:</b> '
        + $.map(linked, function (ht) {
          return '<span style="color:#2196F3;">' + esc(hiLabel(ht.hi_type)) + '<span class="badge bg-success" style="font-size:10px; margin-left:4px;">&#10003;</span></span>';
        }).join(', ') + '</div>';
    }
    if (!resp.existing) {
      var toLink = resp.unlinked_hi_types || resp.available_hi_types || [];
      if (toLink.length) {
        html += '<div style="font-size:12px; margin-top:8px;"><b>HI Types to be linked:</b> '
          + $.map(toLink, function (ht) { return '<span style="color:#4CAF50;">' + esc(hiLabel(ht)) + '</span>'; }).join(', ') + '</div>';
      }
    }

    // What will actually be shared; with nothing to share there is nothing to link.
    var dc = resp.data_check;
    if (dc) {
      if (dc.has_any) {
        html += '<div class="alert alert-info mt-10" style="font-size:13px;"><i class="fa fa-info-circle"></i> <b>Data to be shared:</b><br>'
          + '<ul style="margin:5px 0 0 0; padding-left:20px;">'
          + $.map(dc.details || [], function (d) { return '<li>' + esc(d) + '</li>'; }).join('')
          + '</ul></div>';
      } else {
        html += '<div class="alert alert-warning mt-10" style="font-size:13px;"><i class="fa fa-exclamation-triangle"></i> <b>No data available to share</b><br>'
          + esc(NO_DATA_TEXT[TYPE]) + '</div>';
        $s.html(html);
        return;
      }
    }

    if (resp.existing) {
      linkedChanged = true; // also covers a page that was opened before the link: refresh drops its stale button
      html += '<div class="alert alert-success mt-10"><i class="fa fa-check-circle"></i> <b>Care context linked successfully!</b><br>Linked: ' + esc(resp.linked_at) + ' UTC</div>';
      $s.html(html);
      return;
    }

    if (resp.pending) {
      html += '<div class="alert alert-info mt-10">' + spinner('Waiting for ABDM to confirm the care context link... this usually takes just a few seconds.') + '</div>';
      $s.html(html);
      pollForCareContext();
      return;
    }

    var hasCooldown = !!(resp.cooldown_seconds_remaining && resp.cooldown_seconds_remaining > 0);
    if (hasCooldown) {
      var reason = resp.cooldown_error_message || (resp.cooldown_is_duplicate
        ? 'ABDM already has this care context linked'
        : 'the previous attempt failed');
      html += '<div class="alert alert-warning mt-10" style="font-size:13px;"><i class="fa fa-clock"></i> ' + esc(reason) + '</div>';
    }
    html += '<button type="button" id="abdm_care_context_btn" class="btn btn-primary btn-sm"' + (hasCooldown ? ' disabled' : '') + '>Link Care Context</button>';
    html += '<div id="abdm_care_context_result" class="mt-10"></div>';
    $s.html(html);

    if (hasCooldown) {
      runButtonCooldown($('#abdm_care_context_btn'), resp.cooldown_seconds_remaining, 'Link Care Context', 'cc');
    } else if (!ccAutoTriggered) {
      // Auto-trigger once per modal session; after that only an explicit click links again.
      ccAutoTriggered = true;
      linkCareContext($('#abdm_care_context_btn'));
    }
  }

  $(document).on('click', '#abdm_care_context_btn', function () { linkCareContext($(this)); });

  function linkCareContext($btn) {
    if (ccLinkInProgress) { return; }
    ccLinkInProgress = true;
    var session = openCount;
    var label = $btn.length ? $btn.text() : 'Link Care Context';
    $btn.prop('disabled', true);
    $('#abdm_care_context_result').html('<div class="text-muted">Linking care context with ABDM...</div>');

    post(C.urls.ccLink).done(function (resp) {
      if (session !== openCount) { return; }
      ccLinkInProgress = false;
      var $result = $('#abdm_care_context_result');

      if (resp.success) {
        if (resp.linked) {
          // The on_carecontext callback arrived while the request was held open: confirmed.
          linkedChanged = true;
          $btn.remove();
          $result.html('<div class="alert alert-success"><i class="fa fa-check-circle"></i> <b>Care context linked successfully!</b><br>'
            + esc(resp.message || 'ABDM confirmed the link.') + '</div>');
          return;
        }
        if (resp.pending) {
          $btn.remove();
          if (resp.timeout) {
            $result.html('<div class="alert alert-warning"><i class="fa fa-clock"></i> ' + esc(resp.message || 'Request is taking longer than expected.')
              + ' <button type="button" class="btn btn-sm btn-primary" id="abdm_refresh_btn">Refresh to Check Status</button></div>');
          } else {
            $result.html('<div class="alert alert-info">' + spinner(esc(resp.message || 'Request accepted by ABDM - waiting for confirmation.')) + '</div>');
          }
          pollForCareContext();
          return;
        }
        // resp.existing: everything was already linked
        linkedChanged = true;
        $btn.remove();
        $result.html('<div class="alert alert-success"><i class="fa fa-check-circle"></i> <b>Care context linked successfully!</b></div>');
        return;
      }

      if (resp.pending) {
        // A previous request for this encounter is still awaiting ABDM's callback.
        $btn.remove();
        $result.html('<div class="alert alert-info">' + spinner(esc(resp.error)) + '</div>');
        pollForCareContext();
        return;
      }

      $result.html('<div class="alert alert-danger">' + esc(resp.error || 'Failed to link care context') + '</div>');
      if (resp.cooldown_seconds_remaining && $btn.length) {
        runButtonCooldown($btn, resp.cooldown_seconds_remaining, label, 'cc');
      } else {
        // Local validation error: nothing was sent to ABDM, so retrying at once is safe.
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      if (session !== openCount) { return; }
      ccLinkInProgress = false;
      $btn.prop('disabled', false);
      $('#abdm_care_context_result').html('<div class="alert alert-danger">Could not reach the server. Please try again.</div>');
    });
  }

  $(document).on('click', '#abdm_refresh_btn', function () { window.location.reload(); });


})(window, window.jQuery);
