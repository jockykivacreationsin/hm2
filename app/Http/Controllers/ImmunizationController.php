<?php

namespace App\Http\Controllers;

use App\Models\ImmunizationRecord;
use Illuminate\Support\Facades\Log;
use App\Models\ImmunizationRecommendation;
use App\Models\Vaccine;
use App\Models\VaccineDoseSchedule;
use App\Models\MedicineRoute;
use App\Models\StaffMember;
use App\Services\PermissionService;
use App\Support\CurrentUser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImmunizationController extends Controller
{
    /**
     * Show the immunization tab for a given visit module (opd|ipd|dcd) and visit id.
     * Route param is {id}; $module is injected via route default.
     */
    public function index(int $id, PermissionService $ps, string $module = 'opd')
    {
        $this->authoriseView($module, $ps);

        $visit = $this->loadVisit($module, $id);

        $vaccines = Vaccine::where('is_active', 1)->orderBy('sort_order')->get();
        $routes   = MedicineRoute::orderBy('sort_order')->get();
        $staff    = StaffMember::where('active', 'Yes')->orderBy('staff_name')->get(['id', 'staff_name']);

        $records = ImmunizationRecord::with(['vaccine', 'route', 'performer'])
            ->where($module . '_id', $id)
            ->orderByDesc('occurrence_date_time')
            ->get();

        $recommendations = ImmunizationRecommendation::with('vaccine')
            ->where('patient_id', (int) ($visit->patient_id ?? 0))
            ->where('status', 'due')
            ->orderBy('due_date')
            ->get();

        $canAdd    = $ps->hasPermission('b_immunization_add');
        $canEdit   = $ps->hasPermission('b_immunization_edit');
        $canDelete = $ps->hasPermission('b_immunization_delete');

        return view('immunization.index', compact(
            'module', 'visit', 'vaccines', 'routes', 'staff',
            'records', 'recommendations',
            'canAdd', 'canEdit', 'canDelete'
        ))->with('visitId', $id);
    }

    /**
     * Store a new immunization record.
     *
     * Fixes applied:
     *  - Gap #2 : fulfil any existing 'due' recommendation for THIS dose before creating next-dose rec
     *  - Gap #3 : when dose 1 is absent, use the earliest known completed dose as the reference date
     *             instead of blindly treating the current record as dose 1
     *  - Gap #6 : guard against duplicate (patient, vaccine, dose_number) completed records
     */
    public function store(Request $request, int $id, string $module = 'opd'): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_immunization_add'), 403);

        $visit = $this->loadVisit($module, $id);

        $validated = $request->validate([
            'vaccine_id'            => 'required|integer|exists:vaccines,id',
            'dose_number'           => 'required|integer|min:1',
            'status'                => 'required|in:completed,not-done,entered-in-error',
            'occurrence_date_time'  => 'required',
            'lot_number'            => 'nullable|string|max:50',
            'expiry_date'           => 'nullable|date',
            'manufacturer'          => 'nullable|string|max:150',
            'brand_name'            => 'nullable|string|max:150',
            'route_id'              => 'nullable|integer|exists:medicine_routes,id',
            'site'                  => 'nullable|string|max:100',
            'performer_staff_id'    => 'nullable|integer|exists:staff_members,id',
            'remarks'               => 'nullable|string',
        ]);

        $validated['occurrence_date_time'] = date('Y-m-d H:i:s', strtotime($validated['occurrence_date_time']));
        if (!empty($validated['expiry_date'])) {
            $validated['expiry_date'] = date('Y-m-d', strtotime($validated['expiry_date']));
        }
        $validated[$module . '_id'] = $id;
        $validated['patient_id']    = (int) $visit->patient_id;
        $validated['created_on']    = now()->toDateTimeString();
        $validated['updated_on']    = now()->toDateTimeString();
        $validated['user_id']       = CurrentUser::id() ?? 1;

        // Gap #6: block duplicate completed dose for the same patient+vaccine+dose_number
        if ($validated['status'] === 'completed') {
            $alreadyExists = ImmunizationRecord::where('patient_id', $validated['patient_id'])
                ->where('vaccine_id',   $validated['vaccine_id'])
                ->where('dose_number',  $validated['dose_number'])
                ->where('status',       'completed')
                ->exists();

            if ($alreadyExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A completed record for this vaccine dose already exists for this patient.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $record = ImmunizationRecord::create($validated);

            if ($validated['status'] === 'completed') {
                // Gap #2: mark any existing 'due' recommendation for THIS dose as completed
                ImmunizationRecommendation::where('patient_id',  $validated['patient_id'])
                    ->where('vaccine_id',   $validated['vaccine_id'])
                    ->where('dose_number',  $validated['dose_number'])
                    ->where('status',       'due')
                    ->update([
                        'status'               => 'completed',
                        'fulfilled_record_id'  => $record->id,
                        'updated_on'           => now()->toDateTimeString(),
                    ]);

                // Auto-generate next-dose recommendation when vaccine has more doses
                $vaccine = Vaccine::find($validated['vaccine_id']);
                if ($vaccine && $validated['dose_number'] < $vaccine->total_doses) {
                    $nextDoseNumber = $validated['dose_number'] + 1;

                    $dueDate = $this->calcNextDueDate(
                        $validated['vaccine_id'],
                        $validated['patient_id'],
                        $nextDoseNumber,
                        $validated['occurrence_date_time']
                    );

                    // Prefer reactivating a cancelled recommendation over creating a new row,
                    // to avoid accumulating stale duplicates per patient+vaccine+dose.
                    $reactivated = ImmunizationRecommendation::where('patient_id',  $validated['patient_id'])
                        ->where('vaccine_id',   $validated['vaccine_id'])
                        ->where('dose_number',  $nextDoseNumber)
                        ->where('status',       'cancelled')
                        ->orderByDesc('id')
                        ->first();

                    if ($reactivated) {
                        $reactivated->update([
                            'status'           => 'due',
                            'due_date'         => $dueDate,
                            'source_record_id' => $record->id,
                            'updated_on'       => now()->toDateTimeString(),
                        ]);
                    } else {
                        ImmunizationRecommendation::updateOrCreate(
                            [
                                'patient_id'  => $validated['patient_id'],
                                'vaccine_id'  => $validated['vaccine_id'],
                                'dose_number' => $nextDoseNumber,
                                'status'      => 'due',
                            ],
                            [
                                'due_date'         => $dueDate,
                                'source_record_id' => $record->id,
                                'created_on'       => now()->toDateTimeString(),
                                'updated_on'       => now()->toDateTimeString(),
                                'user_id'          => CurrentUser::id() ?? 1,
                            ]
                        );
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'id' => $record->id, 'message' => 'Immunization record saved.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImmunizationController@store failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /**
     * Return a single record for the edit modal.
     */
    public function show(int $id): JsonResponse
    {
        $record = ImmunizationRecord::findOrFail($id);
        return response()->json(['success' => true, 'record' => $record]);
    }

    /**
     * Update an existing immunization record.
     *
     * Fixes applied:
     *  - Gap #1 : sync recommendations on every meaningful change
     *  - Gap #5 : not-done → completed now creates a recommendation
     */
    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_immunization_edit'), 403);

        $record = ImmunizationRecord::findOrFail($id);

        $validated = $request->validate([
            'vaccine_id'            => 'sometimes|integer|exists:vaccines,id',
            'dose_number'           => 'sometimes|integer|min:1',
            'status'                => 'sometimes|in:completed,not-done,entered-in-error',
            'occurrence_date_time'  => 'sometimes',
            'lot_number'            => 'nullable|string|max:50',
            'expiry_date'           => 'nullable|date',
            'manufacturer'          => 'nullable|string|max:150',
            'brand_name'            => 'nullable|string|max:150',
            'route_id'              => 'nullable|integer|exists:medicine_routes,id',
            'site'                  => 'nullable|string|max:100',
            'performer_staff_id'    => 'nullable|integer|exists:staff_members,id',
            'remarks'               => 'nullable|string',
        ]);

        if (!empty($validated['occurrence_date_time'])) {
            $validated['occurrence_date_time'] = date('Y-m-d H:i:s', strtotime($validated['occurrence_date_time']));
        }
        if (!empty($validated['expiry_date'])) {
            $validated['expiry_date'] = date('Y-m-d', strtotime($validated['expiry_date']));
        }
        $validated['updated_on'] = now()->toDateTimeString();

        // Capture old values before the update so we can diff them
        $oldStatus     = $record->status;
        $oldVaccineId  = $record->vaccine_id;
        $oldDoseNumber = $record->dose_number;

        DB::beginTransaction();
        try {
            $record->update($validated);
            $record->refresh();

            $newStatus     = $record->status;
            $newVaccineId  = $record->vaccine_id;
            $newDoseNumber = $record->dose_number;
            $patientId     = $record->patient_id;

            // ── Determine whether recommendation state needs to change ────────

            $vaccineOrDoseChanged = ($newVaccineId !== $oldVaccineId || $newDoseNumber !== $oldDoseNumber);
            $wasCompleted         = ($oldStatus === 'completed');
            $isNowCompleted       = ($newStatus === 'completed');

            // Case A: was completed, now un-completed (not-done / entered-in-error)
            //         OR the vaccine / dose changed — revoke what this record generated
            if ($wasCompleted && (!$isNowCompleted || $vaccineOrDoseChanged)) {
                // Cancel the next-dose recommendation that was sourced from this record
                ImmunizationRecommendation::where('source_record_id', $record->id)
                    ->where('status', 'due')
                    ->update([
                        'status'     => 'cancelled',
                        'updated_on' => now()->toDateTimeString(),
                    ]);

                // Also un-fulfil any recommendation this record fulfilled
                ImmunizationRecommendation::where('fulfilled_record_id', $record->id)
                    ->where('status', 'completed')
                    ->update([
                        'status'               => 'due',
                        'fulfilled_record_id'  => null,
                        'updated_on'           => now()->toDateTimeString(),
                    ]);
            }

            // Case B: now completed AND something meaningful changed
            //         (just became completed, or vaccine/dose changed while completed).
            //         Skip when the record was already completed and nothing significant changed
            //         — that covers edits to lot_number, remarks, site, etc. which should not
            //         touch recommendation state at all.
            $needsRecommendationSync = $isNowCompleted && (!$wasCompleted || $vaccineOrDoseChanged);

            if ($needsRecommendationSync) {
                // Fulfil any 'due' recommendation for the current dose
                ImmunizationRecommendation::where('patient_id',  $patientId)
                    ->where('vaccine_id',   $newVaccineId)
                    ->where('dose_number',  $newDoseNumber)
                    ->where('status',       'due')
                    ->update([
                        'status'               => 'completed',
                        'fulfilled_record_id'  => $record->id,
                        'updated_on'           => now()->toDateTimeString(),
                    ]);

                // Regenerate the next-dose recommendation.
                // Prefer reactivating a cancelled row over creating a new one to avoid
                // accumulating stale duplicate rows per patient+vaccine+dose.
                $vaccine = Vaccine::find($newVaccineId);
                if ($vaccine && $newDoseNumber < $vaccine->total_doses) {
                    $nextDoseNumber = $newDoseNumber + 1;

                    $occurrenceDt = !empty($validated['occurrence_date_time'])
                        ? $validated['occurrence_date_time']
                        : $record->occurrence_date_time;

                    $dueDate = $this->calcNextDueDate(
                        $newVaccineId,
                        $patientId,
                        $nextDoseNumber,
                        $occurrenceDt
                    );

                    // Try to reactivate an existing cancelled recommendation first
                    $reactivated = ImmunizationRecommendation::where('patient_id',  $patientId)
                        ->where('vaccine_id',   $newVaccineId)
                        ->where('dose_number',  $nextDoseNumber)
                        ->where('status',       'cancelled')
                        ->orderByDesc('id')
                        ->first();

                    if ($reactivated) {
                        $reactivated->update([
                            'status'           => 'due',
                            'due_date'         => $dueDate,
                            'source_record_id' => $record->id,
                            'updated_on'       => now()->toDateTimeString(),
                        ]);
                    } else {
                        ImmunizationRecommendation::updateOrCreate(
                            [
                                'patient_id'  => $patientId,
                                'vaccine_id'  => $newVaccineId,
                                'dose_number' => $nextDoseNumber,
                                'status'      => 'due',
                            ],
                            [
                                'due_date'         => $dueDate,
                                'source_record_id' => $record->id,
                                'created_on'       => now()->toDateTimeString(),
                                'updated_on'       => now()->toDateTimeString(),
                                'user_id'          => CurrentUser::id() ?? 1,
                            ]
                        );
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Immunization record updated.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImmunizationController@update failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    /**
     * Delete an immunization record.
     *
     * Fix Gap #4: cancel any 'due' recommendations this record generated,
     * and reopen any recommendation it had fulfilled.
     */
    public function destroy(int $id): JsonResponse
    {
        abort_unless(app(PermissionService::class)->hasPermission('b_immunization_delete'), 403);

        $record = ImmunizationRecord::findOrFail($id);

        DB::beginTransaction();
        try {
            // Cancel next-dose recommendations triggered by this record
            ImmunizationRecommendation::where('source_record_id', $record->id)
                ->where('status', 'due')
                ->update([
                    'status'     => 'cancelled',
                    'updated_on' => now()->toDateTimeString(),
                ]);

            // Reopen any recommendation that this record fulfilled (dose is no longer given)
            ImmunizationRecommendation::where('fulfilled_record_id', $record->id)
                ->where('status', 'completed')
                ->update([
                    'status'               => 'due',
                    'fulfilled_record_id'  => null,
                    'updated_on'           => now()->toDateTimeString(),
                ]);

            $record->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Immunization record deleted.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImmunizationController@destroy failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Calculate the due date for $nextDoseNumber of a vaccine.
     *
     * Fix Gap #3: interval_days is always relative to dose 1.
     * When dose 1 has not been recorded in the system, we fall back to
     * the earliest known completed dose for that vaccine — rather than
     * blindly treating the current record as dose 1, which produced wrong
     * dates for dose 3+.
     */
    private function calcNextDueDate(
        int    $vaccineId,
        int    $patientId,
        int    $nextDoseNumber,
        string $currentOccurrenceDt
    ): string {
        $nextSchedule = VaccineDoseSchedule::where('vaccine_id', $vaccineId)
            ->where('dose_number', $nextDoseNumber)
            ->first();

        if ($nextSchedule && $nextSchedule->interval_days > 0) {
            // Prefer the actual dose-1 record; fall back to the earliest completed dose
            $referenceRecord = ImmunizationRecord::where('patient_id', $patientId)
                ->where('vaccine_id', $vaccineId)
                ->where('status', 'completed')
                ->orderBy('dose_number')
                ->orderBy('occurrence_date_time')
                ->first();

            $referenceDate = $referenceRecord
                ? Carbon::parse($referenceRecord->occurrence_date_time)
                : Carbon::parse($currentOccurrenceDt); // last resort: treat current as reference

            return $referenceDate->copy()->addDays($nextSchedule->interval_days)->toDateString();
        }

        // No schedule row configured — fall back to 4 weeks from today
        return now()->addWeeks(4)->toDateString();
    }

    private function authoriseView(string $module, PermissionService $ps): void
    {
        $permission = match ($module) {
            'opd' => 'im_opd_immunization',
            'ipd' => 'im_ipd_immunization',
            'dcd' => 'im_dcd_immunization',
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
}
