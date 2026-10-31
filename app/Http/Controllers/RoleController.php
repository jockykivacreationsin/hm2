<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRolePermissionsRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use App\Models\UserRole;
use App\Models\UserRolePermission;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    // All known permission keys. Source of truth is the legacy role screen (bmhabdm/role-permissions.php);
    // any key a role holds that is missing here is deleted the next time that role is saved.
    private array $allPermissions = [
        // Menu permissions
        'm_beds', 'm_billing', 'm_charges',
        'm_dashboard', 'm_dcd', 'm_discharge',
        'm_ipd', 'm_master', 'm_opd',
        'm_patient_file', 'm_patient_periodic_medicines', 'm_patients',
        'm_pharmacy', 'm_reports', 'm_roles_and_permissions',
        'm_sale_bill', 'm_staff', 'm_top',
        // Menu-item permissions
        'mi_pharmacy_products',
        // Inner-module permissions
        'im_beds', 'im_bunits', 'im_charges',
        'im_charges_categories', 'im_charges_departments', 'im_collections',
        'im_dcd_charges', 'im_dcd_discharge_approval', 'im_dcd_discharge_approve',
        'im_dcd_discharge_patients', 'im_dcd_file', 'im_dcd_history',
        'im_dcd_lab_investigations', 'im_dcd_medication', 'im_dcd_overview',
        'im_dcd_payments', 'im_dcd_prescription', 'im_dcd_procedures', 'im_discharge_approval',
        'im_discharge_approve', 'im_discharge_patients', 'im_disease_patients',
        'im_diseases', 'im_floor_areas', 'im_floors',
        'im_ipd_bed_history', 'im_ipd_charges', 'im_ipd_consultations',
        'im_ipd_history', 'im_ipd_intake_output', 'im_ipd_lab_investigations',
        'im_ipd_medication', 'im_ipd_overview', 'im_ipd_patient_file',
        'im_ipd_payments', 'im_ipd_prescription', 'im_ipd_procedures',
        'im_ipd_progress_sheet', 'im_ipd_rmo_notes', 'im_ipd_treatment',
        'im_ipd_vital_monitoring', 'im_opd_charges', 'im_opd_history',
        'im_opd_lab_investigations', 'im_opd_overview', 'im_opd_prescription',
        'im_opd_procedures', 'im_patient_medicine_issue', 'im_patients_medicine_due_days',
        'im_periodic_medicine_patients', 'im_roles', 'im_sale_bill',
        'im_sale_search', 'im_tpa', 'im_ward_categories',
        'im_opd_wellness', 'im_ipd_wellness', 'im_dcd_wellness',
        'im_opd_immunization', 'im_ipd_immunization', 'im_dcd_immunization',
        // Button permissions
        'b_add', 'b_beds_add', 'b_beds_copy',
        'b_beds_edit', 'b_beds_ui', 'b_billing_delete',
        'b_billing_edit', 'b_billing_new', 'b_bunits_add',
        'b_bunits_delete', 'b_bunits_edit', 'b_charges_categories_charges',
        'b_charges_categories_edit', 'b_charges_categories_new', 'b_charges_delete',
        'b_charges_edit', 'b_charges_new', 'b_dcd_delete_charge',
        'b_dcd_delete_prescription', 'b_dcd_discharge_approval_view', 'b_dcd_discharge_approve_and_discharge',
        'b_dcd_discharge_patient', 'b_dcd_discharge_patient_view', 'b_dcd_edit_charge',
        'b_dcd_edit_prescription', 'b_dcd_file_save', 'b_dcd_invoice',
        'b_dcd_new_charge', 'b_dcd_new_prescription', 'b_dcd_save',
        'b_dcd_to_ipd', 'b_dcd_view_history_detail', 'b_delete_opd_prescription',
        'b_departments_categories_list', 'b_discharge_approval_view', 'b_discharge_approve_and_discharge',
        'b_discharge_patient_view', 'b_disease_patients', 'b_diseases_add',
        'b_diseases_edit', 'b_edit_opd_prescription', 'b_floor_areas_add',
        'b_floor_areas_edit', 'b_floors_add', 'b_floors_areas',
        'b_floors_edit', 'b_ipd_add_payment', 'b_ipd_change_bed',
        'b_ipd_consultation_add', 'b_ipd_consultation_delete', 'b_ipd_consultation_edit',
        'b_ipd_copy_medication', 'b_ipd_delete_charge', 'b_ipd_delete_intake_output',
        'b_ipd_delete_prescription', 'b_ipd_delete_treatment', 'b_ipd_delete_vital_monitoring',
        'b_ipd_discharge_patient', 'b_ipd_edit_charge', 'b_ipd_edit_intake_output',
        'b_ipd_edit_prescription', 'b_ipd_edit_treatment', 'b_ipd_edit_vital_monitoring',
        'b_ipd_invoice', 'b_ipd_new_charge', 'b_ipd_new_intake_output',
        'b_ipd_new_prescription', 'b_ipd_new_treatment', 'b_ipd_new_vital_monitoring',
        'b_ipd_patient_file_save', 'b_ipd_progress_sheet_save', 'b_ipd_rmo_notes_save',
        'b_ipd_save', 'b_ipd_view_history_detail', 'b_new_dcd',
        'b_new_ipd', 'b_new_opd', 'b_new_opd_prescription',
        'b_opd_delete_charge', 'b_opd_edit_charge', 'b_opd_new_charge',
        'b_opd_save', 'b_opd_view_history_detail', 'b_patient_edit',
        'b_patient_new', 'b_patient_visit_history', 'b_pharma_category_edit',
        'b_pharma_category_new', 'b_pharma_category_product_delete', 'b_pharma_category_product_edit',
        'b_pharma_category_product_list', 'b_pharma_category_product_new', 'b_role_add',
        'b_role_edit', 'b_role_permissions', 'b_staff_delete',
        'b_staff_edit', 'b_staff_new', 'b_tpa_add',
        'b_tpa_delete', 'b_tpa_edit', 'b_ward_categories_add',
        'b_ward_categories_edit',
        'b_wellness_save', 'b_wellness_delete',
        'b_immunization_add', 'b_immunization_edit', 'b_immunization_delete',
        'im_patient_documents', 'b_document_upload', 'b_document_delete',
        'im_audit_logs',
    ];

    public function index(Request $request, PermissionService $permissions)
    {
        $canAccess = $permissions->hasPermission('im_roles') && $permissions->hasPermission('m_roles_and_permissions');
        $canAdd = $permissions->hasPermission('b_role_add');
        $canEdit = $permissions->hasPermission('b_role_edit');
        $canPermissions = $permissions->hasPermission('b_role_permissions');

        $perPage = 50;
        $roles = $canAccess
            ? UserRole::orderBy('role', 'asc')->paginate($perPage)->withQueryString()
            : collect();

        return view('roles.index', compact('canAccess', 'canAdd', 'canEdit', 'canPermissions', 'roles'));
    }

    public function getUserRole($id = null): JsonResponse
    {
        $roleId = is_numeric($id) ? (int)$id : (int)request()->input('id', 0);
        $role = UserRole::find($roleId);
        return response()->json($role ? ['id' => $role->id, 'role' => $role->role] : (object)[]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $roleName = (string) $request->input('role', '');
        $userRole = UserRole::create(['role' => $roleName]);

        app(AuditLogger::class)->createRole($userRole->id, $roleName);

        return response()->json(['user_role_id' => $userRole->id]);
    }

    public function updateRole(Request $request): JsonResponse
    {
        $userRoleId = (int) $request->input('user_role_id');
        $roleName = (string) $request->input('role', '');
        if ($userRoleId > 0) {
            UserRole::where('id', $userRoleId)->update(['role' => $roleName]);
            app(AuditLogger::class)->updateRole($userRoleId, $roleName);
        }
        return response()->json(['user_role_id' => $userRoleId]);
    }

    public function saveRolePermissionsFromAjax(Request $request): JsonResponse
    {
        $roleId = (int) $request->input('user_role_id');
        $cb = (array) $request->input('cb', []);

        UserRolePermission::where('user_role_id', $roleId)->delete();

        $saved = [];
        foreach ($cb as $perm) {
            if (!empty($perm)) {
                UserRolePermission::create([
                    'user_role_id' => $roleId,
                    'permission'   => $perm,
                ]);
                $saved[] = $perm;
            }
        }

        Cache::forget("role_permissions_{$roleId}");

        $role = UserRole::find($roleId);
        app(AuditLogger::class)->permissionChange($roleId, (string) ($role?->role ?? $roleId), $saved);

        return response()->json(['result' => 'OK']);
    }

    /** Edit-modal payload. */
    public function show(int $id): JsonResponse
    {
        $role = UserRole::find($id);
        return response()->json($role ? ['id' => $role->id, 'role' => $role->role] : (object)[]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = UserRole::create($request->validated());

        app(AuditLogger::class)->createRole($role->id, (string) $role->role);

        return response()->json([
            'success' => true,
            'message' => 'Role created.',
            'data' => $role,
            'user_role_id' => $role->id,
        ]);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = UserRole::findOrFail($id);
        $role->update($request->validated());

        app(AuditLogger::class)->updateRole($role->id, (string) $role->role);

        return response()->json([
            'success' => true,
            'message' => 'Role updated.',
            'user_role_id' => $role->id,
        ]);
    }

    /**
     * Role Permissions screen (GET /roles/{id}/permissions; the old role-permissions.php?id= URL redirects here).
     * JSON clients (Accept: application/json) get the role with its granted and all known permissions instead.
     */
    public function permissions(Request $request, PermissionService $permissions, int $roleId): JsonResponse|View
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success'         => true,
                'role'            => UserRole::findOrFail($roleId),
                'all_permissions' => $this->allPermissions,
                'granted'         => UserRolePermission::where('user_role_id', $roleId)->pluck('permission')->toArray(),
            ]);
        }

        $canAccess = $permissions->hasPermission('b_role_permissions') && $permissions->hasPermission('m_roles_and_permissions');
        if (!$canAccess) {
            // Checked before the lookup, so a user without access cannot tell which roles exist.
            return view('roles.permissions', ['canAccess' => false, 'role' => null, 'assignedPermissions' => []]);
        }

        $role = UserRole::findOrFail($roleId);
        $assignedPermissions = UserRolePermission::where('user_role_id', $roleId)->pluck('permission')->toArray();

        return view('roles.permissions', ['canAccess' => true, 'role' => $role, 'assignedPermissions' => $assignedPermissions]);
    }

    public function savePermissions(SaveRolePermissionsRequest $request, int $roleId): JsonResponse
    {
        $role = UserRole::findOrFail($roleId);

        $newPermissions = (array) $request->validated('permissions', []);

        // Validate each permission is from known list
        $newPermissions = array_filter($newPermissions, fn($p) => in_array($p, $this->allPermissions));

        // Delete old and insert new
        UserRolePermission::where('user_role_id', $roleId)->delete();

        foreach ($newPermissions as $perm) {
            UserRolePermission::create([
                'user_role_id' => $roleId,
                'permission'   => $perm,
            ]);
        }

        // Bust cache
        Cache::forget("role_permissions_{$roleId}");

        app(AuditLogger::class)->permissionChange($roleId, (string) $role->role, array_values($newPermissions));

        return response()->json(['success' => true, 'message' => 'Permissions saved.', 'result' => 'OK']);
    }

    public function destroy(int $id): JsonResponse
    {
        UserRole::findOrFail($id)->delete();
        UserRolePermission::where('user_role_id', $id)->delete();
        Cache::forget("role_permissions_{$id}");

        app(AuditLogger::class)->deleteRole($id);

        return response()->json(['success' => true, 'message' => 'Role deleted.']);
    }
}
