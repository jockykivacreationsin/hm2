@extends('layouts.app')
@section('title', 'IPD Intake Output')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu', ['menu' => 'pf'])
        </div>
        <div class="card-body">
            <div class="row ipd-patient-intake_output-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Intake Output</div>
                    @if($new)
                        <div class="ipd-buttons">
                            <ul>
                                <li><a href="javascript:void(0)" onclick="addIPDIntakeOutputModal();"><i class="fa fa-plus"></i> New</a></li>
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
                                @foreach($records as $row)
                                    <tr id="row_{{ $row->id }}">
                                        <td class="tr-sm-action-buttons center">
                                            @if($edit)
                                                <a href="javascript:void(0)" onclick="editIPDIntakeOutputModal({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                            @endif
                                            @if($delete)
                                                <a href="javascript:void(0)" onclick="deleteIPDIntakeOutput({{ $row->id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $row->intake_output_date }}</td>
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
                                                        <th>IV Fluids</th>
                                                        <th>Oral</th>
                                                        <th>Urine</th>
                                                        <th>Stool</th>
                                                        <th>Vomiting</th>
                                                        <th>RT/ASP</th>
                                                        <th>Drain</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($row->details as $mdrow)
                                                        <tr>
                                                            <td>{{ $mdrow->intake_output_time }}</td>
                                                            <td>{{ $mdrow->iv_fluids }}</td>
                                                            <td>{{ $mdrow->oral }}</td>
                                                            <td>{{ $mdrow->urine }}</td>
                                                            <td>{{ $mdrow->stool }}</td>
                                                            <td>{{ $mdrow->vomiting }}</td>
                                                            <td>{{ $mdrow->rt_asp }}</td>
                                                            <td>{{ $mdrow->drain }}</td>
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
        <a class="btn btn-success btn-rounded" target="_blank" href="{{ route('pdf.ipd.intake-output', ['ipdId' => $ipd->id]) }}">Print</a>
    </div>
</div>

<div class="modal fade" id="addIPDIntakeOutputModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Intake Output</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_ipd_intake_output_form">
                <input type="hidden" id="ipd_id" name="ipd_id" value="{{ $ipd->id }}">
                <input type="hidden" name="bed_id" value="{{ $ipd->bed_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Date <span class="req-star">*</span></label>
                                        <input type="text" name="intake_output_date" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-8">
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
                                        <input type="text" id="a_intake_output_time" class="form-control timepicker">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>IV Fluids </label>
                                        <input type="text" id="a_iv_fluids" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Oral </label>
                                        <input type="text" id="a_oral" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Urine </label>
                                        <input type="text" id="a_urine" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Stool </label>
                                        <input type="text" id="a_stool" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Vomiting </label>
                                        <input type="text" id="a_vomiting" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>RT/ASP </label>
                                        <input type="text" id="a_rt_asp" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Drain </label>
                                        <input type="text" id="a_drain" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addIntakeOutputDetail()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="intake_output_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Time</th>
                                                    <th>IV Fluids</th>
                                                    <th>Oral</th>
                                                    <th>Urine</th>
                                                    <th>Stool</th>
                                                    <th>Vomiting</th>
                                                    <th>RT/ASP</th>
                                                    <th>Drain</th>
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

<div class="modal fade" id="editIPDIntakeOutputModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Intake Output</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_ipd_intake_output_form">
                <input type="hidden" id="ipd_intake_output_id" name="ipd_intake_output_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="row">
                                <div class="col-12 col-sm-4">
                                    <div class="form-group local-forms">
                                        <label>Date<span class="req-star">*</span></label>
                                        <input type="text" id="intake_output_date" name="intake_output_date" class="form-control datepicker" value="{{ date('d-m-Y') }}" required>
                                    </div>
                                </div>

                                <div class="col-12 col-sm-8">
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
                                        <input type="text" id="e_intake_output_time" class="form-control timepicker">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>IV Fluids </label>
                                        <input type="text" id="e_iv_fluids" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-5">
                                    <div class="form-group local-forms">
                                        <label>Oral </label>
                                        <input type="text" id="e_oral" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-3">
                                    <div class="form-group local-forms">
                                        <label>Urine </label>
                                        <input type="text" id="e_urine" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Stool </label>
                                        <input type="text" id="e_stool" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Vomiting </label>
                                        <input type="text" id="e_vomiting" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>RT/ASP </label>
                                        <input type="text" id="e_rt_asp" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-2">
                                    <div class="form-group local-forms">
                                        <label>Drain </label>
                                        <input type="text" id="e_drain" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-1">
                                    <div class="form-group local-forms">
                                        <button type="button" class="ssbtn" onclick="addEIntakeOutputDetail()"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 col-sm-12">
                                    <div class="table-responsive">  
                                        <table id="e_intake_output_detail_tbl" class="table table-bordered table-sm table-text-sm">
                                            <thead>
                                                <tr>
                                                    <th class="tr1btn"></th>
                                                    <th>Time</th>
                                                    <th>IV Fluids</th>
                                                    <th>Oral</th>
                                                    <th>Urine</th>
                                                    <th>Stool</th>
                                                    <th>Vomiting</th>
                                                    <th>RT/ASP</th>
                                                    <th>Drain</th>
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
var IPD_INTAKE_OUTPUT_URLS = {
    store:   @json(route('ipd.intake-output.store', $ipd->id)),
    get:     @json(route('ipd.intake-output.get', ['id' => '__ID__'])),
    update:  @json(route('ipd.intake-output.update', ['id' => '__ID__'])),
    destroy: @json(route('ipd.intake-output.destroy', ['id' => '__ID__']))
};

$(document).ready(function() {
    ipdBindForm('#add_ipd_intake_output_form', {
        url: IPD_INTAKE_OUTPUT_URLS.store,
        requiredRows: '#intake_output_detail_tbl > tbody > tr'
    });
    ipdBindForm('#edit_ipd_intake_output_form', {
        url: function () { return ipdUrl(IPD_INTAKE_OUTPUT_URLS.update, $('#edit_ipd_intake_output_form #ipd_intake_output_id').val()); },
        method: 'PUT',
        requiredRows: '#e_intake_output_detail_tbl > tbody > tr'
    });

    $('[data-bs-toggle="tooltip"]').tooltip();
    $('#addIPDIntakeOutputModal, #editIPDIntakeOutputModal').on('shown.bs.modal', function () {
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

function editIPDIntakeOutputModal(id) {
    $("#editIPDIntakeOutputModal").modal('show');
    ipdFetch(ipdUrl(IPD_INTAKE_OUTPUT_URLS.get, id), function (data) {
        $("#edit_ipd_intake_output_form #ipd_intake_output_id").val(data.row.id);
        $("#edit_ipd_intake_output_form #intake_output_date").val(data.row.intake_output_date);
        $("#edit_ipd_intake_output_form #remarks").val(data.row.remarks);
        $("#edit_ipd_intake_output_form #epmnor").val(data.nor);
        $("#e_intake_output_detail_tbl tbody").html(data.table_html);
    });
}

function deleteIPDIntakeOutput(id) {
    ipdConfirmDelete(ipdUrl(IPD_INTAKE_OUTPUT_URLS.destroy, id), id);
}
</script>
@endpush
