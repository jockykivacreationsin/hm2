@extends('layouts.app')
@section('title', 'Staff Roster Calendar')
@section('content')
@if(!($canView ?? true))
	<div class="themebody-wrap">
		<div class="no-access">
			<i class="fa-duotone fa-shield-halved"></i>
			<p>Access Denied</p>
		</div>
	</div>
@else
@php
	$rosterRows = [
		['Doctor_Day',   'Day Doctors',  'Doctor', 'Day',   $doctorDesignationId],
		['Doctor_Night', 'Night Doctors', 'Doctor', 'Night', $doctorDesignationId],
		['Nurse_Day',    'Day Nurses',   'Nurse',  'Day',   $nurseDesignationId],
		['Nurse_Night',  'Night Nurses', 'Nurse',  'Night', $nurseDesignationId],
	];
@endphp
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Staff Roster Calendar</div>
					<div class="fc-toolbar">
						<div class="fc-left">
							<a href="{{ route('staff.roster-calendar', ['d' => 'l', 'month' => $month - 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-left"></i></a>
						</div>
						<div class="fc-center"><h2>{{ $monthName }} {{ $year }}</h2></div>
						<div class="fc-right">
							<a href="{{ route('staff.roster-calendar', ['d' => 'r', 'month' => $month + 1, 'year' => $year]) }}" class="cal-btn"><i class="fas fa-chevron-right"></i></a>
						</div>
					</div>
				</div>	

				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="table table-sm table-bordered nowrap">
							<thead>
								<tr>
									<th>
										Date
									</th>
									@for($i = 1; $i <= $days; $i++)
										@php
											$dateStr = sprintf('%04d-%02d-%02d', $year, $month, $i);
											$dayName = strtoupper(date('D', strtotime($dateStr)));
										@endphp
										@if($i < 10)
											<th>0{{ $i }} {{ $dayName }}</th>
										@else
											<th>{{ $i }} {{ $dayName }}</th>
										@endif
									@endfor
								</tr>
							</thead>
							<tbody>
								@foreach($rosterRows as [$rowId, $label, $designation, $shift, $designationId])
								<tr id="{{ $rowId }}">
									<th>{{ $label }}</th>
									@for($i = 1; $i <= $days; $i++)
										@php
											$date = sprintf('%04d-%02d-%02d', $year, $month, $i);
											$pId  = $rowId . '_' . $date;
										@endphp
										<td>
											<div id="{{ $pId }}" class="roster-td">
												<a href="javascript:void(0)" onclick="addRosterStaffModal('{{ $date }}', {{ (int) $designationId }}, '{{ $designation }}', '{{ $shift }}')"><i class="fas fa-plus"></i></a>
												@foreach($grid[$designation][$shift][$date] ?? [] as $pill)
													@php($cs = \App\Services\StaffService::rosterColor($pill['staff_id']))
													<div id="staff_roster_id_{{ $pill['roster_id'] }}" class="btn-group">
														<button id="staff_roster_btn_{{ $pill['roster_id'] }}" style="background: {{ $cs['background'] }};color: {{ $cs['text'] }}" class="btn dropdown-toggle" type="button" id="dropdownMenuButton{{ $pId }}" data-bs-toggle="dropdown" aria-expanded="false">
															{{ $pill['staff_name'] }}
														</button>
														<div class="dropdown-menu" aria-labelledby="dropdownMenuButton{{ $pId }}" style="">
															<a class="dropdown-item" href="javascript:void(0)" onclick="removeRosterStaff({{ $pill['roster_id'] }})">Remove</a>
															<a class="dropdown-item" href="javascript:void(0)" onclick="replaceRosterStaff({{ $pill['roster_id'] }}, {{ (int) $designationId }})">Replace</a>
														</div>
													</div>
												@endforeach
											</div>
										</td>
									@endfor
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="addRosterStaffModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add Roster Staff</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="add_roster_staff_form" action="{{ route('staff.roster.add') }}" method="POST">
				<input type="hidden" id="roster_date" name="roster_date" value="">
				<input type="hidden" id="designation_id" name="designation_id" value="">
				<input type="hidden" id="designation" name="designation" value="">
				<input type="hidden" id="shift" name="shift" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Name <span class="req-star">*</span></label>
								<select id="roster_staff_id" name="staff_id" class="form-control" required>

								</select>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label id="number_of_days_nights_lbl">No <span class="req-star">*</span></label>
								<input type="number" id="date_number" name="date_number" class="form-control" value="6" required>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Add</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="replaceRosterStaffModal">
	<div class="modal-dialog modal-sm">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Replace Roster Staff</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<form id="replace_roster_staff_form" action="" method="POST">
				@method('PUT')
				<input type="hidden" id="replace_roster_id" name="roster_id" value="">
				<div class="modal-body">
					<div class="row">
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Name <span class="req-star">*</span></label>
								<select id="replace_roster_staff_id" name="staff_id" class="form-control" required>

								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button class="btn btn-primary ml-10" type="submit">Replace</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif
@endsection

