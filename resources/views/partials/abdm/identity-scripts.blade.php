{{--
  Shared behaviour of the "Patient Identity (Optional)" sections - port of the identity JS in bmhabdm/opd-list.php
  and bmhabdm/patients.php. Three contexts, told apart by $mode / the element-id prefix (see identity-tabs.blade.php):
    'add'  - Add Patient modal: a brand new patient, unprefixed ids; the verified ABHA travels with the form.
    'edit' - Edit Patient modal (Patients page): ids prefixed "edit_"; the verified ABHA travels with the form too.
    'opd'  - Create OPD modal: verifies/links an ABHA on the EXISTING patient picked in the patient dropdown,
             saved separately (see opd/partials/identity-scripts.blade.php); ids prefixed "opd_".

  The including page must define, BEFORE including this partial:
      var IDENTITY_CFG = { forms: { add: '#...', edit: '#...' }, modals: { add: '#...', edit: '#...' } };
  and, for the Add modal's "Use This Existing Patient Instead" button, a function useExistingPatientMatch().

  Legacy POSTed {action: ...} to abdm/identity_ajax.php; each action now has its own Laravel route (abdm.identity.*).
--}}
<script>
    var aadhaarVerifiedProfile = { add: null, edit: null, opd: null };      // last successful Aadhaar verify (name/gender/yob/address/mobile)
    var mobileLoginAccounts = { add: null, edit: null, opd: null };          // accounts[] of the last successful mobile-OTP verify
    var mobileLoginSelectedProfile = { add: null, edit: null, opd: null };   // the ONE account picked from that list
    var verifiedXToken = { add: null, edit: null, opd: null };               // ABDM "Download ABHA Card" X-token - held in memory only
    var verifiedIdentitySource = { add: null, edit: null, opd: null };       // 'mobile' | 'aadhaar' | 'abha_card'
    var lastIdentityMatch = { add: null };                                   // existing-patient candidate found for the Add modal
    var abhaTermsAccepted = { add: false, edit: false, opd: false };         // T&C acceptance is tracked per modal
    var pendingAbhaTermsMode = null;                                         // which modal opened #abhaTermsModal
    var opdSelectedPatient = null;                                           // Create OPD: the existing patient being verified
    var editingPatientHasPhoto = false;                                      // Edit Patient: a photo is already on file

    var IDENTITY_URLS = {
        abha_login_send_otp:        @json(route('abdm.identity.abha-login-send-otp')),
        abha_login_verify_otp:      @json(route('abdm.identity.abha-login-verify-otp')),
        aadhaar_send_otp:           @json(route('abdm.identity.aadhaar-send-otp')),
        aadhaar_verify_otp:         @json(route('abdm.identity.aadhaar-verify-otp')),
        aadhaar_create_abha_address:@json(route('abdm.identity.aadhaar-create-address')),
        mobile_login_send_otp:      @json(route('abdm.identity.mobile-send-otp')),
        mobile_login_verify_otp:    @json(route('abdm.identity.mobile-verify-otp')),
        download_abha_card:         @json(route('abdm.identity.download-abha-card')),
        save_profile_photo:         @json(route('abdm.identity.save-profile-photo')),
        check_identity_match:       @json(route('abdm.identity.check-identity-match'))
    };

    function identityIdPrefix(mode) { return mode === 'opd' ? 'opd_' : (mode === 'edit' ? 'edit_' : ''); }
    function identityForm(mode) { return $((IDENTITY_CFG.forms || {})[mode] || '#__none__'); }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function identityWarn(message) {
        Swal.fire({ icon: 'warning', text: message });
    }

    // ---------------------------------------------------------------
    // Terms & Conditions (settings.abha_creation_terms) - staff must accept before an ABHA can be created via Aadhaar
    // ---------------------------------------------------------------
    function openAbhaTermsModal(mode) {
        pendingAbhaTermsMode = mode;
        $('#abhaTermsModal').modal('show');
    }

    function acceptAbhaTerms() {
        if (pendingAbhaTermsMode) {
            abhaTermsAccepted[pendingAbhaTermsMode] = true;
            var p = identityIdPrefix(pendingAbhaTermsMode);
            $('#' + p + 'aadhaar_terms_link').html('<i class="fa fa-check-circle"></i> Terms &amp; Conditions are Accepted').removeClass('text-primary').addClass('text-success');
        }
        $('#abhaTermsModal').modal('hide');
    }

    // Only ONE tab's verified identity is "the" identity at a time - when a different tab verifies, the results
    // left showing in the other tabs are superseded and must be cleared so two contradictory identities never show.
    function clearOtherIdentityResults(mode, keepSource) {
        var p = identityIdPrefix(mode);

        if (keepSource !== 'mobile') {
            $('#' + p + 'mobile_login_result').hide();
            $('#' + p + 'mobile_login_result_photo').attr('src', '').hide();
            $('#' + p + 'mobile_login_accounts_list').hide().empty();
            mobileLoginAccounts[mode] = null;
            mobileLoginSelectedProfile[mode] = null;
        }

        if (keepSource !== 'aadhaar') {
            $('#' + p + 'aadhaar_result').hide();
            $('#' + p + 'aadhaar_result_photo').attr('src', '').hide();
            $('#' + p + 'aadhaar_result_abha_number').val('');
            $('#' + p + 'aadhaar_result_abha_address').val('');
            $('#' + p + 'aadhaar_result .aadhaar-is-new-banner').remove();
            $('#' + p + 'aadhaar_address_picker').hide();
            $('#' + p + 'aadhaar_address_suggestions').empty();
            $('#' + p + 'aadhaar_address_custom').val('');
            $('#' + p + 'aadhaar_address_error').hide();
            aadhaarVerifiedProfile[mode] = null;
        }

        if (keepSource !== 'abha_card') {
            $('#' + p + 'abha_card_result').hide();
            $('#' + p + 'abha_card_result_photo').attr('src', '').hide();
            $('#' + p + 'abha_card_result_number').text('');
            $('#' + p + 'abha_card_result_address').text('');
        }
    }

    function identityErrorMessage(xhr) {
        var j = xhr && xhr.responseJSON;
        if (j && j.errors) { return Object.values(j.errors).flat().join(' '); }
        if (j && (j.error || j.message)) { return j.error || j.message; }
        if (xhr && xhr.status === 419) { return 'Your session has expired. Please reload the page and try again.'; }
        return 'Could not reach the server. Please try again.';
    }

    function identityAjax(action, data, onSuccess, onError, $btn) {
        if ($btn) { $btn.prop('disabled', true); }
        $.ajax({
            url: IDENTITY_URLS[action],
            type: 'POST',
            data: data,
            dataType: 'json'
        }).done(function (resp) {
            if (resp && resp.success) {
                onSuccess(resp);
            } else {
                onError((resp && resp.error) ? resp.error : 'Something went wrong. Please try again.');
            }
        }).fail(function (xhr) {
            onError(identityErrorMessage(xhr));
        }).always(function () {
            if ($btn) { $btn.prop('disabled', false); }
        });
    }

    function startResendCooldown($btn, seconds) {
        cancelResendCooldown($btn, false);
        $btn.prop('disabled', true).text('Resend OTP (' + seconds + 's)');
        var remaining = seconds;
        var timer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timer);
                $btn.prop('disabled', false).text('Resend OTP');
            } else {
                $btn.text('Resend OTP (' + remaining + 's)');
            }
        }, 1000);
        $btn.data('resendTimer', timer);
    }

    function cancelResendCooldown($btn, resetLabel) {
        var timer = $btn.data('resendTimer');
        if (timer) {
            clearInterval(timer);
            $btn.removeData('resendTimer');
        }
        if (resetLabel !== false) {
            $btn.prop('disabled', false).text('Send OTP');
        }
    }

    // ---------------------------------------------------------------
    // Has ABHA Card
    // ---------------------------------------------------------------
    function sendAbhaCardOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        var val = $('#' + p + 'abha_card_input').val().trim();
        if (!val) { identityWarn('Please enter the ABHA Number or ABHA Address'); return; }
        $('#' + p + 'abha_card_error').hide();
        identityAjax('abha_login_send_otp', { login_id: val }, function (resp) {
            $('#' + p + 'abha_card_otp_group').show();
            $('#' + p + 'abha_card_otp_input').val('').focus();
            $('#' + p + 'abha_card_otp_sent_message').text(resp.message || '').toggle(!!resp.message);
            startResendCooldown($('#' + p + 'abha_card_send_otp_btn'), 30);
        }, function (err) {
            $('#' + p + 'abha_card_error').text(err).show();
        }, $('#' + p + 'abha_card_send_otp_btn'));
    }

    function verifyAbhaCardOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        var otp = $('#' + p + 'abha_card_otp_input').val().trim();
        if (!otp) { identityWarn('Please enter the OTP'); return; }
        $('#' + p + 'abha_card_error').hide();
        identityAjax('abha_login_verify_otp', { otp: otp }, function (resp) {
            $('#' + p + 'abha_card_result_number').text(resp.abha_number || '-');
            $('#' + p + 'abha_card_result_address').text(resp.abha_address || '-');
            $('#' + p + 'new_patient_abha_number').val(resp.abha_number || '');
            $('#' + p + 'new_patient_abha_address').val(resp.abha_address || '');
            if (resp.date_of_birth) { $('#' + p + 'new_patient_date_of_birth').val(resp.date_of_birth); }
            $('#' + p + 'abha_card_result').show();
            $('#' + p + 'abha_card_otp_group').hide();
            $('#' + p + 'abha_card_send_otp_btn').prop('disabled', true);

            autofillProfileFieldsToForm(mode, {
                name: resp.name || '',
                gender: resp.gender || '',
                year_of_birth: resp.year_of_birth || '',
                address: resp.address || '',
                mobile: resp.mobile || ''
            });

            verifiedIdentitySource[mode] = 'abha_card';
            clearOtherIdentityResults(mode, 'abha_card');

            checkIdentityMatch(mode, { name: resp.name, gender: resp.gender, mobile: resp.mobile, date_of_birth: resp.date_of_birth });

            showAbhaCardDownloadOption(mode, resp.x_token);

            handleIdentityPhoto(mode, resp.photo, $('#' + p + 'abha_card_result_photo'));
        }, function (err) {
            $('#' + p + 'abha_card_error').text(err).show();
        }, $('#' + p + 'abha_card_verify_btn'));
    }

    // ---------------------------------------------------------------
    // Has Aadhaar Only
    // ---------------------------------------------------------------
    function sendAadhaarOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        // Creating an ABHA via Aadhaar needs NHA's consent terms accepted first - block before the OTP is sent so
        // nothing is created without consent on record.
        if (!abhaTermsAccepted[mode]) {
            identityWarn('Please accept the Terms & Conditions before creating an ABHA using Aadhaar.');
            return;
        }
        var val = $('#' + p + 'aadhaar_input').val().trim();
        if (!/^\d{12}$/.test(val)) { identityWarn('Please enter a valid 12-digit Aadhaar number'); return; }
        $('#' + p + 'aadhaar_error').hide();
        identityAjax('aadhaar_send_otp', { aadhaar_number: val }, function (resp) {
            $('#' + p + 'aadhaar_otp_group').show();
            $('#' + p + 'aadhaar_otp_input').val('').focus();
            $('#' + p + 'aadhaar_otp_sent_message').text(resp.message || '').toggle(!!resp.message);
            startResendCooldown($('#' + p + 'aadhaar_send_otp_btn'), 30);
        }, function (err) {
            $('#' + p + 'aadhaar_error').text(err).show();
        }, $('#' + p + 'aadhaar_send_otp_btn'));
    }

    function verifyAadhaarOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        var otp = $('#' + p + 'aadhaar_otp_input').val().trim();
        var mobile = $('#' + p + 'aadhaar_mobile_input').val().trim();
        if (!otp) { identityWarn('Please enter the OTP'); return; }
        if (!/^\d{10}$/.test(mobile)) { identityWarn('ABDM requires a valid 10-digit mobile number to create the ABHA - please enter one (the Aadhaar-linked mobile if you have it).'); return; }
        $('#' + p + 'aadhaar_error').hide();
        identityAjax('aadhaar_verify_otp', { otp: otp, mobile: mobile }, function (resp) {
            $('#' + p + 'aadhaar_result_abha_number').val(resp.abha_number || '');
            $('#' + p + 'aadhaar_result_abha_address').val(resp.abha_address || '');
            $('#' + p + 'new_patient_abha_number').val(resp.abha_number || '');
            $('#' + p + 'new_patient_abha_address').val(resp.abha_address || '');
            if (resp.date_of_birth) { $('#' + p + 'new_patient_date_of_birth').val(resp.date_of_birth); }
            if (!resp.abha_address && resp.needs_abha_address) {
                showAbhaAddressPicker(mode, resp.suggestions || []);
            } else {
                $('#' + p + 'aadhaar_address_picker').hide();
            }
            // Only the masked Aadhaar is ever kept - the raw number is cleared from the page right away.
            var digits = $('#' + p + 'aadhaar_input').val().replace(/\D/g, '');
            if (digits.length === 12) {
                $('#' + p + 'new_patient_aadhaar_number').val('XXXX XXXX ' + digits.slice(-4));
            }
            $('#' + p + 'aadhaar_input').val('');
            aadhaarVerifiedProfile[mode] = {
                name: resp.name || '',
                gender: resp.gender || '',
                year_of_birth: resp.year_of_birth || '',
                date_of_birth: resp.date_of_birth || '',
                address: resp.address || '',
                mobile: resp.mobile || ''
            };
            $('#' + p + 'aadhaar_result').show();
            $('#' + p + 'aadhaar_otp_group').hide();
            $('#' + p + 'aadhaar_send_otp_btn').prop('disabled', true);

            $('#' + p + 'aadhaar_result .aadhaar-is-new-banner').remove();
            if (resp.is_new === false) {
                $('#' + p + 'aadhaar_result').prepend('<div class="aadhaar-is-new-banner text-warning mb-5"><i class="fa fa-info-circle"></i> This Aadhaar already had an existing ABHA - its profile was fetched, no new account was created.</div>');
            } else if (resp.is_new === true) {
                $('#' + p + 'aadhaar_result').prepend('<div class="aadhaar-is-new-banner text-success mb-5"><i class="fa fa-check-circle"></i> A new ABHA was created for this Aadhaar.</div>');
            }

            autofillProfileFieldsToForm(mode, aadhaarVerifiedProfile[mode]);

            verifiedIdentitySource[mode] = 'aadhaar';
            clearOtherIdentityResults(mode, 'aadhaar');

            checkIdentityMatch(mode, aadhaarVerifiedProfile[mode]);

            showAbhaCardDownloadOption(mode, resp.x_token);

            handleIdentityPhoto(mode, resp.photo, $('#' + p + 'aadhaar_result_photo'));
        }, function (err) {
            $('#' + p + 'aadhaar_error').text(err).show();
        }, $('#' + p + 'aadhaar_verify_btn'));
    }

    function showAbhaAddressPicker(mode, suggestions) {
        var p = identityIdPrefix(mode);
        var $select = $('#' + p + 'aadhaar_address_suggestions').empty();
        (suggestions || []).forEach(function (s) {
            $select.append($('<option>').val(s).text(s));
        });
        $('#' + p + 'aadhaar_address_custom').val('');
        $('#' + p + 'aadhaar_address_error').hide();
        $('#' + p + 'aadhaar_address_picker').show();
    }

    function createAbhaAddress(mode) {
        var p = identityIdPrefix(mode);
        var custom = $('#' + p + 'aadhaar_address_custom').val().trim();
        var chosen = custom || $('#' + p + 'aadhaar_address_suggestions').val();
        if (!chosen) { identityWarn('Please choose a suggested ABHA Address or type your own'); return; }
        $('#' + p + 'aadhaar_address_error').hide();
        identityAjax('aadhaar_create_abha_address', { abha_address: chosen }, function (resp) {
            var addr = resp.abha_address || chosen;
            $('#' + p + 'aadhaar_result_abha_address').val(addr);
            $('#' + p + 'new_patient_abha_address').val(addr);
            $('#' + p + 'aadhaar_address_picker').hide();
        }, function (err) {
            $('#' + p + 'aadhaar_address_error').text(err).show();
        }, $('#' + p + 'aadhaar_address_create_btn'));
    }

    // ---------------------------------------------------------------
    // Find by Mobile
    // ---------------------------------------------------------------
    function sendMobileLoginOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        var val = $('#' + p + 'mobile_login_input').val().trim();
        if (!/^\d{10}$/.test(val)) { identityWarn('Please enter a valid 10-digit mobile number'); return; }
        $('#' + p + 'mobile_login_error').hide();
        $('#' + p + 'mobile_login_accounts_list').hide().empty();
        $('#' + p + 'mobile_login_result').hide();
        identityAjax('mobile_login_send_otp', { mobile_number: val }, function () {
            $('#' + p + 'mobile_login_otp_group').show();
            $('#' + p + 'mobile_login_otp_input').val('').focus();
            startResendCooldown($('#' + p + 'mobile_login_send_otp_btn'), 30);
        }, function (err) {
            $('#' + p + 'mobile_login_error').text(err).show();
        }, $('#' + p + 'mobile_login_send_otp_btn'));
    }

    function verifyMobileLoginOtp(mode) {
        mode = mode || 'add';
        var p = identityIdPrefix(mode);
        var otp = $('#' + p + 'mobile_login_otp_input').val().trim();
        if (!otp) { identityWarn('Please enter the OTP'); return; }
        $('#' + p + 'mobile_login_error').hide();
        identityAjax('mobile_login_verify_otp', { otp: otp }, function (resp) {
            mobileLoginAccounts[mode] = resp.accounts || [];
            renderMobileLoginAccounts(mode);
            $('#' + p + 'mobile_login_otp_group').hide();
            $('#' + p + 'mobile_login_send_otp_btn').prop('disabled', true);

            if (mode !== 'opd') {
                var $mobileField = identityForm(mode).find('[name="mobile_number"]');
                if (!$mobileField.val()) {
                    $mobileField.val($('#' + p + 'mobile_login_input').val().trim());
                }
            }
        }, function (err) {
            $('#' + p + 'mobile_login_error').text(err).show();
        }, $('#' + p + 'mobile_login_verify_btn'));
    }

    function renderMobileLoginAccounts(mode) {
        var p = identityIdPrefix(mode);
        var accounts = mobileLoginAccounts[mode] || [];
        var $list = $('#' + p + 'mobile_login_accounts_list');
        $list.empty();

        if (!accounts.length) {
            $list.html('<div class="text-muted">No ABHA accounts found linked to this mobile number.</div>').show();
            return;
        }

        accounts.forEach(function (account, index) {
            var isActive = (String(account.status || '').toUpperCase() === 'ACTIVE');
            var $card = $('<div class="identity-result mb-10"></div>');
            var html = '<div><b>' + escapeHtml(account.name || 'Unknown') + '</b> '
                + '<span class="badge ' + (isActive ? 'bg-success' : 'bg-secondary') + '">' + escapeHtml(account.status || 'UNKNOWN') + '</span></div>'
                + '<div>ABHA Number: ' + escapeHtml(account.abha_number || '-') + '</div>'
                + '<div>ABHA Address: ' + escapeHtml(account.abha_address || '-') + '</div>'
                + '<div>Gender: ' + escapeHtml(account.gender || '-') + ', Year of Birth: ' + escapeHtml(account.year_of_birth || '-') + '</div>'
                + '<div>KYC Verified: ' + (account.kyc_verified ? 'Yes' : 'No') + ', Mobile Verified: ' + (account.mobile_verified ? 'Yes' : 'No') + '</div>';
            if (isActive) {
                html += '<button type="button" class="btn btn-primary btn-sm mt-10" onclick="selectMobileLoginAccount(\'' + mode + '\', ' + index + ')">Select This ABHA</button>';
            } else {
                html += '<div class="text-danger mt-10">This account is deactivated and cannot be selected.</div>';
            }
            $card.html(html);
            $list.append($card);
        });
        $list.show();
    }

    function selectMobileLoginAccount(mode, index) {
        var p = identityIdPrefix(mode);
        var account = (mobileLoginAccounts[mode] || [])[index];
        if (!account) { return; }

        $('#' + p + 'new_patient_abha_number').val(account.abha_number || '');
        $('#' + p + 'new_patient_abha_address').val(account.abha_address || '');
        // Only set the DOB when ABDM returned a full date - never blank out a known value because this account only carried a year.
        if (account.date_of_birth) { $('#' + p + 'new_patient_date_of_birth').val(account.date_of_birth); }
        mobileLoginSelectedProfile[mode] = account;

        $('#' + p + 'mobile_login_result_number').text(account.abha_number || '-');
        $('#' + p + 'mobile_login_result_address').text(account.abha_address || '-');
        $('#' + p + 'mobile_login_result').show();
        $('#' + p + 'mobile_login_accounts_list').hide();

        autofillProfileFieldsToForm(mode, account);

        verifiedIdentitySource[mode] = 'mobile';
        clearOtherIdentityResults(mode, 'mobile');

        // The account itself carries no "mobile" - it's whatever the staff member searched by.
        checkIdentityMatch(mode, $.extend({}, account, { mobile: $('#' + p + 'mobile_login_input').val() }));

        showAbhaCardDownloadOption(mode, account.x_token);

        handleIdentityPhoto(mode, account.profile_photo, $('#' + p + 'mobile_login_result_photo'));
    }

    // ---------------------------------------------------------------
    // Auto-fill from the verified profile
    // ---------------------------------------------------------------

    // Name and gender are always synced to the freshly verified value: ABDM's "generate link token" call validates
    // name/gender/year of birth against its own record. 'opd' mode has no name field - the value is stashed in a
    // hidden field and sent with saveOPDPatientIdentity() so the existing patient's own record is synced too.
    function autofillPatientName(mode, name) {
        if (!name) { return; }
        if (mode === 'opd') {
            $('#opd_new_patient_name').val(name);
            return;
        }
        identityForm(mode).find('[name="patient_name"]').val(name);
    }

    // Age / address / mobile only apply to the Add/Edit forms and only fill fields that are still empty (ABDM never
    // validates them).
    function autofillProfileFieldsToForm(mode, profile) {
        if (!profile) { return; }
        autofillPatientName(mode, profile.name);
        if (profile.gender) {
            var g = profile.gender.toUpperCase();
            var genderVal = (g === 'M') ? 'Male' : (g === 'F') ? 'Female' : (g === 'O') ? 'Other' : profile.gender;
            if (mode === 'opd') {
                $('#opd_new_patient_gender').val(genderVal);
            } else {
                identityForm(mode).find('[name="gender"]').val(genderVal);
            }
        }
        if (mode === 'opd') { return; }
        var $form = identityForm(mode);
        if (profile.year_of_birth) {
            var $ageField = $form.find('[name="age"]');
            if (!$ageField.val()) {
                var yob = parseInt(profile.year_of_birth, 10);
                if (!isNaN(yob) && yob > 0) {
                    $ageField.val(new Date().getFullYear() - yob);
                }
            }
        }
        if (profile.address) {
            var $addressField = $form.find('[name="address"]');
            if (!$addressField.val()) { $addressField.val(profile.address); }
        }
        if (profile.mobile) {
            var $mobileField = $form.find('[name="mobile_number"]');
            if (!$mobileField.val()) { $mobileField.val(profile.mobile); }
        }
    }

    // ---------------------------------------------------------------
    // ABDM M1 "New vs Returning Patient" - advisory only, staff always has the final say.
    // 'add':        is the verified person already in our patients table (mobile + gender + age +/-2y + phonetic name)?
    // 'edit'/'opd': the patient is already known - check the identity against THAT patient: hard conflict if the ABHA /
    //               Aadhaar belongs to a DIFFERENT patient, soft mismatch if the demographics don't line up.
    // ---------------------------------------------------------------
    function checkIdentityMatch(mode, profile) {
        if (!profile) { return; }
        var p = identityIdPrefix(mode);
        var data = {
            abha_number: $('#' + p + 'new_patient_abha_number').val(),
            abha_address: $('#' + p + 'new_patient_abha_address').val(),
            aadhaar_number: $('#' + p + 'new_patient_aadhaar_number').val()
        };

        if (mode === 'opd') {
            if (!opdSelectedPatient) { return; }
            data.mobile = profile.mobile || opdSelectedPatient.mobile_number;
            data.name = profile.name || opdSelectedPatient.patient_name;
            data.gender = profile.gender || opdSelectedPatient.gender;
            data.date_of_birth = profile.date_of_birth || opdSelectedPatient.date_of_birth;
            data.patient_id = opdSelectedPatient.id;
        } else {
            var $form = identityForm(mode);
            data.mobile = profile.mobile || $form.find('[name="mobile_number"]').val();
            data.name = profile.name || $form.find('[name="patient_name"]').val();
            data.gender = profile.gender || $form.find('[name="gender"]').val();
            data.date_of_birth = profile.date_of_birth || $('#' + p + 'new_patient_date_of_birth').val();
            if (mode === 'edit') { data.patient_id = $form.find('[name="patient_id"]').val(); }
        }

        $.ajax({ url: IDENTITY_URLS.check_identity_match, type: 'POST', data: data, dataType: 'json' }).done(function (resp) {
            if (!resp || !resp.success) { return; }
            if (mode === 'add') {
                if (resp.match) { showReturningPatientMatch(resp.match); }
            } else if (resp.identity_conflict) {
                showIdentityConflict(mode, resp.identity_conflict);
            } else if (resp.matches === false) {
                showIdentityMismatch(mode);
            } else {
                hideIdentityBanner(mode);
            }
        });
    }

    // Hard conflict: this ABHA/Aadhaar is already linked to a DIFFERENT patient (these columns are UNIQUE).
    function showIdentityConflict(mode, conflict) {
        var p = identityIdPrefix(mode);
        $('#' + p + 'identity_mismatch_banner').removeClass('alert-warning').addClass('alert-danger').show();
        $('#' + p + 'identity_mismatch_text').html(
            'This ' + escapeHtml(conflict.label) + ' is already linked to a different patient record: <b>'
            + escapeHtml(conflict.patient_name) + '</b> (Patient ID #' + escapeHtml(String(conflict.patient_id)) + '). '
            + 'The same ' + escapeHtml(conflict.label) + ' cannot be linked to two different patients - please '
            + 'verify you have the right person before saving.'
        );
    }

    // Softer, advisory mismatch: guards against linking the wrong person's ABHA to this patient.
    function showIdentityMismatch(mode) {
        var p = identityIdPrefix(mode);
        $('#' + p + 'identity_mismatch_banner').removeClass('alert-danger').addClass('alert-warning').show();
        $('#' + p + 'identity_mismatch_text').text(
            "This verified identity's name, gender or age doesn't closely match this patient's existing record. Please double-check this is the right person before saving."
        );
    }

    function hideIdentityBanner(mode) {
        var p = identityIdPrefix(mode);
        $('#' + p + 'identity_mismatch_banner').hide().removeClass('alert-danger').addClass('alert-warning');
        $('#' + p + 'identity_mismatch_text').empty();
    }

    function showReturningPatientMatch(match) {
        lastIdentityMatch.add = match;
        var isCertain = match.match_reason === 'identity';
        var html;
        if (isCertain) {
            html = 'This ' + escapeHtml(match.matched_field) + ' is already linked to an existing patient: <b>' + escapeHtml(match.patient_name) + '</b> '
                + '(UHID: ' + escapeHtml(match.uhid) + ', Mobile: ' + escapeHtml(match.mobile_number) + ', Status: ' + escapeHtml(match.status) + '). '
                + 'This is the same person - use their existing record instead of creating a new one.';
        } else {
            html = 'This looks like an existing patient already in the system: <b>' + escapeHtml(match.patient_name) + '</b> '
                + '(UHID: ' + escapeHtml(match.uhid) + ', Mobile: ' + escapeHtml(match.mobile_number) + ', Status: ' + escapeHtml(match.status) + '). '
                + 'To avoid creating a duplicate record, consider using their existing record instead.';
        }
        $('#identity_match_text').html(html);
        $('#identity_match_banner').toggleClass('alert-danger', isCertain).toggleClass('alert-warning', !isCertain).show();
        // A certain match IS the same person by definition - "different person" only makes sense for the fuzzy match.
        $('#identity_match_different_person_btn').toggle(!isCertain);
    }

    function dismissIdentityMatchBanner() {
        $('#identity_match_banner').hide().removeClass('alert-danger').addClass('alert-warning');
        $('#identity_match_text').empty();
        $('#identity_match_different_person_btn').show();
        lastIdentityMatch.add = null;
    }

    // ---------------------------------------------------------------
    // Edit Patient: "Linked" summary vs the verify tabs
    // ---------------------------------------------------------------
    function showEditIdentityForm() {
        $('#edit_identity_linked_summary').hide();
        $('#edit_identity_form').show();
    }

    // ---------------------------------------------------------------
    // ABDM KYC photo + "Download ABHA Card"
    // ---------------------------------------------------------------
    function hasExistingIdentityPhoto(mode) {
        if (mode === 'opd') { return !!(opdSelectedPatient && opdSelectedPatient.photo); }
        var p = identityIdPrefix(mode);
        if ($('#' + p + 'new_patient_photo').val()) { return true; }                // an ABDM photo is already saved in this form
        if (mode === 'edit' && editingPatientHasPhoto) { return true; }               // a photo is already on file for this patient
        var file = identityForm(mode).find('[name="photo"][type="file"]')[0];
        if (file && file.files && file.files.length) { return true; }                 // staff picked a photo themselves
        var slimPhoto = mode === 'edit' ? $('#edit_photo').val() : $('#photo').val();
        return !!slimPhoto;                                                           // or uploaded via slim cropper
    }

    // Persists a KYC photo returned by ABDM - shared by all three verify flows. Its filename travels in the hidden
    // photo_filename field. A photo that already exists (on file, or chosen by staff) is NEVER overwritten.
    function handleIdentityPhoto(mode, photoBase64, $preview) {
        if (!photoBase64) { return; }
        $preview.attr('src', 'data:image/jpeg;base64,' + photoBase64).show();

        if (hasExistingIdentityPhoto(mode)) { return; }

        var p = identityIdPrefix(mode);
        identityAjax('save_profile_photo', { photo_base64: photoBase64 }, function (photoResp) {
            var filename = photoResp.file || '';
            $('#' + p + 'new_patient_photo').val(filename);
            var $photo = mode === 'edit' ? $('#edit_photo') : $('#photo');
            if ($photo.length && !$photo.val()) {
                $photo.val(filename);
            }
        }, function (err) {
            console.warn('Could not save ABHA profile photo: ' + err);
        });
    }

    // The X-token of the verify that just succeeded is held only in memory (verifiedXToken) - never in
    // localStorage/cookies. It is short-lived on ABDM's side; once expired, staff simply re-verifies the ABHA.
    function showAbhaCardDownloadOption(mode, xToken) {
        if (!xToken) { return; }
        var p = identityIdPrefix(mode);
        verifiedXToken[mode] = xToken;
        $('#' + p + 'abha_card_download_error').hide();
        $('#' + p + 'abha_card_download_preview').hide();
        $('#' + p + 'abha_card_download_box').show();
    }

    function hideAbhaCardDownloadOption(mode) {
        var p = identityIdPrefix(mode);
        verifiedXToken[mode] = null;
        $('#' + p + 'abha_card_download_box').hide();
        $('#' + p + 'abha_card_download_error').hide().empty();
        $('#' + p + 'abha_card_download_preview').hide();
        $('#' + p + 'abha_card_download_image').attr('src', '');
    }

    function downloadAbhaCard(mode) {
        var p = identityIdPrefix(mode);
        var xToken = verifiedXToken[mode];
        if (!xToken) { return; }

        var $btn = $('#' + p + 'abha_card_download_btn');
        var $error = $('#' + p + 'abha_card_download_error').hide();
        $btn.prop('disabled', true);

        $.ajax({
            url: IDENTITY_URLS.download_abha_card,
            type: 'POST',
            data: { x_token: xToken },
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.success) {
                $error.text((resp && resp.error) ? resp.error : 'Could not download the ABHA card. Please try again.').show();
                return;
            }
            var contentType = (resp.content_type && /^image\//i.test(resp.content_type)) ? resp.content_type.split(';')[0] : 'image/png';
            var dataUrl = 'data:' + contentType + ';base64,' + resp.data;
            var abhaNumber = $('#' + p + 'new_patient_abha_number').val() || $('#' + p + 'abha_card_result_number').text() || 'card';
            $('#' + p + 'abha_card_download_image').attr('src', dataUrl);
            $('#' + p + 'abha_card_download_link').attr('href', dataUrl).attr('download', 'ABHA_Card_' + abhaNumber.replace(/[^0-9A-Za-z]/g, '') + '.png');
            $('#' + p + 'abha_card_download_preview').show();
        }).fail(function (xhr) {
            $error.text(identityErrorMessage(xhr)).show();
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    // ---------------------------------------------------------------
    // Reset a mode's transient OTP/verify state - when its modal closes or (opd) the patient changes, so state from
    // one patient never carries over to the next. $keepValues leaves the hidden abha/aadhaar/dob values alone: the
    // Edit modal re-populates them from the patient's current DB values every time it opens.
    // ---------------------------------------------------------------
    function resetIdentitySection(mode, keepValues) {
        var p = identityIdPrefix(mode), t = (mode === 'opd' ? 'opd-' : (mode === 'edit' ? 'edit-' : ''));
        $('#' + p + 'abha_card_input, #' + p + 'abha_card_otp_input, #' + p + 'aadhaar_input, #' + p + 'aadhaar_otp_input, #' + p + 'aadhaar_mobile_input, #' + p + 'mobile_login_input, #' + p + 'mobile_login_otp_input').val('');
        $('#' + p + 'abha_card_otp_group, #' + p + 'abha_card_result, #' + p + 'aadhaar_otp_group, #' + p + 'aadhaar_result, #' + p + 'abha_card_error, #' + p + 'aadhaar_error, #' + p + 'mobile_login_otp_group, #' + p + 'mobile_login_error, #' + p + 'mobile_login_result, #' + p + 'aadhaar_address_picker, #' + p + 'aadhaar_address_error, #' + p + 'aadhaar_otp_sent_message, #' + p + 'abha_card_otp_sent_message').hide();
        $('#' + p + 'aadhaar_otp_sent_message, #' + p + 'abha_card_otp_sent_message').empty();
        $('#' + p + 'aadhaar_address_custom').val('');
        $('#' + p + 'aadhaar_address_suggestions').empty();
        $('#' + p + 'mobile_login_accounts_list').hide().empty();
        $('#' + p + 'mobile_login_result_photo, #' + p + 'aadhaar_result_photo, #' + p + 'abha_card_result_photo').attr('src', '').hide();
        cancelResendCooldown($('#' + p + 'abha_card_send_otp_btn'));
        cancelResendCooldown($('#' + p + 'aadhaar_send_otp_btn'));
        cancelResendCooldown($('#' + p + 'mobile_login_send_otp_btn'));
        if (!keepValues) {
            // new_patient_photo may hold an ABDM KYC photo filename - clear it so it never leaks into the next patient.
            $('#' + p + 'new_patient_abha_number, #' + p + 'new_patient_abha_address, #' + p + 'new_patient_aadhaar_number, #' + p + 'new_patient_date_of_birth, #' + p + 'new_patient_photo, #' + p + 'new_patient_name, #' + p + 'new_patient_gender').val('');
        } else {
            $('#' + p + 'new_patient_photo').val('');
        }
        $('#' + p + 'aadhaar_result .aadhaar-is-new-banner').remove();
        aadhaarVerifiedProfile[mode] = null;
        mobileLoginAccounts[mode] = null;
        mobileLoginSelectedProfile[mode] = null;
        verifiedIdentitySource[mode] = null;
        hideAbhaCardDownloadOption(mode);
        if (mode === 'add') { dismissIdentityMatchBanner(); } else { hideIdentityBanner(mode); }
        if ($('#' + t + 'identity-mobile-tab').length) { $('#' + t + 'identity-mobile-tab').tab('show'); }
        abhaTermsAccepted[mode] = false;
        $('#' + p + 'aadhaar_terms_link').html('<i class="fa fa-file-text-o"></i> Accept Terms &amp; Conditions').removeClass('text-success').addClass('text-primary');
    }

    $(function () {
        var modals = IDENTITY_CFG.modals || {};
        if (modals.add) { $(modals.add).on('hidden.bs.modal', function () { resetIdentitySection('add'); }); }
        if (modals.edit) { $(modals.edit).on('hidden.bs.modal', function () { resetIdentitySection('edit', true); }); }
    });
</script>
