{{-- DCD inner-navigation matching legacy dcd-menu.php --}}
@php use App\Services\PermissionService; $ps = app(PermissionService::class); @endphp
<nav class="navbar navbar-expand-lg navbar-light dcd-menu">
  <div class="container-fluid">
    <a class="navbar-brand" href="javascript:void(0)"></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#dcd-nav" aria-controls="dcd-nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="dcd-nav">
      <ul class="inner-menu navbar-nav me-auto mb-2 mb-lg-0">
        @if($ps->hasPermission('im_dcd_overview'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.show') ? 'active' : '' }}" aria-current="page" href="{{ route('dcd.show', $dcd->id) }}"><i class="fa fa-th"></i> Overview</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_file'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.patient-file') ? 'active' : '' }}" aria-current="page" href="{{ route('dcd.patient-file', $dcd->id) }}"><i class="far fa-book-medical"></i> File</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_prescription'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.prescriptions') ? 'active' : '' }}" href="{{ route('dcd.prescriptions', $dcd->id) }}"><i class="fas fa-file-prescription"></i> Prescription</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_charges'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.charges') ? 'active' : '' }}" href="{{ route('dcd.charges', $dcd->id) }}"><i class="fas fa-donate"></i> Charges</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_history'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.visits') ? 'active' : '' }}" href="{{ route('dcd.visits', $dcd->id) }}"><i class="fas fa-sticky-note"></i> History</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_medication'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.medications') ? 'active' : '' }}" href="{{ route('dcd.medications', $dcd->id) }}"><i class="fa fa-medkit"></i> Medication</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_lab_investigations'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.lab-investigations') ? 'active' : '' }}" href="{{ route('dcd.lab-investigations', $dcd->id) }}"><i class="fas fa-diagnoses"></i> Lab Investigation</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_procedures'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.procedures') ? 'active' : '' }}" href="{{ route('dcd.procedures', $dcd->id) }}"><i class="fas fa-face-mask"></i> Procedures</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_wellness'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.wellness') ? 'active' : '' }}" href="{{ route('dcd.wellness', $dcd->id) }}"><i class="fas fa-heartbeat"></i> Wellness</a>
        </li>
        @endif
        @if($ps->hasPermission('im_dcd_immunization'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.immunization') ? 'active' : '' }}" href="{{ route('dcd.immunization', $dcd->id) }}"><i class="fas fa-syringe"></i> Immunization</a>
        </li>
        @endif
        @if($ps->hasPermission('im_patient_documents'))
        <li class="nav-item">
          <a class="nav-link {{ request()->routeIs('dcd.patient-documents') ? 'active' : '' }}" href="{{ route('dcd.patient-documents', $dcd->id) }}"><i class="fas fa-file-medical"></i> Documents</a>
        </li>
        @endif
      </ul>
    </div>
  </div>
</nav>
