{{-- Patient block of the IPD clinical charts. Vars: $p (chart patient row), optional $consultant. --}}
<div class="rpt-info"><table>
	<tr>
		<td class="label">Patient Name:</td><td>{{ $p->patient_name }}</td>
		<td class="label">UHID:</td><td>{{ \App\Support\Uhid::format($p->patient_id) }}</td>
	</tr>
	<tr>
		<td class="label">Age/Gender:</td><td>{{ (int) $p->age }} Years / {{ $p->gender }}</td>
		<td class="label">IPD No:</td><td>{{ (int) $p->id }}/bmh/{{ $p->iyear }}</td>
	</tr>
	@if(!empty($consultant))
	<tr><td class="label">Consultant Doctor:</td><td colspan="3">{{ $consultant }}</td></tr>
	@endif
</table></div>
