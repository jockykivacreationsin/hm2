@extends('layouts.app')
@section('title', 'Periodic Medicine Patients' . (isset($disease) && $disease->disease_name ? ' — ' . e($disease->disease_name) : ''))
@section('content')

@if(!$canAccess)
<div class="themebody-wrap">
    <div class="no-access">
        <i class="fa-duotone fa-shield-halved"></i>
        <p>Access Denied</p>
    </div>
</div>
@else
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Periodic Medicine Patients</div>
                    <div class="ipd-buttons">
                        <ul>
                            <li><a href="{{ route('master.diseases') }}"><i class="fas fa-arrow-left"></i> Back</a></li>
                        </ul>
                    </div>
                </div>
                <div class="sm-table mt-10">
                    <div class="table-responsive">  
                        <table class="data-tbl table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort tr2btns"></th>
                                    <th>ID</th>
                                    <th>Days Left</th>
                                    <th>Name</th>
                                    <th>Mobile Number</th>
                                    <th>Address</th>
                                    <th>Age</th>
                                    <th>Diseases</th>
                                    <th>Last Medicine Date</th>
                                    <th>Next Medicine Date</th>
                                    <th>Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($patientRecords as $r)
                                <tr id="row_{{ $r['ppm_id'] }}">
                                    <td class="tr-sm-action-buttons center">
                                        <a href="javascript:void(0)" onclick="issueMedicineDetailModal({{ $r['ppm_id'] }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Medicine Detail"><i class="far fa-pills"></i></a>
                                        <a href="{{ route('periodic-medicine.issued', $r['patient_id']) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="All Issued Medicines"><i class="fas fa-list"></i></a>
                                    </td>
                                    <td>{{ $r['patient_id'] }}</td>
                                    <td class="{{ $r['days_class'] }}">{{ $r['due_days'] }}</td>
                                    <td>{{ $r['patient_name'] }}</td>
                                    <td>{{ $r['mobile_number'] }}</td>
                                    <td>{{ $r['address'] }}</td>
                                    <td>{{ $r['age'] }}</td>
                                    <td>{{ $r['disease_names'] }}</td>
                                    <td>{{ $r['medicine_date_time'] }}</td>
                                    <td>{{ $r['next_medicine_date'] }}</td>
                                    <td>{{ $r['delivery'] }}</td>
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

<div class="modal fade" id="issueMedicineDetailModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail</h5><a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 col-sm-12">
                        <div class="issue-medicine-detail">
                            <table id="issue-medicine-detail-tbl" class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Dose</th>
                                        <th>Interval</th>
                                        <th>Remarks</th>
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
</div>

@push('scripts')
<script>
function issueMedicineDetailModal(id)
{
    $("#issueMedicineDetailModal").modal('show');
    $.ajax({ 
        type: 'GET',
        url: '{{ route('periodic-medicine.issue-detail') }}',
        data: {
            id: id
        },                       
        dataType: 'json',                   
        success: function(data)         
        {
            $("#issue-medicine-detail-tbl tbody").html(data.medicine_detail);
        } 
    });
}

$(document).ready(function() {
    if (typeof $.fn.tooltip !== 'undefined') {
        $('[data-bs-toggle="tooltip"]').tooltip();
    }
});
</script>
@endpush
@endif
@endsection
