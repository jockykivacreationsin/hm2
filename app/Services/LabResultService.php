<?php

namespace App\Services;

use App\Models\BiochemistrySubTest;
use App\Models\BiochemistryTest;
use App\Models\Charge;
use App\Models\IdFieldSetting;
use App\Models\DcdLabInvestigationNote;
use App\Models\DcdLabTestResult;
use App\Models\IpdLabInvestigationNote;
use App\Models\OpdLabInvestigationNote;
use App\Models\OpdLabTestResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lab investigation listing / result entry shared by OPD, IPD and DCD.
 *
 * Replaces the legacy opd|ipd|dcd-lab-investigation(s).php pages: result entry, saved-result detail,
 * lab conclusion and radiology notes, plus the test pickers used by the prescription / lab modals.
 */
class LabResultService
{
    /**
     * Per-module table / column map. Note that IPD results live in the older `biochemistry_tests_result`
     * table (model BiochemistryTest); OPD and DCD have their own *_lab_test_results tables.
     */
    private const MODULES = [
        'opd' => [
            'entity_fk'      => 'opd_id',
            'service_table'  => 'opd_services',
            'detail_table'   => 'opd_service_detail',
            'detail_fk'      => 'opd_service_id',
            'result_detail'  => 'opd_service_detail_id',
            'result_model'   => OpdLabTestResult::class,
            'note_model'     => OpdLabInvestigationNote::class,
            'radiology_table' => 'opd_radiology_notes',
            // Legacy OPD page lists Radiology services as well (with a notes button instead of result entry)
            'categories'     => ['Lab', 'Radiology'],
        ],
        'ipd' => [
            'entity_fk'      => 'ipd_id',
            'service_table'  => 'ipd_services',
            'detail_table'   => 'ipd_service_detail',
            'detail_fk'      => 'ipd_service_id',
            'result_detail'  => 'ipd_service_detail_id',
            'result_model'   => BiochemistryTest::class,
            'note_model'     => IpdLabInvestigationNote::class,
            'radiology_table' => 'ipd_radiology_notes',
            'categories'     => ['Lab', 'Radiology'],
        ],
        'dcd' => [
            'entity_fk'      => 'dcd_id',
            'service_table'  => 'dcd_services',
            'detail_table'   => 'dcd_service_detail',
            'detail_fk'      => 'dcd_service_id',
            'result_detail'  => 'dcd_service_detail_id',
            'result_model'   => DcdLabTestResult::class,
            'note_model'     => DcdLabInvestigationNote::class,
            'radiology_table' => 'dcd_radiology_notes',
            'categories'     => ['Lab', 'Radiology'],
        ],
    ];

    public static function modules(): array
    {
        return array_keys(self::MODULES);
    }

    private function cfg(string $module): array
    {
        return self::MODULES[$module] ?? abort(404);
    }

    /** Lab (and for OPD: Radiology) services of one OPD/IPD/DCD visit, with their charge names. */
    public function investigations(string $module, int $entityId): Collection
    {
        $c = $this->cfg($module);
        $s = $c['service_table'];

        $query = DB::table($s)
            ->join('charge_departments', 'charge_departments.id', '=', "$s.charge_department_id")
            ->where("$s.{$c['entity_fk']}", $entityId)
            ->whereIn('charge_departments.category', $c['categories'])
            ->select("$s.*", 'charge_departments.department_name', 'charge_departments.category');

        if ($module === 'ipd') {
            $query->orderByDesc("$s.id");
        } else {
            $query->orderBy("$s.id");
        }
        $services = $query->get();

        // One query for all "Detail" column values (legacy ran GROUP_CONCAT per row)
        $d = $c['detail_table'];
        $charges = DB::table($d)
            ->join('charges', 'charges.id', '=', "$d.charge_id")
            ->whereIn("$d.{$c['detail_fk']}", $services->pluck('id'))
            ->orderBy("$d.id")
            ->get(["$d.{$c['detail_fk']} as service_id", 'charges.charge_name'])
            ->groupBy('service_id');

        return $services->map(function ($row) use ($charges) {
            $row->service_date_time_fmt = $row->service_date_time
                ? Carbon::parse($row->service_date_time)->format('d-m-Y h:i A') : '';
            $row->charges = ($charges[$row->id] ?? collect())->pluck('charge_name')->implode(', ');
            $row->is_radiology = stripos((string) $row->department_name, 'radiology') !== false;
            return $row;
        });
    }

