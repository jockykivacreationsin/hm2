@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="themebody-wrap">
 <!-- theme body start-->
 <div class="theme-body common-dash" data-simplebar="">
   <div class="container-fluid">
   
     <!-- Income Cards -->
     <div class="row section-box">
       <div class="col-lg-3 col-md-6 col-sm-6">
         <a href="javascript:void(0)" onclick="viewModuleCharges('OPD')">
          <div class="dincome-card opd-card">
           <span class="dincome-icon"><i class="fas fa-stethoscope"></i></span>
           <div class="dincome-content">
             <span class="dincome-text">OPD</span>
             <span class="dincome-number">Rs.{{ number_format($opdTodayPaid, 2) }}/{{ number_format($opdToday, 2) }}</span>
             <span class="dincome-subtext">Today's Income</span>
           </div>
         </div>
       </a>
     </div>
     
     <div class="col-lg-3 col-md-6 col-sm-6">
       <a href="javascript:void(0)" onclick="viewModuleCharges('IPD')">
        <div class="dincome-card ipd-card">
         <span class="dincome-icon"><i class="fas fa-procedures"></i></span>
         <div class="dincome-content">
           <span class="dincome-text">IPD</span>
           <span class="dincome-number">Rs.{{ number_format($ipdTodayPaid, 2) }}/{{ number_format($ipdToday, 2) }}</span>
           <span class="dincome-subtext">Today's Income</span>
         </div>
       </div>
     </a>
   </div>
   
   <div class="col-lg-3 col-md-6 col-sm-6">
     <a href="javascript:void(0)" onclick="viewModuleCharges('DCD')">
      <div class="dincome-card dcd-card">
       <span class="dincome-icon"><i class="fas fa-hospital-user"></i></span>
       <div class="dincome-content">
         <span class="dincome-text">DCD</span>
         <span class="dincome-number">Rs.{{ number_format($dcdTodayPaid, 2) }}/{{ number_format($dcdToday, 2) }}</span>
         <span class="dincome-subtext">Today's Income</span>
       </div>
     </div>
   </a>
 </div>
 
 <div class="col-lg-3 col-md-6 col-sm-6">
   <a href="javascript:void(0)" onclick="viewModuleCharges('OTHERS')">
    <div class="dincome-card pharma-card">
     <span class="dincome-icon"><i class="fas fa-briefcase-medical"></i></span>
     <div class="dincome-content">
       <span class="dincome-text">OTHERS</span>
       <span class="dincome-number">Rs.{{ number_format($othersTodayPaid, 2) }}/{{ number_format($othersToday, 2) }}</span>
       <span class="dincome-subtext">Today's Income</span>
     </div>
   </div>
 </a>
</div>
</div>

<!-- Charts -->
<div class="row section-box">
 <div class="col-lg-7">
  <div class="hchartbox">
   <div id="dyi-chart" style="height: 370px; width: 100%;"></div>
 </div>
</div>
<div class="col-lg-5">
 <div class="hchartbox">
   <div id="dmi-chart" style="height: 370px; width: 100%;"></div>
 </div>
</div>
</div>

<!-- Daily Income Chart - Current Month -->
<div class="row section-box">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h4><i class="fas fa-chart-line"></i> Daily Income - {{ now()->format('F Y') }}</h4>
      </div>
      <div class="card-body">
        <canvas id="dailyIncomeChart" style="height: 300px;"></canvas>
      </div>
    </div>
  </div>
</div>

@include('dashboard.partials.discharge-approvals')

<!-- Today's Working Staff -->
@if($presentStaff->count() > 0)
<div class="row section-box">
  <div class="col-12 col-lg-12">
    <div class="card toprated-doctor">
      <div class="card-header">
        <h4><i class="fas fa-user-friends"></i> Today's Present Staff ({{ $presentStaff->count() }})</h4>
      </div>
      <div class="card-body">
        <div id="present-staff-slider" class="ratedoctor-slide">
          @foreach($presentStaff as $staff)
          <div>
            <div class="staff-slide-box">
              <div class="staff-img">
                @if(!empty($staff->photo) && \App\Support\Uploads::exists('staffmembers', $staff->photo))
                  <img src="{{ \App\Support\Uploads::url('staffmembers', $staff->photo) }}" alt="{{ $staff->staff_name }}">
                @else
                  <img src="{{ asset('assets/images/no_image.png') }}" alt="{{ $staff->staff_name }}">
                @endif
              </div>
              <div class="staff-detail">
                <h4>{{ $staff->staff_name }}</h4>
                <span>{{ $staff->designation ?? 'Staff' }}</span>
                <p>{{ $staff->mobile_number }}</p>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endif

