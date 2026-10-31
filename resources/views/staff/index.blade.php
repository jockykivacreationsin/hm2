@extends('layouts.app')
@section('title', 'Staff Members')
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
					<div class="ipd-title">Staff Members</div>
					@if($canNew)
					<div class="ipd-buttons">
						<ul>
							<li><a href="javascript:void(0)" onclick="addStaffMemberModal()"><i class="fa fa-plus"></i> New</a></li>
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
									<th>Name</th>
									<th>Designation</th>
									<th>Department</th>
									<th>Mobile Number</th>
								</tr>
							</thead>
							<tbody>
								@foreach($members as $row)
								<tr id="row_{{ $row->id }}">
									<td class="tr-sm-action-buttons center">@if($canDelete)<a href="javascript:void(0)" onclick="deleteStaffMember({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete"><i class="fas fa-times"></i></a>@endif @if($canEdit)<a href="javascript:void(0)" onclick="editStaffMemberModal({{ (int) $row->id }});" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"><i class="fas fa-pencil"></i></a>@endif </td>
									<td>{{ $row->staff_name }}</td>
									<td>{{ $row->designation }}</td>
									<td>{{ $row->department }}</td>
									<td>{{ $row->mobile_number }}</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
				<!-- PAGINATION CONTROLS -->
				@if($totalPages > 1)
				<div class="pagination-wrapper mt-20">
					<div class="pagination-info">
						Showing {{ ($page - 1) * $perPage + 1 }} to {{ min($page * $perPage, $total) }} of {{ $total }} staff members
					</div>
					<nav>
						<ul class="pagination">
							@if($page > 1)
							<li class="page-item">
								<a class="page-link" href="{{ route('staff.index', ['page' => 1]) }}">First</a>
							</li>
							<li class="page-item">
								<a class="page-link" href="{{ route('staff.index', ['page' => $page - 1]) }}">Previous</a>
							</li>
							@endif

							@for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
							<li class="page-item {{ $i == $page ? 'active' : '' }}">
								<a class="page-link" href="{{ route('staff.index', ['page' => $i]) }}">{{ $i }}</a>
							</li>
							@endfor

							@if($page < $totalPages)
							<li class="page-item">
								<a class="page-link" href="{{ route('staff.index', ['page' => $page + 1]) }}">Next</a>
							</li>
							<li class="page-item">
								<a class="page-link" href="{{ route('staff.index', ['page' => $totalPages]) }}">Last</a>
							</li>
							@endif
						</ul>
					</nav>
				</div>
				@endif
			</div>
		</div>
	</div>
</div>

@include('staff.partials.member-modals')
@endif
@endsection
