@extends('layouts.app')
@section('title', 'IPD Vital Monitoring')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu', ['menu' => 'pf'])
        </div>
        <div class="card-body">
            <div class="row ipd-patient-vital_monitoring-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Vital Monitoring</div>
                    @if($new)
                        <div class="ipd-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="addIPDVitalMonitoringModal();"><i class="fa fa-plus"></i> New</a></li>
                            </ul>
                        </div>
                    @endif
                </div>	
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>Date</th>
                                    <th>Ward</th>
                                    <th>Added By</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vitals as $row)
                                    <tr id="row_{{ $row->id }}">
                                        <td class="tr-sm-action-buttons center">
                                            @if($edit)
                                                <a href="javascript:void(0)" onclick="editIPDVitalMonitoringModal({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                            @endif
                                            @if($delete)
                                                <a href="javascript:void(0)" onclick="deleteIPDVitalMonitoring({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $row->vital_monitoring_date }}</td>
                                        <td>{{ $row->ward_category }} - {{ $row->bed_name }}</td>
                                        <td>{{ $row->added_by }}</td>
                                        <td>{{ $row->remarks }}</td>
                                    </tr>
                                    <tr id="drow_{{ $row->id }}">
                                        <td></td>
                                        <td colspan="5">
                                            <table class="table table-bordered table-sm table-text-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Time</th>
                                                        <th>BP</th>
                                                        <th>Pulse</th>
                                                        <th>Temp</th>
                                                        <th>R.R</th>
                                                        <th>SPO2</th>
                                                        <th>FBS</th>
                                                        <th>RBS</th>
                                                        <th>Intake</th>
                                                        <th>Output</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($row->details as $mdrow)
                                                        <tr>
                                                            <td>{{ $mdrow->vital_monitoring_time }}</td>
                                                            <td>{{ $mdrow->bp }}</td>
                                                            <td>{{ $mdrow->pulse }}</td>
                                                            <td>{{ $mdrow->temperature }}</td>
                                                            <td>{{ $mdrow->respiration }}</td>
                                                            <td>{{ $mdrow->spo2 }}</td>
                                                            <td>{{ $mdrow->fbs }}</td>
                                                            <td>{{ $mdrow->rbs }}</td>
                                                            <td>{{ $mdrow->intake }}</td>
                                                            <td>{{ $mdrow->output }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bottom-center-btns">
        <a class="btn btn-success btn-rounded" target="_blank" href="{{ route('pdf.ipd.vital-monitoring', ['ipdId' => $ipd->id]) }}">Print</a>
    </div>
</div>

<div class="modal fade" id="addIPDVitalMonitoringModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Vital Monitoring</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_vital_monitoring_form">
                <input type="hidden" id="ipd_id" name="ipd_id" value="{{ $ipd->id }}">
                <input type="hidden" name="bed_id" value="{{ $ipd->bed_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Date <span class="req-star">*</span></label>
                                        <input type="text" name="vital_monitoring_date" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-9">
                                    <div class="form-group local-forms mb-0">
                                        <label>Remarks</label>
                                        <input type="text" name="remarks" class="form-control">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="pmnor" value="0">
                            <div class="row">
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Time <span class="req-star">*</span></label>
                                        <input type="text" id="a_vital_monitoring_time" class="form-control timepicker">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>BP </label>
                                        <input type="text" id="a_bp" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Pulse </label>
                                        <input type="text" id="a_pulse" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Temp </label>
                                        <input type="text" id="a_temperature" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>R.R </label>
                                        <input type="text" id="a_respiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>SPO2 </label>
                                        <input type="text" id="a_spo2" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>FBS </label>
                                        <input type="text" id="a_fbs" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>RBS </label>
                                        <input type="text" id="a_rbs" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Intake </label>
                                        <input type="text" id="a_intake" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Output </label>
                                        <input type="text" id="a_output" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addVMD()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="vital_monitoring_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Time</th>
                                                    <th>BP</th>
                                                    <th>Pulse</th>
                                                    <th>Temp</th>
                                                    <th>R.R.</th>
                                                    <th>SPO2</th>
                                                    <th>FBS</th>
                                                    <th>RBS</th>
                                                    <th>Intake</th>
                                                    <th>Output</th>
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

<div class="modal fade" id="editIPDVitalMonitoringModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Vital Monitoring</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_vital_monitoring_form">
                <input type="hidden" id="ipd_vital_monitoring_id" name="ipd_vital_monitoring_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Date<span class="req-star">*</span></label>
                                        <input type="text" id="vital_monitoring_date" name="vital_monitoring_date" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-9">
                                    <div class="form-group local-forms mb-0">
                                        <label>Remarks</label>
                                        <input type="text" id="remarks" name="remarks" class="form-control">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="dashed2px"><div></div></div>
                        <div class="col-lg-12 mt-20">
                            <input type="hidden" id="epmnor" value="0">
                            <div class="row">
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Time <span class="req-star">*</span></label>
                                        <input type="text" id="e_vital_monitoring_time" class="form-control timepicker">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>BP </label>
                                        <input type="text" id="e_bp" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Pulse </label>
                                        <input type="text" id="e_pulse" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Temp </label>
                                        <input type="text" id="e_temperature" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>R.R </label>
                                        <input type="text" id="e_respiration" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>SPO2 </label>
                                        <input type="text" id="e_spo2" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>FBS <span class="req-star">*</span></label>
                                        <input type="text" id="e_fbs" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <label>RBS </label>
                                        <input type="text" id="e_rbs" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Intake </label>
                                        <input type="text" id="e_intake" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Output </label>
                                        <input type="text" id="e_output" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addEVMD()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_vital_monitoring_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Time</th>
                                                    <th>BP</th>
                                                    <th>Pulse</th>
                                                    <th>Temp</th>
                                                    <th>R.R.</th>
                                                    <th>SPO2</th>
                                                    <th>FBS</th>
                                                    <th>RBS</th>
                                                    <th>Intake</th>
                                                    <th>Output</th>
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
@include('ipd.partials.ajax-helpers')
<script>
var IPD_VITAL_URLS = {
    store:   @json(route('ipd.vital-monitoring.store', $ipd->id)),
    get:     @json(route('ipd.vital-monitoring.get', ['id' => '__ID__'])),
    update:  @json(route('ipd.vital-monitoring.update', ['id' => '__ID__'])),
    destroy: @json(route('ipd.vital-monitoring.destroy', ['id' => '__ID__']))
};

$(document).ready(function() {
    ipdBindForm('#add_ipd_vital_monitoring_form', {
        url: IPD_VITAL_URLS.store,
        requiredRows: '#vital_monitoring_detail_tbl > tbody > tr'
    });
    ipdBindForm('#edit_ipd_vital_monitoring_form', {
        url: function () { return ipdUrl(IPD_VITAL_URLS.update, $('#edit_ipd_vital_monitoring_form #ipd_vital_monitoring_id').val()); },
        method: 'PUT',
        requiredRows: '#e_vital_monitoring_detail_tbl > tbody > tr'
    });

    $('[data-bs-toggle="tooltip"]').tooltip();
    $('#addIPDVitalMonitoringModal, #editIPDVitalMonitoringModal').on('shown.bs.modal', function () {
        if (typeof flatpickr !== 'undefined') {
            $(this).find(".datepicker").flatpickr({
                enableTime: false,
                dateFormat: "d-m-Y"
            });
            $(this).find(".timepicker").flatpickr({
                enableTime: true,
                noCalendar: true,
                dateFormat: "h:i K"
            });
        }
    });
});

function editIPDVitalMonitoringModal(id) {
    $("#editIPDVitalMonitoringModal").modal('show');
    ipdFetch(ipdUrl(IPD_VITAL_URLS.get, id), function (data) {
        $("#edit_ipd_vital_monitoring_form #ipd_vital_monitoring_id").val(data.row.id);
        $("#edit_ipd_vital_monitoring_form #vital_monitoring_date").val(data.row.vital_monitoring_date);
        $("#edit_ipd_vital_monitoring_form #remarks").val(data.row.remarks);
        $("#edit_ipd_vital_monitoring_form #epmnor").val(data.nor);
        $("#e_vital_monitoring_detail_tbl tbody").html(data.table_html);
    });
}

function deleteIPDVitalMonitoring(id) {
    ipdConfirmDelete(ipdUrl(IPD_VITAL_URLS.destroy, id), id);
}
</script>
@endpush
