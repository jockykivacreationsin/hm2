@extends('layouts.app')
@section('title', 'Charge Departments')
@section('content')
@if(!($canView ?? false))
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
					<div class="ipd-title">Charge Departments</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table id="charge_departments_tbl" class="data-tbl table table-bordered table-sm table-text-sm sortable-tbl" db_table="charge_departments" data-sort-url="{{ route('master.sort-order.update', 'charge_departments') }}">
							<thead>
								<tr>
									<th class="no-sort tr1btn"></th>
									<th>Department Name</th>
								</tr>
							</thead>
							<tbody>
								@foreach($departments as $dept)
								<tr tr_id="{{ $dept->id }}">
									<td class="tr-sm-action-buttons center">
										@if($canListCategories ?? false)
										<a href="{{ route('master.charge-categories') }}?id={{ $dept->id }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $dept->department_name }}'s Charge Types"><i class="fas fa-list"></i></a>
										@endif
									</td>
									<td>{{ $dept->department_name }}</td>
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
@endif
@endsection
