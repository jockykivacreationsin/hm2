<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\ChargeCategory;
use App\Models\ChargeDepartment;
use App\Models\DcdPrescription;
use App\Models\IpdPrescription;
use App\Models\OpdPrescription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only lookups behind the charge pickers and the OPD / IPD / DCD "add charge" and "prescription to billing" modals.
 */
class ChargeLookupService
{
    /** Active charge departments as select2 rows. */
    public function departments(string $search = ''): Collection
    {
        return ChargeDepartment::where('active', 'Yes')
            ->when($search !== '', fn ($q) => $q->where('department_name', 'like', "%{$search}%"))
            ->orderBy('department_name')
            ->get(['id', 'department_name'])
            ->map(fn ($d) => ['id' => $d->id, 'text' => $d->department_name]);
    }

    /** Charge categories (optionally of one department) as select2 rows. */
    public function categories(int $departmentId = 0, string $search = ''): Collection
    {
        return ChargeCategory::query()
            ->when($departmentId > 0, fn ($q) => $q->where('charge_department_id', $departmentId))
            ->when($search !== '', fn ($q) => $q->where('charge_category', 'like', "%{$search}%"))
            ->orderBy('charge_category')
            ->limit(50)
            ->get(['id', 'charge_category'])
            ->map(fn ($c) => ['id' => $c->id, 'text' => $c->charge_category]);
    }

    /** Charges (optionally of one department) as select2 rows. */
    public function charges(int $departmentId = 0, string $search = ''): Collection
    {
        return Charge::query()
            ->when($departmentId > 0, fn ($q) => $q->where('charge_department_id', $departmentId))
            ->when($search !== '', fn ($q) => $q->where('charge_name', 'like', "%{$search}%"))
            ->orderBy('charge_name')
            ->limit(50)
            ->get(['id', 'charge_name'])
            ->map(fn ($c) => ['id' => $c->id, 'text' => $c->charge_name]);
    }

    /** Amount a patient category pays for a charge (0 when none is set). */
    public function amount(int $chargeId, int $patientCategoryId): float
    {
        return (float) (DB::table('patient_categories_charges')
            ->where('charge_id', $chargeId)
            ->where('patient_category_id', $patientCategoryId)
            ->value('amount') ?? 0);
    }

    /** `<option>` list of the test groups (biochemistry / radiology / procedures) a prescription contains. */
    public function prescriptionOptionsHtml(int $prescriptionId, string $department): string
    {
        $rx = match ($department) {
            'IPD'   => IpdPrescription::find($prescriptionId),
            'DCD'   => DcdPrescription::find($prescriptionId),
            default => OpdPrescription::find($prescriptionId),
        };

        $html = '<option></option>';
        if ($rx) {
            foreach (['pathology_tests' => 'Biochemistry', 'radiology_tests' => 'Radiology', 'procedures' => 'Procedures'] as $column => $label) {
                if (!empty($rx->{$column})) {
                    $html .= '<option value="' . e($rx->{$column}) . '">' . $label . '</option>';
                }
            }
        }

        return $html;
    }

    /**
     * Charge ids already on the bills of the visit a prescription belongs to (OPD / IPD / DCD), empty when the
     * prescription is unknown. Bills of other visits do not count.
     *
     * @return int[]
     */
    public function billedChargeIds(string $department, int $prescriptionId): array
    {
        [$prescription, $visitColumn, $module] = match (strtoupper($department)) {
            'IPD'   => [IpdPrescription::class, 'ipd_id', 'IPD'],
            'DCD'   => [DcdPrescription::class, 'dcd_id', 'DCD'],
            default => [OpdPrescription::class, 'opd_id', 'OPD'],
        };
        $visitId = (int) $prescription::whereKey($prescriptionId)->value($visitColumn);
        if ($visitId <= 0) {
            return [];
        }

        return DB::table('billing_charges_detail as d')
            ->join('billing_charges as b', 'b.id', '=', 'd.module_charge_id')
            ->where('b.module', $module)->where('b.module_id', $visitId)
            ->pluck('d.charge_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Table of the charges listed in a prescription (comma separated ids), each with an "add to billing" button.
     *
     * A charge that is already in billing is not offered again: neither one saved on the bills of this visit (found
     * through $department + $prescriptionId), nor one the page has queued in its modal table but not saved yet
     * ($queued). The pages ask again whenever either changes, so an item disappears when it is added to billing and
     * comes back when it is removed from it.
     *
     * @param int[] $queued charge ids queued in the page's add-charges table
     */
    public function prescriptionChargesHtml(string $type, string $values, string $department = '', int $prescriptionId = 0, array $queued = []): string
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $values))));

        $inBilling = array_map('intval', $queued);
        if ($department !== '' && $prescriptionId > 0) {
            $inBilling = array_merge($inBilling, $this->billedChargeIds($department, $prescriptionId));
        }
        $offered = array_values(array_diff($ids, $inBilling));

        $html = "<table class='table table-bordered'>";
        if (!empty($ids) && empty($offered)) {
            $html = "<div class='text-muted' style='font-size:13px;'><i class='fa fa-check-circle text-success'></i> Everything in this prescription is already in billing.</div>" . $html;
        }
        if (!empty($offered)) {
            // Gap 5: fetch only the two columns actually used
            foreach (Charge::whereIn('id', $offered)->get(['id', 'charge_name']) as $c) {
                $html .= '<tr>';
                $html .= '<td>' . e($c->charge_name) . '</td>';
                $html .= "<td style='width:50px; text-align:center;'>";
                $html .= "<button type='button' class='btn btn-sm btn-success' onclick='addPrescriptionItemToCharges(" . $c->id . ", \"" . addslashes($type) . "\")' title='Add to billing'>";
                $html .= "<i class='fa fa-plus'></i>";
                $html .= '</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        }

        return $html . '</table>';
    }

    /**
     * Department, category, amount AND display names to pre-fill when a prescription charge is added to billing.
     * Returns null when the charge does not exist.
     *
     * Gap 4: single JOIN replaces the previous Charge::find() + separate amount() call.
     * The response now includes charge_name and department_name so the JS no longer
     * needs to fire two extra round-trips to fetch them.
     */
    public function chargeForPrescription(int $chargeId, int $patientCategoryId): ?array
    {
        $row = DB::table('charges as c')
            ->leftJoin('charge_departments as d', 'd.id', '=', 'c.charge_department_id')
            ->leftJoin('patient_categories_charges as pcc', function ($join) use ($chargeId, $patientCategoryId) {
                $join->on('pcc.charge_id', '=', 'c.id')
                     ->where('pcc.patient_category_id', '=', $patientCategoryId);
            })
            ->where('c.id', $chargeId)
            ->select(
                'c.id',
                'c.charge_name',
                'c.charge_department_id',
                'c.charge_category_id',
                'd.department_name',
                'pcc.amount'
            )
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'success'              => true,
            'charge_id'            => (int) $row->id,
            'charge_name'          => $row->charge_name ?? '',
            'charge_department_id' => (int) $row->charge_department_id,
            'department_name'      => $row->department_name ?? '',
            'charge_category_id'   => (int) ($row->charge_category_id ?? 0),
            'amount'               => (float) ($row->amount ?? 0),
        ];
    }
}
