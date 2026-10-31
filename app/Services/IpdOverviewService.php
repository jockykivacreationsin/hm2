<?php

namespace App\Services;

use App\Models\Ipd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds everything the IPD overview page shows, mirroring legacy bmhabdm/ipd-overview.php.
 */
class IpdOverviewService
{
    /** Legacy label => ipd_patient_files column, in display order. */
    private const FINDING_FIELDS = [
        'General Examination' => 'general_examination',
        'Systemic Examination' => 'systemic_examination',
        'CNS Mental Status' => 'cns_system_mental_status',
        'CNS Sensory' => 'cns_system_sensory_examination',
        'CNS Motor Tone' => 'cns_system_motor_examination_tone',
        'CNS Reflexes' => 'cns_system_reflexes',
        'CNS Power' => 'cns_system_power',
    ];

    public function build(Ipd $ipd): array
    {
        return [
            'vitals'        => $this->vitals($ipd),
            'symptoms'      => $this->symptoms($ipd),
            'findings'      => $this->findings($ipd),
            'billing'       => $this->billingBoxes($ipd->id),
            'credit'        => $this->creditUsage($ipd),
            'medications'   => $this->latestMedications($ipd->id),
            'prescriptions' => $this->prescriptions($ipd->id),
            'bedHistory'    => $this->bedHistory($ipd->id),
        ];
    }

    /** Vitals always come from the linked OPD row (there is no IPD vitals table). BP is stored as "sys/dia". */
    public function vitals(Ipd $ipd): array
    {
        $opd = $ipd->opd;
        $bp = (string) ($opd->bp ?? '');
        [$systolic, $diastolic] = str_contains($bp, '/') ? explode('/', $bp, 2) : ['', ''];

        return [
            'height'       => (string) ($opd->height ?? ''),
            'weight'       => (string) ($opd->weight ?? ''),
            'bp_systolic'  => $systolic,
            'bp_diastolic' => $diastolic,
            'pulse'        => (string) ($opd->pulse ?? ''),
            'temperature'  => (string) ($opd->temperature ?? ''),
            'respiration'  => (string) ($opd->respiration ?? ''),
            'spo2'         => (string) ($opd->spo2 ?? ''),
        ];
    }