</div>
</div>
<!-- theme body end-->
</div>

<!-- Module Charges Modal -->
<div class="modal fade" id="moduleChargesModal">
	<div class="modal-dialog modal-fullscreen">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="moduleChargesModalTitle">Today's Charges</h5>
				<a class="close-modal" href="javascript:void(0);" data-bs-dismiss="modal"><i class="ti-close"></i></a>
			</div>
			<div class="modal-body">
				<div class="table-responsive">
					<table id="module_charges_tbl" class="table table-bordered table-sm table-text-sm">
						<thead>
							<tr>
								<th>No</th>
								<th>Date Time</th>
								<th>Patient</th>
								<th>Department</th>
								<th>Title</th>
								<th>Total</th>
								<th>Paid</th>
								<th>Payment Mode</th>
							</tr>
						</thead>
						<tbody></tbody>
						<tfoot>
							<tr>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th>Total:</th>
								<th class="aright"></th>
								<th class="aright"></th>
								<th></th>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
  // Initialize tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();
  
  $('#present-staff-slider').slick({
    infinite: true,
    slidesToShow: 5,
    slidesToScroll: 1,
    dots: true,
    arrows: false,
    autoplay: true,
    autoplaySpeed: 3000,
    fadeSpeed: 1000,
    responsive: [
    {
      breakpoint: 1600,
      settings: {
        slidesToShow: 3,
        slidesToScroll: 1,
      }
    },
    {
      breakpoint: 1400,
      settings: {
        slidesToShow: 2,
        slidesToScroll: 1,
      }
    }
    ]
  });
});

