<?php

namespace App\Http\Controllers;

use App\Models\WellnessRecord;
use Illuminate\Support\Facades\Log;
use App\Models\WellnessObservation;
use App\Models\WellnessObservationType;
use App\Services\PermissionService;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WellnessController extends Controller
{
    /**
     * Show the wellness tab for a given visit module (opd|ipd|dcd) and visit id.
     * Route param is {id}; $module is injected via route default.
     */
    public function index(int $id, PermissionService $ps, string $module = 'opd')
    {
        $this->authoriseView($module, $ps);

        $visit = $this->loadVisit($module, $id);

        $observationTypes = WellnessObservationType::where('is_active', 1)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        $records = WellnessRecord::with('observations.type')
            ->where($module . '_id', $id)
            ->orderByDesc('recorded_at')
            ->get();

        $canSave   = $ps->hasPermission('b_wellness_save');
        $canDelete = $ps->hasPermission('b_wellness_delete');

        return view('wellness.index', compact(
            'module', 'visit',
            'observationTypes', 'records',
            'canSave', 'canDelete'
        ))->with('visitId', $id);
    }

    /**
     * Store a new wellness record + its observations atomically.
     * Route param is {id}; $module is injected via route default.
     */
    public function store(Request $request, int $id, string $module = 'opd'): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_wellness_save'), 403);

        $this->loadVisit($module, $id); // 404 if visit doesn't exist

        $filledObservations = array_filter(
            $request->input('observations', []),
            function ($o) {
                $hasNumeric = isset($o['value_numeric']) && $o['value_numeric'] !== '' && $o['value_numeric'] !== null;
                $hasText    = isset($o['value_text'])    && $o['value_text']    !== '';
                return !empty($o['type_id']) && ($hasNumeric || $hasText);
            }
        );

        if (empty($filledObservations)) {
            return response()->json(['success' => false, 'message' => 'Please enter at least one observation value before saving.'], 422);
        }

        $recordedAt = $request->input('recorded_at');
        $recordedAt = $recordedAt ? date('Y-m-d H:i:s', strtotime($recordedAt)) : now()->toDateTimeString();

        DB::beginTransaction();
        try {
            $record = WellnessRecord::create([
                $module . '_id' => $id,
                'patient_id'    => $this->patientIdFor($module, $id),
                'recorded_at'   => $recordedAt,
                'source'        => $module,
                'remarks'       => $request->input('remarks', ''),
                'created_on'    => now()->toDateTimeString(),
                'user_id'       => CurrentUser::id() ?? 1,
            ]);

            $this->upsertObservations($record->id, $request->input('observations', []));

            DB::commit();
            return response()->json(['success' => true, 'id' => $record->id, 'message' => 'Wellness record saved.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WellnessController@store failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /**
     * Return a single record's data for the edit modal.
     */
    public function show(int $id): JsonResponse
    {
        $record = WellnessRecord::with('observations')->findOrFail($id);
        return response()->json(['success' => true, 'record' => $record]);
    }

    /**
     * Update observations on an existing record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_wellness_save'), 403);

        $record = WellnessRecord::findOrFail($id);

        $filledObservations = array_filter(
            $request->input('observations', []),
            function ($o) {
                $hasNumeric = isset($o['value_numeric']) && $o['value_numeric'] !== '' && $o['value_numeric'] !== null;
                $hasText    = isset($o['value_text'])    && $o['value_text']    !== '';
                return !empty($o['type_id']) && ($hasNumeric || $hasText);
            }
        );

        if (empty($filledObservations)) {
            return response()->json(['success' => false, 'message' => 'Please enter at least one observation value before saving.'], 422);
        }

        $recordedAt = $request->input('recorded_at');
        if ($recordedAt) {
            $record->recorded_at = date('Y-m-d H:i:s', strtotime($recordedAt));
        }
        $record->remarks = $request->input('remarks', $record->remarks);
        $record->save();

        DB::beginTransaction();
        try {
            $this->upsertObservations($record->id, $request->input('observations', []));
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Wellness record updated.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WellnessController@update failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /**
     * Delete a wellness record and all its observations (cascade handles observations).
     */
    public function destroy(int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_wellness_delete'), 403);

        WellnessRecord::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Wellness record deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function authoriseView(string $module, PermissionService $ps): void
    {
        $permission = match ($module) {
            'opd' => 'im_opd_wellness',
            'ipd' => 'im_ipd_wellness',
            'dcd' => 'im_dcd_wellness',
            default => null,
        };
        abort_if($permission === null, 404);
        abort_unless($ps->hasPermission($permission), 403);
    }

    private function loadVisit(string $module, int $id): object
    {
        $table = match ($module) { 'opd' => 'opds', 'ipd' => 'ipds', 'dcd' => 'dcds', default => null };
        abort_if($table === null, 404);
        $visit = DB::table($table)->where('id', $id)->first();
        abort_if($visit === null, 404);
        return $visit;
    }

    private function patientIdFor(string $module, int $visitId): int
    {
        $table = match ($module) { 'opd' => 'opds', 'ipd' => 'ipds', 'dcd' => 'dcds', default => 'opds' };
        return (int) DB::table($table)->where('id', $visitId)->value('patient_id');
    }

    /** Upsert observations: insert or update each submitted type_id. */
    private function upsertObservations(int $recordId, array $observations): void
    {
        foreach ($observations as $obs) {
            $typeId = (int) ($obs['type_id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            WellnessObservation::updateOrCreate(
                ['wellness_record_id' => $recordId, 'type_id' => $typeId],
                [
                    'value_numeric' => isset($obs['value_numeric']) && $obs['value_numeric'] !== '' ? (float) $obs['value_numeric'] : null,
                    'value_text'    => $obs['value_text'] ?? null,
                    'created_on'    => now()->toDateTimeString(),
                ]
            );
        }
    }
}
