@php
    $photoUrl = $patient && $patient->photo 
        ? \App\Support\Uploads::url('patients', $patient->photo) 
        : asset('assets/images/no_image.png');
    $admissionDateFormatted = $dcd->dcd_date_time 
        ? \Carbon\Carbon::parse($dcd->dcd_date_time)->format('d-m-Y h:i A') 
        : '';
    $dischargeDateFormatted = $discharge->discharge_date_time 
        ? \Carbon\Carbon::parse($discharge->discharge_date_time)->format('d-m-Y h:i A') 
        : '';
    $floorName = $dcd->bed?->floor?->floor ?? '';
    $wardCategory = $dcd->bed?->wardCategory?->ward_category ?? '';
    $bedName = $dcd->bed ? ($dcd->bed->bed_name . '-' . ($dcd->bed->bedType?->bed_type ?? '')) : '';
@endphp

<div class="row ipd-patient-overview-row">
    <div class="col-lg-6 ipd-patient-left-part">
        <div class="ipd-overview-left">
            <div class="ipd-patient-info-top-bar row-bottom-border">
                <div class="ipd-patient-name">
                    <span class="patient-name">{{ $patient->patient_name ?? 'N/A' }}</span><span>({{ $patient->id ?? '' }})</span>
                </div>
                <div class="flex-row-g4">
                    <p><b>Discharge Date Time:</b> {{ $dischargeDateFormatted }}</p>
                    <p><b>Patient Condition:</b> {{ $discharge->condition_on_discharge ?? '' }}</p>
                </div>
            </div>
            <div class="ipd-patient-personal-bar row-bottom-border">
                <div class="row">
                    <div class="col-lg-2">
                        <img class="fullwidth" src="{{ $photoUrl }}" alt="Patient Photo">
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
                            <li><span class="bold">Admission Date</span>{{ $admissionDateFormatted }}</li>
                            <li><span class="bold">Floor</span>{{ $floorName }}</li>
                            <li><span class="bold">Ward </span>{{ $wardCategory }}</li>
                            <li><span class="bold">Bed</span>{{ $bedName }}</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="ipd-patient-tag-bar row-bottom-border">
                <div class="tag-title caps">Symptoms</div>
                <ul>
                    <li>{!! \App\Support\SafeHtml::clean(($dcd->opd?->symptoms ?? 'None')) !!}</li>
                </ul>
            </div>
            <div class="ipd-patient-tag-bar row-bottom-border">
                <div class="tag-title caps">Finding</div>
                <ul>
                    <li>{!! \App\Support\SafeHtml::clean(($dcd->opd?->remarks ?? 'None')) !!}</li>
                </ul>
            </div>
            <div class="ipd-patient-tag-bar row-bottom-border">
                <div class="tag-title caps">Vital Signs</div>
                <ul class="vital-signs-box">
                    <li><span>BP</span> {{ $dcd->opd?->bp ?? '-' }} mmHg</li>
                    <li><span>PR</span> {{ $dcd->opd?->pulse ?? '-' }} b/M</li>
                    <li><span>SPO2</span> {{ $dcd->opd?->respiration ?? '-' }}%</li>
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
                                    <img class="fullwidth" src="{{ !empty($doctor->photo) ? \App\Support\Uploads::url('staffmembers', $doctor->photo) : asset('assets/images/no_image.png') }}" alt="Doctor Photo">
                                </div>
                                <div class="col-lg-8">
                                    <ul>
                                        <li><span class="bold">Name</span>{{ $doctor->staff_name ?? '' }}</li>
                                        <li><span class="bold">Designation</span>{{ $doctor->designation ?? '' }}</li>
                                        <li><span class="bold">Contact Number</span>{{ $doctor->mobile_number ?? '' }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="col-12"><p class="text-muted small">No consultant doctor assigned.</p></div>
                    @endif
                </div>
            </div>
            <div class="row-bottom-border">
                <div class="title-with-small-buttons">
                    <div class="bold caps">Investigations</div>
                </div>
                <div id="dcd_discharge_investigations" class="row">
                    @forelse($investigations as $inv)
                    <div class="col-sm-6">
                        <p><strong>{{ $inv->investigation }}</strong><br>{!! \App\Support\SafeHtml::clean($inv->result) !!}</p>
                    </div>
                    @empty
                    <div class="col-12"><p class="text-muted small">None</p></div>
                    @endforelse
                </div>
            </div>
            <div class="row-bottom-border">
                <div class="title-with-small-buttons">
                    <div class="bold caps">Procedure</div>
                </div>
                <div id="dcd_discharge_procedures" class="row">
                    <ul>
                        @forelse($procedures as $proc)
                        <li>{{ $proc->procedure_detail ?? $proc->procedure_title ?? '' }}</li>
                        @empty
                        <li class="text-muted small">None</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ipd-overview-right">
            @if($medicationsGiven->isNotEmpty())
            <div class="sm-table table-sm-bottom-border">
                <span class="table-title caps">Treatment Given</span>
                <div class="table-responsive">  
                    <table class="table table-bordered table-sm table-text-sm">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Quantity</th>
                                <th>Frequency</th>
                                <th>Timing</th>
                                <th>Meal</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($medicationsGiven as $med)
                            <tr>
                                <td>{{ $med->medicine_name }}</td>
                                <td>{{ $med->dose_quantity }}</td>
                                <td>{{ $med->frequency_display }}</td>
                                <td>{{ $med->timing_display }}</td>
                                <td>{{ $med->meal_relation }}</td>
                                <td>{{ $med->duration_days }} days</td>
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
            <p>{{ $discharge->diagnosis ?? '' }}</p>
        </div>
        <div class="row-bottom-border">
            @if($treatmentAdvised->isNotEmpty())
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
                                <th>Meal</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($treatmentAdvised as $ta)
                            <tr>
                                <td>{{ $ta->medicine_name }}</td>
                                <td>{{ $ta->dose_quantity }}</td>
                                <td>{{ $ta->frequency_display }}</td>
                                <td>{{ $ta->timing_display }}</td>
                                <td>{{ $ta->meal_relation }}</td>
                                <td>{{ $ta->duration_days }} days</td>
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
            <p>{{ $discharge->urgent_care_condition ?? '' }}</p>
        </div>
        <div class="ipd-patient-tag-bar row-bottom-border">
            <div class="tag-title caps">Follow Up Detail<span class="req-star">*</span></div>
            <p>{{ $discharge->follow_up_detail ?? '' }}</p>
        </div>
        <div class="ipd-patient-tag-bar row-bottom-border">
            <div class="tag-title caps">Note</div>
            <p>{!! \App\Support\SafeHtml::clean(((string)($discharge->note ?? '')), true) !!}</p>
        </div>
    </div>	
</div>
