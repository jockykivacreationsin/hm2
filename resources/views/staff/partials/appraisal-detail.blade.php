<table class='table table-sm table-bordered'>
	<tr><th>Appraisal Date</th><th>Salary</th><th>New Salary</th><th>Remarks</th></tr>
	@foreach($rows as $row)
		<tr>
			<td>{{ date('d-m-Y', strtotime($row->appraisal_date)) }}</td>
			<td>{{ $row->current_salary }}</td>
			<td>{{ $row->new_salary }}</td>
			<td>{{ $row->remarks }}</td>
		</tr>
	@endforeach
</table>
