@php
  $permissionService = app(\App\Services\PermissionService::class);
  $loggedInPatientCategoryId = (int) session('patient_category_id', 0);
  $can = fn (string $key) => $permissionService->hasPermission($key);

  // url of the first route name that exists (other modules register theirs independently), '#' otherwise
  $menuUrl = function (array $names) {
      foreach ($names as $name) {
          if (Route::has($name)) {
              return route($name);
          }
      }
      return '#';
  };
  // true when the current request is one of the given route names (or sits under it)
  $menuActive = function (array $names) {
      foreach ($names as $name) {
          if (request()->routeIs($name, $name . '.*')) {
              return true;
          }
      }
      return false;
  };

  // Reports + Patient Medicines are only shown for patient category 1 (as legacy)
  $showReports = $loggedInPatientCategoryId == 1;
@endphp
<!-- sidebar start-->
<div class="codex-sidebar">
  {{-- legacy logo links go to index.php, which forwards to the user's landing page (dashboard, or OPD without m_dashboard) --}}
  <div class="logo-gridwrap"><a class="lightlogo" href="{{ route('login') }}"><img class="img-fluid" src="{{ asset('assets/images/logo/logo.png') }}" alt="sidebar-lightlogo"></a>
    <div class="sidebar-action"><i data-feather="menu"></i></div>
  </div>
  <div class="icon-logo"><a class="lightlogo" href="{{ route('login') }}"><img class="img-fluid" src="{{ asset('assets/images/logo/icon-logo.png') }}" alt="sidebar-lightlogo"></a></div>
  <ul class="codex-menu custom-scroll" data-simplebar="">
    @php
      $singleItems = function (string $perm, string $icon, string $title, array $routes) use ($can, $menuUrl, $menuActive) {
          if (!$can($perm)) {
              return '';
          }
          $active = $menuActive($routes);
          $module = explode('.', $routes[0])[0];
          if (!$active && in_array($module, ['opd', 'ipd', 'dcd', 'billing', 'patients', 'roles', 'settings'], true)) {
              // whole module (detail tabs etc.) keeps its menu entry highlighted; discharge pages belong to the Discharge menu
              $active = request()->routeIs($module . '.*')
                  && !request()->routeIs($module . '.discharge*', $module . '.view-discharge*');
          }
          return '<li class="menu-item' . ($active ? ' active' : '') . '"><a href="' . e($menuUrl($routes)) . '"' . ($active ? ' class="active"' : '') . '>'
              . '<div class="icon-item"><i class="' . e($icon) . '"></i></div><span>' . e($title) . '</span></a></li>';
      };
      $subMenu = function (string $icon, string $title, array $links) use ($menuUrl, $menuActive) {
          $any = collect($links)->contains(fn ($l) => $menuActive($l[1]));
          $html = '<li class="menu-item' . ($any ? ' active' : '') . '"><a href="javascript:void(0);">'
              . '<div class="icon-item"><i class="' . e($icon) . '"></i></div><span>' . e($title) . '</span><i class="fa fa-angle-down"></i></a>'
              . '<ul class="submenu-list">';
          foreach ($links as [$label, $routes]) {
              $on = $menuActive($routes);
              $html .= '<li' . ($on ? ' class="active"' : '') . '><a href="' . e($menuUrl($routes)) . '"' . ($on ? ' class="active"' : '') . '>' . e($label) . '</a></li>';
          }
          return $html . '</ul></li>';
      };
    @endphp

    {!! $singleItems('m_dashboard', 'fas fa-home', 'Dashboard', ['dashboard']) !!}
    {!! $singleItems('m_opd', 'fas fa-stethoscope', 'OPD - Out Patient', ['opd.index']) !!}
    {!! $singleItems('m_ipd', 'fas fa-procedures', 'IPD - In Patient', ['ipd.index']) !!}
    {!! $singleItems('m_dcd', 'fa-solid fa-monitor-waveform', 'Day Care Patient', ['dcd.index']) !!}
    {!! $singleItems('m_billing', 'far fa-money-check-edit', 'Billing', ['billing.index']) !!}

    @if($can('m_staff'))
      {!! $subMenu('far fa-id-card', 'Staff', [
        ['Staff List', ['staff.index']],
        ['Attendance', ['staff.attendance']],
        ['Roster', ['staff.roster-calendar']],
        ['Staff Roster Dates', ['staff.roster-dates']],
        ['View Print Roster', ['staff.roster-staff-wise', 'staff.roster.staff-wise']],
        ['Salary', ['staff.salary']],
        ['Salary Settings', ['staff.salary-settings']],
      ]) !!}
    @endif

    {!! $singleItems('m_patients', 'fa-solid fa-users-medical', 'Patients', ['patients.index']) !!}

    @if($can('m_beds'))
      {!! $subMenu('fas fa-bed-bunk', 'Beds', [
        ['Floors', ['beds.floors']],
        ['Ward Categories', ['beds.ward-categories']],
        ['Beds', ['beds.index']],
      ]) !!}
    @endif

    @if($can('m_charges'))
      {!! $subMenu('fas fa-money-bill', 'Charges', [
        ['Departments', ['charges.departments']],
        ['Charges', ['charges.index']],
      ]) !!}
    @endif

    {!! $singleItems('m_pharmacy', 'fas fa-pills', 'Pharmacy', ['master.pharmacy-categories', 'master.pharmacy']) !!}

    @if($can('m_discharge'))
      {!! $subMenu('fas fa-hospital-user', 'Discharge', [
        ['IPD Discharge Approvals', ['ipd.discharge-approvals']],
        ['IPD Discharged Patients', ['ipd.discharged']],
        ['DC Discharge Approvals', ['dcd.discharge-approvals']],
        ['DC Discharged Patients', ['dcd.discharged']],
      ]) !!}
    @endif

    @if($can('m_master'))
      {!! $subMenu('fas fa-tasks', 'Master', [
        ['TPA', ['master.tpa']],
        ['Biochemistry Units', ['master.biochemistry-units', 'master.biochemistry']],
        ['Biochemistry Tests', ['master.biochemistry-tests', 'master.biochemistry']],
        ['Backups', ['db-backups.index', 'master.backups']],
      ]) !!}
    @endif

    @if($can('m_sale_bill'))
      {!! $subMenu('fas fa-files-medical', 'Sale', [
        ['Bills', ['sale-bills.index']],
      ]) !!}
    @endif

    @if($can('m_reports') && $showReports)
      {!! $subMenu('fas fa-paste', 'Reports', [
        ['Collection', ['reports.collections']],
      ]) !!}
    @endif

    @if($can('m_patient_periodic_medicines') && $showReports)
      {!! $subMenu('fas fa-prescription', 'Patient Medicines', [
        ['Diseases', ['master.diseases']],
        ['Patient Medicine', ['periodic-medicine.issue-list']],
        ['Patients', ['periodic-medicine.index']],
        ['Medicine Due Days', ['periodic-medicine.due-days']],
      ]) !!}
    @endif

    @php
      // ABDM parent menu: consent pages follow im_opd_history, settings follows the roles permission (as legacy)
      $psConsent = $can('im_opd_history');
      $psSettings = $can('m_roles_and_permissions');
    @endphp
    @if($psConsent || $psSettings)
      {!! $subMenu('fa-solid fa-shield-halved', 'ABDM', array_merge(
        $psConsent ? [
          ['ABHA Account Services', ['abdm.abha-account-services']],
          ['Consent Requests', ['abdm.consent-requests']],
        ] : [],
        $psSettings ? [['ABDM Settings', ['abdm.settings']]] : []
      )) !!}
    @endif

    {!! $singleItems('m_roles_and_permissions', 'fa-solid fa-user-shield', 'User Roles', ['roles.index']) !!}
    {{-- Laravel addition: general hospital settings (the legacy app edits these straight in the DB) --}}
    {!! $singleItems('m_roles_and_permissions', 'fas fa-cog', 'Settings', ['settings.index']) !!}
    {{-- PHI Audit Log: visible only to staff with im_audit_logs permission (Admin only by default) --}}
    {!! $singleItems('im_audit_logs', 'fas fa-shield-alt', 'Audit Log', ['audit-logs.index']) !!}
  </ul>
</div>
<!-- sidebar end-->
