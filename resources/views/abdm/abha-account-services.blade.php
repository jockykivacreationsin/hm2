@extends('layouts.app')
@section('title', 'ABHA Account Services')
@section('content')
{{-- Legacy: abha-account-services.php (permission im_opd_history is enforced by the route-permission middleware). --}}
@if(isset($canAccess) && !$canAccess)
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">ABHA Account Services</div>
                    <div class="tbl-filter-box">
                        <p class="text-muted mb-0">Manage existing ABHA accounts: forgot number recovery, update mobile/email</p>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="mt-3">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#forgot-abha-tab" role="tab">
                                <i class="fa fa-search"></i> Forgot ABHA Number
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#update-mobile-tab" role="tab">
                                <i class="fa fa-mobile"></i> Update Mobile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#update-email-tab" role="tab">
                                <i class="fa fa-envelope"></i> Update Email
                            </a>
                        </li>
                    </ul>

                    <!-- Tabs Content -->
                    <div class="tab-content p-3">

                        <!-- Forgot ABHA Number Tab -->
                        <div class="tab-pane fade show active" id="forgot-abha-tab" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> Recover your ABHA Number using Aadhaar OTP verification
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>Aadhaar Number <span class="req-star">*</span></label>
                                        <input type="text" id="forgot_aadhaar_input" class="form-control" maxlength="12" inputmode="numeric" autocomplete="off" placeholder="12-digit Aadhaar number">
                                    </div>
                                    <button type="button" id="forgot_send_btn" class="btn btn-primary" onclick="sendForgotAbhaOtp()">
                                        <i class="fa fa-paper-plane"></i> Send OTP
                                    </button>
                                </div>
                            </div>

                            <div id="forgot_otp_group" class="row mt-20" style="display:none;">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>Enter OTP <span class="req-star">*</span></label>
                                        <input type="text" id="forgot_otp_input" class="form-control" maxlength="6" inputmode="numeric" autocomplete="off" placeholder="6-digit OTP">
                                    </div>
                                    <button type="button" id="forgot_verify_btn" class="btn btn-success" onclick="verifyForgotAbhaOtp()">
                                        <i class="fa fa-check"></i> Verify &amp; Find ABHA
                                    </button>
                                </div>
                            </div>

                            <div id="forgot_error" class="alert alert-danger mt-20" style="display:none;"></div>
                            <div id="forgot_result" class="alert alert-success mt-20" style="display:none;"></div>
                        </div>

                        <!-- Update Mobile Tab -->
                        <div class="tab-pane fade" id="update-mobile-tab" role="tabpanel">
                            <div class="alert alert-info">
                                <strong>Step 1:</strong> First verify your ABHA with existing credentials
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>ABHA Number / Address <span class="req-star">*</span></label>
                                        <input type="text" id="upd_mobile_abha_input" class="form-control" placeholder="e.g. 91-1234-5678-9012 or name@sbx">
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" onclick="sendLoginOtpForMobile()">
                                        <i class="fa fa-key"></i> Send Login OTP
                                    </button>
                                </div>
                            </div>

                            <div id="upd_mobile_login_group" class="row mt-20" style="display:none;">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>Login OTP <span class="req-star">*</span></label>
                                        <input type="text" id="upd_mobile_login_otp" class="form-control" maxlength="6" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="verifyLoginOtpForMobile()">
                                        <i class="fa fa-check"></i> Verify Login
                                    </button>
                                </div>
                            </div>

                            <div id="upd_mobile_update_group" class="mt-20" style="display:none;">
                                <hr>
                                <div class="alert alert-success">
                                    <strong>Step 2:</strong> Enter new mobile number
                                </div>
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group local-forms">
                                            <label>New Mobile Number <span class="req-star">*</span></label>
                                            <input type="text" id="upd_mobile_input" class="form-control" maxlength="10" inputmode="numeric" autocomplete="off" placeholder="10-digit mobile">
                                        </div>
                                        <button type="button" class="btn btn-info" onclick="sendUpdateMobileOtp()">
                                            <i class="fa fa-paper-plane"></i> Send OTP to New Mobile
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="upd_mobile_otp_group" class="row mt-20" style="display:none;">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>OTP (sent to new mobile) <span class="req-star">*</span></label>
                                        <input type="text" id="upd_mobile_otp_input" class="form-control" maxlength="6" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <button type="button" class="btn btn-success" onclick="verifyUpdateMobileOtp()">
                                        <i class="fa fa-check"></i> Verify &amp; Update
                                    </button>
                                </div>
                            </div>

                            <div id="upd_mobile_error" class="alert alert-danger mt-20" style="display:none;"></div>
                            <div id="upd_mobile_success" class="alert alert-success mt-20" style="display:none;"></div>
                        </div>

                        <!-- Update Email Tab -->
                        <div class="tab-pane fade" id="update-email-tab" role="tabpanel">
                            <div class="alert alert-info">
                                <strong>Step 1:</strong> First verify your ABHA with existing credentials
                            </div>

                            <div class="row">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>ABHA Number / Address <span class="req-star">*</span></label>
                                        <input type="text" id="upd_email_abha_input" class="form-control" placeholder="e.g. 91-1234-5678-9012 or name@sbx">
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" onclick="sendLoginOtpForEmail()">
                                        <i class="fa fa-key"></i> Send Login OTP
                                    </button>
                                </div>
                            </div>

                            <div id="upd_email_login_group" class="row mt-20" style="display:none;">
                                <div class="col-12 col-md-6">
                                    <div class="form-group local-forms">
                                        <label>Login OTP <span class="req-star">*</span></label>
                                        <input type="text" id="upd_email_login_otp" class="form-control" maxlength="6" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="verifyLoginOtpForEmail()">
                                        <i class="fa fa-check"></i> Verify Login
                                    </button>
                                </div>
                            </div>

                            <div id="upd_email_update_group" class="mt-20" style="display:none;">
                                <hr>
                                <div class="alert alert-success">
                                    <strong>Step 2:</strong> Enter new email address
                                </div>
                                <div class="alert alert-warning">
                                    <i class="fa fa-info-circle"></i> ABDM will send a verification link to the new email address
                                </div>
                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <div class="form-group local-forms">
                                            <label>New Email Address <span class="req-star">*</span></label>
                                            <input type="email" id="upd_email_input" class="form-control" maxlength="254" autocomplete="off" placeholder="new.email@example.com">
                                        </div>
                                        <button type="button" class="btn btn-warning" onclick="sendUpdateEmailLink()">
                                            <i class="fa fa-paper-plane"></i> Send Verification Link
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="upd_email_error" class="alert alert-danger mt-20" style="display:none;"></div>
                            <div id="upd_email_success" class="alert alert-success mt-20" style="display:none;"></div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if(!isset($canAccess) || $canAccess)
