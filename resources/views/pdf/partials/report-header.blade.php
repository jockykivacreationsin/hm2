{{--
  Standard report header: logo + hospital name / address / phone, centred, with a rule underneath.
  Needs: $settings (hospital settings row as array). Styles: .rpt-header in report-standard.css.
  Logo = uploaded settings logo, falling back to public/assets/images/hlogo.jpg.
--}}
@php
	$rptContact = array_filter([
		!empty($settings['phone_number'])  ? 'Phone: ' . e($settings['phone_number'])  : null,
		!empty($settings['mobile_number']) ? 'Mobile: ' . e($settings['mobile_number']) : null,
	]);
	$rptLogo = \App\Support\Uploads::path(\App\Support\Uploads::SETTINGS, $settings['logo'] ?? null);
	if ($rptLogo === null && is_file(public_path('assets/images/hlogo.jpg'))) {
		$rptLogo = public_path('assets/images/hlogo.jpg');
	}
@endphp
{{-- Shrink-to-fit table + nowrap: a fixed/percentage width leaves a gap beside the logo, and without nowrap mPDF wraps the name letter by letter. --}}
<div class="rpt-header">
	<table align="center" style="border-collapse:collapse; margin:0 auto;">
		<tr>
			<td style="vertical-align:middle; text-align:center; padding:0 8px 0 0;">@if($rptLogo)<img src="{{ $rptLogo }}" style="height:60px;">@endif</td>
			<td style="vertical-align:middle; text-align:center; padding:0; white-space:nowrap;">
				<h2>{{ $settings['hospital_name'] ?? '' }}</h2>
				@if(!empty($settings['address']))
				<p>{{ $settings['address'] }}</p>
				@endif
				@if($rptContact)
				<p>{!! implode(' | ', $rptContact) !!}</p>
				@endif
			</td>
		</tr>
	</table>
</div>
