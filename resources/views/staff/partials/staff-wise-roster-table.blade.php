{{-- Inner HTML of #staff_wise_roster_tbl (roster-staff-wise page). Same columns as the printed roster. --}}
<thead>
	<tr>
		<th>Staff Name</th>
		<th>Days</th>
		<th style="width: 80px">Total</th>
		<th>Signature</th>
	</tr>
</thead>
<tbody>
	@forelse($rows as $row)
		<tr>
			<th class="aleft">{{ $row['staff_name'] }}</th>
			<td>{{ implode(', ', $row['days']) }}</td>
			<td class="center">{{ count($row['days']) }}</td>
			<td></td>
		</tr>
	@empty
		<tr><td colspan="4" class="center">No roster found</td></tr>
	@endforelse
</tbody>
