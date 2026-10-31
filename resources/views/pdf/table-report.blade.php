{{-- Generic "print table" PDF: a DataTable's thead/tbody/tfoot posted by the browser. Standard layout + report-standard.css (docs/PDF_REPORTS.md).
     Vars: title, thead / tbody / tfoot (sanitised table-row HTML, see ReportController::createTablePdf), settings. --}}
@extends('pdf.layouts.standard')

@section('title', $title)
@section('report-title', $title . ' - ' . date('d-m-Y h:i A'))

@section('content')
<table class="rpt-table rpt-table-sm">
	@if($thead !== '')
	<thead>{!! $thead !!}</thead>
	@else
	<thead><tr><th>Title</th><th>Date Time</th><th class="num">Amount</th></tr></thead>
	@endif
	<tbody>{!! $tbody !!}</tbody>
	@if($tfoot !== '')
	<tfoot>{!! $tfoot !!}</tfoot>
	@endif
</table>
@endsection
