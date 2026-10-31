@extends('layouts.app')
@section('title', 'DCD Discharged Detail')
@section('content')

@if(!$canView)
<div class="themebody-wrap">
  <div class="no-access">
    <i class="fa-duotone fa-shield-halved"></i>
    <p>Access Denied</p>
  </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
	<form id="approve_discharge_form" enctype="multipart/form-data">
		<input type="hidden" name="discharge_id" value="{{ $discharge->id }}">
		<div class="card-ipd">
			<div class="card-body">
				<div class="row ipd-patient-overview-row">
					<div class="col-lg-6 ipd-patient-left-part">
						<div class="ipd-overview-left">
							<div class="ipd-patient-info-top-bar row-bottom-border">
								<div class="ipd-patient-name">
									<span class="patient-name">{{ $patient->patient_name ?? '' }}</span><span>({{ $patient->id ?? '' }})</span>
								</div>
								<div class="flex-row-g4">
										<p><b>Discharge Date Time:</b> {{ $dischargeDateTimeFormatted }}</p>
										<p><b>Patient Condition:</b> {{ $discharge->condition_on_discharge }}</p>
								</div>
							</div>
							<div class="ipd-patient-personal-bar row-bottom-border">
								<div class="row">
									<div class="col-lg-2">
										@php 
										$photo = $patient?->photo ? \App\Support\Uploads::url('patients', $patient->photo) : asset('assets/images/no_image.png');
										@endphp
										<img class="fullwidth" src="{{ $photo }}">
									</div>
									<div class="col-lg-5">
										<ul class="ipd-patient-personal-ul">
											<li><span class="bold">OPD No</span>{{ $dcd->opd_id }}</li>
											<li><span class="bold">Guardian Name</span>{{ $patient->guardian_name ?? '' }}</li>
											<li><span class="bold">Gender</span>{{ $patient->gender ?? '' }}</li>
											<li><span class="bold">Age</span>{{ $patient->age ?? '' }} Yrs</li>
											<li><span class="bold">Phone</span>{{ $patient->mobile_number ?? '' }}{{ !empty($patient->alternate_mobile_number) ? ', ' . $patient->alternate_mobile_number : '' }}</li>
										</ul>
									</div>
									<div class="col-lg-5">
										<ul class="ipd-patient-personal-ul">
											<li><span class="bold">DCD No</span>{{ $dcd->id }}</li>
											<li><span class="bold">Admission Date</span>{{ $dcdDateTimeFormatted }}</li>
											<li><span class="bold">Floor</span>{{ $dcd->bed?->floor?->floor ?? '' }}</li>
											<li><span class="bold">Ward </span>{{ $dcd->bed?->wardCategory?->ward_category ?? '' }}</li>
											<li><span class="bold">Bed</span>{{ implode('-', array_filter([$dcd->bed?->bed_name, $dcd->bed?->bedType?->bed_type])) }}</li>
										</ul>
									</div>

								</div>
							</div>

							<div class="ipd-patient-tag-bar row-bottom-border">
								<div class="tag-title caps">Symptoms</div>
								<ul>
									<li>{!! \App\Support\SafeHtml::clean(($dcd->opd?->symptoms ?? ''), true) !!}</li>
								</ul>
							</div>
							<div class="ipd-patient-tag-bar row-bottom-border">
								<div class="tag-title caps">Finding</div>
								<ul>
									<li>{!! \App\Support\SafeHtml::clean(($dcd->opd?->remarks ?? ''), true) !!}</li>
								</ul>
							</div>
							<div class="ipd-patient-tag-bar row-bottom-border">
								<div class="tag-title caps">Vital Signs</div>
								<ul class="vital-signs-box">
									<li><span>BP</span> {{ $dcd->opd?->bp ?? '' }} mmHg</li>
									<li><span>PR</span> {{ $dcd->opd?->pulse ?? '' }} b/M</li>
									<li><span>SPO2</span> {{ $dcd->opd?->respiration ?? '' }}%</li>
								</ul>
							</div>
							<div class="ipd-patient-consultant-doctor-bar row-bottom-border">
								<div class="title-with-small-buttons">
									<div class="bold caps">Consultant Doctor</div>
								</div>
								<div id="dcd_consultant_doctors" class="row">
									@if($doctor)
									<div class="col-lg-6">
										<div class="ipd-doctor-box">
											<div class="ipd-patient-consultant-doctor-box row">
												<div class="col-lg-4">
													@php 
													$docPhoto = !empty($doctor->photo) ? \App\Support\Uploads::url('staffmembers', $doctor->photo) : asset('assets/images/no_image.png');
													@endphp
													<img class="fullwidth" src="{{ $docPhoto }}">
												</div>
												<div class="col-lg-8">
													<ul>
														<li><span class="bold">Name</span>{{ $doctor->staff_name }}</li>
														<li><span class="bold">Designation</span>{{ $doctor->designation?->designation ?? '' }}</li>
														<li><span class="bold">Contact Number</span>{{ $doctor->mobile_number }}</li>
													</ul>
												</div>
											</div>
										</div>
									</div>
									@endif
								</div>
							</div>
							<div class="row-bottom-border">
								<div class="title-with-small-buttons">
									<div class="bold caps">Investigations</div>
								</div>
								<div id="dcd_discharge_investigations" class="row">
									@foreach($investigations as $idirow)
									<div class="col-sm-6">
										<p><strong>{{ $idirow->investigation }}</strong><br>{{ $idirow->result }}</p>
									</div>
									@endforeach
								</div>
							</div>
							<div class="row-bottom-border">
								<div class="title-with-small-buttons">
									<div class="bold caps">Procedure</div>
								</div>
								<div id="dcd_discharge_procedures" class="row">
									<ul>
										@foreach($procedures as $pcrow)
										<li>{{ $pcrow->procedure_detail }}</li>
										@endforeach
									</ul>
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="ipd-overview-right">
							@if(count($medicationsGiven) > 0)
							<div class="sm-table table-sm-bottom-border">
								<span class="table-title caps">Treatment Given</span>
								<div class="table-responsive">  
									<table class="table table-bordered table-sm table-text-sm">
										<thead>
											<tr>
												<th>Medicine</th>
												<th>Dose</th>
												<th>Frequency</th>
												<th>Timing</th>
												<th>Meal</th>
												<th>Duration</th>
											</tr>
										</thead>
										<tbody>
											@foreach($medicationsGiven as $pmrow)
											<tr>
												<td>{{ $pmrow->medicine_name }}</td>
												<td>{{ $pmrow->dose_quantity }}</td>
												<td>{{ $pmrow->frequency_display }}</td>
												<td>{{ $pmrow->timing_display }}</td>
												<td>{{ $pmrow->meal_relation }}</td>
												<td>{{ $pmrow->duration_days }} days</td>
											</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							</div>
							@endif

						</div>
						<div class="ipd-patient-tag-bar row-bottom-border">
							<div class="tag-title caps">Diagnosis<span class="req-star">*</span></div>
							<p>{{ $discharge->diagnosis }}</p>
						</div>
						<div class="row-bottom-border">
							@if(count($treatmentAdvised) > 0)
							<div class="sm-table table-sm-bottom-border">
								<span class="table-title caps">Treatment Advised</span>
								<div class="table-responsive">  
									<table class="table table-bordered table-sm table-text-sm">
										<thead>
											<tr>
												<th>Medicine</th>
												<th>Quantity</th>
												<th>Frequency</th>
												<th>Timing</th>
												<th>Meal Relation</th>
												<th>Duration</th>
											</tr>
										</thead>
										<tbody>
											@foreach($treatmentAdvised as $tarow)
											<tr>
												<td>{{ $tarow->medicine_name }}</td>
												<td>{{ $tarow->dose_quantity }}</td>
												<td>{{ $tarow->frequency_display }}</td>
												<td>{{ $tarow->timing_display }}</td>
												<td>{{ $tarow->meal_relation }}</td>
												<td>{{ $tarow->duration_days }} days</td>
											</tr>
											@endforeach
										</tbody>
									</table>
								</div>
							</div>
							@endif
						</div>
						<div class="ipd-patient-tag-bar row-bottom-border">
							<div class="tag-title caps">Condition that may require urgent care<span class="req-star">*</span></div>
							<p>{{ $discharge->urgent_care_condition }}</p>
						</div>
						<div class="ipd-patient-tag-bar row-bottom-border">
							<div class="tag-title caps">Follow Up Detail<span class="req-star">*</span></div>
							<p>{{ $discharge->follow_up_detail }}</p>
						</div>
						<div class="ipd-patient-tag-bar row-bottom-border">
							<div class="tag-title caps">Note</div>
							<p>{!! \App\Support\SafeHtml::clean(($discharge->note ?? ''), true) !!}</p>
						</div>
					</div>	

				</div>
			</div>
		</div>
		<div class="bottom-center-btns">
			<a target="_blank" class="btn btn-success btn-rounded" href="{{ route('pdf.discharge.dcd', $discharge->id) }}">Print</a>
		</div>
	</form>
</div>
@endif
@endsection