    /** The service row (opd_services / ipd_services / dcd_services) or 404. */
    public function service(string $module, int $serviceId): object
    {
        $c = $this->cfg($module);
        return DB::table($c['service_table'])->where('id', $serviceId)->first() ?? abort(404, 'Lab investigation not found');
    }

    /**
     * Result-entry form data: one block per charge of the service, each with its biochemistry sub-tests
     * (main_category_id = charge id) pre-filled with any previously saved value / abnormal flag.
     */
    public function resultForm(string $module, int $serviceId): array
    {
        $c = $this->cfg($module);
        $service = $this->service($module, $serviceId);

        $d = $c['detail_table'];
        $details = DB::table($d)
            ->join('charges', 'charges.id', '=', "$d.charge_id")
            ->where("$d.{$c['detail_fk']}", $serviceId)
            ->orderBy("$d.id")
            ->get(["$d.id as detail_id", 'charges.id as charge_id', 'charges.charge_name']);

        $subTests = BiochemistrySubTest::whereIn('main_category_id', $details->pluck('charge_id')->unique())
            ->orderBy('id')->get()->groupBy('main_category_id');

        $existing = $c['result_model']::where('lab_investigation_id', $serviceId)->get()->keyBy('test_id');

        $blocks = $details->map(function ($detail) use ($subTests, $existing) {
            $detail->tests = ($subTests[$detail->charge_id] ?? collect())->map(function ($t) use ($existing) {
                $row = $existing->get($t->id);
                return (object) [
                    'id'              => $t->id,
                    'name'            => $t->sub_test_name,
                    'reference_range' => $t->reference_range,
                    'value'           => $row?->test_value ?? '',
                    'highlighted'     => ($row?->highlighted === 'Yes'),
                ];
            })->values();
            return $detail;
        });

        $conclusion = $c['note_model']::where($c['detail_fk'], $serviceId)->value('note') ?? '';

        return [
            'service'    => $service,
            'entity_id'  => (int) $service->{$c['entity_fk']},
            'blocks'     => $blocks,
            'conclusion' => $conclusion,
        ];
    }

    /**
     * Save every entered cell plus the conclusion in one transaction.
     * $cells: [['detail_id' => int, 'test_id' => int, 'test_value' => ?string, 'highlighted' => bool], ...]
     *
     * Legacy rule kept: blank values are skipped (an already saved result is never wiped by an empty cell);
     * everything else is upserted on the (lab_investigation_id, test_id) unique key.
     */
    public function saveResults(string $module, int $serviceId, array $cells, ?string $conclusion, int $userId): void
    {
        $c = $this->cfg($module);
        $service = $this->service($module, $serviceId);
        $model = $c['result_model'];

        $validDetails = DB::table($c['detail_table'])->where($c['detail_fk'], $serviceId)->pluck('id')->all();

        DB::transaction(function () use ($module, $c, $service, $serviceId, $cells, $conclusion, $userId, $model, $validDetails) {
            foreach ($cells as $cell) {
                $detailId = (int) $cell['detail_id'];
                $testId   = (int) $cell['test_id'];
                $value    = trim((string) ($cell['test_value'] ?? ''));

                // IDOR guard: the detail row must belong to this service
                if (!in_array($detailId, $validDetails, true) || $testId <= 0) {
                    continue;
                }

                $key = ['lab_investigation_id' => $serviceId, 'test_id' => $testId];

                if ($value === '') {
                    continue;
                }

                $model::updateOrCreate($key, [
                    $c['entity_fk']        => (int) $service->{$c['entity_fk']},
                    'test_date_time'       => $service->service_date_time,
                    $c['result_detail']    => $detailId,
                    'test_value'           => $value,
                    'highlighted'          => !empty($cell['highlighted']) ? 'Yes' : '',
                ]);
            }

            $this->saveConclusion($module, $serviceId, $conclusion ?? '', $userId);
        });
    }

