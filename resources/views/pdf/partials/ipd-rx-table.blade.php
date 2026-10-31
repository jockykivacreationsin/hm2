{{-- Medicine table of the IPD prescription PDF (standard .rpt-table). Vars: $rows (collection), $timingKey ('timing_names' | 'timing_display') --}}
<table class="rpt-table">
	<tr><th style="width:35%;">Medicine</th><th style="width:15%;">Dose</th><th style="width:20%;">Timing</th><th style="width:15%;">Duration</th><th style="width:15%;">Meal</th></tr>
	@foreach($rows as $m)
	@php
		$timing = trim((string) ($m->{$timingKey} ?? ''));
		$duration = $m->duration_days ?? null;
	@endphp
	<tr>
		<td>{{ $m->medicine_name }}</td>
		<td>{{ $m->dose_quantity ?? '1' }}</td>
		<td>{{ $timing === '' ? '-' : $m->{$timingKey} }}</td>
		<td>{{ $duration === null ? '-' : $duration . ' days' }}</td>
		<td>{{ $m->meal_relation ?? '-' }}</td>
	</tr>
	@endforeach
</table>