<script>
/*
 * Same flow/ids/messages as legacy abha-account-services.php. The legacy abdm/abha_account_ajax.php
 * actions map onto the AbdmIdentityController routes below. Login txn ids and the X-token live in
 * the server session; the browser only ever sends the user's own inputs (never a token).
 */
var ABHA_ACCT_URLS = {
    forgotSend:   @json(route('abdm.identity.aadhaar-login-send-otp')),
    forgotVerify: @json(route('abdm.identity.aadhaar-login-verify-otp')),
    mobileLogin:  @json(route('abdm.identity.update-mobile.login-otp')),
    mobileVerify: @json(route('abdm.identity.update-mobile.verify-login')),
    mobileSend:   @json(route('abdm.identity.update-mobile.send-otp')),
    mobileFinish: @json(route('abdm.identity.update-mobile.verify-otp')),
    emailLogin:   @json(route('abdm.identity.update-email.login-otp')),
    emailVerify:  @json(route('abdm.identity.update-email.verify-login')),
    emailSend:    @json(route('abdm.identity.update-email.send-link'))
};

/* ABDM values are inserted as HTML below - always escape them. */
function abhaAcctEsc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

function abhaAcctAlert(icon, title, text, timer) {
    var o = { icon: icon, title: title, text: text };
    if (timer) { o.timer = timer; }
    return Swal.fire(o);
}

