{{--
    Add Staff + Update Staff modals and their JS (staff members / salary settings pages).
    Included by staff/index and staff/salary-settings.
    $withSalary: show the "Salary" field in the Add form (salary-settings page only).
--}}
@php($withSalary = $withSalary ?? false)
<div class="modal fade" id="addStaffMemberModal">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Staff</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_staff_member_form" action="{{ route('staff.store') }}" method="POST">
				@unless($withSalary)
					<input type="hidden" name="current_salary" value="0">
				@endunless
				<div class="modal-body">
					<div class="row">
						<div class="col-lg-9">
							<div class="row">
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Joining Date <span class="req-star">*</span></label>
										<input type="text" name="joining_date" class="form-control datepicker" value="{{ date('d-m-Y') }}">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Name <span class="req-star">*</span></label>
										<input type="text" name="staff_name" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Mobile Number <span class="req-star">*</span></label>
										<input type="number" name="mobile_number" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Department <span class="req-star">*</span></label>
										<select class="form-control" id="a_department_id" name="department_id" required>
											<option></option>
										</select>
									</div>
								</div>

								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Designation <span class="req-star">*</span></label>
										<select class="form-control" id="a_designation_id" name="designation_id" required>
											<option></option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Specialities</label>
										<select class="form-control" id="a_specialities" name="specialities[]" multiple>
											<option></option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Gender <span class="req-star">*</span></label>
										<select class="form-control" name="gender" required>
											<option></option>
											<option value="Male">Male</option>
											<option value="Female">Female</option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>DOB <span class="req-star">*</span></label>
										<input type="text" name="dob" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Email</label>
										<input type="email" name="email" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-6">
									<div class="form-group local-forms">
										<label>Residential Address <span class="req-star">*</span></label>
										<input type="text" name="residential_address" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-6">
									<div class="form-group local-forms">
										<label>Permanent Address </label>
										<input type="text" name="permanent_address" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Qualification </label>
										<input type="text" name="qualification" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Experience (Yrs) <span class="req-star">*</span></label>
										<input type="number" name="experience_years" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Aadhaar Number </label>
										<input type="number" name="aadhaar_number" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Role <span class="req-star">*</span></label>
										<select class="form-control" id="a_user_role_id" name="user_role_id" required>
											<option></option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Password <span class="req-star">*</span></label>
										<input type="password" name="password" class="form-control" autocomplete="new-password" required>
									</div>
								</div>
								<div class="col-12"><hr class="my-1"><small class="text-muted">ABDM Practitioner Identity (optional)</small></div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Registration Number</label>
										<input type="text" name="medical_registration_number" class="form-control" maxlength="50">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Registration Council</label>
										<input type="text" name="registration_council" class="form-control" maxlength="150">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>HPR ID</label>
										<input type="text" name="hpr_id" class="form-control" maxlength="50">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>HPR Address</label>
										<input type="text" name="hpr_address" class="form-control" maxlength="100" placeholder="name@hpr.abdm">
									</div>
								</div>
								@if($withSalary)
									<div class="col-12 col-sm-4">
										<div class="form-group local-forms">
											<label>Salary <span class="req-star">*</span></label>
											<input type="number" name="current_salary" class="form-control" min="0" required>
										</div>
									</div>
								@endif
							</div>
						</div>
						<div class="col-lg-3">
							<div class="slim_image_box">
								<input class="form-control" type="hidden" id="photo" name="photo" value="">
								<div class="slim"
									data-label="Upload Image"
									data-ratio="1:1"
									data-size="300,300"
									data-did-upload="pUpload"
									data-service="{{ route('uploads.photo.store', 'staff') }}?_token={{ csrf_token() }}">
									<input type="file" name="slim[]" capture />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="editStaffMemberModal">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Update Staff</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="edit_staff_member_form" action="" method="POST">
				@method('PUT')
				<input type="hidden" id="staff_member_id" name="staff_member_id" value="0">
				<div class="modal-body">
					<div class="row">
						<div class="col-lg-9">
							<div class="row">
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Joining Date <span class="req-star">*</span></label>
										<input type="text" id="joining_date" name="joining_date" class="form-control datepicker">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Name <span class="req-star">*</span></label>
										<input type="text" id="staff_name" name="staff_name" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Mobile Number <span class="req-star">*</span></label>
										<input type="number" id="mobile_number" name="mobile_number" class="form-control" required>
									</div>
								</div>

								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Department <span class="req-star">*</span></label>
										<select class="form-control" id="e_department_id" name="department_id" required>
											<option></option>
										</select>
									</div>
								</div>

								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Designation <span class="req-star">*</span></label>
										<select class="form-control" id="e_designation_id" name="designation_id" required>
											<option></option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Specialities </label>
										<select class="form-control" id="e_specialities" name="specialities[]" multiple>
											<option></option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Gender <span class="req-star">*</span></label>
										<select class="form-control" id="gender" name="gender" required>
											<option></option>
											<option value="Male">Male</option>
											<option value="Female">Female</option>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>DOB <span class="req-star">*</span></label>
										<input type="text" id="dob" name="dob" class="form-control datepicker" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Email</label>
										<input type="email" id="email" name="email" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-6">
									<div class="form-group local-forms">
										<label>Residential Address <span class="req-star">*</span></label>
										<input type="text" id="residential_address" name="residential_address" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-6">
									<div class="form-group local-forms">
										<label>Permanent Address </label>
										<input type="text" id="permanent_address" name="permanent_address" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Qualification</label>
										<input type="text" id="qualification" name="qualification" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Experience (Yrs) <span class="req-star">*</span></label>
										<input type="number" id="experience_years" name="experience_years" class="form-control" required>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Aadhaar Number</label>
										<input type="number" id="aadhaar_number" name="aadhaar_number" class="form-control">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Role <span class="req-star">*</span></label>
										<select class="form-control" id="e_user_role_id" name="user_role_id" required>
										</select>
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										{{-- Legacy pre-filled the stored hash here and re-hashed it on every save; blank now keeps the password. --}}
										<label>Password</label>
										<input type="password" id="password" name="password" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
									</div>
								</div>
								<div class="col-12"><hr class="my-1"><small class="text-muted">ABDM Practitioner Identity (optional)</small></div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Registration Number</label>
										<input type="text" id="medical_registration_number" name="medical_registration_number" class="form-control" maxlength="50">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>Registration Council</label>
										<input type="text" id="registration_council" name="registration_council" class="form-control" maxlength="150">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>HPR ID</label>
										<input type="text" id="hpr_id" name="hpr_id" class="form-control" maxlength="50">
									</div>
								</div>
								<div class="col-12 col-sm-4">
									<div class="form-group local-forms">
										<label>HPR Address</label>
										<input type="text" id="hpr_address" name="hpr_address" class="form-control" maxlength="100" placeholder="name@hpr.abdm">
									</div>
								</div>
							</div>
						</div>
						<div class="col-lg-3">
							<div id="existing_image_box">
								<div class="img-wrapper">
									<img id="staff_image" class='fullwidth' src='{{ asset('assets/images/no_image.png') }}' />
									<div class="img-btns">
										<button type="button" class="edit-image-btn" onclick="showSlimCropper()">Change</button>
									</div>
								</div>
							</div>
							<div id="edit_image_box">
								<input class="form-control" type="hidden" id="edit_photo" name="photo" value="">
								<div class="slim"
									data-label="Upload Image"
									data-ratio="1:1"
									data-did-upload="epUpload"
									data-service="{{ route('uploads.photo.store', 'staff') }}?_token={{ csrf_token() }}">
									<input type="file" name="slim[]" capture />
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

