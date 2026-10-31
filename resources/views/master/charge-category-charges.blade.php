@extends('layouts.app')

@section('title', $charge_category !== '' ? 'Charges: ' . $charge_category : 'Charges')

@section('content')
@if($canView)
@if($charge_category_id > 0 && $charge_category !== '')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Charges: {{ $charge_category }}</div>
                    @if($canNew)
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addCategoryChargeModal()"><i class="fa fa-plus"></i> New</a><a href="{{ route('master.charge-categories') }}?id={{ $charge_department_id }}"><i class="fas fa-chevron-left"></i> Back</a></li>
                        </ul>
                    </div>
                    @else
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="{{ route('master.charge-categories') }}?id={{ $charge_department_id }}"><i class="fas fa-chevron-left"></i> Back</a></li>
                        </ul>
                    </div>
                    @endif
                </div>  
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm" data-delete-url-template="{{ route('master.charges.destroy', ':id') }}" data-amount-url-template="{{ route('master.charges.amount.update', ':id') }}">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>Department</th>
                                    <th>Charge Name</th>
                                    <th>Charge Category</th>
                                    <th style="width: 120px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($charges as $row)
                                    @php
                                        $charge_id = (int) $row->id;
                                        $department_name = $row->department_name;
                                        $charge_name = $row->charge_name;
                                        $category_name = $row->charge_category;
                                    @endphp
                                    <tr id="row_{{ $charge_id }}">
                                        <td class="tr-sm-action-buttons center">
                                            @if($canDelete)
                                            <a href="javascript:void(0)" onclick="deleteCharge({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>
                                            @endif
                                            @if($canEdit)
                                            <a href="javascript:void(0)" onclick="editCategoryChargeModal({{ $charge_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $department_name }}</td>
                                        <td>{{ $charge_name }}</td>
                                        <td>{{ $category_name }}</td>
                                        <td>
                                            <table class="table table-sm table-bordered itbl">
                                                @foreach($patient_categories as $pc)
                                                    @php
                                                        $pcid = (int) $pc['id'];
                                                        $pcname = $pc['name'];
                                                        $amount = isset($all_charges_map[$charge_id][$pcid]) ? $all_charges_map[$charge_id][$pcid] : 0;
                                                    @endphp
                                                    <tr>
                                                        <td style="width:60px;">{{ $pcname }}</td>
                                                        <td style="width:60px;">
                                                            <input id="charge_amount_{{ $charge_id }}_{{ $pcid }}" onchange="updateChargeAmount({{ $charge_id }}, {{ $pcid }}, this.value)" style="width:55px;" type="number" value="{{ $amount }}">
                                                        </td>
                                                    </tr>
                                                @endforeach
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
</div>

<div class="modal fade" id="addCategoryChargeModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Charge</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_category_charge_form" data-action="{{ route('master.charge-category-charges.store', $charge_category_id) }}" data-method="POST">
                <input type="hidden" name="charge_category_id" value="{{ $charge_category_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Name <span class="req-star">*</span></label>
                                <input type="text" name="charge_name" class="form-control" required>
                            </div>
                        </div>
                        @foreach($patient_categories as $pc)
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Amount ({{ $pc['name'] }})<span class="req-star">*</span></label>
                                <input type="hidden" name="patient_category_id[]" value="{{ $pc['id'] }}">
                                <input type="text" name="amount[]" class="form-control" required>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary ml-10" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryChargeModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Charge</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_category_charge_form" data-action-template="{{ route('master.charges.update', ':id') }}" data-method="PUT" data-show-url-template="{{ route('master.charges.show', ':id') }}">
                <input type="hidden" id="charge_id" name="charge_id" value="">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Name <span class="req-star">*</span></label>
                                <input type="text" id="charge_name" name="charge_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Department <span class="req-star">*</span></label>
                                <select class="form-control" id="e_charge_department_id" name="charge_department_id" data-lookup-url="{{ route('charges.lookup.departments') }}" required>
                                    <option></option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Charge Category <span class="req-star">*</span></label>
                                <select class="form-control" id="e_charge_category_id" name="charge_category_id" data-lookup-url="{{ route('charges.lookup.categories') }}" data-lookup-params="charge_department_id=#e_charge_department_id" required>
                                    <option></option>
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
@else
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function editCategoryChargeModal(id) {
    var $form = $('#edit_category_charge_form');
    appForm.edit('#edit_category_charge_form', '#editCategoryChargeModal', appForm.fill($form.data('showUrlTemplate'), id), id, function (data) {
        $form.find('#charge_id').val(id);
        $form.find('#charge_name').val(data.charge_name);
        appForm.setOption('#e_charge_department_id', data.charge_department_id, data.department_name);
        appForm.setOption('#e_charge_category_id', data.charge_category_id, data.charge_category);
    });
}

function deleteCharge(id) {
    appForm.destroy(appForm.fill($('table[data-delete-url-template]').data('deleteUrlTemplate'), id), id, { inUseText: 'In use unable to delete' });
}

// Inline edit of the amount one patient category pays for a charge.
function updateChargeAmount(chargeId, patientCategoryId, amount) {
    if (amount === '') {
        Swal.fire({ icon: 'warning', title: 'Amount Required!', text: 'Please enter amount', confirmButtonText: 'OK' }).then(function () {
            $('#charge_amount_' + chargeId + '_' + patientCategoryId).focus();
        });
        return;
    }
    $.ajax({
        type: 'POST',
        url: appForm.fill($('table[data-amount-url-template]').data('amountUrlTemplate'), chargeId),
        data: { _method: 'PUT', patient_category_id: patientCategoryId, amount: amount },
        dataType: 'json',
        error: function (xhr) { appForm.toast('error', 'Error!', appForm.errorMessage(xhr, 'Failed to update the amount.'), 2500); }
    });
}
</script>
@endpush