/* POST helper: ok -> onSuccess(resp); business failure -> "Failed" style alert; transport failure -> network alert. */
function abhaAcctPost(url, data, handlers) {
    $.ajax({
        url: url, type: 'POST', data: data, dataType: 'json',
        success: function (resp) {
            if (resp && resp.success) { handlers.success(resp); }
            else { abhaAcctAlert('error', handlers.failTitle, (resp && (resp.error || resp.message)) || handlers.failText); }
        },
        error: function (xhr) {
            var j = xhr.responseJSON || {};
            var msg = j.error || j.message;
            if (j.errors) { msg = Object.values(j.errors).map(function (v) { return v.join(' '); }).join(' '); }
            if (msg) { abhaAcctAlert('error', handlers.failTitle, msg); }
            else { abhaAcctAlert('error', 'Network Error', handlers.networkText || 'Please try again'); }
        },
        complete: handlers.complete
    });
}

// ============================================================================
// FORGOT ABHA NUMBER  (Login via Aadhaar number - verify only, never enrols a new ABHA)
// ============================================================================
function sendForgotAbhaOtp() {
    var aadhaar = $('#forgot_aadhaar_input').val().trim();
    if (!/^\d{12}$/.test(aadhaar)) {
        abhaAcctAlert('error', 'Invalid Input', 'Please enter a valid 12-digit Aadhaar number');
        return;
    }
    $('#forgot_error, #forgot_result').hide();
    $('#forgot_send_btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
    abhaAcctPost(ABHA_ACCT_URLS.forgotSend, { aadhaar_number: aadhaar }, {
        failTitle: 'Failed', failText: 'Failed to send OTP',
        networkText: 'Please check your connection and try again',
        success: function () {
            $('#forgot_aadhaar_input').val('');
            $('#forgot_otp_group').slideDown();
            $('#forgot_otp_input').focus();
            abhaAcctAlert('success', 'OTP Sent', 'OTP has been sent to your Aadhaar-linked mobile number', 2000);
        },
        complete: function () {
            $('#forgot_send_btn').prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send OTP');
        }
    });
}

function verifyForgotAbhaOtp() {
    var otp = $('#forgot_otp_input').val().trim();
    if (!/^\d{6}$/.test(otp)) {
        abhaAcctAlert('error', 'Invalid OTP', 'Please enter a valid 6-digit OTP');
        return;
    }
    $('#forgot_error, #forgot_result').hide();
    $('#forgot_verify_btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying...');
    abhaAcctPost(ABHA_ACCT_URLS.forgotVerify, { otp: otp }, {
        failTitle: 'Verification Failed', failText: 'Could not verify OTP',
        networkText: 'Please check your connection and try again',
        success: function (resp) {
            var list = (resp.abha_numbers && resp.abha_numbers.length)
                ? resp.abha_numbers
                : [(resp.abha_number || 'N/A') + (resp.name ? ' (' + resp.name + ')' : '')];
            var html = '<strong>Found ABHA Account(s):</strong><ul class="mt-10">';
            list.forEach(function (num) { html += '<li>' + abhaAcctEsc(num) + '</li>'; });
            html += '</ul>';
            $('#forgot_result').html(html).show();
            $('#forgot_otp_group').slideUp();
            $('#forgot_otp_input').val('');
            Swal.fire({ icon: 'success', title: 'ABHA Found!', html: html });
        },
        complete: function () {
            $('#forgot_verify_btn').prop('disabled', false).html('<i class="fa fa-check"></i> Verify & Find ABHA');
        }
    });
}

// ============================================================================
// UPDATE MOBILE
// ============================================================================
function sendLoginOtpForMobile() {
    var abha = $('#upd_mobile_abha_input').val().trim();
    if (!abha) { abhaAcctAlert('error', 'Required', 'Please enter ABHA Number or Address'); return; }
    $('#upd_mobile_error, #upd_mobile_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.mobileLogin, { abha: abha }, {
        failTitle: 'Failed', failText: 'Failed to send OTP',
        success: function () {
            $('#upd_mobile_login_group').slideDown();
            $('#upd_mobile_login_otp').val('').focus();
            abhaAcctAlert('success', 'OTP Sent', 'Login OTP has been sent', 2000);
        }
    });
}

