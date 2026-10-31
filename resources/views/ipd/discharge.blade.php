@extends('layouts.app')
@section('title', 'IPD Discharge')
@section('content')

@if(isset($canView) && !$canView)
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@elseif((isset($notFound) && $notFound) || !$ipd)
<div class="themebody-wrap">
    <div class="no-access">
        <p>IPD record not found</p>
    </div>
</div>
@elseif($ipd->status === 'Discharged')
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fad fa-hospital-user"></i>
        <p>Discharge Submitted For Approval</p>
    </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
    <form id="add_discharge_form" data-action="{{ route('ipd.discharge.store', $ipd->id) }}" data-method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="ipd_id" value="{{ $ipd->id }}">
        <div class="card-ipd">
            <div class="card-body">
                <div class="row ipd-patient-overview-row">
                    <div class="col-lg-6 ipd-patient-left-part">
                        <div class="ipd-overview-left">
                            <div class="ipd-patient-info-top-bar row-bottom-border">
                                <div class="ipd-patient-name">
                                    <span class="patient-name">{{ $ipd->patient?->patient_name }}</span><span>({{ $ipd->patient?->id }})</span>
                                </div>
                                <div class="flex-row-g4">
                                    <div class="local-forms">
                                        <label>Discharge Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="discharge_date_time" class="form-control datetimepicker" value="{{ $currentDateTimeFormatted }}" required>
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
                                        @php 
                                            $photo = !empty($ipd->patient?->photo) ? \App\Support\Uploads::url('patients', $ipd->patient->photo) : asset('assets/images/no_image.png');
                                        @endphp
                                        <img class="fullwidth" src="{{ $photo }}">
                                    </div>
                                    <div class="col-lg-5">
                                        <ul class="ipd-patient-personal-ul">
                                            <li><span class="bold">OPD No</span>{{ $ipd->opd_id }}</li>
                                            <li><span class="bold">Guardian Name</span>{{ $ipd->patient?->guardian_name }}</li>
                                            <li><span class="bold">Gender</span>{{ $ipd->patient?->gender }}</li>
                                            <li><span class="bold">Age</span>{{ $ipd->patient?->age }} Yrs</li>
                                            <li><span class="bold">Phone</span>{{ $ipd->patient?->mobile_number }}{{ !empty($ipd->patient?->alternate_mobile_number) ? ', ' . $ipd->patient->alternate_mobile_number : '' }}</li>
                                        </ul>
                                    </div>
                                    <div class="col-lg-5">
                                        <ul class="ipd-patient-personal-ul">
                                            <li><span class="bold">IPD No</span>{{ $ipd->id }}</li>
                                            <li><span class="bold">Admission Date</span>{{ $admissionDateTimeFormatted }}</li>
                                            <li><span class="bold">Floor</span>{{ $ipd->bed?->floor?->floor ?? '' }}</li>
                                            <li><span class="bold">Ward </span>{{ $ipd->bed?->wardCategory?->ward_category ?? '' }}</li>
                                            <li><span class="bold">Bed</span>{{ implode('-', array_filter([$ipd->bed?->bed_name, $ipd->bed?->bedType?->bed_type])) }}</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Symptoms</div>
                                <ul>
                                    <li>{!! \App\Support\SafeHtml::clean(($ipd->opd?->symptoms ?? ''), true) !!}</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Finding</div>
                                <ul>
                                    <li>{!! \App\Support\SafeHtml::clean(($ipd->opd?->remarks ?? ''), true) !!}</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-tag-bar row-bottom-border">
                                <div class="tag-title caps">Vital Signs</div>
                                <ul class="vital-signs-box">
                                    <li><span>BP</span> {{ $ipd->opd?->bp ?? '' }} mmHg</li>
                                    <li><span>PR</span> {{ $ipd->opd?->pulse ?? '' }} b/M</li>
                                    <li><span>SPO2</span> {{ $ipd->opd?->spo2 ?? $ipd->opd?->respiration ?? '' }}%</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Consultant Doctor</div>
                                    <div class="ipd-patient-links">
                                        <ul>
                                            <li><a href="javascript:void(0)" onclick="addIPDDoctorModal()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Doctor"><i class="fas fa-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div id="ipd_consultant_doctors" class="row">
                                    @foreach($consultantDoctors as $doc)
                                    <div class="col-lg-6">
                                        <div class="ipd-doctor-box">
                                            <div class="ipd-patient-consultant-doctor-box row">
                                                <div class="col-lg-4">
                                                    @php 
                                                        $docPhoto = !empty($doc->photo) ? \App\Support\Uploads::url('staffmembers', $doc->photo) : asset('assets/images/no_image.png');
                                                    @endphp
                                                    <img class="fullwidth" src="{{ $docPhoto }}">
                                                </div>
                                                <div class="col-lg-8">
                                                    <ul>
                                                        <li><span class="bold">{{ $doc->staff_name }}</span></li>
                                                        <li>{{ $doc->designation }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Investigations</div>
                                    <div class="ipd-patient-links">
                                        <ul>
                                            <li><a href="javascript:void(0)" onclick="addDischargeInvestigationBox()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Investigation"><i class="fas fa-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div id="ipd_discharge_investigations" class="row">
                                    @foreach($investigations as $idirow)
                                    @php $ibox_id = rand(1000, 9999); @endphp
                                    <div id="ibox_{{ $ibox_id }}" class="col-sm-12 ibox">
                                        <a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removeIBox({{ $ibox_id }});"><i class="fas fa-times-circle"></i></a>
                                        <input type="text" name="ipd_discharge_investigation_name[]" class="form-control fminwidth" placeholder="Investigation" value="{{ $idirow->investigation }}" required>
                                        <input type="text" name="ipd_discharge_investigation_value[]" class="form-control fmaxwidth" placeholder="Result" value="{{ $idirow->result ?? '' }}" required>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            
                            <div class="row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Procedure</div>
                                    <div class="ipd-patient-links">
                                        <ul>
                                            <li><a href="javascript:void(0)" onclick="addDischargeProcedureBox()" data-bs-toggle="tooltip" data-bs-placement="top" title="Add Procedure"><i class="fas fa-plus"></i></a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div id="ipd_discharge_procedures" class="row">
                                    @foreach($procedures as $pcrow)
                                    @php $pcbox_id = rand(1000, 9999); @endphp
                                    <div id="pcbox_{{ $pcbox_id }}" class="col-sm-6 pcbox">
                                        <a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removePCBox({{ $pcbox_id }});"><i class="fas fa-times-circle"></i></a>
                                        <input type="text" name="ipd_discharge_procedure[]" class="form-control" placeholder="Procedure" value="{{ $pcrow->procedure ?? $pcrow->charge_title }}" required>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="ipd-overview-right">
                            @if(count($latestMedicines) > 0)
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
                                                <th>Reason</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($latestMedicines as $pmrow)
                                            <tr id="pdmedicine_{{ $pmrow->id }}">
                                                <td><a href="javascript:void(0)" onclick="removePDMedicine({{ $pmrow->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>
                                                <td>{{ $pmrow->product_name }}<input type="hidden" name="medicine[]" value="{{ $pmrow->product_name }}"></td>
                                                <td>{{ $pmrow->dose_quantity }}<input type="hidden" name="dose_quantity[]" value="{{ $pmrow->dose_quantity }}"></td>
                                                <td>{{ $pmrow->frequency_display }}<input type="hidden" name="frequency[]" value="{{ $pmrow->frequency_display }}"></td>
                                                <td>{{ $pmrow->timing_names }}<input type="hidden" name="timing[]" value="{{ $pmrow->timing_names }}"></td>
                                                <td>{{ $pmrow->meal_name }}<input type="hidden" name="meal[]" value="{{ $pmrow->meal_name }}"></td>
                                                <td>{{ $pmrow->duration_days }} days<input type="hidden" name="duration_days[]" value="{{ $pmrow->duration_days }}"></td>
                                                <td>{{ !empty($pmrow->condition_name) ? $pmrow->condition_name : 'Not Specified' }}<input type="hidden" name="medical_condition_id[]" value="{{ $pmrow->medical_condition_id }}"></td>
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

<div class="modal fade" id="addIPDDoctorModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Doctor</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_doctor_form" data-action="{{ route('ipd.doctor.add', $ipd->id) }}" data-method="POST">
                @csrf
                <input type="hidden" name="ipd_id" value="{{ $ipd->id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Doctors <span class="req-star">*</span></label>
                                <select class="form-control" id="a_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    @foreach($allDoctors as $doc)
                                        <option value="{{ $doc->id }}">{{ $doc->staff_name }}</option>
                                    @endforeach
                                </select>
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
@endif

@endsection

@push('scripts')
<script>
/* appForm:wired */
appForm.bind('#add_discharge_form', {
        syncEditors: true,
        busyText: 'Please wait...',
        confirm: { title: 'Submit?', text: 'Are you sure you want to submit!', button: 'Yes, submit it!' }
    });
    appForm.bind('#add_ipd_doctor_form', {
        onSuccess: function () {
            $('#add_ipd_doctor_form')[0].reset();
            $('#addIPDDoctorModal').modal('hide');
            appForm.toast('success', 'Saved!', 'Saved Successfully.');
            location.reload();
        }
    });
</script>
@endpush
