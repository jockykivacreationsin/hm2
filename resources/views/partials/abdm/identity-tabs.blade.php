{{--
  "Patient Identity (Optional)" verification tabs shared by the OPD register and the Patients page
  (port of the tabs in bmhabdm/opd-list.php and bmhabdm/patients.php):
  Find by Mobile / Has Aadhaar Only / Has ABHA Card, plus the "Download ABHA Card" box.

  $mode = 'add'  -> Add Patient modal (new patient), element ids unprefixed
  $mode = 'edit' -> Edit Patient modal (Patients page), element ids prefixed with "edit_" / "edit-"
  $mode = 'opd'  -> Create OPD modal (existing patient), element ids prefixed with "opd_" / "opd-"

  $only  = 'tabs' | 'download' renders just that part (default: both). The Edit Patient modal keeps the
           download box outside the tabs, so it stays reachable while the tabs are collapsed behind "Linked".

  Behaviour lives in partials/abdm/identity-scripts.blade.php; every id/onclick below is shared with it.
--}}
@php
    $mode = $mode ?? 'add';
    $only = $only ?? null;
    $p    = ['opd' => 'opd_', 'edit' => 'edit_'][$mode] ?? '';
    $t    = ['opd' => 'opd-', 'edit' => 'edit-'][$mode] ?? '';
    $tabsId = ['opd' => 'opdPatientIdentityTabs', 'edit' => 'editPatientIdentityTabs'][$mode] ?? 'patientIdentityTabs';
@endphp
@if($only !== 'download')
<ul class="nav nav-tabs" id="{{ $tabsId }}" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="{{ $t }}identity-mobile-tab" data-bs-toggle="tab" data-bs-target="#{{ $t }}identity-mobile-pane" type="button" role="tab">Find by Mobile</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="{{ $t }}identity-aadhaar-tab" data-bs-toggle="tab" data-bs-target="#{{ $t }}identity-aadhaar-pane" type="button" role="tab">Has Aadhaar Only</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="{{ $t }}identity-abha-tab" data-bs-toggle="tab" data-bs-target="#{{ $t }}identity-abha-pane" type="button" role="tab">Has ABHA Card</button>
    </li>
