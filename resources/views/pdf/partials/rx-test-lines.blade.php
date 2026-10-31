{{-- Ordered tests of one prescription (charge ids -> names). Needs: $rx, $chargeNames (id => name). --}}
@foreach(['pathology_tests' => 'Pathology Tests', 'radiology_tests' => 'Radiology Tests', 'procedures' => 'Procedures'] as $field => $label)
@php
	$names = collect(explode(',', (string) $rx->$field))
		->map(fn ($id) => $chargeNames[(int) trim($id)] ?? null)->filter()->implode(', ');
@endphp
@if($names !== '')
<p class="rpt-line"><b>{{ $label }}:</b> {{ $names }}</p>
@endif
@endforeach
