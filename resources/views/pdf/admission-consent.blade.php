{{-- IPD admission consent form (2 pages: consent + blood transfusion). Standard layout + report-standard.css (docs/PDF_REPORTS.md). Vars: ipd, file, settings. --}}
@extends('pdf.layouts.standard')

@section('title', 'Admission Consent Form - ' . ($ipd->patient?->patient_name ?? ''))
@section('report-title', 'ADMISSION CONSENT FORM')

@section('content')
<div class="rpt-form">
<div class="rpt-box">
  <table>
    <tr>
      <td style="width:60%"><b>Patient Name:</b> {{ $ipd->patient?->patient_name }}</td>
      <td><b>IPD Ref:</b> {{ $ipd->ipd_ref }}</td>
    </tr>
    <tr>
      <td><b>Age / Sex:</b> {{ $ipd->patient?->age }} Yrs / {{ $ipd->patient?->gender }}</td>
      <td><b>Admission:</b> {{ \Carbon\Carbon::parse($ipd->admission_date_time)->format('d-m-Y') }}</td>
    </tr>
    <tr>
      <td><b>Guardian:</b> {{ $ipd->patient?->guardian_name }}</td>
      <td><b>Bed/Ward:</b> {{ $ipd->bed?->bed_name }} / {{ $ipd->bed?->wardCategory?->ward_category }}</td>
    </tr>
    <tr>
      <td><b>Address:</b> {{ $ipd->patient?->address }}</td>
      <td><b>Mobile:</b> {{ $ipd->patient?->mobile_number }}</td>
    </tr>
    @if($file?->relative_name)
    <tr>
      <td><b>Relative / Attendant:</b> {{ $file->relative_name }} ({{ $file->relationship }})</td>
      <td><b>Witness:</b> {{ $file->witness_name }}</td>
    </tr>
    @endif
  </table>
</div>
{{-- Two-column: English (left) | Hindi (right) --}}
<table class="rpt-cols">
<tr>
<td>
  <div class="rpt-heading">Admission Consent Form</div>
  <p>
    I, <b>{{ $ipd->patient?->patient_name }}</b>, aged <b>{{ $ipd->patient?->age }}</b> yrs,
    @if($ipd->patient?->guardian_name) S/o, D/o, W/o <b>{{ $ipd->patient?->guardian_name }}</b>, @endif
    understand that my health condition requires inpatient admission.
    I consent to and authorise investigations, treatment and hospital care at this hospital.
    All terms and conditions were explained to me in a language I understand.
    I hereby promise to pay the hospital charges for all services and goods provided to me.
  </p>
  <div class="sig-block" style="margin-top:10px;">
    <p>Relation with Patient: ______________________</p>
    <p>Date &amp; Time: ______________________</p>
    <p style="margin-top:16px;"><span class="rpt-sigline">Name &amp; Signature of Patient / Relative</span></p>
  </div>

  <div class="rpt-hr"></div>
  <div class="rpt-heading">Letter of Undertaking</div>
  <p>
    I have been apprised of the rules and regulations of the hospital and the charges of treatment.
    I request you to admit me/the patient for medical treatment.
    I am remitting an advance of Rs. ____________ and undertake to pay any further advances when due
    and the whole treatment charges as per hospital bills on presentation by the hospital authorities.
    I consent to be examined / for the patient to be examined by the Doctors / Consultants / Nurses of
    the hospital, to undergo essential tests including procedures for diagnosis and take treatment
    suggested by the doctors. I agree that any medico-legal dispute will be subject only to the
    jurisdiction of the courts of this city.
  </p>
  <div class="sig-block">
    <p><span class="rpt-sigline">Signature of Patient's Relative</span></p>
    <p>Relationship: ______________________&nbsp;&nbsp; Name: ______________________</p>
    <p style="margin-top:10px;"><span class="rpt-sigline">Signature of Patient</span></p>
  </div>

  <div class="rpt-hr"></div>
  <div class="rpt-box">
    <div class="rpt-heading">Notice</div>
    <p>Information provided at the time of admission regarding the name, age and address, mode, time
    and place of accident, duration of symptoms etc., will not be changed under any circumstances
    as per the order of the Supreme Court.</p>
    <p><span class="rpt-sigline">Signature of Patient's Relative</span>
    &nbsp;&nbsp;&nbsp;<span class="rpt-sigline">Signature of Patient</span></p>
  </div>
