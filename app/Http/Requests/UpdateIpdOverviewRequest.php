<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Save Changes" on the IPD overview.
 * Vitals, symptoms and findings live on the linked OPD row; there are no IPD-specific copies.
 */
class UpdateIpdOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionService::class)->hasPermission('b_ipd_save');
    }

    public function rules(): array
    {
        return [
            'height'       => ['nullable', 'string', 'max:255'],
            'weight'       => ['nullable', 'string', 'max:255'],
            'bp_systolic'  => ['nullable', 'integer', 'between:40,250'],
            'bp_diastolic' => ['nullable', 'integer', 'between:40,200'],
            'pulse'        => ['nullable', 'string', 'max:255'],
            'temperature'  => ['nullable', 'string', 'max:255'],
            'respiration'  => ['nullable', 'string', 'max:255'],
            'spo2'         => ['nullable', 'string', 'max:255'],
            'symptoms'     => ['nullable', 'string'],
            'remarks'      => ['nullable', 'string'],
        ];
    }

    /**
     * Column values for `opds`. Every column is NOT NULL, so blanks are written as ''.
     * BP is stored as "systolic/diastolic"; when only one side is given the other is 0 (as legacy did).
     */
    public function opdColumns(): array
    {
        $v = $this->validated();
        $text = fn (string $key): string => trim((string) ($v[$key] ?? ''));

        $systolic  = (int) ($v['bp_systolic'] ?? 0);
        $diastolic = (int) ($v['bp_diastolic'] ?? 0);
        $bp = ($systolic > 0 || $diastolic > 0) ? "{$systolic}/{$diastolic}" : '';

        return [
            'height'      => $text('height'),
            'weight'      => $text('weight'),
            'bp'          => $bp,
            'pulse'       => $text('pulse'),
            'temperature' => $text('temperature'),
            'respiration' => $text('respiration'),
            'spo2'        => $text('spo2'),
            // Summernote HTML, stored as-is (same as legacy and the other OPD/IPD pages).
            'symptoms'    => (string) ($v['symptoms'] ?? ''),
            'remarks'     => (string) ($v['remarks'] ?? ''),
        ];
    }
}
