@extends('layouts.app')
@section('title', 'IPD Medications')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-medication-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Medication</div>
                </div>
                @foreach($prescriptions as $rx)
                    @php
                        $dt = $rx->prescription_date_time ? \Carbon\Carbon::parse($rx->prescription_date_time)->format('d-m-Y h:i A') : '';
                        $staffName = $rx->doctor?->staff_name ?? '';
                    @endphp
                    <div class="sm-table mt-10">
                        <div class="ipd-title-with-buttons mb-10">
                            <div class="mp-tbl-title">{{ $dt }} - {{ $staffName }}</div>
                            @if(!empty($b_ipd_copy_medication))
                                <div class="ipd-buttons">
                                    <ul>
                                        <li><a href="javascript:void(0);" onclick="copyIPDPrescriptionModal({{ $rx->id }});"><i class="far fa-copy"></i> Make Copy</a></li>
                                    </ul>
                                </div>
                            @endif
                        </div>	
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
                                    @foreach($rx->medicines as $med)
                                        @php
                                            $prodName = $med->product?->product_name ?? '';
                                            $doseQty = $med->dose_quantity ?? '';
                                            $freqDisplay = $med->frequency?->display_text ?? '';
                                            $timingNames = $med->timings->sortBy(fn($t) => $t->timing?->sort_order ?? 0)->map(fn($t) => $t->timing?->timing_name)->filter()->implode(' + ');
                                            $mealName = $med->mealRelation?->relation_name ?? '';
                                            $durationDays = $med->duration_days ?? '';
                                        @endphp
                                        <tr>
                                            <td>{{ $prodName }}</td>
                                            <td>{{ $doseQty }}</td>
                                            <td>{{ $freqDisplay }}</td>
                                            <td>{{ $timingNames }}</td>
                                            <td>{{ $mealName }}</td>
                                            <td>{{ $durationDays }} days</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="copyIPDPrescriptionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Copy Prescription</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_prescription_form" data-action="{{ route('ipd.prescriptions.copy', $ipd->id) }}" data-method="POST">
                <input type="hidden" id="ipd_id" name="ipd_id" value="{{ $ipd->id }}">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Date Time <span class="req-star">*</span></label>
                                        <input type="text" id="prescription_date_time" name="prescription_date_time" class="form-control" value="{{ date('d-m-Y h:i a') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <div class="form-group local-forms">
                                        <label>Doctor <span class="req-star">*</span></label>
                                        <select class="form-control" id="e_doctor_id" data-lookup-url="{{ route('pickers.doctors') }}" name="doctor_id" required>
                                            <option></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Lab Tests</label>
                                        <select class="form-control" id="e_pathology_tests" data-lookup-url="{{ route('lab-results.dropdown.pathology-tests') }}" name="pathology_tests[]" multiple>
                                            
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms">
                                        <label>Radiology Tests</label>
                                        <select class="form-control" id="e_radiology_tests" data-lookup-url="{{ route('lab-results.dropdown.radiology-tests') }}" name="radiology_tests[]" multiple>
                                            
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-12">
                                    <div class="form-group local-forms mb-0">
                                        <label>Findings</label>
                                        <input type="text" id="findings" name="findings" class="form-control">
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="epmnor" value="0">
                            <div class="row">
                                <!-- Medicine -->
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Medicine <span class="req-star">*</span></label>
                                        <select id="e_ipd_medicine_name" name="medicine_name" class="form-control select2">
                                            <option value="">-- Select Medicine --</option>
                                            @foreach($medicines as $med)
                                                @php
                                                    $display = $med->product_name;
                                                    if($med->doseUnit?->unit_name) {
                                                        $display .= " - " . $med->doseUnit->unit_name;
                                                    }
                                                    if($med->route?->route_name) {
                                                        $display .= " (" . $med->route->route_name . ")";
                                                    }
                                                @endphp
                                                <option value="{{ $med->id }}">{{ $display }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dose Quantity -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Quantity <span class="req-star">*</span></label>
                                        <input type="number" step="0.5" min="0.5" id="e_ipd_dose_quantity" name="dose_quantity" class="form-control" placeholder="1" value="1">
                                    </div>
                                </div>

                                <!-- Frequency -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Frequency <span class="req-star">*</span></label>
                                        <select id="e_ipd_frequency" name="frequency" class="form-control">
                                            @foreach($frequencies as $freq)
                                                <option value="{{ $freq->id }}">{{ $freq->display_text }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Meal Relation -->
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Meal</label>
                                        <select id="e_ipd_meal" name="meal" class="form-control">
                                            @foreach($meals as $meal)
                                                <option value="{{ $meal->id }}">{{ $meal->relation_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Duration Days -->
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>Days <span class="req-star">*</span></label>
                                        <input type="number" min="1" id="e_ipd_duration_days" name="duration_days" class="form-control" value="5">
                                    </div>
                                </div>

                                <!-- Timing (Multiple Selection) -->
                                <div class="col-12 col-sm-9">
                                    <div class="form-group local-forms">
                                        <label>Timing <span class="req-star">*</span></label>
                                        <select id="e_ipd_timing" name="timing[]" class="form-control select2" multiple>
                                            @foreach($timings as $timing)
                                                <option value="{{ $timing->id }}">{{ $timing->timing_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <button type="button" class="btn btn-sm btn-success" onclick="addEPrescriptionMedicine()" style="margin-top: 28px;"><i class="fas fa-plus"></i> Add Medicine</button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_prescription_medicine_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Medicine</th>
                                                    <th>Quantity</th>
                                                    <th>Frequency</th>
                                                    <th>Timing</th>
                                                    <th>Meal</th>
                                                    <th>Duration</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                
                                            </tbody>
                                        </table>
                                    </div>
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
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        if (typeof showDropDownPathologyTests === 'function') {
            showDropDownPathologyTests();
        }
        if (typeof showDropDownRadiologyTests === 'function') {
            showDropDownRadiologyTests();
        }
    });

/* appForm:wired */
appForm.bind('#edit_ipd_prescription_form', {
        validate: function () {
            return (($('#e_pathology_tests').val() || []).length || ($('#e_radiology_tests').val() || []).length || ($('#e_procedures').val() || []).length || $('#e_ipd_prescription_medicine_tbl > tbody > tr').length + $('#e_prescription_medicine_tbl > tbody > tr').length) ? true : 'Please Enter Some Data';
        }
    });
</script>
@endpush
