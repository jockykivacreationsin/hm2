@extends('layouts.app')
@section('title', 'DCD Discharge')
@section('content')

{{-- Port of legacy dcd-discharge.php. Submitting sends the discharge for approval (dcd/discharge-approvals). --}}
@if($dcd->status === 'Discharged')
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fad fa-hospital-user"></i>
        <p>Discharge Submitted For Approval</p>
    </div>
</div>
@else
@php
    $patient = $dcd->patient;
    $opd = $dcd->opd;
    $patientPhoto = !empty($patient?->photo) ? \App\Support\Uploads::url('patients', $patient->photo) : asset('assets/images/no_image.png');
    $doctor = $dcd->doctor;
    $doctorPhoto = !empty($doctor?->photo) ? \App\Support\Uploads::url('staffmembers', $doctor->photo) : asset('assets/images/no_image.png');
    $bedName = implode('-', array_filter([$dcd->bed?->bed_name, $dcd->bed?->bedType?->bed_type]));
@endphp
<div class="themebody-wrap ipd-wrap">
    <form id="add_dcd_discharge_form" data-action="{{ route('dcd.discharge.store', $dcd->id) }}" data-method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="dcd_id" value="{{ $dcd->id }}">
        <div class="card-ipd">
            <div class="card-body">
                <div class="row ipd-patient-overview-row">
                    <div class="col-lg-6 ipd-patient-left-part">
                        <div class="ipd-overview-left">
                            <div class="ipd-patient-info-top-bar row-bottom-border">
                                <div class="ipd-patient-name">
                                    <span class="patient-name">{{ $patient?->patient_name }}</span><span>({{ $patient?->id }})</span>
                                </div>
                                <div class="flex-row-g4">
                                    <div class="local-forms">
                                        <label>Discharge Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="discharge_date_time" class="form-control datetimepicker" value="{{ $currentFormatted }}" required>
                                    </div>
                                    <div class="local-forms">
                                        <label>Patient Condition <span class="req-star">*</span></label>
                                        <input type="text" name="condition_on_discharge" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            <div class="ipd-patient-personal-bar row-bottom-border">
                                <div class="row">
                                    <div class="col-lg-2">
                                        <img class="fullwidth" src="{{ $patientPhoto }}" alt="{{ $patient?->patient_name }}">
                                    </div>
                                    <div class="col-lg-5">
                                        <ul class="ipd-patient-personal-ul">
                                            <li><span class="bold">OPD No</span>{{ $dcd->opd_id }}</li>
                                            <li><span class="bold">Guardian Name</span>{{ $patient?->guardian_name }}</li>
                                            <li><span class="bold">Gender</span>{{ $patient?->gender }}</li>
                                            <li><span class="bold">Age</span>{{ $patient?->age }} Yrs</li>
                                            <li><span class="bold">Phone</span>{{ $patient?->mobile_number }}@if(!empty($patient?->alternate_mobile_number)), {{ $patient->alternate_mobile_number }}@endif</li>
                                        </ul>
                                    </div>
                                    <div class="col-lg-5">
                                        <ul class="ipd-patient-personal-ul">
                                            <li><span class="bold">DCD No</span>{{ $dcd->id }}</li>
                                            <li><span class="bold">Admission Date</span>{{ $admissionFormatted }}</li>
                                            <li><span class="bold">Floor</span>{{ $dcd->bed?->floor?->floor }}</li>
                                            <li><span class="bold">Ward </span>{{ $dcd->bed?->wardCategory?->ward_category }}</li>
                                            <li><span class="bold">Bed</span>{{ $bedName }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Symptoms</div>
                                <ul>
                                    <li>{!! \App\Support\SafeHtml::clean(($opd?->symptoms ?? ''), true) !!}</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Finding</div>
                                <ul>
                                    <li>{!! \App\Support\SafeHtml::clean(($opd?->remarks ?? ''), true) !!}</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Vital Signs</div>
                                <ul class="vital-signs-box">
                                    <li><span>BP</span> {{ $opd?->bp }} mmHg</li>
                                    <li><span>PR</span> {{ $opd?->pulse }} b/M</li>
                                    <li><span>SPO2</span> {{ $opd?->spo2 }}%</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Consultant Doctor</div>
                                </div>
                                <div id="dcd_consultant_doctors" class="row">
                                    @if($doctor && $doctor->id)
                                    <div class="col-lg-6">
                                        <div class="ipd-doctor-box">
                                            <div class="ipd-patient-consultant-doctor-box row">
                                                <div class="col-lg-4">
                                                    <img class="fullwidth" src="{{ $doctorPhoto }}" alt="{{ $doctor->staff_name }}">
                                                </div>
                                                <div class="col-lg-8">
                                                    <ul>
                                                        <li><span class="bold">Name</span>{{ $doctor->staff_name }}</li>
                                                        <li><span class="bold">Designation</span>{{ $doctor->designation?->designation }}</li>
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
                                    <div class="ipd-patient-links">
                                        <ul>
                                            <li><a href="javascript:void(0)" onclick="addDDischargeInvestigationBox()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Investigation"><i class="fas fa-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div id="dcd_discharge_investigations" class="row">
                                    @foreach($investigations as $investigation)
                                    @php $iboxId = 'p' . $loop->index; @endphp
                                    <div id="ibox_{{ $iboxId }}" class="col-sm-12 ibox">
                                        <a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removeIBox('{{ $iboxId }}');"><i class="fas fa-times-circle"></i></a>
                                        <input type="text" name="dcd_discharge_investigation_name[]" class="form-control fminwidth" placeholder="Investigation" value="{{ $investigation->investigation }}" required>
                                        <input type="text" name="dcd_discharge_investigation_value[]" class="form-control fmaxwidth" placeholder="Result" value="{{ $investigation->result ?? '' }}" required>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Procedure</div>
                                    <div class="ipd-patient-links">
                                        <ul>
                                            <li><a href="javascript:void(0)" onclick="addDDischargeProcedureBox()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Procedure"><i class="fas fa-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div id="dcd_discharge_procedures" class="row">
                                    @foreach($procedures as $procedure)
                                    @php $pcboxId = 'p' . $loop->index; @endphp
                                    <div id="pcbox_{{ $pcboxId }}" class="col-sm-6 pcbox">
                                        <a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removePCBox('{{ $pcboxId }}');"><i class="fas fa-times-circle"></i></a>
                                        <input type="text" name="dcd_discharge_procedure[]" class="form-control" placeholder="Procedure" value="{{ $procedure }}" required>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ipd-overview-right">
                            @if($medicines->count() > 0)
                            <div class="sm-table table-sm-bottom-border">
                                <span class="table-title caps">Treatment Given</span>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm table-text-sm">
                                        <thead>
                                            <tr>
                                                <th style="width: 20px;"></th>
                                                <th>Name</th>
                                                <th>Dose</th>
                                                <th>Frequency</th>
                                                <th>Timing</th>
                                                <th>Meal</th>
                                                <th>Duration</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($medicines as $m)
                                            <tr id="pdmedicine_{{ $m->id }}">
                                                <td><a href="javascript:void(0)" onclick="removePDMedicine({{ $m->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>
                                                <td>{{ $m->product_name }}<input type="hidden" name="medicine[]" value="{{ $m->product_name }}"></td>
                                                <td>{{ $m->dose_quantity }}<input type="hidden" name="dose_quantity[]" value="{{ $m->dose_quantity }}"></td>
                                                <td>{{ $m->frequency_display }}<input type="hidden" name="frequency[]" value="{{ $m->frequency_display }}"></td>
                                                <td>{{ $m->timing_names }}<input type="hidden" name="timing[]" value="{{ $m->timing_names }}"></td>
                                                <td>{{ $m->meal_name }}<input type="hidden" name="meal[]" value="{{ $m->meal_name }}"></td>
                                                <td>{{ $m->duration_days }} days<input type="hidden" name="duration_days[]" value="{{ $m->duration_days }}"></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="ipd-patient-tag-bar row-bottom-border">
                            <div class="tag-title caps">Follow Up Detail</div>
                            <input type="text" name="follow_up_detail" class="form-control">
                        </div>
                        <div class="ipd-patient-tag-bar row-bottom-border">
                            <div class="tag-title caps">Note</div>
                            <textarea name="note" class="summernote"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bottom-center-btns">
            <button id="submit-for-approval_btn" class="btn btn-success btn-rounded" type="submit">Submit For Approval</button>
        </div>
    </form>
</div>
@endif

@endsection

@push('scripts')
<script>
    appForm.bind('#add_dcd_discharge_form', {
        syncEditors: true,
        busyText: 'Please wait...',
        confirm: { title: 'Submit?', text: 'Are you sure you want to submit!', button: 'Yes, submit it!' }
    });
</script>
@endpush