</td><td class="rpt-col-right">
  <div class="rpt-heading">&#2319;&#2337;&#2350;&#2367;&#2358;&#2344; &#2360;&#2361;&#2350;&#2340;&#2367; &#2347;&#2377;&#2352;&#2381;&#2350;</div>
  <p>
    &#2350;&#2376;&#2306; <b>{{ $ipd->patient?->patient_name }}</b>, &#2313;&#2350;&#2381;&#2352; <b>{{ $ipd->patient?->age }}</b> &#2357;&#2352;&#2381;&#2359;,
    &#2360;&#2350;&#2334;&#2340;&#2366;/&#2360;&#2350;&#2334;&#2340;&#2368; &#2361;&#2370;&#2305; &#2325;&#2367; &#2350;&#2375;&#2352;&#2375;/&#2361;&#2350;&#2366;&#2352;&#2375; &#2352;&#2379;&#2327;&#2368; &#2325;&#2368;
    &#2360;&#2381;&#2357;&#2366;&#2360;&#2381;&#2341;&#2381;&#2351; &#2360;&#2381;&#2341;&#2367;&#2340;&#2367; &#2350;&#2375;&#2306; &#2309;&#2360;&#2381;&#2346;&#2340;&#2366;&#2354; &#2349;&#2352;&#2381;&#2340;&#2368; &#2310;&#2357;&#2358;&#2381;&#2351;&#2325; &#2361;&#2376;&#2404;
    &#2350;&#2376;&#2306; &#2309;&#2360;&#2381;&#2346;&#2340;&#2366;&#2354; &#2350;&#2375;&#2306; &#2325;&#2367;&#2319; &#2332;&#2366;&#2344;&#2375; &#2357;&#2366;&#2354;&#2375; &#2346;&#2352;&#2368;&#2325;&#2381;&#2359;&#2339;, &#2313;&#2346;&#2330;&#2366;&#2352; &#2324;&#2352; &#2309;&#2360;&#2381;&#2346;&#2340;&#2366;&#2354;
    &#2342;&#2375;&#2326;&#2349;&#2366;&#2354; &#2325;&#2368; &#2346;&#2370;&#2352;&#2368; &#2360;&#2361;&#2350;&#2340;&#2367; &#2342;&#2375;&#2340;&#2366;/&#2342;&#2375;&#2340;&#2368; &#2361;&#2370;&#2305;&#2404;
    &#2350;&#2376;&#2306; &#2349;&#2352;&#2381;&#2340;&#2368; &#2358;&#2369;&#2354;&#2381;&#2325; &#2325;&#2366; &#2349;&#2369;&#2327;&#2340;&#2366;&#2344; &#2325;&#2352;&#2344;&#2375; &#2325;&#2366; &#2357;&#2330;&#2344; &#2342;&#2375;&#2340;&#2366;/&#2342;&#2375;&#2340;&#2368; &#2361;&#2370;&#2305;&#2404;
  </p>
  <div class="sig-block" style="margin-top:10px;">
    <p>&#2360;&#2350;&#2381;&#2348;&#2344;&#2381;&#2343;: ______________________</p>
    <p>&#2342;&#2367;&#2344;&#2366;&#2306;&#2325; &#2357; &#2360;&#2350;&#2351;: ______________________</p>
    <p style="margin-top:16px;"><span class="rpt-sigline">&#2352;&#2379;&#2327;&#2368;/&#2360;&#2344;&#2381;&#2348;&#2368; &#2325;&#2375; &#2361;&#2360;&#2381;&#2340;&#2366;&#2325;&#2381;&#2359;&#2352;</span></p>
  </div>

  <div class="rpt-hr"></div>
  <div class="rpt-heading">&#2313;&#2346;&#2325;&#2381;&#2352;&#2350; &#2346;&#2340;&#2381;&#2352;</div>
  <p>
    &#2350;&#2369;&#2334;&#2375; &#2309;&#2360;&#2381;&#2346;&#2340;&#2366;&#2354; &#2325;&#2375; &#2360;&#2349;&#2368; &#2344;&#2367;&#2351;&#2350;&#2379;&#2306; &#2357; &#2358;&#2352;&#2381;&#2340;&#2379;&#2306; &#2310;&#2352; &#2313;&#2346;&#2330;&#2366;&#2352; &#2358;&#2369;&#2354;&#2381;&#2325;&#2379;&#2306; &#2325;&#2366;
    &#2310;&#2325;&#2354;&#2344; &#2325;&#2352;&#2366;&#2351;&#2366; &#2327;&#2351;&#2366; &#2361;&#2376;&#2404; &#2350;&#2376;&#2306; &#2309;&#2344;&#2369;&#2352;&#2379;&#2343; &#2325;&#2352;&#2340;&#2366;/&#2325;&#2352;&#2340;&#2368; &#2361;&#2370;&#2305;
    &#2325;&#2367; &#2350;&#2375;&#2352;&#2375;/&#2352;&#2379;&#2327;&#2368; &#2325;&#2379; &#2330;&#2367;&#2325;&#2367;&#2340;&#2381;&#2360;&#2366; &#2313;&#2346;&#2330;&#2366;&#2352; &#2361;&#2375;&#2340;&#2369; &#2349;&#2352;&#2381;&#2340;&#2368; &#2325;&#2367;&#2351;&#2366; &#2332;&#2366;&#2319;&#2404;
    &#2350;&#2376;&#2306; &#2309;&#2327;&#2381;&#2352;&#2367;&#2350; &#2352;&#2366;&#2358;&#2367; &#2330;&#2369;&#2325;&#2366;&#2344;&#2375; &#2310;&#2352; &#2360;&#2346;&#2370;&#2352;&#2381;&#2339; &#2348;&#2367;&#2354; &#2349;&#2369;&#2327;&#2340;&#2366;&#2344; &#2325;&#2368; &#2332;&#2367;&#2350;&#2381;&#2350;&#2375;&#2342;&#2366;&#2352;&#2368; &#2354;&#2375;&#2340;&#2366;/&#2354;&#2375;&#2340;&#2368; &#2361;&#2370;&#2305;&#2404;
  </p>
  <div class="sig-block">
    <p><span class="rpt-sigline">&#2360;&#2344;&#2381;&#2348;&#2368; &#2325;&#2375; &#2361;&#2360;&#2381;&#2340;&#2366;&#2325;&#2381;&#2359;&#2352;</span></p>
    <p>&#2360;&#2350;&#2381;&#2348;&#2344;&#2381;&#2343;: ____________ &#2344;&#2366;&#2350;: ____________</p>
    <p style="margin-top:10px;"><span class="rpt-sigline">&#2352;&#2379;&#2327;&#2368; &#2325;&#2375; &#2361;&#2360;&#2381;&#2340;&#2366;&#2325;&#2381;&#2359;&#2352;</span></p>
  </div>

  <div class="rpt-hr"></div>
  <div class="rpt-box">
    <div class="rpt-heading">&#2344;&#2379;&#2335;&#2367;&#2360;</div>
    <p>&#2349;&#2352;&#2381;&#2340;&#2368; &#2325;&#2375; &#2360;&#2350;&#2351; &#2342;&#2367;&#2319; &#2327;&#2319; &#2344;&#2366;&#2350;, &#2313;&#2350;&#2381;&#2352;, &#2346;&#2340;&#2366;, &#2342;&#2369;&#2352;&#2381;&#2328;&#2335;&#2344;&#2366; &#2325;&#2366; &#2350;&#2379;&#2337;, &#2360;&#2350;&#2351; &#2324;&#2352; &#2360;&#2381;&#2341;&#2366;&#2344; &#2310;&#2342;&#2367;
    &#2310;&#2344;&#2325;&#2366;&#2352;&#2368; &#2360;&#2369;&#2346;&#2381;&#2352;&#2368;&#2350; &#2325;&#2379;&#2352;&#2381;&#2335; &#2325;&#2375; &#2310;&#2342;&#2375;&#2358; &#2346;&#2352; &#2325;&#2367;&#2360;&#2368; &#2349;&#2368; &#2346;&#2352;&#2367;&#2360;&#2381;&#2341;&#2367;&#2340;&#2367; &#2350;&#2375;&#2306; &#2344;&#2361;&#2368;&#2306; &#2348;&#2342;&#2354;&#2368; &#2332;&#2366;&#2319;&#2327;&#2368;&#2404;</p>
  </div>
