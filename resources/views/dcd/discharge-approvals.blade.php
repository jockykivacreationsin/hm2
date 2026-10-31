@extends('layouts.app')
@section('title', 'Discharge Approvals')
@section('content')
@if($canView)
<div class="themebody-wrap ipd-wrap">
	<div class="card-ipd">
		<div class="card-body">
			<div class="row ipd-patient-consultant-row">
				<div class="ipd-title-with-buttons row-bottom-border">
					<div class="ipd-title">Discharge Approvals</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">
						<table class="data-tbl table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr1btn"></th>
									<th>Patient Name</th>
									<th>OPD No</th>
									<th>DCD No</th>
									<th>Admission Date</th>
								</tr>
							</thead>
							<tbody>
								@foreach($records as $row)
									@php
										$discharge_id = (int) $row->id;
										$patient_name = $row->patient_name;
										$opd_id = $row->opd_id;
										$dcd_id = $row->dcd_id;
										$dcd_date_time = $row->dcd_date_time_formatted;
									@endphp
									<tr tr_id="{{ $discharge_id }}">
										<td class="tr-sm-action-buttons center">
											@if($canApprovalView)
												<a href="{{ route('dcd.view-discharge-approval', $discharge_id) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="View"><i class="fas fa-list"></i></a>
											@endif
										</td>
										<td>{{ $patient_name }}</td>
										<td>{{ $opd_id }}</td>
										<td>{{ $dcd_id }}</td>
										<td>{{ $dcd_date_time }}</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					
					<!-- OPTIMIZED: Pagination Controls -->
					@if ($totalPages > 1)
					<div class="pagination-wrap mt-20">
						<nav aria-label="Page navigation">
							<ul class="pagination justify-content-center">
								@if ($page > 1)
									<li class="page-item">
										<a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page - 1]) }}">Previous</a>
									</li>
								@endif
								
								@php
								$start_page = max(1, $page - 5);
								$end_page = min($totalPages, $page + 4);
								@endphp

								@if ($start_page > 1)
									<li class="page-item"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => 1]) }}">1</a></li>
									@if ($start_page > 2)
										<li class="page-item disabled"><span class="page-link">...</span></li>
									@endif
								@endif
								
								@for ($i = $start_page; $i <= $end_page; $i++)
									<li class="page-item {{ ($i == $page) ? 'active' : '' }}">
										<a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
									</li>
								@endfor
								
								@if ($end_page < $totalPages)
									@if ($end_page < $totalPages - 1)
										<li class="page-item disabled"><span class="page-link">...</span></li>
									@endif
									<li class="page-item"><a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $totalPages]) }}">{{ $totalPages }}</a></li>
								@endif
								
								@if ($page < $totalPages)
									<li class="page-item">
										<a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Next</a>
									</li>
								@endif
							</ul>
							<div class="text-center mt-2">
								<small>Page {{ $page }} of {{ $totalPages }} (Total Records: {{ $totalRecords }})</small>
							</div>
						</nav>
					</div>
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
@else
<div class="themebody-wrap">
  <div class="no-access">
    <i class="fa-duotone fa-shield-halved"></i>
    <p>Access Denied</p>
  </div>
</div>
@endif
@endsection
