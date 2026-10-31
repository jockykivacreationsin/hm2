{{--
  Standard PDF report layout. Render with LegacyReportRenderer::standard() so report-standard.css is applied.
  Child view provides: @section('title') (browser/PDF title) and @section('content').
  Optional: @section('report-title') prints the underlined centred report title under the header.
  Optional: @section('signature') replaces the default signature (logged-in user) e.g. with the treating doctor.
  Variables read: $settings (header), $sign_name / $sign_designation (signature, optional),
  $hideSignature (true = print no signature block at all, e.g. the blank OPD card slip).
  See docs/PDF_REPORTS.md.
--}}
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>@yield('title', 'Report')</title>
</head>
<body>
{{-- Page footer on every page (mPDF htmlpagefooter; the renderer reserves the bottom margin for it). --}}
<htmlpagefooter name="rptfooter">
	<div style="text-align:center; font-size:10px; color:#666;">Page {PAGENO} of {nbpg}</div>
</htmlpagefooter>
<sethtmlpagefooter name="rptfooter" value="on" />
@include('pdf.partials.report-header')

@hasSection('report-title')
<div class="rpt-title">@yield('report-title')</div>
@endif

@yield('content')

@if(empty($hideSignature))
	@hasSection('signature')
	<div class="rpt-signature">@yield('signature')</div>
	@elseif(!empty($sign_name) || !empty($sign_designation))
	<div class="rpt-signature">
		@if(!empty($sign_name))<p><strong>{{ $sign_name }}</strong></p>@endif
		@if(!empty($sign_designation))<p>{{ $sign_designation }}</p>@endif
	</div>
	@endif
@endif
</body>
</html>
