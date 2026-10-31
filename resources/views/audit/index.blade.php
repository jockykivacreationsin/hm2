@extends('layouts.app')

@section('title', 'PHI Audit Log')

@push('styles')
<style>
/* ── Category badges ──────────────────────────────────────── */
.cat-badge         { display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; white-space:nowrap; }
.cat-auth          { background:#e0f2fe; color:#075985; }
.cat-patient       { background:#fce7f3; color:#9d174d; }
.cat-opd           { background:#ede9fe; color:#5b21b6; }
.cat-ipd           { background:#fef3c7; color:#92400e; }
.cat-dcd           { background:#d1fae5; color:#065f46; }
.cat-backup        { background:#fee2e2; color:#991b1b; }
.cat-pdf           { background:#fef9c3; color:#713f12; }
.cat-roles         { background:#e0e7ff; color:#3730a3; }
.cat-other         { background:#f3f4f6; color:#374151; }

/* ── Status badges ────────────────────────────────────────── */
.status-badge      { display:inline-block; font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; white-space:nowrap; }
.st-success        { background:#d1fae5; color:#065f46; }
.st-failure        { background:#fee2e2; color:#991b1b; }
.st-blocked        { background:#fef3c7; color:#92400e; }

/* ── Action code links ────────────────────────────────────── */
#auditTable code   { font-size:11px; color:#9d174d; background:none; padding:0; }

/* ── Expand button ────────────────────────────────────────── */
.expand-btn        { background:none; border:none; padding:0 2px; color:#adb5bd; cursor:pointer; font-size:12px; line-height:1; }
.expand-btn:hover  { color:#495057; }

/* ── Detail sub-row ──────────────────────────────────────── */
.audit-detail-wrap { padding: 12px 16px; background: #f8fafc; }
.audit-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.audit-detail-grid .full { grid-column: 1 / -1; }
.audit-detail-grid label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#6b7280; display:block; margin-bottom:3px; }
.audit-detail-grid p     { font-size:12px; color:#374151; margin:0; word-break:break-all; }
.audit-detail-grid pre   { font-size:11px; background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:8px; white-space:pre-wrap; word-break:break-all; max-height:180px; overflow-y:auto; margin:0; }

/* ── Immutable notice pill ────────────────────────────────── */
.audit-immutable { font-size:11px; color:#6b7280; display:inline-flex; align-items:center; gap:5px; }
.audit-immutable i { color:#f59e0b; }
</style>
@endpush

@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">

                {{-- ── Title + filter bar ── --}}
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">
                        <i class="fas fa-shield-alt me-1" style="color:#6c757d;"></i> PHI Audit Log
                    </div>
                    <div class="tbl-filter-box">

                        <div class="local-forms">
                            <label>From</label>
                            <input type="text" id="filterFrom" class="form-control datepicker"
                                   value="{{ date('d-m-Y', strtotime('-30 days')) }}"
                                   placeholder="dd-mm-yyyy" autocomplete="off">
                        </div>

                        <div class="local-forms">
                            <label>To</label>
                            <input type="text" id="filterTo" class="form-control datepicker"
                                   value="{{ date('d-m-Y') }}"
                                   placeholder="dd-mm-yyyy" autocomplete="off">
                        </div>

                        <div class="local-forms">
                            <label>Category</label>
                            <select id="filterCategory" class="form-control">
                                <option value="">All</option>
                                <option value="auth">Auth</option>
                                <option value="patient">Patient</option>
                                <option value="opd">OPD</option>
                                <option value="ipd">IPD</option>
                                <option value="dcd">DCD</option>
                                <option value="backup">Backup</option>
                                <option value="pdf">PDF</option>
                                <option value="roles">Roles</option>
                            </select>
                        </div>

                        <div class="local-forms">
                            <label>Action</label>
                            <select id="filterAction" class="form-control">
                                <option value="">All</option>
                                <option value="login_success">login_success</option>
                                <option value="login_failure">login_failure</option>
                                <option value="login_blocked">login_blocked</option>
                                <option value="logout">logout</option>
                                <option value="view">view</option>
                                <option value="create">create</option>
                                <option value="update">update</option>
                                <option value="delete">delete</option>
                                <option value="download">download</option>
                                <option value="permission_change">permission_change</option>
                            </select>
                        </div>

                        <div class="local-forms">
                            <label>Status</label>
                            <select id="filterStatus" class="form-control">
                                <option value="">All</option>
                                <option value="success">Success</option>
                                <option value="failure">Failure</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>

                        <div class="local-forms">
                            <label>Resource</label>
                            <select id="filterResourceType" class="form-control">
                                <option value="">All</option>
                                <option value="patient">patient</option>
                                <option value="opd">opd</option>
                                <option value="ipd">ipd</option>
                                <option value="dcd">dcd</option>
                                <option value="pdf_file">pdf_file</option>
                                <option value="backup_file">backup_file</option>
                                <option value="role">role</option>
                            </select>
                        </div>

                        <div class="tbl-top-buttons">
                            <ul>
                                <li>
                                    <a id="csvExportBtn" href="javascript:void(0)"
                                       title="Export filtered results as CSV">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </a>
                                </li>
                                <li>
                                    <span class="audit-immutable">
                                        <i class="fas fa-lock"></i> Read-only
                                    </span>
                                </li>
                            </ul>
                        </div>

                    </div>{{-- /.tbl-filter-box --}}
                </div>{{-- /.ipd-title-with-buttons --}}

                {{-- ── Table ── --}}
                <div class="sm-table mt-10">
                    <div class="table-responsive">
                        <table id="auditTable" class="table table-bordered table-sm table-text-sm">
                            <thead>
                                <tr>
                                    <th class="no-sort" style="width:22px;"></th>
                                    <th>Timestamp</th>
                                    <th>Staff</th>
                                    <th>Category</th>
                                    <th>Action</th>
                                    <th>Resource</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>{{-- /.sm-table --}}

            </div>{{-- /.row.ipd-patient-consultant-row --}}
        </div>{{-- /.card-body --}}
    </div>{{-- /.card-ipd --}}
</div>{{-- /.themebody-wrap --}}
@endsection

@push('scripts')
<script>
$(function () {

    var ft;    // debounce timer for filter changes
    var table; // DataTable instance

    /* ── Flatpickr date pickers ────────────────────────── */
    if (typeof flatpickr !== 'undefined') {
        flatpickr('#filterFrom', {
            enableTime  : false,
            dateFormat  : 'd-m-Y',
            allowInput  : true,
            onChange    : function () { clearTimeout(ft); ft = setTimeout(function () { if (table) table.ajax.reload(); }, 300); }
        });
        flatpickr('#filterTo', {
            enableTime  : false,
            dateFormat  : 'd-m-Y',
            allowInput  : true,
            onChange    : function () { clearTimeout(ft); ft = setTimeout(function () { if (table) table.ajax.reload(); }, 300); }
        });
    }

    var catClass = {
        auth: 'cat-auth', patient: 'cat-patient', opd: 'cat-opd',
        ipd: 'cat-ipd', dcd: 'cat-dcd', backup: 'cat-backup',
        pdf: 'cat-pdf', roles: 'cat-roles'
    };

    /* ── DataTable ─────────────────────────────────────── */
    table = $('#auditTable').DataTable({
        processing : true,
        serverSide : true,
        pageLength : 25,
        order      : [[1, 'desc']],
        ajax: {
            url    : @json(route('audit-logs.data')),
            type   : 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: function (d) {
                d.filter_from          = $('#filterFrom').val();
                d.filter_to            = $('#filterTo').val();
                d.filter_category      = $('#filterCategory').val();
                d.filter_action        = $('#filterAction').val();
                d.filter_status        = $('#filterStatus').val();
                d.filter_resource_type = $('#filterResourceType').val();
            }
        },
        columns: [
            /* 0  expand */
            {
                data: null, orderable: false, searchable: false,
                render: function () {
                    return '<button class="expand-btn" title="Details"><i class="fas fa-chevron-right"></i></button>';
                }
            },
            /* 1  timestamp */
            {
                data: 'created_at',
                render: function (d) { return '<span style="white-space:nowrap;font-size:12px;">' + d + '</span>'; }
            },
            /* 2  staff */
            {
                data: 'staff_name',
                render: function (d, _, row) {
                    var role = row.role_id ? ' <span style="font-size:10px;color:#adb5bd;">#' + row.role_id + '</span>' : '';
                    return '<span style="white-space:nowrap;">' + d + role + '</span>';
                }
            },
            /* 3  category */
            {
                data: 'event_category',
                render: function (d) {
                    return '<span class="cat-badge ' + (catClass[d] || 'cat-other') + '">' + d + '</span>';
                }
            },
            /* 4  action */
            {
                data: 'action',
                render: function (d) { return '<code>' + d + '</code>'; }
            },
            /* 5  resource */
            {
                data: 'resource_type',
                render: function (d, _, row) {
                    if (!d) return '<span style="color:#adb5bd;">—</span>';
                    var id = row.resource_id ? ' <span style="color:#6b7280;font-size:11px;">#' + row.resource_id + '</span>' : '';
                    return '<span style="font-size:12px;">' + d + id + '</span>';
                }
            },
            /* 6  description */
            {
                data: 'description',
                render: function (d) { return '<span style="font-size:12px;">' + (d || '') + '</span>'; }
            },
            /* 7  IP */
            {
                data: 'ip_address',
                render: function (d) { return '<code style="font-size:11px;color:#9d174d;">' + d + '</code>'; }
            },
            /* 8  status */
            {
                data: 'status',
                render: function (d) {
                    var cls = { success: 'st-success', failure: 'st-failure', blocked: 'st-blocked' }[d] || '';
                    return '<span class="status-badge ' + cls + '">' + d + '</span>';
                }
            }
        ],
        language: {
            processing : '<i class="fas fa-spinner fa-spin"></i> Loading…',
            emptyTable : 'No audit events match the current filters.'
        }
    });

    /* ── Reload on filter change (dropdowns only — dates handled by flatpickr onChange) ── */
    $('#filterCategory, #filterAction, #filterStatus, #filterResourceType')
        .on('change input', function () {
            clearTimeout(ft);
            ft = setTimeout(function () { table.ajax.reload(); }, 300);
        });

    /* ── CSV export with current filters ──────────────── */
    $('#csvExportBtn').on('click', function () {
        var p = new URLSearchParams({
            filter_from          : $('#filterFrom').val(),
            filter_to            : $('#filterTo').val(),
            filter_category      : $('#filterCategory').val(),
            filter_action        : $('#filterAction').val(),
            filter_status        : $('#filterStatus').val(),
            filter_resource_type : $('#filterResourceType').val(),
            filter_search        : $('#auditTable_filter input').val() || ''
        });
        window.location.href = @json(route('audit-logs.export')) + '?' + p.toString();
    });

    /* ── Expand / collapse detail row ─────────────────── */
    $('#auditTable tbody').on('click', '.expand-btn', function () {
        var tr  = $(this).closest('tr');
        var row = table.row(tr);
        var ico = $(this).find('i');

        if (row.child.isShown()) {
            row.child.hide();
            ico.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            var d = row.data();
            row.child(buildDetail(d)).show();
            ico.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    });

    function buildDetail(d) {
        var oldBlock = d.old_values
            ? '<pre>' + esc(d.old_values) + '</pre>'
            : '<p style="color:#adb5bd;">—</p>';
        var newBlock = d.new_values
            ? '<pre>' + esc(d.new_values) + '</pre>'
            : '<p style="color:#adb5bd;">—</p>';

        return '<tr><td colspan="9"><div class="audit-detail-wrap"><div class="audit-detail-grid">'
            + '<div><label>Request</label><p><strong>' + esc(d.request_method) + '</strong> ' + esc(d.request_url) + '</p></div>'
            + '<div><label>User Agent</label><p>' + (esc(d.user_agent) || '<span style="color:#adb5bd;">—</span>') + '</p></div>'
            + '<div><label>Old Values</label>' + oldBlock + '</div>'
            + '<div><label>New Values</label>' + newBlock + '</div>'
            + '</div></div></td></tr>';
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

});
</script>
@endpush