</ul>
<div class="tab-content border border-top-0 p-15">
    <!-- Find by Mobile -->
    <div class="tab-pane fade show active" id="{{ $t }}identity-mobile-pane" role="tabpanel">
        <div class="row">
            <div class="col-12 col-sm-6">
                <div class="form-group local-forms mb-10">
                    <label>Mobile Number</label>
                    <input type="text" id="{{ $p }}mobile_login_input" class="form-control" maxlength="10" placeholder="10-digit mobile number">
                    <small class="text-muted">Finds all ABHA accounts linked to this mobile number.</small>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}mobile_login_send_otp_btn" class="btn btn-secondary mt-25" onclick="sendMobileLoginOtp('{{ $mode }}')">Send OTP</button>
            </div>
        </div>
        <div class="row" id="{{ $p }}mobile_login_otp_group" style="display:none;">
            <div class="col-12 col-sm-6">
                <div class="form-group local-forms mb-10">
                    <label>Enter OTP</label>
                    <input type="text" id="{{ $p }}mobile_login_otp_input" class="form-control" maxlength="6">
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}mobile_login_verify_btn" class="btn btn-primary mt-25" onclick="verifyMobileLoginOtp('{{ $mode }}')">Verify &amp; Find ABHAs</button>
            </div>
        </div>
        <div id="{{ $p }}mobile_login_error" class="text-danger mb-10" style="display:none;"></div>
        <div id="{{ $p }}mobile_login_accounts_list" style="display:none;"></div>
        <div id="{{ $p }}mobile_login_result" class="identity-result" style="display:none;margin-top:10px;">
            <div class="d-flex align-items-start">
                <img id="{{ $p }}mobile_login_result_photo" src="" alt="ABHA photo" style="display:none;width:60px;height:60px;object-fit:cover;border-radius:6px;margin-right:12px;">
                <div>
                    <i class="fa fa-check-circle text-success"></i> Selected &mdash;
                    ABHA Number: <b id="{{ $p }}mobile_login_result_number"></b>,
                    ABHA Address: <b id="{{ $p }}mobile_login_result_address"></b>
                    @if($mode !== 'opd')
                        <br><small class="text-muted">Name, gender, age, mobile number and photo (if available) have been filled in automatically below.</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Has Aadhaar Only -->
    <div class="tab-pane fade" id="{{ $t }}identity-aadhaar-pane" role="tabpanel">
        <div class="row">
            <div class="col-12 col-sm-6">
                <div class="form-group local-forms mb-10">
                    <label>Aadhaar Number</label>
                    <input type="text" id="{{ $p }}aadhaar_input" class="form-control" maxlength="12" placeholder="12-digit Aadhaar number" autocomplete="off">
                    <small class="text-muted">OTP will be sent to the mobile number linked with this Aadhaar in UIDAI records.</small>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}aadhaar_send_otp_btn" class="btn btn-secondary mt-25" onclick="sendAadhaarOtp('{{ $mode }}')">Send OTP</button>
            </div>
        </div>
        <div class="mb-10">
            <a href="javascript:void(0)" id="{{ $p }}aadhaar_terms_link" class="text-primary" onclick="openAbhaTermsModal('{{ $mode }}')"><i class="fa fa-file-text-o"></i> Accept Terms &amp; Conditions</a>
        </div>
        <div class="row" id="{{ $p }}aadhaar_otp_group" style="display:none;">
            <div class="col-12 col-sm-4">
                <div class="form-group local-forms mb-10">
                    <label>Enter OTP</label>
                    <input type="text" id="{{ $p }}aadhaar_otp_input" class="form-control" maxlength="6">
                    <small id="{{ $p }}aadhaar_otp_sent_message" class="text-muted d-block mt-5" style="display:none;"></small>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="form-group local-forms mb-10">
                    <label>Primary Mobile Number</label>
                    <input type="text" id="{{ $p }}aadhaar_mobile_input" class="form-control" maxlength="10" placeholder="10-digit mobile number">
                    <small class="text-muted">Required by ABDM to create the ABHA. Use the Aadhaar-linked mobile if you have it, else any valid mobile.</small>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}aadhaar_verify_btn" class="btn btn-primary mt-25" onclick="verifyAadhaarOtp('{{ $mode }}')">Verify &amp; Fetch ABHA</button>
            </div>
        </div>
        <div id="{{ $p }}aadhaar_error" class="text-danger mb-10" style="display:none;"></div>
        <div id="{{ $p }}aadhaar_result" class="identity-result" style="display:none;">
            <img id="{{ $p }}aadhaar_result_photo" src="" alt="ABHA photo" style="display:none;width:60px;height:60px;object-fit:cover;border-radius:6px;margin-bottom:10px;">
            <div class="row">
                <div class="col-12 col-sm-6">
                    <div class="form-group local-forms mb-10">
                        <label>ABHA Number</label>
                        <input type="text" id="{{ $p }}aadhaar_result_abha_number" class="form-control" readonly>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="form-group local-forms mb-10">
                        <label>ABHA Address</label>
                        <input type="text" id="{{ $p }}aadhaar_result_abha_address" class="form-control" readonly>
                    </div>
                </div>
            </div>
        </div>
        <div id="{{ $p }}aadhaar_address_picker" class="mt-10" style="display:none;">
            <div class="text-muted mb-10">This Aadhaar does not have an ABHA Address yet - pick one of ABDM's suggestions or type your own, then create it.</div>
            <div class="row">
                <div class="col-12 col-sm-5">
                    <div class="form-group local-forms mb-10">
                        <label>Suggested ABHA Addresses</label>
                        <select id="{{ $p }}aadhaar_address_suggestions" class="form-control"></select>
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <div class="form-group local-forms mb-10">
                        <label>Or type a custom ABHA Address</label>
                        <input type="text" id="{{ $p }}aadhaar_address_custom" class="form-control" placeholder="e.g. yourname123">
                    </div>
                </div>
                <div class="col-12 col-sm-3">
                    <button type="button" class="btn btn-primary mt-25" id="{{ $p }}aadhaar_address_create_btn" onclick="createAbhaAddress('{{ $mode }}')">Create ABHA Address</button>
                </div>
            </div>
            <div id="{{ $p }}aadhaar_address_error" class="text-danger" style="display:none;"></div>
        </div>
    </div>

    <!-- Has ABHA Card -->
    <div class="tab-pane fade" id="{{ $t }}identity-abha-pane" role="tabpanel">
        <div class="row">
            <div class="col-12 col-sm-6">
                <div class="form-group local-forms mb-10">
                    <label>ABHA Number / ABHA Address</label>
                    <input type="text" id="{{ $p }}abha_card_input" class="form-control" placeholder="e.g. 91-1234-5678-9012 or name@abdm">
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}abha_card_send_otp_btn" class="btn btn-secondary mt-25" onclick="sendAbhaCardOtp('{{ $mode }}')">Send OTP</button>
            </div>
        </div>
        <div class="row" id="{{ $p }}abha_card_otp_group" style="display:none;">
            <div class="col-12 col-sm-6">
                <div class="form-group local-forms mb-10">
                    <label>Enter OTP</label>
                    <input type="text" id="{{ $p }}abha_card_otp_input" class="form-control" maxlength="6">
                    <small id="{{ $p }}abha_card_otp_sent_message" class="text-muted d-block mt-5" style="display:none;"></small>
                </div>
            </div>
            <div class="col-12 col-sm-3">
                <button type="button" id="{{ $p }}abha_card_verify_btn" class="btn btn-primary mt-25" onclick="verifyAbhaCardOtp('{{ $mode }}')">Verify</button>
            </div>
        </div>
        <div id="{{ $p }}abha_card_error" class="text-danger mb-10" style="display:none;"></div>
        <div id="{{ $p }}abha_card_result" class="identity-result" style="display:none;">
            <div class="d-flex align-items-start">
                <img id="{{ $p }}abha_card_result_photo" src="" alt="ABHA photo" style="display:none;width:60px;height:60px;object-fit:cover;border-radius:6px;margin-right:12px;">
                <div>
                    <i class="fa fa-check-circle text-success"></i> Verified &mdash;
                    ABHA Number: <b id="{{ $p }}abha_card_result_number"></b>,
                    ABHA Address: <b id="{{ $p }}abha_card_result_address"></b>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@if($only !== 'tabs')
<div id="{{ $p }}abha_card_download_box" class="mt-10" style="display:none;">
    <button type="button" id="{{ $p }}abha_card_download_btn" class="btn btn-outline-primary btn-sm" onclick="downloadAbhaCard('{{ $mode }}')"><i class="fa fa-id-card"></i> Download ABHA Card</button>
    <small class="text-muted d-block mt-5">Available for a short while after verification - download now if needed.</small>
    <div id="{{ $p }}abha_card_download_error" class="text-danger mt-5" style="display:none;"></div>
    <div id="{{ $p }}abha_card_download_preview" style="display:none;margin-top:10px;">
        <img id="{{ $p }}abha_card_download_image" src="" alt="ABHA Card" style="max-width:100%;border:1px solid #ddd;border-radius:6px;">
        <br>
        <a id="{{ $p }}abha_card_download_link" href="javascript:void(0)" class="btn btn-sm btn-success mt-5" download="ABHA_Card.png"><i class="fa fa-download"></i> Save as PNG</a>
    </div>
</div>
@endif
