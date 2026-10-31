<?php

namespace App\Http\Controllers;

use App\Models\DcdService;
use App\Models\IpdService;
use App\Models\OpdService;
use App\Services\LabResultService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\CurrentUser;

/**
 * Lab investigation result entry for OPD / IPD / DCD (legacy opd|ipd|dcd-lab-investigation-result.php)
 * and the test pickers shared by the OPD / IPD / DCD prescription and lab modals.
 *
 * `{module}` is one of opd|ipd|dcd; `{serviceId}` is the opd_services / ipd_services / dcd_services id.
 */
class LabResultController extends Controller
{
    public function __construct(private LabResultService $labs)
    {
    }

    /** Legacy gate: inner-menu permission `im_<module>_lab_investigations` (plus the module itself). */
    private function authorizeModule(string $module, PermissionService $ps): void
    {
        abort_unless(
            in_array($module, LabResultService::modules(), true)
            && $ps->hasPermission("m_{$module}")
            && $ps->hasPermission("im_{$module}_lab_investigations"),
            403
        );
    }

    // ─── Result entry page ("Add Detail" button on the investigations list) ───

    public function resultForm(string $module, int $serviceId, PermissionService $ps)
    {
        $this->authorizeModule($module, $ps);

        $form = $this->labs->resultForm($module, $serviceId);

        return view('lab-results.result', $form + [
            'module'    => $module,
            'serviceId' => $serviceId,
        ]);
    }

    /**
     * Return the result-entry form body as a plain HTML fragment for the OPD modal.
     * Only OPD is wired here — IPD/DCD still use the full-page approach for now.
     */
    public function resultFormPartial(string $module, int $serviceId, PermissionService $ps)
    {
        $this->authorizeModule($module, $ps);

        $form = $this->labs->resultForm($module, $serviceId);

        return response(
            view('lab-results.partials.result-form', $form + [
                'module'    => $module,
                'serviceId' => $serviceId,
            ])->render()
        );
    }

    public function saveResult(Request $request, string $module, int $serviceId, PermissionService $ps): JsonResponse
    {
        $this->authorizeModule($module, $ps);

        $v = $request->validate([
            'cells'                  => 'nullable|array|max:500',
            'cells.*.detail_id'      => 'required|integer',
            'cells.*.test_id'        => 'required|integer',
            'cells.*.test_value'     => 'nullable|string|max:255',
            'cells.*.highlighted'    => 'nullable|boolean',
            'lab_conclusion'         => 'nullable|string|max:65000',
        ]);

        $this->labs->saveResults(
            $module,
            $serviceId,
            $v['cells'] ?? [],
            $v['lab_conclusion'] ?? '',
            (int) CurrentUser::id()
        );

        return response()->json(['success' => true, 'message' => 'Saved Successfully']);
    }

    // ─── "View Detail" modal on the investigations list (HTML table rows, as in legacy) ───

    public function detail(string $module, int $serviceId, PermissionService $ps)
    {
        $this->authorizeModule($module, $ps);
        $this->labs->service($module, $serviceId);

        return response(
            view('lab-results.partials.saved-results', ['groups' => $this->labs->savedResults($module, $serviceId)])->render()
        );
    }

    // ─── Radiology notes (Summernote HTML, one per service) ───

    public function radiologyNote(string $module, int $serviceId, PermissionService $ps): JsonResponse
    {
        $this->authorizeModule($module, $ps);
        $this->labs->service($module, $serviceId);

        return response()->json(['success' => true, 'note' => $this->labs->radiologyNote($module, $serviceId)]);
    }

    public function saveRadiologyNote(Request $request, string $module, int $serviceId, PermissionService $ps): JsonResponse
    {
        $this->authorizeModule($module, $ps);
        $this->labs->service($module, $serviceId);

        $v = $request->validate(['note' => 'nullable|string|max:16000000']);
        $this->labs->saveRadiologyNote($module, $serviceId, $v['note'] ?? '', (int) CurrentUser::id());

        return response()->json(['success' => true]);
    }

    // ─── Old per-visit "Lab Results" tab: legacy has none, results are entered per service from the Lab tab ───

    public function opdResults(int $opdId): RedirectResponse
    {
        return redirect()->route('opd.lab-investigations', $opdId);
    }

    public function ipdResults(int $ipdId): RedirectResponse
    {
        return redirect()->route('ipd.lab-investigations', $ipdId);
    }

    public function dcdResults(int $dcdId): RedirectResponse
    {
        return redirect()->route('dcd.lab-investigations', $dcdId);
    }

    // ─── Select2 pickers shared by the OPD / IPD / DCD prescription and charge modals ───

    public function dropdownPathologyTests(Request $request): JsonResponse
    {
        return response()->json($this->labs->testOptions('pathology', (string) $request->input('q', '')));
    }

    public function dropdownRadiologyTests(Request $request): JsonResponse
    {
        return response()->json($this->labs->testOptions('radiology', (string) $request->input('q', '')));
    }

    public function dropdownProcedures(Request $request): JsonResponse
    {
        return response()->json($this->labs->testOptions('procedure', (string) $request->input('q', '')));
    }

    // ─── Sub-test picker: the biochemistry sub-tests configured for a charge ───

    public function subTestsForCharge(int $chargeId): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'subtests' => $this->labs->subTestsForCharge($chargeId),
        ]);
    }

    // ─── Cross-module lab investigations list (Reports menu; Laravel-only, legacy pathology page was dead) ───

    public function allResults(Request $request, PermissionService $ps)
    {
        abort_unless($ps->hasPermission('m_reports'), 403);
        $search = (string) $request->input('search', '');
        $from   = $request->input('from_date', now()->format('Y-m-d'));
        $to     = $request->input('to_date', now()->format('Y-m-d'));

        $range = ["{$from} 00:00:00", "{$to} 23:59:59"];
        $lab   = fn ($categories) => fn ($q) => $q->whereIn('category', $categories);

        $rows = collect();
        $sources = [
            ['OPD', OpdService::class, 'opd', ['Lab', 'Radiology']],
            ['IPD', IpdService::class, 'ipd', ['Lab']],
            ['DCD', DcdService::class, 'dcd', ['Lab']],
        ];

        foreach ($sources as [$label, $model, $rel, $categories]) {
            $fk = "{$rel}_id";
            $model::with(['chargeDepartment', "{$rel}.patient"])
                ->whereHas('chargeDepartment', $lab($categories))
                ->whereBetween('service_date_time', $range)
                ->when($search !== '', fn ($q) => $q->whereHas("{$rel}.patient", fn ($sq) => $sq->where('patient_name', 'LIKE', "%{$search}%")))
                ->get()
                ->each(function ($s) use (&$rows, $label, $rel, $fk) {
                    $rows->push([
                        'module'  => $label,
                        'id'      => $s->{$fk},
                        'ref'     => $label . str_pad($s->{$fk}, 7, '0', STR_PAD_LEFT),
                        'patient' => $s->{$rel}?->patient?->patient_name ?? '',
                        'title'   => $s->service_title,
                        'date'    => $s->service_date_time,
                        'dept'    => $s->chargeDepartment?->department_name,
                        'lab_url' => route("{$rel}.lab-investigations", $s->{$fk}),
                    ]);
                });
        }

        $services = $rows->sortByDesc('date')->values();

        return view('lab-results.index', compact('services', 'from', 'to', 'search'));
    }
}