    /** Patient-file chief complaints win; otherwise the OPD symptoms. Returned as HTML source for the editor. */
    public function symptoms(Ipd $ipd): string
    {
        $fromFile = (string) ($ipd->patientFile?->chief_complaints ?? '');
        if (trim(strip_tags($fromFile)) !== '') {
            return html_entity_decode($fromFile, ENT_QUOTES, 'UTF-8');
        }

        return html_entity_decode((string) ($ipd->opd->symptoms ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * One <p> per filled examination field; falls back to the OPD remarks when the patient file has none.
     * The examination fields are plain text, so they are escaped before being wrapped in markup.
     */
    public function findings(Ipd $ipd): string
    {
        $file = $ipd->patientFile;
        $parts = [];
        foreach (self::FINDING_FIELDS as $label => $column) {
            $value = (string) ($file?->{$column} ?? '');
            if ($value !== '') {
                $parts[] = '<p>' . e($label . ': ' . $value) . '</p>';
            }
        }

        if ($parts !== []) {
            return implode('', $parts);
        }

        return html_entity_decode((string) ($ipd->opd->remarks ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /** One box per active charge department - departments without charges still show (0%, Rs.0/Rs.0). */
    public function billingBoxes(int $ipdId): Collection
    {
        $totals = DB::table('billing_charges')
            ->selectRaw('charge_department_id, SUM(total) as total, SUM(paid_amount) as paid')
            ->where('module', 'IPD')->where('module_id', $ipdId)
            ->groupBy('charge_department_id')
            ->get()->keyBy('charge_department_id');

        return DB::table('charge_departments')
            ->where('active', 'Yes')->orderBy('sort_order')->get()
            ->map(function ($dept) use ($totals) {
                $total = (int) ($totals[$dept->id]->total ?? 0);
                $paid  = (int) ($totals[$dept->id]->paid ?? 0);

                return [
                    'name'    => $dept->department_name,
                    'icon'    => $dept->icon,
                    'total'   => $total,
                    'paid'    => $paid,
                    'percent' => $total > 0 ? (int) round(($paid / $total) * 100) : 0,
                ];
            });
    }

    /**
     * Credit-limit pie: "used" is what has been billed but not yet paid. With no credit limit the chart is 100% used
     * (legacy behaviour). Percentages are clamped to 0-100 so an overrun doesn't produce a negative slice.
     */
    public function creditUsage(Ipd $ipd): array
    {
        $row = DB::table('billing_charges')
            ->selectRaw('SUM(total) as total, SUM(paid_amount) as paid')
            ->where('module', 'IPD')->where('module_id', $ipd->id)->first();

        $limit   = (int) $ipd->tpa_credit_limit;
        $used    = (float) ($row->total ?? 0) - (float) ($row->paid ?? 0);
        $balance = $limit - $used;

        $usedPct = 100.0;
        $balPct  = 0.0;
        if ($limit > 0) {
            $usedPct = ($used / $limit) * 100;
            $balPct  = 100 - $usedPct;
        }

        return [
            'limit'       => $limit,
            'used'        => round($used, 2),
            'balance'     => round($balance, 2),
            'used_pct'    => round(max(0, min(100, $usedPct)), 2),
            'balance_pct' => round(max(0, min(100, $balPct)), 2),
        ];
    }

    /** Medicines of the latest prescription that has at least one medicine (legacy used INNER JOINs). */
    public function latestMedications(int $ipdId): Collection
    {
        $prescriptionId = DB::table('ipd_prescriptions')
            ->join('staff_members', 'staff_members.id', '=', 'ipd_prescriptions.doctor_id')
            ->join('ipd_medicines', 'ipd_medicines.ipd_prescription_id', '=', 'ipd_prescriptions.id')
            ->where('ipd_prescriptions.ipd_id', $ipdId)
            ->orderByDesc('ipd_prescriptions.id')
            ->value('ipd_prescriptions.id');

        if (! $prescriptionId) {
            return collect();
        }

        $rows = DB::table('ipd_medicines as im')
            ->join('pharma_products as pp', 'pp.id', '=', 'im.pharma_product_id')
            ->leftJoin('medicine_frequency_types as ft', 'ft.id', '=', 'im.frequency_type_id')
            ->leftJoin('medicine_meal_relation as mr', 'mr.id', '=', 'im.meal_relation_id')
            ->where('im.ipd_prescription_id', $prescriptionId)
            ->orderBy('im.id')
            ->get([
                'im.id', 'pp.product_name', 'im.dose_quantity', 'im.duration_days',
                'ft.display_text as frequency', 'mr.relation_name as meal',
            ]);

        $timings = DB::table('ipd_medicine_timing_selections as s')
            ->join('medicine_timing_of_day as t', 't.id', '=', 's.timing_id')
            ->whereIn('s.ipd_medicine_id', $rows->pluck('id'))
            ->orderBy('t.sort_order')
            ->get(['s.ipd_medicine_id', 't.timing_name'])
            ->groupBy('ipd_medicine_id');

        return $rows->map(function ($r) use ($timings) {
            $r->timing = ($timings[$r->id] ?? collect())->pluck('timing_name')->implode(' + ');
            return $r;
        });
    }

    public function prescriptions(int $ipdId): Collection
    {
        return DB::table('ipd_prescriptions as p')
            ->join('staff_members as s', 's.id', '=', 'p.doctor_id')
            ->where('p.ipd_id', $ipdId)
            ->orderBy('p.id')
            ->get(['p.prescription_date_time', 'p.findings', 's.staff_name'])
            ->map(function ($r) {
                $r->when = $this->format($r->prescription_date_time);
                return $r;
            });
    }

    /** Bed stays, oldest first. Days is inclusive (same-day stay = 1); a bed still occupied runs to now. */
    public function bedHistory(int $ipdId): Collection
    {
        return DB::table('ipd_patient_beds as ipb')
            ->join('beds', 'beds.id', '=', 'ipb.bed_id')
            ->join('floors', 'floors.id', '=', 'beds.floor_id')
            ->join('bed_types', 'bed_types.id', '=', 'beds.bed_type_id')
            ->join('ward_categories', 'ward_categories.id', '=', 'beds.ward_category_id')
            ->where('ipb.ipd_id', $ipdId)
            ->orderBy('ipb.allot_date_time')
            ->get([
                'ipb.allot_date_time', 'ipb.left_date_time',
                'beds.bed_name', 'floors.floor', 'ward_categories.ward_category', 'bed_types.bed_type',
            ])
            ->map(function ($r) {
                $from = Carbon::parse($r->allot_date_time);
                $to   = $r->left_date_time ? Carbon::parse($r->left_date_time) : now();

                $r->label = "{$r->bed_name}-{$r->floor}-{$r->ward_category}-{$r->bed_type}";
                $r->from  = $this->format($r->allot_date_time);
                $r->to    = $this->format($r->left_date_time);
                $r->days  = (int) $from->diff($to)->days + 1;
                return $r;
            });
    }

    private function format(?string $dateTime): string
    {
        return $dateTime ? Carbon::parse($dateTime)->format('d-m-Y h:i A') : '';
    }
}
