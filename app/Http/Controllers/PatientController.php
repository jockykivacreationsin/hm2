<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Opd;
use App\Models\Patient;
use App\Models\PatientCategory;
use App\Services\Abdm\AbdmIdentityService;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use App\Services\SettingsService;
use App\Support\CurrentUser;
use App\Support\Uhid;
use App\Support\Uploads;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PatientController extends Controller
{
    public function index(PermissionService $permissions, SettingsService $settings)
    {
        abort_unless($permissions->hasPermission('m_patients'), 403);

        return view('patients.index', [
            'canNew'        => $permissions->hasPermission('b_patient_new'),
            'canEdit'       => $permissions->hasPermission('b_patient_edit'),
            'canHistory'    => $permissions->hasPermission('b_patient_visit_history'),
            'bloodGroups'   => DB::table('blood_groups')->pluck('blood_group'),
            'abhaTerms'     => $settings->abhaTerms(),
            // ESM / service fields on Add only apply to non-default patient categories (legacy: category > 1).
            'showEsmFields' => (int) session('patient_category_id', 0) > 1,
        ]);
    }

    /**
     * DataTables AJAX endpoint for patients list.
     */
    public function data(Request $request): JsonResponse
    {
        $categoryId = (int) session('patient_category_id', 0);
        $search = $request->input('search.value', '');
        $uhidId = Uhid::parse((string) $search);   // a typed UHID (e.g. BMH/26/0000014) finds that patient

        // Legacy get_patients_datatable: the "total" ignores the search, the search also covers gender, and the
        // column the user clicked decides the order (0 = actions, 1 = UHID, 2 = name, 3 = mobile, 4 = gender, 5 = created on).
        $filtered = Patient::query()
            ->when($categoryId > 0, fn($q) => $q->where('patient_category_id', $categoryId))
            ->when($search, fn($q) => $q->where(function ($sq) use ($search, $uhidId) {
                $sq->where('patient_name', 'LIKE', "%{$search}%")
                   ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                   ->orWhere('gender', 'LIKE', "%{$search}%")
                   ->when($uhidId !== null, fn($u) => $u->orWhere('id', $uhidId));
            }));

        $total         = Patient::count();
        $totalFiltered = (clone $filtered)->count();

        $columns = ['id', 'id', 'patient_name', 'mobile_number', 'gender', 'created_on'];
        $orderBy = $columns[$request->integer('order.0.column', 2)] ?? 'patient_name';
        $dir     = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        $patients = $filtered->orderBy($orderBy, $dir)
            ->skip($request->integer('start'))
            ->take($request->integer('length', 50))
            ->get();

        $data = $patients->map(fn($p) => [
            'id'           => $p->id,
            'uhid'         => $p->uhid,
            'patient_name' => e($p->patient_name),
            'mobile_number'=> e($p->mobile_number),
            'gender'       => e($p->gender),
            'created_on'   => $p->created_on ? date('d-m-Y', strtotime($p->created_on)) : '',
        ]);

        return response()->json([
            'draw'            => $request->integer('draw'),
            'recordsTotal'    => $total,
            'recordsFiltered' => $totalFiltered,
            'data'            => $data,
        ]);
    }

    public function store(StorePatientRequest $request, AbdmIdentityService $identity): JsonResponse
    {
        $validated = $request->safe()->except('photo');
        $validated = $this->guardIdentity($validated, $identity);

        $validated['patient_category_id'] = session('patient_category_id', 1);
        $validated['created_on'] = now()->toDateTimeString();
        $validated['user_id'] = CurrentUser::id();

        $validated = $this->applyEmail($this->mapDateOfBirth($validated));
        if ($photo = $this->resolvePhoto($request)) {
            $validated['photo'] = $photo;
        }

        // Same values the legacy INSERT supplies: the table has NOT NULL columns without defaults and
        // Laravel turns blank form fields into null, which strict MySQL would reject.
        $validated = array_merge([
            'guardian_name' => '', 'alternate_mobile_number' => '', 'esm_name' => '', 'card_number' => '',
            'service_number' => '', 'claim_id' => '', 'photo' => '', 'age' => 0, 'status' => 'New',
        ], array_filter($validated, fn ($v) => $v !== null));
        foreach (['mobile_number', 'blood_group', 'address'] as $textColumn) {
            $validated[$textColumn] ??= '';
        }

        $patient = Patient::create($validated);

        app(AuditLogger::class)->createPatient($patient->id, (string) $patient->patient_name);

        return response()->json([
            'success' => true,
            'message' => 'Patient added successfully.',
            'patient' => [
                'id'   => $patient->id,
                'uhid' => $patient->uhid,
                'name' => $patient->patient_name,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        app(AuditLogger::class)->viewPatient($patient->id, (string) $patient->patient_name);

        $data = $patient->toArray();
        $data['photo_url'] = $patient->photo
            ? Uploads::url(Uploads::PATIENTS, $patient->photo)
            : null;

        return response()->json(['success' => true, 'patient' => $data]);
    }

    /**
     * Masks a raw Aadhaar (only the masked form is ever stored or compared - legacy never kept the raw number
     * either) and rejects an ABHA Number / ABHA Address / Aadhaar already linked to ANOTHER patient with a clear
     * message naming that patient, instead of a duplicate-key error (legacy abdm_find_identity_conflict).
     */
    private function guardIdentity(array $validated, AbdmIdentityService $identity, ?int $excludePatientId = null): array
    {
        if (!empty($validated['aadhaar_number']) && preg_match('/^\d{12}$/', preg_replace('/[\s-]/', '', $validated['aadhaar_number']))) {
            $validated['aadhaar_number'] = $identity->maskAadhaar($validated['aadhaar_number']);
        }

        $conflict = $identity->findIdentityConflict(
            (string) ($validated['aadhaar_number'] ?? ''),
            (string) ($validated['abha_number'] ?? ''),
            (string) ($validated['abha_address'] ?? ''),
            $excludePatientId
        );
        if ($conflict) {
            throw ValidationException::withMessages([$conflict['field'] => [$this->identityConflictMessage($conflict)]]);
        }

        return $validated;
    }

    /**
     * Identity-only update (legacy ajax.php update_patient_identity), used by the OPD register's Create OPD
     * modal to verify/link an ABHA on an EXISTING patient without touching any other field.
     *
     * The ABDM-verified name/gender are synced too: ABDM's "generate link token" call validates
     * name/gender/year of birth against its own record, so they must match. An ABDM photo is only ever
     * attached when the patient has none yet - an existing photo is never overwritten.
     */
    public function updateIdentity(Request $request, int $id, AbdmIdentityService $identity): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        $in = $request->validate([
            'abha_number'    => 'nullable|string|max:50',
            'abha_address'   => 'nullable|string|max:255',
            'aadhaar_number' => 'nullable|string|max:50',
            'date_of_birth'  => 'nullable|string|max:10',
            'patient_name'   => 'nullable|string|max:255',
            'gender'         => 'nullable|in:Male,Female,Other',
            'photo'          => 'nullable|string|max:255',
        ]);

        // Blank must be NULL (not '') - these columns are UNIQUE.
        $abhaNumber  = trim((string) ($in['abha_number'] ?? '')) ?: null;
        $abhaAddress = trim((string) ($in['abha_address'] ?? '')) ?: null;
        $aadhaar     = trim((string) ($in['aadhaar_number'] ?? '')) ?: null;
        if ($aadhaar && preg_match('/^\d{12}$/', preg_replace('/[\s-]/', '', $aadhaar))) {
            $aadhaar = $identity->maskAadhaar($aadhaar);
        }

        if (!$abhaNumber && !$abhaAddress && !$aadhaar) {
            return response()->json(['success' => false, 'error' => 'Please verify an ABHA/Aadhaar identity first']);
        }

        $conflict = $identity->findIdentityConflict((string) $aadhaar, (string) $abhaNumber, (string) $abhaAddress, $id);
        if ($conflict) {
            return response()->json(['success' => false, 'error' => $this->identityConflictMessage($conflict)]);
        }

        $changes = ['abha_number' => $abhaNumber, 'abha_address' => $abhaAddress, 'aadhaar_number' => $aadhaar];

        // A missing/invalid date of birth must never wipe the one already on file.
        $dob = $this->parseDob((string) ($in['date_of_birth'] ?? ''));
        if ($dob && !$dob->isFuture()) {
            $changes['date_of_birth'] = $dob->toDateString();
        }
        if (!empty($in['patient_name'])) {
            $changes['patient_name'] = trim($in['patient_name']);
        }
        if (!empty($in['gender'])) {
            $changes['gender'] = $in['gender'];
        }
        if (($patient->photo === null || $patient->photo === '') && $this->isSavedAbdmPhoto((string) ($in['photo'] ?? ''))) {
            $changes['photo'] = $in['photo'];
        }

        try {
            $patient->update($changes);
        } catch (QueryException $e) {
            // Safety net for a genuine race between two concurrent saves of the same identity.
            $message = ((int) ($e->errorInfo[1] ?? 0) === 1062)
                ? 'This ABHA / Aadhaar is already linked to another patient record.'
                : 'Database error while saving the identity';

            return response()->json(['success' => false, 'error' => $message]);
        }

        return response()->json([
            'success'        => true,
            'patient_id'     => $patient->id,
            'abha_number'    => $patient->abha_number,
            'abha_address'   => $patient->abha_address,
            'aadhaar_number' => $patient->aadhaar_number,
            'date_of_birth'  => $patient->date_of_birth ? (string) $patient->date_of_birth : null,
            'patient_name'   => $patient->patient_name,
            'gender'         => $patient->gender,
            'photo'          => $patient->photo,
        ]);
    }

    private function identityConflictMessage(array $conflict): string
    {
        return $conflict['label'] . ' is already linked to another patient record ("'
            . $conflict['patient_name'] . '", Patient ID #' . $conflict['patient_id'] . '). '
            . 'The same ' . $conflict['label'] . ' cannot be linked to two different patients - '
            . 'please check whether this patient already exists before creating a new record.';
    }

    /** A bare filename written by the ABDM photo save (abdm_<hex>.jpg|png) that really exists on disk. */
    private function isSavedAbdmPhoto(string $candidate): bool
    {
        return $candidate !== ''
            && preg_match('/^abdm_[0-9a-f]+\.(jpg|png)$/', $candidate)
            && Uploads::exists(Uploads::PATIENTS, $candidate);
    }

    public function update(UpdatePatientRequest $request, int $id, AbdmIdentityService $identity): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $oldValues = $patient->toArray();

        $validated = $request->safe()->except('photo');
        $validated = $this->guardIdentity($validated, $identity, $id);

        $validated = $this->applyEmail($this->mapDateOfBirth($validated));
        if ($photo = $this->resolvePhoto($request)) {
            $validated['photo'] = $photo;
        }
        $validated['user_id'] = CurrentUser::id() ?: $patient->user_id;

        // These are NOT NULL columns, and Laravel turns a cleared form field into null: store '' like the legacy
        // app instead of letting strict MySQL reject the save.
        foreach (['mobile_number', 'blood_group', 'address', 'guardian_name', 'alternate_mobile_number',
                  'esm_name', 'card_number', 'service_number', 'claim_id'] as $textColumn) {
            if (array_key_exists($textColumn, $validated)) {
                $validated[$textColumn] ??= '';
            }
        }
        if (array_key_exists('age', $validated) && $validated['age'] === null) {
            unset($validated['age']);
        }

        $patient->update($validated);

        app(AuditLogger::class)->updatePatient(
            $patient->id,
            (string) $patient->patient_name,
            $oldValues,
            $validated
        );

        return response()->json(['success' => true, 'message' => 'Patient updated successfully.']);
    }

    private function parseDob(string $value): ?\Carbon\Carbon
    {
        return PatientRequest::parseDob($value);
    }

    /**
     * The email column is added by an additive migration. Until it has been run, saving must keep working
     * (the value is dropped, as before) instead of every patient save failing on an unknown column.
     * Blank is stored as NULL: the column is nullable and an optional field should read as "not given".
     */
    private function applyEmail(array $validated): array
    {
        if (!Schema::hasColumn('patients', 'email')) {
            unset($validated['email']);

            return $validated;
        }
        if (array_key_exists('email', $validated)) {
            $email = is_string($validated['email']) ? trim($validated['email']) : $validated['email'];
            $validated['email'] = ($email === '' || $email === null) ? null : mb_strtolower($email);
        }

        return $validated;
    }

    /** The form field is `dob`; the column is `date_of_birth` (and `age` is NOT NULL, so derive it). */
    private function mapDateOfBirth(array $validated): array
    {
        $dob = $validated['dob'] ?? $validated['date_of_birth'] ?? null;
        unset($validated['dob'], $validated['date_of_birth']);
        if ($dob && ($date = $this->parseDob((string) $dob))) {
            $validated['date_of_birth'] = $date->toDateString();
            // An age typed into the form wins (legacy saved it as posted); otherwise derive it from the date.
            if (!isset($validated['age'])) {
                $validated['age'] = max(0, (int) $date->diffInYears(now()));
            }
        }

        return $validated;
    }

    /**
     * Patient photos live in the patients folder of the `uploads` disk (App\Support\Uploads).
     * Accepts:
     * 1. Direct file upload ($request->hasFile('photo'))
     * 2. The bare filename of a photo already saved there by the ABDM flow (abdm_<hex>.jpg|png)
     * 3. A base64 data URL from the Slim cropper (data:image/jpeg;base64,...)
     * 4. The bare filename of a photo already on disk (existing photo kept through an edit)
     */
    private function resolvePhoto(Request $request): ?string
    {
        if ($request->hasFile('photo')) {
            return Uploads::putUploaded(Uploads::PATIENTS, $request->file('photo'), 'patient_');
        }

        $candidate = (string) $request->input('photo_filename', '');
        if ($this->isSavedAbdmPhoto($candidate)) {
            return $candidate;
        }

        $photoInput = (string) $request->input('photo', '');

        // Base64 data URL from the Slim cropper (school pattern: no separate upload endpoint).
        if (str_starts_with($photoInput, 'data:image/')) {
            return Uploads::putBase64(Uploads::PATIENTS, $photoInput, 'patient_');
        }

        // Bare filename: an existing photo kept through an edit.
        if ($photoInput !== ''
            && !str_contains($photoInput, '..')
            && preg_match('/^[A-Za-z0-9_.-]+\.(jpg|jpeg|png|gif|webp)$/i', $photoInput)
            && Uploads::exists(Uploads::PATIENTS, $photoInput)
        ) {
            return $photoInput;
        }

        return null;
    }

    public function destroy(int $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);
        $patientName = (string) $patient->patient_name;
        $patient->delete();

        app(AuditLogger::class)->deletePatient($id, $patientName);

        return response()->json(['success' => true, 'message' => 'Patient deleted.']);
    }

    /**
     * Legacy patient-visit-history.php: every OPD visit of the patient, each followed by the IPD admission and
     * DCD visit raised from it. IPD/DCD rows show the OPD's symptoms/remarks, exactly as the legacy page did.
     */
    public function history(int $id, PermissionService $permissions)
    {
        abort_unless($permissions->hasPermission('b_patient_visit_history'), 403);

        $patient = Patient::findOrFail($id);
        $opds = Opd::with(['doctor', 'ipd', 'dcd'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('opd_date_time')
            ->get();

        return view('patients.history', compact('patient', 'opds'));
    }

    public function visitHistory(int $id): JsonResponse
    {
        $patient = Patient::with(['opds.doctor', 'ipds.doctor', 'dcds'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'opds'    => $patient->opds ?? [],
            'ipds'    => $patient->ipds ?? [],
            'dcds'    => $patient->dcds ?? [],
        ]);
    }

    /**
     * Select2 source of the patient pickers (OPD / DCD / periodic-medicine modals): match on id / name / mobile,
     * never offer an already-admitted patient, and with no search term list the 50 most recent patients.
     * Returns a bare [{id, text}] array.
     */
    public function dropdown(Request $request): JsonResponse
    {
        $q = trim((string) ($request->input('q') ?: $request->input('searchTerm', '')));

        $uhidId = Uhid::parse((string) $q);

        $patients = Patient::where('status', '!=', 'Admitted')
            ->when($q !== '', fn ($query) => $query
                ->where(fn ($sq) => $sq->where('id', 'like', "%{$q}%")
                    ->orWhere('patient_name', 'like', "%{$q}%")
                    ->orWhere('mobile_number', 'like', "%{$q}%")
                    ->when($uhidId !== null, fn ($u) => $u->orWhere('id', $uhidId)))
                ->orderBy('patient_name'), fn ($query) => $query->orderByDesc('id'))
            ->limit(50)
            ->get(['id', 'patient_name', 'mobile_number', 'created_on']);

        return response()->json($patients->map(fn ($p) => [
            'id'   => $p->id,
            'text' => trim("{$p->patient_name} {$p->mobile_number} {$p->id}"),
        ])->values());
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $q     = $request->input('q', '');
        $catId = session('patient_category_id', 0);

        $uhidId = Uhid::parse((string) $q);

        $patients = Patient::when($catId > 0, fn($query) => $query->where('patient_category_id', $catId))
            ->where(fn($query) => $query->where('patient_name', 'LIKE', "%{$q}%")
                ->orWhere('mobile_number', 'LIKE', "%{$q}%")
                ->when($uhidId !== null, fn($u) => $u->orWhere('id', $uhidId)))
            ->orderBy('patient_name')->limit(30)
            ->get(['id', 'patient_name', 'mobile_number', 'created_on']);   // created_on: the UHID may contain the year

        return response()->json([
            'results' => $patients->map(fn($p) => [
                'id'   => $p->id,
                'text' => $p->patient_name . ' (' . $p->uhid . ') ' . $p->mobile_number,
            ]),
        ]);
    }
}
