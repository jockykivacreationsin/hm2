@extends('layouts.app')
@section('title', 'Periodic Medicine Patients')

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
								@foreach($records as $row)
								<tr id="row_{{ $row['record_id'] }}">
									<td class="tr-sm-action-buttons center">
										<a href="javascript:void(0)" onclick="issueMedicineDetailModal({{ $row['record_id'] }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Medicine Detail"><i class="far fa-pills"></i></a>
										<a href="{{ route('periodic-medicine.issued', $row['patient_id']) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="All Issued Medicines"><i class="fas fa-list"></i></a>
									</td>
									<td>{{ $row['patient_id'] }}</td>
									<td class="{{ $row['days_class'] }}">{{ $row['due_days'] }}</td>
									<td>{{ $row['patient_name'] }}</td>
									<td>{{ $row['mobile_number'] }}</td>
									<td>{{ $row['address'] }}</td>
									<td>{{ $row['age'] }}</td>
									<td>{{ $row['disease_names'] }}</td>
									<td>{{ $row['medicine_date_time'] }}</td>
									<td>{{ $row['next_medicine_date'] }}</td>
									<td>{{ $row['delivery'] }}</td>
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
@endif
@endsection
