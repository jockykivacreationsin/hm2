@extends('layouts.app')
@section('title', 'Main Categories')
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
					<div class="ipd-title">Main Categories</div>
				</div>	
				<div class="sm-table mt-10">
					<div class="table-responsive">  
						<table class="data-tbl table table-bordered table-sm table-text-sm">
							<thead>
								<tr>
									<th class="no-sort tr1btn"></th>
									<th>Charge Name</th>
								</tr>
							</thead>
							<tbody>
								@foreach($charges as $charge)
								<tr id="row_{{ $charge->id }}">
									<td class="tr-sm-action-buttons center"><a href="{{ route('master.biochemistry-sub-tests', ['id' => $charge->id]) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="View"><i class="fas fa-list"></i></a></td>
									<td>{{ $charge->charge_name }}</td>
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