    /** Lab investigation "Conclusion / Interpretation" (one row per service). */
    public function saveConclusion(string $module, int $serviceId, string $note, int $userId): void
    {
        $c = $this->cfg($module);
        $c['note_model']::updateOrCreate(
            [$c['detail_fk'] => $serviceId],
            ['note' => $note, 'updated_on' => now()->toDateTimeString(), 'user_id' => $userId]
        );
    }

    /**
     * "View Detail" modal rows: for each charge with saved results, the sub-test name, value + unit
     * (bold when flagged) and reference range.
     */
    public function savedResults(string $module, int $serviceId): Collection
    {
        $c = $this->cfg($module);
        $r = (new ($c['result_model']))->getTable();
        $d = $c['detail_table'];

        $rows = DB::table($r)
            ->join($d, "$d.id", '=', "$r.{$c['result_detail']}")
            ->join('charges', 'charges.id', '=', "$d.charge_id")
            ->join('biochemistry_sub_tests as st', 'st.id', '=', "$r.test_id")
            ->join('bunits', 'bunits.id', '=', 'st.unit_id')
            ->where("$r.lab_investigation_id", $serviceId)
            ->orderBy("$r.{$c['result_detail']}")->orderBy("$r.id")
            ->get([
                "$r.{$c['result_detail']} as detail_id", 'charges.charge_name', 'st.sub_test_name',
                'st.reference_range', 'bunits.unit_name', "$r.test_value", "$r.highlighted",
            ]);

        return $rows->groupBy('detail_id');
    }

    public function radiologyNote(string $module, int $serviceId): string
    {
        $c = $this->cfg($module);
        return (string) (DB::table($c['radiology_table'])->where($c['detail_fk'], $serviceId)->value('note') ?? '');
    }

    public function saveRadiologyNote(string $module, int $serviceId, string $note, int $userId): void
    {
        $c = $this->cfg($module);
        DB::table($c['radiology_table'])->upsert(
            [[$c['detail_fk'] => $serviceId, 'note' => $note, 'updated_on' => now()->toDateTimeString(), 'user_id' => $userId]],
            [$c['detail_fk']],
            ['note', 'updated_on', 'user_id']
        );
    }

    /**
     * Select2 options for the prescription / charge pickers: pathology and radiology tests are the charges of the
     * department configured in id_field_settings (3 / 4 when unset); procedures are the charges of any department
     * with category 'Procedure'.
     *
     * @param  'pathology'|'radiology'|'procedure'  $kind
     */
    public function testOptions(string $kind, string $search = ''): Collection
    {
        $query = match ($kind) {
            'pathology' => Charge::where('charge_department_id', IdFieldSetting::first()?->pathology_department_id ?? 3),
            'radiology' => Charge::where('charge_department_id', IdFieldSetting::first()?->radiology_department_id ?? 4),
            'procedure' => Charge::whereHas('chargeDepartment', fn ($cd) => $cd->where('category', 'Procedure')),
        };

        if ($search !== '') {
            $query->where('charge_name', 'like', "%{$search}%");
        }

        return $query->orderBy('charge_name')->get(['id', 'charge_name'])
            ->map(fn ($charge) => ['id' => $charge->id, 'text' => $charge->charge_name]);
    }

    /** Sub-tests configured for one charge (main_category_id) with unit + reference range. */
    public function subTestsForCharge(int $chargeId): Collection
    {
        return BiochemistrySubTest::with('unit')->where('main_category_id', $chargeId)->orderBy('id')->get()
            ->map(fn ($st) => [
                'id'              => $st->id,
                'sub_test_name'   => $st->sub_test_name,
                'reference_range' => $st->reference_range,
                'unit'            => $st->unit?->unit_name ?? '',
            ]);
    }
}