</td>
</tr>
</table>
{{-- =========================================================== --}}
{{-- PAGE 2: Declaration + Blood Transfusion Consent              --}}
{{-- =========================================================== --}}
<div class="rpt-pagebreak"></div>

@include('pdf.partials.report-header')

<div class="rpt-title">DECLARATION</div>
<p style="text-align:right;font-size:8pt;">Date: {{ \Carbon\Carbon::parse($ipd->admission_date_time)->format('d-m-Y') }}</p>
<div class="rpt-hr"></div>

<p>I certify that the statements made in this consent form have been read over and explained to me
in a language I easily understand. I have fully understood the implications of the consent and further
submit that the statements therein referred to were filled in and any inapplicable paragraphs stricken
off before I signed / applied my thumb impression.</p>

<table style="margin-top:14px;">
  <tr>
    <td style="width:50%;vertical-align:bottom;">
      <p>Name of Patient / Guardian / Relative:</p>
      <p style="margin-top:4px;">{{ $ipd->patient?->patient_name }}
        @if($file?->relative_name) / {{ $file->relative_name }} @endif
      </p>
      <p>Relation with Patient: {{ $file?->relationship }}</p>
      <p style="margin-top:20px;"><span class="rpt-sigline">Signature / Thumb Impression</span></p>
    </td>
    <td style="width:50%;vertical-align:bottom;text-align:right;">
      <p>Date: ____________________</p>
      <p>Time: ____________________</p>
      <p style="margin-top:20px;"><span class="rpt-sigline">Name &amp; Signature of Doctor / Reg.No</span></p>
    </td>
  </tr>
