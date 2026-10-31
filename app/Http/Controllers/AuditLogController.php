<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Read-only audit log viewer — Admin only (im_audit_logs permission).
 *
 * Three endpoints:
 *   GET  /audit-logs          → Blade view (filters + DataTable)
 *   POST /audit-logs/data     → DataTables JSON source
 *   GET  /audit-logs/export   → CSV download of current filter
 */
class AuditLogController extends Controller
{
    public function index(PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('im_audit_logs'), 403);

        return view('audit.index');
    }

    /**
     * DataTables server-side JSON endpoint.
     * Accepts the same filter params as the CSV export.
     */
    public function data(Request $request, PermissionService $permissions): JsonResponse
    {
        abort_unless($permissions->hasPermission('im_audit_logs'), 403);

        $query = $this->buildQuery($request);

        $total    = AuditLog::count();
        $filtered = (clone $query)->count();

        // Sorting
        $cols = [
            0 => 'created_at',
            1 => 'staff_name',
            2 => 'event_category',
            3 => 'action',
            4 => 'resource_type',
            5 => 'resource_id',
            6 => 'ip_address',
            7 => 'status',
        ];
        $colIdx = $request->integer('order.0.column', 0);
        $orderBy = $cols[$colIdx] ?? 'created_at';
        $dir     = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

        $rows = $query
            ->orderBy($orderBy, $dir)
            ->skip($request->integer('start', 0))
            ->take(max(1, $request->integer('length', 25)))
            ->get();

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $rows->map(fn ($r) => [
                'id'             => $r->id,
                'created_at'     => $r->created_at ? $r->created_at->format('d-m-Y H:i:s') : '',
                'staff_name'     => e((string) ($r->staff_name ?? '—')),
                'role_id'        => $r->role_id,
                'event_category' => $r->event_category,
                'action'         => $r->action,
                'resource_type'  => $r->resource_type ?? '',
                'resource_id'    => e((string) ($r->resource_id ?? '')),
                'description'    => e((string) ($r->description ?? '')),
                'ip_address'     => e($r->ip_address),
                'status'         => $r->status,
                // Only sent when the row is expanded — keeps payload small otherwise.
                'old_values'     => $r->old_values ? json_encode($r->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
                'new_values'     => $r->new_values ? json_encode($r->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
                'user_agent'     => e((string) ($r->user_agent ?? '')),
                'request_method' => $r->request_method ?? '',
                'request_url'    => e((string) ($r->request_url ?? '')),
            ]),
        ]);
    }

    /**
     * Stream a CSV of the current filtered result-set (no pagination limit).
     */
    public function export(Request $request, PermissionService $permissions): Response
    {
        abort_unless($permissions->hasPermission('im_audit_logs'), 403);

        $rows = $this->buildQuery($request)->orderBy('created_at', 'desc')->get();

        $filename = 'audit_log_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ];

        $csvHeaders = [
            'ID', 'Timestamp', 'Staff ID', 'Staff Name', 'Role ID',
            'Category', 'Action', 'Resource Type', 'Resource ID',
            'Description', 'IP Address', 'Status',
            'Request Method', 'Request URL', 'User Agent',
            'Old Values', 'New Values',
        ];

        $body = implode(',', array_map([$this, 'csvCell'], $csvHeaders)) . "\n";

        foreach ($rows as $r) {
            $body .= implode(',', array_map([$this, 'csvCell'], [
                $r->id,
                $r->created_at ? $r->created_at->format('Y-m-d H:i:s.v') : '',
                $r->staff_id ?? '',
                $r->staff_name ?? '',
                $r->role_id ?? '',
                $r->event_category,
                $r->action,
                $r->resource_type ?? '',
                $r->resource_id ?? '',
                $r->description ?? '',
                $r->ip_address,
                $r->status,
                $r->request_method ?? '',
                $r->request_url ?? '',
                $r->user_agent ?? '',
                $r->old_values ? json_encode($r->old_values, JSON_UNESCAPED_UNICODE) : '',
                $r->new_values ? json_encode($r->new_values, JSON_UNESCAPED_UNICODE) : '',
            ])) . "\n";
        }

        return response($body, 200, $headers);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Build the base query from the shared filter parameters.
     * Used by both data() and export().
     */
    private function buildQuery(Request $request)
    {
        $query = AuditLog::query();

        // Date range — accepts both d-m-Y (flatpickr) and Y-m-d (ISO fallback)
        if ($from = $request->input('filter_from')) {
            $parsed = \DateTime::createFromFormat('d-m-Y', $from) ?: \DateTime::createFromFormat('Y-m-d', $from);
            if ($parsed) {
                $query->where('created_at', '>=', $parsed->format('Y-m-d') . ' 00:00:00');
            }
        }
        if ($to = $request->input('filter_to')) {
            $parsed = \DateTime::createFromFormat('d-m-Y', $to) ?: \DateTime::createFromFormat('Y-m-d', $to);
            if ($parsed) {
                $query->where('created_at', '<=', $parsed->format('Y-m-d') . ' 23:59:59');
            }
        }

        // Dropdowns
        if ($cat = $request->input('filter_category')) {
            $query->where('event_category', $cat);
        }
        if ($action = $request->input('filter_action')) {
            $query->where('action', $action);
        }
        if ($status = $request->input('filter_status')) {
            $query->where('status', $status);
        }
        if ($resourceType = $request->input('filter_resource_type')) {
            $query->where('resource_type', $resourceType);
        }

        // Free-text search across staff name, description, IP, resource_id
        if ($search = $request->input('search.value', $request->input('filter_search', ''))) {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('staff_name',   'like', $s)
                  ->orWhere('description', 'like', $s)
                  ->orWhere('ip_address',  'like', $s)
                  ->orWhere('resource_id', 'like', $s);
            });
        }

        return $query;
    }

    /** Wrap a value for safe CSV output. */
    private function csvCell(mixed $value): string
    {
        $v = (string) $value;
        if (str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n")) {
            return '"' . str_replace('"', '""', $v) . '"';
        }

        return $v;
    }
}