@push('scripts')
@if($canView ?? true)
<script>
	var rosterUrls = {
		item: "{{ route('staff.roster.remove', ['id' => '__ID__']) }}",
		replaceOptions: "{{ route('staff.roster.replace-options', ['id' => '__ID__']) }}"
	};

	function rosterAjaxError(xhr) {
		var msg = 'Something went wrong. Please try again.';
		if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
			msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
		} else if (xhr.responseJSON && xhr.responseJSON.message) {
			msg = xhr.responseJSON.message;
		}
		Swal.fire({ icon: 'error', title: 'Error', text: msg });
	}

	function escapeHtml(s) {
		return $('<div>').text(s).html();
	}

	$(document).ready(function() {
		$("#add_roster_staff_form").on('submit', function(e) {
			e.preventDefault();
			$.ajax({
				url: this.action,
				type: "POST",
				data: new FormData(this),
				contentType: false,
				cache: false,
				processData: false,
				dataType: 'json',
				success: function(data) {
					$("#add_roster_staff_form")[0].reset();
					$("#addRosterStaffModal").modal('hide');
					Swal.fire({
						icon: "success",
						title: "Saved!",
						text: "Saved Successfully.",
						showConfirmButton: false,
						timer: 1500
					});

					let cs = getHexColorFromID(data.staff_id);

					let roster_ids = data.roster_ids;
					roster_ids.forEach(function(item) {
						let roster_date = item.date;
						let roster_id = item.id;

						let p_id = data.designation + "_" + data.shift + "_" + roster_date;

						if ($("#staff_roster_id_" + roster_id).length) {
							return;
						}

						let a = '<div id="staff_roster_id_' + roster_id + '" class="btn-group">' +
							'<button id="staff_roster_btn_' + roster_id + '" style="background: ' + cs.background + ';color: ' + cs.text + '" class="btn dropdown-toggle" type="button" id="dropdownMenuButton' + p_id + '" data-bs-toggle="dropdown" aria-expanded="false">' +
							escapeHtml(data.staff_name) + '</button>' +
							'<div class="dropdown-menu" aria-labelledby="dropdownMenuButton' + p_id + '" style="">' +
							'<a class="dropdown-item" href="javascript:void(0)" onclick="removeRosterStaff(' + roster_id + ')">Remove</a>' +
							'<a class="dropdown-item" href="javascript:void(0)" onclick="replaceRosterStaff(' + roster_id + ', ' + data.designation_id + ')">Replace</a>' +
							'</div></div>';

						$("#" + p_id).append(a);
					});
				},
				error: rosterAjaxError
			});
		});

		$("#replace_roster_staff_form").on('submit', function(e) {
			e.preventDefault();
			$.ajax({
				url: this.action,
				type: "POST",
				data: new FormData(this),
				contentType: false,
				cache: false,
				processData: false,
				dataType: 'json',
				success: function(data) {
					$("#replace_roster_staff_form")[0].reset();
					$("#replaceRosterStaffModal").modal('hide');
					Swal.fire({
						icon: "success",
						title: "Saved!",
						text: "Saved Successfully.",
						showConfirmButton: false,
						timer: 1500
					});
					$("#staff_roster_btn_" + data.id).html(escapeHtml(data.sn));
				},
				error: rosterAjaxError
			});
		});
	});

	function addRosterStaffModal(roster_date, designation_id, designation, shift) {
		$("#addRosterStaffModal").modal('show');
		$("#number_of_days_nights_lbl").html("Number of " + shift);
		$("#roster_date").val(roster_date);
		$("#designation_id").val(designation_id);
		$("#designation").val(designation);
		$("#shift").val(shift);
		$.ajax({
			type: 'GET',
			url: "{{ route('staff.roster.staff-options') }}",
			data: { roster_date: roster_date, designation_id: designation_id, shift: shift },
			dataType: 'json',
			success: function(data) {
				$("#roster_staff_id").html(data.s);
			},
			error: rosterAjaxError
		});
	}

	function removeRosterStaff(id) {
		$.ajax({
			type: 'DELETE',
			url: rosterUrls.item.replace('__ID__', id),
			dataType: 'json',
			success: function(data) {
				$("#staff_roster_id_" + id).remove();
			},
			error: rosterAjaxError
		});
	}

	function replaceRosterStaff(id, designation_id) {
		$("#replaceRosterStaffModal").modal('show');
		$("#replace_roster_id").val(id);
		$("#replace_roster_staff_form").attr('action', rosterUrls.item.replace('__ID__', id));
		$.ajax({
			type: 'GET',
			url: rosterUrls.replaceOptions.replace('__ID__', id),
			data: { designation_id: designation_id },
			dataType: 'json',
			success: function(data) {
				$("#replace_roster_staff_id").html(data.s);
			},
			error: rosterAjaxError
		});
	}

	function getHexColorFromID(id) {
		let r = (id * 123) % 256;
		let g = (id * 321) % 256;
		let b = (id * 213) % 256;
		let bgColor = `rgb(${r}, ${g}, ${b})`;

		let luminance = 0.299 * r + 0.587 * g + 0.114 * b;
		let textColor = (luminance > 150) ? 'black' : 'white';

		return {
			background: bgColor,
			text: textColor
		};
	}
</script>
@endif
@endpush