function verifyLoginOtpForMobile() {
    var otp = $('#upd_mobile_login_otp').val().trim();
    if (!/^\d{6}$/.test(otp)) { abhaAcctAlert('error', 'Invalid OTP', 'Please enter a valid 6-digit OTP'); return; }
    $('#upd_mobile_error, #upd_mobile_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.mobileVerify, { otp: otp }, {
        failTitle: 'Login Failed', failText: 'Could not verify OTP',
        success: function () {
            $('#upd_mobile_login_otp').val('');
            $('#upd_mobile_login_group').slideUp();
            $('#upd_mobile_update_group').slideDown();
            $('#upd_mobile_input').focus();
            abhaAcctAlert('success', 'Login Successful', 'Now enter your new mobile number', 2000);
        }
    });
}

function sendUpdateMobileOtp() {
    var mobile = $('#upd_mobile_input').val().trim();
    if (!/^\d{10}$/.test(mobile)) { abhaAcctAlert('error', 'Invalid Mobile', 'Please enter a valid 10-digit mobile number'); return; }
    $('#upd_mobile_error, #upd_mobile_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.mobileSend, { mobile: mobile }, {
        failTitle: 'Failed', failText: 'Failed to send OTP',
        success: function () {
            $('#upd_mobile_otp_group').slideDown();
            $('#upd_mobile_otp_input').val('').focus();
            abhaAcctAlert('success', 'OTP Sent', 'OTP sent to new mobile number', 2000);
        }
    });
}

function verifyUpdateMobileOtp() {
    var otp = $('#upd_mobile_otp_input').val().trim();
    if (!/^\d{6}$/.test(otp)) { abhaAcctAlert('error', 'Invalid OTP', 'Please enter a valid 6-digit OTP'); return; }
    $('#upd_mobile_error, #upd_mobile_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.mobileFinish, { otp: otp }, {
        failTitle: 'Update Failed', failText: 'Could not update mobile',
        success: function () {
            Swal.fire({ icon: 'success', title: 'Success!', text: 'Mobile number updated successfully' })
                .then(function () { location.reload(); });
        }
    });
}

// ============================================================================
// UPDATE EMAIL
// ============================================================================
function sendLoginOtpForEmail() {
    var abha = $('#upd_email_abha_input').val().trim();
    if (!abha) { abhaAcctAlert('error', 'Required', 'Please enter ABHA Number or Address'); return; }
    $('#upd_email_error, #upd_email_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.emailLogin, { abha: abha }, {
        failTitle: 'Failed', failText: 'Failed to send OTP',
        success: function () {
            $('#upd_email_login_group').slideDown();
            $('#upd_email_login_otp').val('').focus();
            abhaAcctAlert('success', 'OTP Sent', 'Login OTP has been sent', 2000);
        }
    });
}

function verifyLoginOtpForEmail() {
    var otp = $('#upd_email_login_otp').val().trim();
    if (!/^\d{6}$/.test(otp)) { abhaAcctAlert('error', 'Invalid OTP', 'Please enter a valid 6-digit OTP'); return; }
    $('#upd_email_error, #upd_email_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.emailVerify, { otp: otp }, {
        failTitle: 'Login Failed', failText: 'Could not verify OTP',
        success: function () {
            $('#upd_email_login_otp').val('');
            $('#upd_email_login_group').slideUp();
            $('#upd_email_update_group').slideDown();
            $('#upd_email_input').focus();
            abhaAcctAlert('success', 'Login Successful', 'Now enter your new email address', 2000);
        }
    });
}

function sendUpdateEmailLink() {
    var email = $('#upd_email_input').val().trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { abhaAcctAlert('error', 'Invalid Email', 'Please enter a valid email address'); return; }
    $('#upd_email_error, #upd_email_success').hide();
    abhaAcctPost(ABHA_ACCT_URLS.emailSend, { email: email }, {
        failTitle: 'Failed', failText: 'Failed to send link',
        success: function () {
            Swal.fire({ icon: 'success', title: 'Link Sent!', text: 'Verification link sent to email. Please check your inbox.' })
                .then(function () { location.reload(); });
        }
    });
}
</script>
@endif
@endpush
