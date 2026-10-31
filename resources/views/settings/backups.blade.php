@extends('layouts.app')
@section('title', 'DB Backups')
@push('styles')
<style>
/* ── Backup page – stat cards ───────────────────────────────── */
.backup-stat-card        { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; height: 100%; }
.backup-stat-icon        { flex: 0 0 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.backup-stat-icon.icon-total   { background: #eaf0fb; color: #3b6fc9; }
.backup-stat-icon.icon-latest  { background: #e8f5e9; color: #2e7d32; }
.backup-stat-icon.icon-storage { background: #fff8e1; color: #f57f17; }
.backup-stat-label       { font-size: 12px; color: #6b7280; font-weight: 500; margin-bottom: 2px; }
.backup-stat-value       { font-size: 20px; font-weight: 700; color: #1a3a5c; line-height: 1.2; }
.backup-stat-value.small-val   { font-size: 13px; font-weight: 600; }
.backup-stat-value.no-backup   { font-size: 13px; font-weight: 500; color: #9ca3af; }
.backup-stat-size        { font-size: 11px; color: #6b7280; }
.backup-storage-ok       { color: #2e7d32; }
/* ── Table styling ───────────────────────────────────────────── */
.backup-table-wrap { border: 1px solid #e3e8ef; border-radius: 8px; overflow: hidden; background: #fff; }
.backup-tbl        { width: 100%; border-collapse: collapse; font-size: 13px; }
.backup-tbl thead tr  { background: #f4f7fb; }
.backup-tbl thead th  { padding: 10px 14px; font-weight: 600; color: #374151; border-bottom: 1px solid #e3e8ef; white-space: nowrap; }
.backup-tbl tbody tr  { border-bottom: 1px solid #f0f3f7; transition: background .12s; }
.backup-tbl tbody tr:last-child { border-bottom: none; }
.backup-tbl tbody tr:hover      { background: #f8fafc; }
.backup-tbl td        { padding: 11px 14px; vertical-align: middle; color: #374151; }
.backup-tbl .col-actions { width: 180px; }
.backup-filename     { font-family: 'Consolas', 'Menlo', monospace; font-size: 12px; color: #3b6fc9; background: #eaf0fb; padding: 1px 6px; border-radius: 4px; word-break: break-all; }
.backup-date-badge   { font-weight: 600; color: #1a3a5c; font-size: 12px; }
.backup-cal-icon     { font-size: 12px; }
.backup-created-at   { color: #374151; }
.size-badge          { display: inline-block; background: #f4f7fb; border: 1px solid #d9e2ef; border-radius: 5px; padding: 2px 9px; font-size: 12px; font-weight: 600; color: #374151; }
/* ── Empty state ─────────────────────────────────────────────── */
.backup-empty-state   { text-align: center; padding: 52px 20px; color: #6b7280; }
.backup-empty-state i { font-size: 44px; color: #c5d0de; margin-bottom: 14px; display: block; }
.backup-empty-state p { margin-bottom: 18px; font-size: 14px; }
</style>
@endpush
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">

                {{-- ── Page header ──────────────────────────────────── --}}
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title"><i class="fa fa-database me-2 text-primary"></i>Database Backups</div>
                    <div class="tbl-top-buttons">
                        <ul>
                            <li>
                                <a href="javascript:void(0)" id="btn-create-backup">
                                    <i class="fa fa-plus-circle"></i> Create Backup Now
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('settings.index', [], true) }}#tab-backups">
                                    <i class="fa fa-cog"></i> Hospital Settings
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- ── Flash messages ───────────────────────────────── --}}
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="fa fa-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- ── Stat cards ───────────────────────────────────── --}}
                <div class="row g-3 mt-3 mb-4">
                    <div class="col-sm-4">
                        <div class="backup-stat-card">
                            <div class="backup-stat-icon icon-total"><i class="fa fa-copy"></i></div>
                            <div>
                                <div class="backup-stat-label">Total Backups</div>
                                <div class="backup-stat-value">{{ $stats['total_backups'] ?? count($backups) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="backup-stat-card">
                            <div class="backup-stat-icon icon-latest"><i class="fa fa-clock"></i></div>
                            <div>
                                <div class="backup-stat-label">Most Recent Backup</div>
                                @if(!empty($stats['latest_backup']))
                                            <div class="backup-stat-value small-val">{{ $stats['latest_backup']['formatted_time'] }}</div>
                                            <div class="backup-stat-size">{{ $stats['latest_backup']['file_size'] }}</div>
                                        @else
                                            <div class="backup-stat-value no-backup">No backups yet</div>
                                        @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="backup-stat-card">
                            <div class="backup-stat-icon icon-storage"><i class="fa fa-shield-alt"></i></div>
                            <div>
                                <div class="backup-stat-label">Storage Status</div>
                                <div class="backup-stat-value small-val backup-storage-ok">
                                    <i class="fa fa-check-circle me-1"></i>Protected
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Backups table ─────────────────────────────────── --}}
                @if(count($backups) > 0)
                    <div class="backup-table-wrap">
                        <div class="table-responsive">
                            <table class="backup-tbl" id="backupTable">
                                <thead>
                                    <tr>
                                        <th><i class="fa fa-file-archive me-1 text-warning"></i> DB File</th>
                                        <th><i class="fa fa-weight me-1 text-muted"></i> Size</th>
                                        <th><i class="fa fa-clock me-1 text-muted"></i> Created At</th>
                                        <th class="text-center col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($backups as $backup)
                                    <tr>
                                        <td>
                                            <div class="backup-date-badge mb-1">
                                                <i class="fa fa-calendar-alt me-1 text-primary backup-cal-icon"></i>
                                                {{ $backup['display_date'] }}
                                            </div>
                                            <div><span class="backup-filename">{{ $backup['filename'] }}</span></div>
                                        </td>
                                        <td>
                                            <span class="size-badge">{{ $backup['file_size'] }}</span>
                                        </td>
                                        <td>
                                            <span class="backup-created-at">{{ $backup['formatted_time'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <button type="button"
                                                        class="btn btn-primary btn-sm btn-download-backup"
                                                        data-filename="{{ $backup['filename'] }}"
                                                        data-url="{{ route('db-backups.verify-download', ['filename' => $backup['filename']]) }}"
                                                        title="Download {{ $backup['filename'] }}">
                                                    <i class="fa fa-download me-1"></i> Download
                                                </button>
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-sm btn-delete-backup"
                                                        data-filename="{{ $backup['filename'] }}"
                                                        title="Delete backup">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                @else
                    <div class="backup-table-wrap">
                        <div class="backup-empty-state">
                            <i class="fa fa-database"></i>
                            <p>No backup files found in storage.</p>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-create-backup-empty">
                                <i class="fa fa-plus-circle me-1"></i> Create First Backup
                            </button>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {

    // ── Create backup — Swal password prompt ───────────────────────────────────
    function doCreateBackup() {
        Swal.fire({
            title: 'Admin Password Required',
            input: 'password',
            inputPlaceholder: 'Enter admin password',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-key me-1"></i> Verify & Backup',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#1a3a5c',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: function (password) {
                if (!password) { Swal.showValidationMessage('Please enter the admin password.'); return false; }
                return $.ajax({
                    url: '{{ route('db-backups.verify-password') }}',
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', password: password },
                    dataType: 'json'
                }).then(function (r) { return r; }, function (xhr) {
                    Swal.showValidationMessage(xhr.responseJSON?.message || 'Incorrect password.');
                });
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            showLoading();
            $.ajax({
                url: '{{ route('db-backups.store') }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function (resp) {
                    Swal.close();
                    if (resp.success) {
                        Swal.fire({ icon: 'success', title: 'Backup Completed', text: resp.message || 'Database backup created successfully.', confirmButtonText: 'OK' })
                            .then(function () { window.location.reload(); });
                    } else {
                        Swal.fire('Error', resp.message || 'Failed to create backup.', 'error');
                    }
                },
                error: function (xhr) {
                    Swal.close();
                    Swal.fire('Error', xhr.responseJSON?.message || 'Server error while generating backup.', 'error');
                }
            });
        });
    }

    $('#btn-create-backup, #btn-create-backup-empty').on('click', function () { doCreateBackup(); });

    // ── Download backup — Swal password prompt ─────────────────────────────────
    $(document).on('click', '.btn-download-backup', function () {
        var filename = $(this).data('filename');
        var url      = $(this).data('url');
        if (!filename || !url) return;

        Swal.fire({
            title: 'Admin Password Required',
            html: '<p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Enter the admin password to download<br><code style="font-size:11px;">' + filename + '</code></p>',
            input: 'password',
            inputPlaceholder: 'Enter admin password',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true,
            confirmButtonText: '<i class="fa fa-download me-1"></i> Verify & Download',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#1a3a5c',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            preConfirm: function (password) {
                if (!password) { Swal.showValidationMessage('Please enter the admin password.'); return false; }
                return $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}', password: password },
                    xhrFields: { responseType: 'blob' }
                }).then(function (blob, status, xhr) {
                    return { blob: blob, xhr: xhr };
                }, function (xhr) {
                    var msg = 'Incorrect password.';
                    if (xhr.status === 403) { msg = 'Incorrect admin password.'; }
                    else if (xhr.status === 404) { msg = 'Backup file not found.'; }
                    Swal.showValidationMessage(msg);
                });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;
            var contentDisposition = result.value.xhr.getResponseHeader('Content-Disposition') || '';
            var nameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            var dlName = nameMatch ? nameMatch[1].replace(/['"]/g, '') : filename;
            var objectUrl = URL.createObjectURL(result.value.blob);
            var a = document.createElement('a');
            a.href = objectUrl;
            a.download = dlName;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(objectUrl);
        });
    });

    // ── Delete backup ──────────────────────────────────────────────────────────
    $(document).on('click', '.btn-delete-backup', function () {
        var filename = $(this).data('filename');
        if (!filename) return;

        Swal.fire({
            title: 'Delete Backup?',
            html: 'Are you sure you want to delete<br><code style="font-size:12px;">' + filename + '</code>?<br><span style="color:#ef4444;font-size:13px;">This action cannot be undone.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fa fa-trash me-1"></i> Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Deleting…',
                text: 'Please wait.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: '{{ url('db-backups') }}/' + encodeURIComponent(filename),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function (resp) {
                    if (resp.success) {
                        Swal.fire({ icon: 'success', title: 'Deleted', text: resp.message, timer: 1200, showConfirmButton: false })
                            .then(() => { window.location.reload(); });
                    } else {
                        Swal.fire('Error', resp.message, 'error');
                    }
                },
                error: function (xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete backup.', 'error');
                }
            });
        });
    });
});
</script>
@endpush
