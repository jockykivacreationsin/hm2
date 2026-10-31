{{-- Staff-wise monthly roster (landscape, one column per day). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: staff, roster, start, month, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Staff Roster - ' . $start->format('F Y'))
@section('report-title', 'STAFF ROSTER - ' . strtoupper($start->format('F Y')))

@section('content')
<table class="rpt-table rpt-table-sm">
	<tr>
		<th style="width:130px;">Staff</th>
		@for($d = 1; $d <= $start->daysInMonth; $d++)<th class="center" style="padding:3px 1px;">{{ $d }}</th>@endfor
	</tr>
	@forelse($staff as $s)
	@php $staffRoster = ($roster->get($s->id) ?? collect())->keyBy(fn ($r) => \Carbon\Carbon::parse($r->roster_date)->day); @endphp
	<tr>
		<td>{{ $s->staff_name }}</td>
		@for($d = 1; $d <= $start->daysInMonth; $d++)
		@php $entry = $staffRoster->get($d); @endphp
		<td class="center {{ $entry ? 'rpt-on' : '' }}" style="padding:3px 1px;">{{ $entry ? ($entry->shift ? substr($entry->shift, 0, 1) : '✓') : '' }}</td>
		@endfor
	</tr>
	@empty
	<tr><td colspan="{{ $start->daysInMonth + 1 }}" class="center">No staff found.</td></tr>
	@endforelse
</table>
@endsection
