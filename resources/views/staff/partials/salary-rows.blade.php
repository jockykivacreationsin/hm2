@foreach($rows as $r)
<tr id='row_{{ $r->staff_id }}'>
	<td>{{ $r->staff_name }}</td>
	<td>{{ $r->designation }}</td>
	<td>{{ $r->current_salary }}</td>
	<td>{{ $r->working_days }}</td>
	<td>{{ $r->absent_days }}</td>
	<td>{{ $r->total_working_hours }}</td>
	<td>{{ $r->total_salary }}</td>
	<td id='isp_{{ $r->staff_id }}'>
		@if($r->paid === 'Yes')
			<a href='javascript:void(0)' onclick='viewSalaryPaymentDetail({{ (int) $r->salary_payment_id }});'>View</a>
		@else
			<a href='javascript:void(0)' onclick='staffSalaryPaymentModal({{ (int) $r->staff_id }}, {{ (int) $r->total_salary }});'><i class='fas fa-plus'></i></a>
		@endif
	</td>
</tr>
@endforeach
