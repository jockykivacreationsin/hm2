@extends('layouts.app')

@section('title', $charge_department !== '' ? 'Charge Category: ' . $charge_department . ' Department' : 'Charge Categories')

@section('content')
@if($canView)
@if($charge_department_id > 0 && $charge_department !== '')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Charge Category: {{ $charge_department }} Department</div>
                    @if($canNew)
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="javascript:void(0)" onclick="addChargeCategoryModal()"><i class="fa fa-plus"></i> New</a><a href="{{ route('charges.departments') }}"><i class="fas fa-chevron-left"></i> Back</a></li>
                        </ul>
                    </div>
                    @else
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="{{ route('charges.departments') }}"><i class="fas fa-chevron-left"></i> Back</a></li>
                        </ul>
                    </div>
                    @endif
                </div>  
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>Charge Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $row)
                                    @php
                                        $category_id = (int) $row->id;
                                        $charge_category_name = $row->charge_category;
                                        $charge_category_title = $row->charge_category . "'s Charges";
                                    @endphp
                                    <tr>
                                        <td class="tr-sm-action-buttons center">
                                            @if($canEdit)
                                            <a href="javascript:void(0)" onclick="editChargeCategoryModal({{ $category_id }})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>
                                            @endif
                                            @if($canCharges)
                                            <a href="{{ route('master.charge-category-charges', $category_id) }}?cd_id={{ $charge_department_id }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $charge_category_title }}"><i class="fas fa-list"></i></a>
                                            @endif
                                        </td>
                                        <td>{{ $charge_category_name }}</td>
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

<div class="modal fade" id="addChargeCategoryModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Charge Category</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="add_charge_category_form" data-action="{{ route('master.charge-categories.store') }}" data-method="POST">
                <input type="hidden" name="charge_department_id" value="{{ $charge_department_id }}">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-sm-12">
                            <div class="form-group local-forms">
                                <label>Category Name <span class="req-star">*</span></label>
                                <input type="text" name="charge_category" class="form-control" required>
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

<div class="modal fade" id="editChargeCategoryModal">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Charge Category</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <form id="edit_charge_category_form" data-action-template="{{ route('master.charge-categories.update', ':id') }}" data-method="PUT" data-show-url-template="{{ route('master.charge-categories.show', ':id') }}">
                <input type="hidden" id="charge_category_id" name="charge_category_id" value="">
                <div class="modal-body">
                    <div class="row">
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
                                <label>Category Name <span class="req-star">*</span></label>
                                <input type="text" id="charge_category" name="charge_category" class="form-control" required>
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
function editChargeCategoryModal(id) {
    var $form = $('#edit_charge_category_form');
    appForm.edit('#edit_charge_category_form', '#editChargeCategoryModal', appForm.fill($form.data('showUrlTemplate'), id), id, function (data) {
        $form.find('#charge_category_id').val(id);
        $form.find('#charge_category').val(data.charge_category);
        appForm.setOption('#e_charge_department_id', data.charge_department_id, data.department_name);
    });
}
</script>
@endpush
