<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHI audit log entry — HDMP §9 compliant.
 *
 * Rows are immutable: no update, no soft-delete, no updated_at.
 * The table has millisecond-precision created_at; we suppress
 * Laravel's default timestamp management and set it ourselves.
 */
class AuditLog extends Model
{
    /** Disable updated_at; we manage created_at manually for ms precision. */
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'staff_id',
        'staff_name',
        'role_id',
        'event_category',
        'action',
        'resource_type',
        'resource_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'status',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s.v',
    ];
}
