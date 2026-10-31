{{-- Dashboard widgets: pending IPD / DCD discharge approvals (legacy dashboard.php). Vars: ipdDischargeApprovals, dcdDischargeApprovals. --}}
<!-- IPD & DCD Discharge Approvals (Two Columns Equal Height) -->
<div class="row section-box">
  <!-- IPD Discharge Approvals -->
  <div class="col-lg-6 col-md-12">
    <div class="card h-100">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0;"><i class="fas fa-clipboard-check"></i> IPD Discharge Approvals - Pending</h4>
        <a href="{{ route('ipd.discharge-approvals') }}" class="btn btn-sm btn-primary">View All</a>
      </div>
      <div class="card-body">
        @if($ipdDischargeApprovals->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead style="background: #f8f9fa;">
              <tr>
                <th>Admission Date</th>
                <th>Patient Name</th>
                <th>OPD No</th>
                <th>IPD No</th>
                <th style="width: 80px; text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($ipdDischargeApprovals as $approval)
              <tr>
                <td>{{ $approval->admission_date_time }}</td>
                <td><strong>{{ $approval->patient_name }}</strong></td>
                <td>{{ $approval->opd_id }}</td>
                <td>{{ $approval->ipd_id }}</td>
                <td style="text-align: center;">
                  <a href="{{ route('ipd.view-discharge-approval', $approval->id) }}" class="btn btn-xs btn-info" data-bs-toggle="tooltip" title="View Details">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div style="text-align: center; padding: 40px 0;">
          <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 15px;"></i>
          <p class="text-muted" style="font-size: 16px;">No pending IPD discharge approvals</p>
        </div>
        @endif
      </div>
    </div>
  </div>

  <!-- DCD Discharge Approvals -->
  <div class="col-lg-6 col-md-12">
    <div class="card h-100">
      <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0;"><i class="fas fa-clipboard-check"></i> DCD Discharge Approvals - Pending</h4>
        <a href="{{ route('dcd.discharge-approvals') }}" class="btn btn-sm btn-primary">View All</a>
      </div>
      <div class="card-body">
        @if($dcdDischargeApprovals->count() > 0)
        <div class="table-responsive">
          <table class="table table-sm">
            <thead style="background: #f8f9fa;">
              <tr>
                <th>Admission Date</th>
                <th>Patient Name</th>
                <th>OPD No</th>
                <th>DCD No</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($dcdDischargeApprovals as $approval)
              <tr>
                <td>{{ $approval->dcd_date_time }}</td>
                <td><strong>{{ $approval->patient_name }}</strong></td>
                <td>{{ $approval->opd_id }}</td>
                <td>{{ $approval->dcd_id }}</td>
                <td style="text-align: center;">
                  <a href="{{ route('dcd.view-discharge-approval', $approval->id) }}" class="btn btn-xs btn-info" data-bs-toggle="tooltip" title="View Details">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div style="text-align: center; padding: 40px 0;">
          <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 15px;"></i>
          <p class="text-muted" style="font-size: 16px;">No pending DCD discharge approvals</p>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
