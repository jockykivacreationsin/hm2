<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Required meta tags-->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="author" content="">
  <meta name="keywords" content="">
  <title>eZeeHospital</title>
  <!-- shortcut icon-->
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
  <!-- Fonts css-->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap" rel="stylesheet">
  <!-- Font awesome -->
  <link href="{{ asset('assets/fontawesome-pro-6/css/all.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/themify-icons.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/icoicon.css') }}" rel="stylesheet">
  <!-- Slick slider-->
  <link href="{{ asset('assets/css/vendor/slider/slick-slider/slick.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/slider/slick-slider/slick-theme.css') }}" rel="stylesheet">
  <!-- Scrollbar-->
  <link href="{{ asset('assets/css/vendor/simplebar.css') }}" rel="stylesheet">
  <!-- Bootstrap css-->
  <link href="{{ asset('assets/css/vendor/bootstrap.css') }}" rel="stylesheet">
  <!--Datatable-->
  <link href="{{ asset('assets/datatables/RowGroup-1.5.0/css/rowGroup.dataTables.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/datatable/jquery.dataTables.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/datatable/buttons.dataTables.min.css') }}" rel="stylesheet">
  <!-- select 2 css-->
  <link href="{{ asset('assets/css/vendor/select2/select2.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/vendor/sweetalert/sweetalert2.min.css') }}" rel="stylesheet">
  <!-- datepicker-->
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/flatpickr/flatpickr.min.css') }}">

  <!-- Custom css-->
  <link rel="stylesheet" href="{{ asset('assets/jquery-ui/jquery-ui.min.css') }}">
  <link rel="stylesheet" type="text/css" href="{{ asset('assets/summernote/summernote-lite.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/slimcropper/css/slim.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/jquerytimepicker135/jquery.timepicker.min.css') }}">
  <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/materialize/css/materialize.css') }}" rel="stylesheet">
  @stack('styles')
  <script src="{{ asset('assets/js/jquery-3.7.0.min.js') }}"></script>
  <script src="{{ asset('assets/js/vendors/chart/chart.min.js') }}"></script>
  <script src="{{ asset('assets/jquery-ui/jquery-ui.min.js') }}"></script>
  <script src="{{ asset('assets/js/html2pdf.bundle.js') }}"></script>
  <script src="{{ asset('assets/js/jspdf.min.js') }}"></script>
  <script src="{{ asset('assets/js/app.js') }}?v={{ time() }}"></script>
  <script src="{{ asset('assets/js/forms.js') }}?v={{ time() }}"></script>
  <script type="text/javascript" src="{{ asset('assets/materialize/js/materialize.min.js') }}"></script>
  <script>
    // Laravel: CSRF header for every jQuery AJAX write + session-expiry handling (replaces legacy session checks)
    $.ajaxSetup({
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });

    // Named routes for the shared script public/assets/js/app.js (it cannot call route()).
    // `__ID__` is replaced by appRoute(name, id).
    window.appRoutes = {
      abdmHiuPatientStatus:        '{{ route('abdm.hiu.patients.status', '__ID__') }}',
      abdmHiuPatientConsentStatus: '{{ route('abdm.hiu.patients.consent-status', '__ID__') }}',
      abdmHiuFetchArtefact:        '{{ route('abdm.hiu.artefact.fetch') }}',
      abdmHiuRequestHealthData:    '{{ route('abdm.hiu.health-data.request') }}',
      abdmHiuConsentRecords:       '{{ route('abdm.hiu.consents.records.index', '__ID__') }}',
      abdmHiuRecordShow:           '{{ route('abdm.hiu.records.show', '__ID__') }}',

      chargeLookupPrescriptionDetails:   '{{ route('charges.lookup.prescription-details') }}',
      chargeLookupPrescriptionCharges:   '{{ route('charges.lookup.prescription-charges') }}',
      chargeLookupChargeName:            '{{ route('charges.lookup.charge-name') }}',
      chargeLookupCategoryName:          '{{ route('charges.lookup.category-name') }}',
      chargeLookupDepartmentName:        '{{ route('charges.lookup.department-name') }}',
      chargeLookupChargeForPrescription: '{{ route('charges.lookup.charge-for-prescription') }}',
      chargeLookupAmount:                '{{ route('charges.lookup.amount') }}',
      sortOrder:                '{{ route('master.sort-order.update', '__ID__') }}',
      dcdToIpd:                 '{{ route('dcd.to-ipd.show', '__ID__') }}',
      ipdBedPicker:             '{{ route('ipd.bed-picker') }}',
      opdPrescriptionGet:       '{{ route('opd.prescriptions.get', '__ID__') }}',
      dcdPrescriptionGet:       '{{ route('dcd.prescriptions.get', '__ID__') }}',
      ipdPrescriptionGet:       '{{ route('ipd.prescriptions.get', '__ID__') }}',
      staffShow:                '{{ route('staff.show', '__ID__') }}',
      patientShow:              '{{ route('patients.show', '__ID__') }}',
      dcdOpdPatient:            '{{ route('dcd.opd-patient', '__ID__') }}',
      abdmHiuConsentsIndex:     '{{ route('abdm.hiu.consents.index') }}',
      abdmHiuConsentsStore:     '{{ route('abdm.hiu.consents.store') }}',
      abdmHiuPatientsDropdown:  '{{ route('abdm.hiu.patients.dropdown') }}',
      abdmHiuPatientsSearch:    '{{ route('abdm.hiu.patients.search') }}',
      abdmHiuPatientShow:       '{{ route('abdm.hiu.patients.show', '__ID__') }}',
      masterChargeShow:         '{{ route('master.charges.show', '__ID__') }}',
      billingShow:              '{{ route('billing.show', '__ID__') }}',
      billingSplitPayments:     '{{ route('billing.split-payments', '__ID__') }}',
      dcdChargeGet:             '{{ route('dcd.charges.get', '__ID__') }}',
      dcdChargeSplitPayments:   '{{ route('dcd.charges.split-payments', '__ID__') }}',
      dcdPatientFileShow:       '{{ route('dcd.patient-file.show', '__ID__') }}',
      dcdChargeDestroy:         '{{ route('dcd.charges.destroy', '__ID__') }}',
      opdPrescriptionDestroy:   '{{ route('opd.prescriptions.destroy', '__ID__') }}',
      dcdPrescriptionDestroy:   '{{ route('dcd.prescriptions.destroy', '__ID__') }}',
      staffDestroy:             '{{ route('staff.destroy', '__ID__') }}',
      masterChargeDestroy:      '{{ route('master.charges.destroy', '__ID__') }}',
      billingDestroy:           '{{ route('billing.destroy', '__ID__') }}',
      dcdCollectPayments:       '{{ route('dcd.charges.collect-payments', '__ID__') }}',
      staffSalaryPaymentDetail: '{{ route('staff.salary.payment.detail', '__ID__') }}',
    };
    // Base URLs of the uploaded-image route (photos live on the private `uploads` disk, not under public/).
    window.appUploads = {
      patients:     '{{ \App\Support\Uploads::baseUrl('patients') }}',
      staffmembers: '{{ \App\Support\Uploads::baseUrl('staffmembers') }}',
    };
    window.appUploadUrl = function (folder, file) {
      return window.appUploads[folder] + encodeURIComponent(file);
    };
    window.appRoute = function (name, id) {
      return window.appRoutes[name].replace('__ID__', encodeURIComponent(id));
    };
    $(document).ajaxError(function (event, xhr) {
      if (xhr.status === 401) {
        window.location = '{{ route('login') }}';
      }
    });

    // Global SweetAlert helpers (used by pages ported from legacy)
    function showSuccess(msg) {
      Swal.fire({ icon: 'success', title: 'Success', text: msg, timer: 2000, showConfirmButton: false });
    }
    function showError(msg) {
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
    }
    function showLoading() {
      Swal.fire({ title: 'Please wait...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }
  </script>
</head>
<body>
  <!-- Loader Start-->
  <div class="codex-loader">
    <div class="loader-item">
      <svg class="spinner-pulse" version="1.2" height="210" width="550" xmlns="http://www.w3.org/2000/svg" viewport="0 0 60 60" xmlns:xlink="http://www.w3.org/1999/xlink">
        <path class="spinnersub-pulse" fill="none" stroke-width="3" stroke-linejoin="round" d="M0,90L250,90Q257,60 262,87T267,95 270,88 273,92t6,35 7,-60T290,127 297,107s2,-11 10,-10 1,1 8,-10T319,95c6,4 8,-6 10,-17s2,10 9,11h210"></path>
      </svg>
    </div>
  </div>
  <!-- Loader End-->

  @include('layouts.partials.header')

  @include('layouts.partials.sidebar')

  @yield('content')

  @include('layouts.partials.footer')

  @stack('scripts')
</body>
</html>
