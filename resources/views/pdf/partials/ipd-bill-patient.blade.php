{{-- Patient block of the IPD discharge bills. Vars: $row (ipds + patients row), $hidePaid (non-category-1 patient), $dateLabel/$dateValue. --}}
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $row->patient_name }}</td>
		<td class="label">IPD No:</td><td>{{ $row->id }}/bmh/{{ $row->iyear }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ $row->age }} Years / {{ $row->gender }}</td>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($row->patient_id) }}</td>
	</tr>
	<tr>
		<td class="label">Contact:</td><td>{{ $row->mobile_number }}</td>
		<td class="label">{{ $dateLabel }}</td><td>{{ $dateValue }}</td>
	</tr>
	@if(!empty($row->address))
	<tr><td class="label">Address:</td><td colspan="3">{{ $row->address }}</td></tr>
	@endif
	@if($hidePaid)
	<tr>
		<td class="label">ESM Name:</td><td>{{ $row->esm_name }}</td>
		<td class="label">Card Number:</td><td>{{ $row->card_number }}</td>
	</tr>
	<tr>
		<td class="label">Service Number:</td><td>{{ $row->service_number }}</td>
		<td class="label">Claim ID:</td><td>{{ $row->claim_id }}</td>
	</tr>
	@endif
</table></div>
