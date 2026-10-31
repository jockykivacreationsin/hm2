{{--
  OPD-register-only part of the Patient Identity behaviour (the shared core is partials/abdm/identity-scripts.blade.php):
  the Create OPD modal's box that verifies/links an ABHA on the EXISTING patient picked in the patient dropdown and
  saves it on its own (patients.identity.update), plus "Use This Existing Patient Instead" for the Add Patient modal.
  Must be included AFTER the shared core.
--}}
<script>
    var PATIENT_IDENTITY_URL = @json(route('patients.identity.update', ['id' => '__ID__']));

    function showOPDIdentityForm() {
        $('#opd_identity_linked_summary, #opd_identity_not_linked').hide();
        $('#opd_identity_form').show();
    }

    function cancelOPDIdentityForm() {
        resetOPDIdentitySection();
        renderOPDPatientIdentitySummary(opdSelectedPatient);
    }

    // The "Linked" summary or "Not linked yet" prompt for the patient currently selected in the Create OPD modal.
    function renderOPDPatientIdentitySummary(patient) {
        if (!patient) { $('#opd_identity_box').hide(); return; }
        $('#opd_identity_box').show();
        $('#opd_identity_form').hide();
        if (patient.abha_number || patient.abha_address) {
            $('#opd_identity_linked_abha_number').text(patient.abha_number || '-');
            $('#opd_identity_linked_abha_address').text(patient.abha_address || '-');
            $('#opd_identity_linked_summary').show();
            $('#opd_identity_not_linked').hide();
        } else {
            $('#opd_identity_linked_summary').hide();
            $('#opd_identity_not_linked').show();
        }
    }

    // Clears all transient OTP/verify state of the 'opd' identity box - on patient change, after a save and when the
    // Create OPD modal closes, so state from one patient never carries over to another.
    function resetOPDIdentitySection() {
        resetIdentitySection('opd');
        $('#opd_identity_form').hide();
    }

    // Persists the ABHA/Aadhaar verified above onto the selected existing patient - an identity-only save, separate
    // from the Create OPD form itself (which only creates the visit).
    function saveOPDPatientIdentity() {
        if (!opdSelectedPatient) { return; }

        var abhaNumber = $('#opd_new_patient_abha_number').val();
        var abhaAddress = $('#opd_new_patient_abha_address').val();
        var aadhaarNumber = $('#opd_new_patient_aadhaar_number').val();
        var dateOfBirth = $('#opd_new_patient_date_of_birth').val();
        // ABDM's verified Name/Gender are synced onto the patient's own record too (see autofillPatientName()).
        var verifiedName = $('#opd_new_patient_name').val();
        var verifiedGender = $('#opd_new_patient_gender').val();
        // Only populated when this patient had NO photo at verify time (handleIdentityPhoto()) - an existing photo is never overwritten.
        var photo = $('#opd_new_patient_photo').val();

        if (!abhaNumber && !abhaAddress && !aadhaarNumber) {
            Swal.fire({ icon: 'warning', title: 'Nothing to Save', text: 'Please verify an ABHA/Aadhaar identity above first.', showConfirmButton: true });
            return;
        }

        $.ajax({
            url: PATIENT_IDENTITY_URL.replace('__ID__', encodeURIComponent(opdSelectedPatient.id)),
            type: 'POST',
            data: {
                abha_number: abhaNumber,
                abha_address: abhaAddress,
                aadhaar_number: aadhaarNumber,
                date_of_birth: dateOfBirth,
                patient_name: verifiedName,
                gender: verifiedGender,
                photo: photo
            },
            dataType: 'json'
        }).done(function (resp) {
            if (!resp || !resp.success) {
                Swal.fire({ icon: 'error', title: 'Could Not Save Identity', text: (resp && resp.error) ? resp.error : 'Please try again.', showConfirmButton: true });
                return;
            }
            opdSelectedPatient.abha_number = resp.abha_number;
            opdSelectedPatient.abha_address = resp.abha_address;
            opdSelectedPatient.aadhaar_number = resp.aadhaar_number;
            if (resp.date_of_birth) { opdSelectedPatient.date_of_birth = resp.date_of_birth; }
            if (resp.patient_name) { opdSelectedPatient.patient_name = resp.patient_name; }
            if (resp.gender) { opdSelectedPatient.gender = resp.gender; }
            if (resp.photo) { opdSelectedPatient.photo = resp.photo; }
            resetOPDIdentitySection();
            renderOPDPatientIdentitySummary(opdSelectedPatient);
            Swal.fire({ icon: 'success', title: 'Identity Saved', text: 'The verified ABHA has been linked to this patient. Their Name/Gender were also synced to match the ABHA record.', showConfirmButton: false, timer: 2000 });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Could Not Save Identity', text: identityErrorMessage(xhr), showConfirmButton: true });
        });
    }

    // "Use This Existing Patient Instead" - this page has no Edit Patient modal, so close Add Patient and select the
    // matched patient in the Create OPD form's patient dropdown.
    function useExistingPatientMatch() {
        var match = lastIdentityMatch.add;
        if (!match) { return; }

        $('#addOPDPatientModal').modal('hide');

        var label = match.patient_name + ' ' + (match.mobile_number || '') + ' ' + match.id;
        $('#opd_patient_id').find('option[value="' + match.id + '"]').remove();
        $('#opd_patient_id').append(new Option(label, match.id, true, true)).trigger('change');

        Swal.fire({
            icon: 'info',
            title: 'Existing Patient Selected',
            text: 'This existing patient (' + match.patient_name + ') has been selected for the OPD visit instead of creating a new record.',
            showConfirmButton: true
        });
    }

    $('#createOPDModal').on('hidden.bs.modal', function () {
        resetOPDIdentitySection();
        $('#opd_identity_box').hide();
        $('#opd_identity_linked_summary, #opd_identity_not_linked').hide();
        opdSelectedPatient = null;
    });
</script>
