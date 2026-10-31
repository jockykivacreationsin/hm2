{{-- Shared OPD inner-navigation. Expects $opd in scope --}}
@php use App\Services\PermissionService; $ps = app(PermissionService::class); @endphp
<nav class="navbar navbar-expand-lg navbar-light ipd-menu">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#opd-nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="opd-nav">
      <ul class="inner-menu navbar-nav me-auto">
        @if($ps->hasPermission('im_opd_overview'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.show') ? 'active' : '' }}" href="{{ route('opd.show', $opd->id) }}"><i class="fa fa-th"></i> Overview</a></li>
        @endif
        @if($ps->hasPermission('im_opd_prescription'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.prescriptions') ? 'active' : '' }}" href="{{ route('opd.prescriptions', $opd->id) }}"><i class="far fa-calendar-check"></i> Prescription</a></li>
        @endif
        @if($ps->hasPermission('im_opd_charges'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.charges') ? 'active' : '' }}" href="{{ route('opd.charges', $opd->id) }}"><i class="fas fa-donate"></i> Charges</a></li>
        @endif
        @if($ps->hasPermission('im_opd_history'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.visits') ? 'active' : '' }}" href="{{ route('opd.visits', $opd->id) }}"><i class="fas fa-sticky-note"></i> History</a></li>
        @endif
        @if($ps->hasPermission('im_opd_lab_investigations'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.lab-investigations') ? 'active' : '' }}" href="{{ route('opd.lab-investigations', $opd->id) }}"><i class="fas fa-flask"></i> Lab Investigations</a></li>
        @endif
        @if($ps->hasPermission('im_opd_procedures'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.procedures') ? 'active' : '' }}" href="{{ route('opd.procedures', $opd->id) }}"><i class="fas fa-face-mask"></i> Procedures</a></li>
        @endif
        @if($ps->hasPermission('im_opd_wellness'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.wellness') ? 'active' : '' }}" href="{{ route('opd.wellness', $opd->id) }}"><i class="fas fa-heartbeat"></i> Wellness</a></li>
        @endif
        @if($ps->hasPermission('im_opd_immunization'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.immunization') ? 'active' : '' }}" href="{{ route('opd.immunization', $opd->id) }}"><i class="fas fa-syringe"></i> Immunization</a></li>
        @endif
        @if($ps->hasPermission('im_patient_documents'))
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('opd.patient-documents') ? 'active' : '' }}" href="{{ route('opd.patient-documents', $opd->id) }}"><i class="fas fa-file-medical"></i> Documents</a></li>
        @endif
      </ul>
    </div>
  </div>
</nav>
