{{-- "View Detail" modal rows (legacy viewtestdetail[opd|dcd]); $groups = LabResultService::savedResults() grouped by service-detail id. --}}
<tr><th>Lab Test</th><th>Result</th><th>Reference Range</th></tr>
@foreach($groups as $rows)
<tr><th>{{ $rows->first()->charge_name }}</th></tr>
@foreach($rows as $r)
<tr><td>{{ $r->sub_test_name }}</td><td class="{{ $r->highlighted === 'Yes' ? 'bold' : '' }}">{{ $r->test_value }} {{ $r->unit_name }}</td><td>{{ $r->reference_range }}</td></tr>
@endforeach
@endforeach
