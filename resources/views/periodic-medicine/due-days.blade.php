@extends('layouts.app')
@section('title', 'Patients Medicine Due Days')

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
					<div class="ipd-title">Patients Medicine Due Days</div>
					<div class="tbl-filter-box">
						<div class="local-forms">
							<label>Due Days <span class="req-star">*</span></label>
							<select class="form-control" id="s_due_days">
								<option value="10">10</option>
								<option value="5">5</option>
								<option value="0">Overdue</option>
							</select>
						</div>
						<div class="tbl-top-buttons">
							<ul>
								<li><a id="search-pmdd-btn" href="javascript:void(0)"><i class="fa fa-search"></i></a></li>
							</ul>
						</div>
					</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table id="patients_medicine_due_days_tbl" class="table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr1btn"></th>
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