</table>

<div class="rpt-hr" style="margin-top:16px;"></div>

<div class="rpt-title">BLOOD COMPONENT TRANSFUSION CONSENT</div>
<div class="rpt-heading rpt-hi" style="text-align:center;">
  &#2352;&#2325;&#2381;&#2340; / &#2352;&#2325;&#2381;&#2340; &#2313;&#2340;&#2381;&#2346;&#2366;&#2342; &#2310;&#2343;&#2366;&#2344; &#2360;&#2361;&#2350;&#2340;&#2367; &#2346;&#2340;&#2381;&#2352;
</div>
<div class="rpt-hr"></div>

<ol>
  <li>
    <b>Blood Transfusion:</b> I have been informed that I / the patient requires blood transfusion.
    I generally understand the uses of blood transfusion.
    <br><i>&#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344;: &#2350;&#2369;&#2334;&#2375; &#2348;&#2340;&#2366;&#2351;&#2366; &#2327;&#2351;&#2366; &#2361;&#2376; &#2325;&#2367; &#2350;&#2369;&#2334;&#2375;/&#2350;&#2375;&#2352;&#2375; &#2350;&#2352;&#2368;&#2332; &#2325;&#2379; &#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344; &#2325;&#2368; &#2332;&#2352;&#2370;&#2352;&#2340; &#2361;&#2376;&#2404;</i>
  </li>
  <li>
    <b>Risks of Blood Transfusion:</b> I have been informed of the possible risks including HIV,
    Hepatitis and other transfusion reactions such as haemolysis and allergic reactions.
    <br><i>&#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344; &#2325;&#2375; &#2332;&#2379;&#2326;&#2367;&#2350;: &#2350;&#2369;&#2334;&#2375; &#2360;&#2306;&#2349;&#2366;&#2357;&#2367;&#2340; &#2332;&#2379;&#2326;&#2367;&#2350;&#2379;&#2306; &#2360;&#2361;&#2367;&#2340; HIV &#2360;&#2381;&#2341;&#2367;&#2340;&#2367;&#2351;&#2379;&#2306; &#2325;&#2375; &#2348;&#2366;&#2352;&#2375; &#2350;&#2375;&#2306; &#2360;&#2370;&#2330;&#2367;&#2340; &#2325;&#2367;&#2351;&#2366; &#2327;&#2351;&#2366; &#2361;&#2376;&#2404;</i>
  </li>
  <li>
    <b>Alternatives:</b> The alternatives to blood transfusion and the consequences of refusing
    this treatment have been explained to me.
    <br><i>&#2357;&#2367;&#2325;&#2354;&#2381;&#2346;: &#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344; &#2325;&#2375; &#2357;&#2367;&#2325;&#2354;&#2381;&#2346;&#2379;&#2306; &#2324;&#2352; &#2311;&#2360;&#2375; &#2344; &#2354;&#2375;&#2344;&#2375; &#2325;&#2375; &#2346;&#2352;&#2367;&#2339;&#2366;&#2350;&#2379;&#2306; &#2325;&#2375; &#2348;&#2366;&#2352;&#2375; &#2350;&#2375;&#2306; &#2360;&#2350;&#2334;&#2366;&#2351;&#2366; &#2327;&#2351;&#2366; &#2361;&#2376;&#2404;</i>
  </li>
  <li>
    <b>Patient Consent:</b> I accept all the explained risks and authorise the hospital to
    administer blood transfusion.
    <br><i>&#2352;&#2379;&#2327;&#2368; &#2360;&#2361;&#2350;&#2340;&#2367;: &#2350;&#2376;&#2306; &#2360;&#2349;&#2368; &#2360;&#2350;&#2334;&#2366;&#2319; &#2327;&#2319; &#2332;&#2379;&#2326;&#2367;&#2350;&#2379;&#2306; &#2325;&#2379; &#2360;&#2381;&#2357;&#2368;&#2325;&#2366;&#2352; &#2325;&#2352;&#2340;&#2366;/&#2325;&#2352;&#2340;&#2368; &#2361;&#2370;&#2305; &#2324;&#2352; &#2309;&#2360;&#2381;&#2346;&#2340;&#2366;&#2354; &#2325;&#2379; &#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344; &#2361;&#2375;&#2340;&#2369; &#2309;&#2343;&#2367;&#2325;&#2371;&#2340; &#2325;&#2352;&#2340;&#2366;/&#2325;&#2352;&#2340;&#2368; &#2361;&#2370;&#2305;&#2404;</i>
  </li>
  <li>
    <b>No Guarantee:</b> The blood used during transfusion has been tested; however, it is not
    100% reliable. I accept that no guarantee of the outcome of blood transfusion has been given to me.
    <br><i>&#2325;&#2379;&#2312; &#2327;&#2366;&#2352;&#2350;&#2381;&#2335;&#2368; &#2344;&#2361;&#2368;&#2306;: &#2352;&#2325;&#2381;&#2340; &#2310;&#2343;&#2366;&#2344; &#2325;&#2375; &#2342;&#2380;&#2352;&#2366;&#2344; &#2346;&#2381;&#2352;&#2351;&#2379;&#2327; &#2325;&#2367;&#2319; &#2327;&#2319; &#2352;&#2325;&#2381;&#2340; &#2325;&#2368; &#2332;&#2366;&#2306;&#2330; &#2325;&#2368; &#2327;&#2312; &#2361;&#2376;, &#2347;&#2367;&#2352; &#2349;&#2368; &#2351;&#2361; 100% &#2357;&#2367;&#2358;&#2381;&#2357;&#2360;&#2344;&#2368;&#2351; &#2344;&#2361;&#2368;&#2306; &#2361;&#2376;&#2404;</i>
  </li>
</ol>

<p style="font-size:8.5pt;margin-top:6px;">
  I have been explained by the treating doctor about the need for blood / blood component transfusion.
  After clarifying all doubts, I hereby accept to give single / multiple units of blood / blood component
  transfusion to the patient as and when required during the duration of hospital stay.
</p>

<table style="margin-top:16px;">
  <tr>
    <td style="width:50%;vertical-align:bottom;">
      <p>Date: ____________________</p>
      <p style="margin-top:20px;"><span class="rpt-sigline">Signature of Attendant</span></p>
      <p style="margin-top:14px;"><span class="rpt-sigline">Signature of Witness</span></p>
    </td>
    <td style="width:50%;vertical-align:bottom;text-align:right;">
      <p>Time: ____________________</p>
      <p style="margin-top:20px;"><span class="rpt-sigline">Relation with Patient</span></p>
      <p style="margin-top:14px;"><span class="rpt-sigline">Signature of Medical Officer</span></p>
    </td>
  </tr>
</table>
</div>
@endsection