window.onload = function () {
  var chart = new CanvasJS.Chart("dyi-chart", {
   animationEnabled: true,
		theme: "light1",
		title:{
			text: "Yearly Income Chart - {{ $currentYear }}"
		},
		axisY: {
			title: "Amount (Rs.)",
			labelFormatter: function(e) {
				return "Rs." + (e.value / 1000) + "K";
			}
		},
		data: [{        
			type: "column",  
			showInLegend: true, 
			legendMarkerColor: "grey",
			legendText: "Monthly Income",
			dataPoints: [      
        { y: {{ $monthlyData[0] }}, label: "Jan" },
        { y: {{ $monthlyData[1] }}, label: "Feb" },
        { y: {{ $monthlyData[2] }}, label: "Mar" },
        { y: {{ $monthlyData[3] }}, label: "Apr" },
        { y: {{ $monthlyData[4] }}, label: "May" },
        { y: {{ $monthlyData[5] }}, label: "Jun" },
        { y: {{ $monthlyData[6] }}, label: "Jul" },
        { y: {{ $monthlyData[7] }}, label: "Aug" },
        { y: {{ $monthlyData[8] }}, label: "Sep" },
        { y: {{ $monthlyData[9] }}, label: "Oct" },
        { y: {{ $monthlyData[10] }}, label: "Nov" },
        { y: {{ $monthlyData[11] }}, label: "Dec" },
        ]
		}]
	});
  chart.render();

  var dmi_options = {
   title: {
    text: "Today's Income Distribution"
  },
  subtitles: [{
    text: "{{ now()->format('d M Y') }}"
  }],
  animationEnabled: true,
  data: [{
    type: "pie",
    startAngle: 40,
    toolTipContent: "<b>{name}</b>: Rs.{amount}<br/>({y}%)",
    showInLegend: "true",
    legendText: "{name}",
    indexLabelFontSize: 10,
    indexLabel: "{name} - {y}%",
    dataPoints: [
     @php $has_data = false; @endphp
     @if($opdPercent > 0)
       @if($has_data),@endif
       { y: {{ $opdPercent }}, name: "OPD", amount: {{ $opdToday }} }
       @php $has_data = true; @endphp
     @endif
     @if($ipdPercent > 0)
       @if($has_data),@endif
       { y: {{ $ipdPercent }}, name: "IPD", amount: {{ $ipdToday }} }
       @php $has_data = true; @endphp
     @endif
     @if($dcdPercent > 0)
       @if($has_data),@endif
       { y: {{ $dcdPercent }}, name: "DCD", amount: {{ $dcdToday }} }
       @php $has_data = true; @endphp
     @endif
     @if($othersPercent > 0)
       @if($has_data),@endif
       { y: {{ $othersPercent }}, name: "Others", amount: {{ $othersToday }} }
       @php $has_data = true; @endphp
     @endif
     @if(!$has_data)
       { y: 100, name: "No Data", amount: 0 }
     @endif
     ]
  }]
};
$("#dmi-chart").CanvasJSChart(dmi_options);

// Chart.js - Daily Income Chart
var ctx = document.getElementById('dailyIncomeChart').getContext('2d');
var dailyIncomeChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: [@for($i = 1; $i <= $daysInMonth; $i++){{ $i }}@if($i < $daysInMonth),@endif @endfor],
    datasets: [
      {
        label: 'OPD',
        data: [{{ implode(',', $dailyOpd) }}],
        borderColor: '#28a745',
        backgroundColor: 'rgba(40, 167, 69, 0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 5
      },
      {
        label: 'IPD',
        data: [{{ implode(',', $dailyIpd) }}],
        borderColor: '#007bff',
        backgroundColor: 'rgba(0, 123, 255, 0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 5
      },
      {
        label: 'DCD',
        data: [{{ implode(',', $dailyDcd) }}],
        borderColor: '#6f42c1',
        backgroundColor: 'rgba(111, 66, 193, 0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 5
      },
      {
        label: 'OTHERS',
        data: [{{ implode(',', $dailyOthers) }}],
        borderColor: '#fd7e14',
        backgroundColor: 'rgba(253, 126, 20, 0.1)',
        borderWidth: 2,
        tension: 0.4,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 5
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: {
        display: true,
        position: 'top',
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            var label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            label += 'Rs. ' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            return label;
          }
        }
      }
    },
    scales: {
      x: {
        display: true,
        title: {
          display: true,
          text: 'Day of Month'
        },
        grid: {
          display: false
        }
      },
      y: {
        display: true,
        title: {
          display: true,
          text: 'Income (Rs.)'
        },
        beginAtZero: true,
        ticks: {
          callback: function(value) {
            return 'Rs. ' + value.toLocaleString('en-IN');
          }
        }
      }
    }
  }
});
}

// View Module Charges
function viewModuleCharges(module) {
  $('#moduleChargesModalTitle').text(module + " - Today's Charges");
  $('#moduleChargesModal').modal('show');
  
  if ($.fn.DataTable.isDataTable('#module_charges_tbl')) {
    $('#module_charges_tbl').DataTable().destroy();
  }
  
  $('#module_charges_tbl').DataTable({
    ajax: {
      url: '{{ route("dashboard.module-charges") }}',
      type: 'GET',
      data: { module: module }
    },
    columns: [
      { data: 'id' },
      { data: 'charge_date_time' },
      { data: 'patient_name' },
      { data: 'department_name' },
      { data: 'charge_title' },
      { data: 'total', render: $.fn.dataTable.render.number(',', '.', 2, 'Rs.') },
      { data: 'paid_amount', render: $.fn.dataTable.render.number(',', '.', 2, 'Rs.') },
      { data: 'payment_mode' }
    ],
    footerCallback: function(row, data, start, end, display) {
      var api = this.api();
      var totalAmount = api.column(5, { page: 'current' }).data().reduce(function(a, b) {
        return parseFloat(a) + parseFloat(b);
      }, 0);
      var paidAmount = api.column(6, { page: 'current' }).data().reduce(function(a, b) {
        return parseFloat(a) + parseFloat(b);
      }, 0);
      $(api.column(5).footer()).html('Rs.' + totalAmount.toFixed(2));
      $(api.column(6).footer()).html('Rs.' + paidAmount.toFixed(2));
    }
  });
}
</script>
@endpush
