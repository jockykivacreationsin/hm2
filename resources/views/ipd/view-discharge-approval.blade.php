@extends('layouts.app')
@section('title', 'Discharge Approval')
@section('content')

@if(!$canView)
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@elseif(!empty($notFound) || !$discharge || !$ipd)
<div class="themebody-wrap">
    <div class="alert alert-danger" style="margin: 20px;">Discharge record not found.</div>
</div>
@elseif($discharge->approved === 'Yes')
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fad fa-hospital-user"></i>
        <p>Approved Successfully</p>
    </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
    <form id="approve_discharge_form" data-action="{{ route('ipd.discharge.approve', $ipd->id) }}" data-method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="discharge_id" value="{{ $discharge->id }}">
        <input type="hidden" name="patient_id" value="{{ $patient->id ?? $ipd->patient_id }}">
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
                                    <div class="local-forms">
                                        <label>Discharge Date Time <span class="req-star">*</span></label>
                                        <input type="text" name="discharge_date_time" class="form-control datetimepicker" value="{{ $dischargeDateTimeFormatted }}" required>
                                    </div>
                                    <div class="local-forms">
                                        <label>Patient Condition <span class="req-star">*</span></label>
                                        <input type="text" name="condition_on_discharge" class="form-control" value="{{ $discharge->condition_on_discharge }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="ipd-patient-personal-bar row-bottom-border">
                                <div class="row">
                                    <div class="col-lg-2">
                                        @php 
                                            $photo = !empty($patient->photo) ? \App\Support\Uploads::url('patients', $patient->photo) : asset('assets/images/no_image.png');
                                        @endphp
                                        <img class="fullwidth" src="{{ $photo }}">
                                    </div>
                                    <div class="col-lg-5">
                                        <ul class="ipd-patient-personal-ul">
                                            <li><span class="bold">OPD No</span>{{ $ipd->opd_id }}</li>
                                            <li><span class="bold">Guardian Name</span>{{ $patient->guardian_name ?? '' }}</li>
                                            <li><span class="bold">Gender</span>{{ $patient->gender ?? '' }}</li>
                                            <li><span class="bold">Age</span>{{ $patient->age ?? '' }} Yrs</li>
                                            <li><span class="bold">Phone</span>{{ $patient->mobile_number ?? '' }}{{ !empty($patient->alternate_mobile_number) ? ', ' . $patient->alternate_mobile_number : '' }}</li>
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
                                    <li><span>SPO2</span> {{ $ipd->opd?->respiration ?? '' }}%</li>
                                </ul>
                            </div>
                            <div class="ipd-patient-consultant-doctor-bar row-bottom-border">
                                <div class="title-with-small-buttons">
                                    <div class="bold caps">Consultant Doctor</div>
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
                                                        <li><span class="bold">Name</span>{{ $doc->staff_name }}</li>
                                                        <li><span class="bold">Designation</span>{{ $doc->designation ?? '' }}</li>
                                                        <li><span class="bold">Contact Number</span>{{ $doc->mobile_number ?? '' }}</li>
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
                                </div>
                                <div id="ipd_discharge_investigations" class="row">
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
                                <div id="ipd_discharge_procedures" class="row">
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
                                                <th>Name</th>
                                                <th>Dose</th>
                                                <th>Interval</th>
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
                            <input type="text" name="diagnosis" class="form-control" value="{{ $discharge->diagnosis }}" required>
                        </div>

                        <div class="row-bottom-border">
                            <div class="title-with-small-buttons">
                                <div class="bold caps">Treatment Advised</div>
                            </div>
                            <input type="hidden" id="pmnor" value="0">
                            <div style="display: grid; grid-template-columns: 1fr 0.15fr 0.3fr 0.3fr; gap: 10px; margin-top: 10px;">
                                <div class="form-group local-forms">
                                    <label>Medicine Name <span class="req-star">*</span></label>
                                    <select id="a_pharma_product_id" name="pharma_product_id" class="form-control select2"> 
                                        <option value="">Select Medicine</option>
                                        @foreach($pharmaProducts as $pprow)
                                            <option value="{{ $pprow->id }}">{{ $pprow->product_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group local-forms">
                                    <label>QNT <span class="req-star">*</span></label>
                                    <input type="text" id="a_dose_quantity" name="dose_quantity" class="form-control">
                                </div>
                                <div class="form-group local-forms">
                                    <label>Frequency <span class="req-star">*</span></label>
                                    <select id="a_frequency_type_id" name="frequency_type_id" class="form-control"> 
                                        <option value="">Select</option>
                                        @foreach($frequencyTypes as $ftrow)
                                            <option value="{{ $ftrow->id }}">{{ $ftrow->display_text }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group local-forms">
                                    <label>Meal <span class="req-star">*</span></label>
                                    <select id="a_meal_relation_id" name="meal_relation_id" class="form-control"> 
                                        <option value="">Select</option>
                                        @foreach($mealRelations as $mrrow)
                                            <option value="{{ $mrrow->id }}">{{ $mrrow->relation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 0.20fr 0.5fr 40px; gap: 10px;">
                                <div class="form-group local-forms">
                                    <label>Timing <span class="req-star">*</span></label>
                                    <select id="a_timing_ids" name="timing_ids" class="form-control select2" multiple> 
                                        @foreach($timingList as $trow)
                                            <option value="{{ $trow->id }}">{{ $trow->timing_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group local-forms">
                                    <label>Days <span class="req-star">*</span></label>
                                    <input type="number" id="a_duration_days" name="duration_days" class="form-control" min="1">
                                </div>
                                <div class="form-group local-forms">
                                    <label>Reason/Diagnosis</label>
                                    <select id="a_medical_condition_id" name="medical_condition_id" class="form-control select2">
                                        <option value="">-- Select --</option>
                                        @foreach($medicalConditions as $condition)
                                            @php 
                                                $display = $condition->condition_name . ($condition->category ? " [{$condition->category}]" : '');
                                            @endphp
                                            <option value="{{ $condition->id }}">{{ $display }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group local-forms">
                                    <label></label>
                                    <button type="button" class="ssbtn btn-block" onclick="addPrescriptionMedicine()"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Medicine</th>
                                                    <th>QNT</th>
                                                    <th>Frequency</th>
                                                    <th>Timing</th>
                                                    <th>Meal</th>
                                                    <th>Days</th>
                                                    <th>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ipd-patient-tag-bar row-bottom-border">
                            <div class="tag-title caps">Condition that may require urgent care<span class="req-star">*</span></div>
                            <input type="text" name="urgent_care_condition" class="form-control" value="{{ $discharge->urgent_care_condition }}">
                        </div>
                        <div class="ipd-patient-tag-bar row-bottom-border">
                            <div class="tag-title caps">Follow Up Detail<span class="req-star">*</span></div>
                            <input type="text" name="follow_up_detail" class="form-control" value="{{ $discharge->follow_up_detail }}" required>
                        </div>
                        <div class="ipd-patient-tag-bar row-bottom-border">
                            <div class="tag-title caps">Note</div>
                            <textarea name="note" class="summernote">{{ \App\Support\SafeHtml::clean(($discharge->note ?? ''), true) }}</textarea>
                        </div>
                    </div>	
                </div>
            </div>
        </div>
        @if($approve)
        <div class="bottom-center-btns">
            <button class="btn btn-success btn-rounded" type="submit">Approve & Discharge</button>
        </div>
        @endif
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
                <input type="hidden" name="discharge_id" value="{{ $discharge->id }}">
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
appForm.bind('#approve_discharge_form', {
        syncEditors: true,
        confirm: { title: 'Submit?', text: 'Are you sure you want to approve!', button: 'Yes, approve it!' }
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
