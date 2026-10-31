{{-- Shared IPD inner navigation. Expects $ipd in scope.
     Legacy has two bars: ipd-menu.php (overview, prescription, charges...) and ipd-pf-menu.php (the patient-file
     group: file, treatment, nursing...). The patient-file pages use the second one; every other page the first.
     Pass `'menu' => 'pf'|'main'` when including to override the route based choice. --}}
@php
  $ps = app(\App\Services\PermissionService::class);

  $isPfMenu = ($menu ?? null) !== null
    ? $menu === 'pf'
    : request()->routeIs('ipd.patient-file*', 'ipd.treatments*', 'ipd.vital-monitoring*', 'ipd.intake-output*', 'ipd.progress-sheet*', 'ipd.rmo-notes*');

  // [permission, route name, label, icon]
  $mainTabs = [
    ['im_ipd_overview',           'ipd.show',               'Overview',          'fa fa-th'],
    ['im_ipd_prescription',       'ipd.prescriptions',      'Prescription',      'fas fa-file-prescription'],
    ['im_ipd_charges',            'ipd.charges',            'Charges',           'fas fa-donate'],
    ['im_ipd_history',            'ipd.visits',             'History',           'fas fa-sticky-note'],
    ['im_ipd_medication',         'ipd.medications',        'Medication',        'fa fa-medkit'],
    ['im_ipd_lab_investigations', 'ipd.lab-investigations', 'Lab Investigation', 'fas fa-diagnoses'],
    ['im_ipd_procedures',         'ipd.procedures',         'Procedures',        'fas fa-face-mask'],
    ['im_ipd_consultations',      'ipd.consultations',      'Consultations',     'far fa-calendar-check'],
    ['im_ipd_bed_history',        'ipd.bed-history',        'Bed History',       'fas fa-procedures'],
    ['im_ipd_wellness',           'ipd.wellness',           'Wellness',          'fas fa-heartbeat'],
    ['im_ipd_immunization',       'ipd.immunization',       'Immunization',      'fas fa-syringe'],
    ['im_patient_documents',      'ipd.patient-documents',  'Documents',         'fas fa-file-medical'],
  ];

  $pfTabs = [
    ['im_ipd_patient_file',       'ipd.patient-file',       'File',              'far fa-book-medical'],
    ['im_ipd_treatment',          'ipd.treatments',         'Treatment',         'fas fa-pills'],
    ['im_ipd_vital_monitoring',   'ipd.vital-monitoring',   'Nursing',           'far fa-user-nurse'],
    ['im_ipd_intake_output',      'ipd.intake-output',      'Intake Output',     'fas fa-sync'],
    ['im_ipd_progress_sheet',     'ipd.progress-sheet',     'Progress Sheet',    'fal fa-file-check'],
    ['im_ipd_rmo_notes',          'ipd.rmo-notes',          'RMO Notes',         'far fa-file-edit'],
  ];

  $extraTabs = [
    ['im_ipd_patient_file',       'ipd.patient-file',       'Patient File',      'far fa-book-medical'],
    ['im_ipd_treatment',          'ipd.treatments',         'Treatment',         'fas fa-pills'],
    ['im_ipd_vital_monitoring',   'ipd.vital-monitoring',   'Nursing',           'far fa-user-nurse'],
    ['im_ipd_intake_output',      'ipd.intake-output',      'Intake Output',     'fas fa-sync'],
    ['im_ipd_progress_sheet',     'ipd.progress-sheet',     'Progress Sheet',    'fal fa-file-check'],
    ['im_ipd_rmo_notes',          'ipd.rmo-notes',          'RMO Notes',         'far fa-file-edit'],
    ['b_ipd_discharge_patient',   'ipd.discharge',          'Discharge',         'fas fa-sign-out-alt'],
  ];

  $tabs               = array_filter($isPfMenu ? $pfTabs : $mainTabs, fn ($t) => $ps->hasPermission($t[0]));
  $permittedExtraTabs = !$isPfMenu ? array_filter($extraTabs, fn ($t) => $ps->hasPermission($t[0])) : [];
  $navId              = $isPfMenu ? 'ipd-pf-nav' : 'ipd-nav';
@endphp
<nav class="navbar navbar-expand-lg navbar-light ipd-menu">
  <div class="container-fluid">
    <a class="navbar-brand" href="javascript:void(0)"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $navId }}" aria-controls="{{ $navId }}" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="{{ $navId }}">
      <ul class="inner-menu navbar-nav me-auto mb-2 mb-lg-0">
        @foreach($tabs as [$perm, $route, $label, $icon])
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" @if($loop->first) aria-current="page" @endif href="{{ route($route, $ipd->id) }}"><i class="{{ $icon }}"></i> {{ $label }}</a>
        </li>
        @endforeach
        @if(count($permittedExtraTabs) > 0)
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="ipd-more-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            More
          </a>
          <ul class="dropdown-menu" aria-labelledby="ipd-more-menu">
            @foreach($permittedExtraTabs as [$perm, $route, $label, $icon])
            <li>
              <a class="dropdown-item" href="{{ route($route, $ipd->id) }}"><i class="{{ $icon }}"></i> {{ $label }}</a>
            </li>
            @endforeach
          </ul>
        </li>
        @endif
      </ul>
    </div>
  </div>
</nav>