@push('scripts')
<script>
	function pUpload(error, data, response) {
		$("#photo").val(response.file);
	}
	function epUpload(error, data, response) {
		$("#edit_photo").val(response.file);
	}

	var staffDropdownUrl = "{{ route('staff.dropdown', ['type' => '__TYPE__']) }}";
	var staffShowUrl = "{{ route('staff.show', ['id' => '__ID__']) }}";
	var staffUpdateUrl = "{{ route('staff.update', ['id' => '__ID__']) }}";
	var staffDestroyUrl = "{{ route('staff.destroy', ['id' => '__ID__']) }}";

	function initStaffSelect2(selector, type, placeholder, modalSelector) {
		var $el = $(selector);
		if ($el.hasClass('select2-hidden-accessible')) {
			$el.select2('destroy');
		}
		$el.select2({
			placeholder: placeholder,
			dropdownParent: $(modalSelector),
			ajax: {
				url: staffDropdownUrl.replace('__TYPE__', type),
				dataType: 'json',
				type: 'GET',
				delay: 250,
				data: function (params) {
					return { q: params.term };
				},
				processResults: function (data) { return { results: data }; },
				cache: true
			}
		});
	}

	// Replaces the legacy global helper of the same name (loads the speciality options of the edit modal).
	function showDropDownSpecialities() {
		$.getJSON(staffDropdownUrl.replace('__TYPE__', 'specialities'), function (data) {
			$("#e_specialities").select2({
				dropdownParent: $('#editStaffMemberModal'),
				data: data
			});
		});
	}

	function staffFormErrors(xhr) {
		var msg = (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors)
			? Object.values(xhr.responseJSON.errors).flat().join('\n')
			: (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not save the staff member.');
		Swal.fire({ icon: 'error', title: 'Error', text: msg });
	}

	function submitStaffMemberForm(form, modalSelector) {
		$.ajax({
			url: form.action,
			type: 'POST',
			data: new FormData(form),
			contentType: false,
			cache: false,
			processData: false,
			dataType: 'json',
			success: function () {
				form.reset();
				$(modalSelector).modal('hide');
				Swal.fire({ icon: 'success', title: 'Saved!', text: 'Saved Successfully.', showConfirmButton: false, timer: 1500 });
				location.reload();
			},
			error: staffFormErrors
		});
	}

	// Replaces the legacy global helper of the same name (fills the edit modal).
	function editStaffMemberModal(id) {
		$("#editStaffMemberModal").modal('show');

		$.ajax({
			type: 'GET',
			url: staffShowUrl.replace('__ID__', id),
			dataType: 'json',
			success: function (data) {
				var $f = $("#edit_staff_member_form");
				$f.attr('action', staffUpdateUrl.replace('__ID__', id));
				$f.find("#staff_member_id").val(id);
				$f.find("#joining_date").val(data.joining_date);
				$f.find("#staff_name").val(data.staff_name);
				$f.find("#mobile_number").val(data.mobile_number);

				$f.find("#e_department_id").html('<option value="' + data.department_id + '" selected>' + data.department + '</option>');
				$f.find("#e_designation_id").html('<option value="' + data.designation_id + '" selected>' + data.designation + '</option>');

				$f.find("#e_specialities").val((data.specialities || '').split(',')).trigger('change');

				$f.find("#gender").val(data.gender);
				$f.find("#dob").val(data.dob);
				$f.find("#email").val(data.email);
				$f.find("#residential_address").val(data.residential_address);
				$f.find("#permanent_address").val(data.permanent_address);
				$f.find("#qualification").val(data.qualification);
				$f.find("#experience_years").val(data.experience_years);
				$f.find("#aadhaar_number").val(data.aadhar_number || data.aadhaar_number);
				$f.find("#password").val('');
				$f.find("#e_user_role_id").html('<option value="' + data.user_role_id + '" selected>' + data.role + '</option>');
				$f.find("#medical_registration_number").val(data.medical_registration_number || '');
				$f.find("#registration_council").val(data.registration_council || '');
				$f.find("#hpr_id").val(data.hpr_id || '');
				$f.find("#hpr_address").val(data.hpr_address || '');

				if (data.photo) {
					$f.find("#edit_photo").val(data.photo);
					$("#staff_image").attr('src', "{{ \App\Support\Uploads::baseUrl('staffmembers') }}" + encodeURIComponent(data.photo));
				}
			}
		});
	}

	// Replaces the legacy global helper of the same name (soft-deletes a staff member).
	function deleteStaffMember(id) {
		Swal.fire({
			title: "Delete?",
			text: "Are you sure you want to delete!",
			icon: "warning",
			showCancelButton: true,
			confirmButtonColor: "#3085d6",
			cancelButtonColor: "#d33",
			confirmButtonText: "Yes, delete it!"
		}).then((result) => {
			if (result.isConfirmed) {
				$.ajax({
					type: 'DELETE',
					url: staffDestroyUrl.replace('__ID__', id),
					dataType: 'json',
					success: function () {
						$("#row_" + id).remove();
					},
					error: function (xhr) {
						Swal.fire({ icon: 'error', title: 'Error', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Could not delete the staff member.' });
					}
				});
			}
		});
	}

	$(document).ready(function () {
		$("#add_staff_member_form").off('submit').on('submit', function (e) {
			e.preventDefault();
			submitStaffMemberForm(this, '#addStaffMemberModal');
		});
		$("#edit_staff_member_form").off('submit').on('submit', function (e) {
			e.preventDefault();
			submitStaffMemberForm(this, '#editStaffMemberModal');
		});

		initStaffSelect2('#a_department_id', 'departments', 'Department', '#addStaffMemberModal');
		initStaffSelect2('#e_department_id', 'departments', 'Department', '#editStaffMemberModal');
		initStaffSelect2('#a_designation_id', 'designations', 'Designation', '#addStaffMemberModal');
		initStaffSelect2('#e_designation_id', 'designations', 'Designation', '#editStaffMemberModal');
		initStaffSelect2('#a_user_role_id', 'user-roles', 'Role', '#addStaffMemberModal');
		initStaffSelect2('#e_user_role_id', 'user-roles', 'Role', '#editStaffMemberModal');
		initStaffSelect2('#a_specialities', 'specialities', 'Specialities', '#addStaffMemberModal');
		showDropDownSpecialities();
	});

	$('#editStaffMemberModal').on('show.bs.modal', function () {
		$("#staff_image").attr('src', "{{ asset('assets/images/no_image.png') }}");
		$("#edit_photo").val('');
		$("#existing_image_box").show();
		$("#edit_image_box").hide();
	});
</script>
@endpush
