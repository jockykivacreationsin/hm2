<?php
namespace App\Services;

use App\Models\UserRolePermission;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    private array $permissions = [];

    public function __construct()
    {
        $this->loadPermissions();
    }


    private function loadPermissions(): void
    {
        $roleId = CurrentUser::roleId();
        if ($roleId <= 0) {
            $this->permissions = [];
            return;
        }

        try {
            $cacheKey = "role_permissions_{$roleId}";
            $this->permissions = Cache::remember($cacheKey, config('hospital.permissions_cache_ttl', 300), function () use ($roleId) {
                return UserRolePermission::where('user_role_id', $roleId)
                    ->pluck('permission')
                    ->toArray();
            });
        } catch (\Throwable $e) {
            $this->permissions = [];
        }
    }

    public function hasPermission(string $permission): bool
    {
        if (isset($this->granted) && is_array($this->granted)) {
            return in_array($permission, $this->granted, true);
        }
        if (empty($this->permissions) && CurrentUser::roleId() > 0) {
            $this->loadPermissions();
        }
        return in_array($permission, $this->permissions);
    }

    public function getPermissions(): array
    {
        if (isset($this->granted) && is_array($this->granted)) {
            return $this->granted;
        }
        if (empty($this->permissions) && CurrentUser::roleId() > 0) {
            $this->loadPermissions();
        }
        return $this->permissions;
    }

    // Replaces legacy rp() function - returns 'Granted' or 'Denied'
    public function check(string $permission): string
    {
        return $this->hasPermission($permission) ? 'Granted' : 'Denied';
    }

    public static function clearCache(int $roleId): void
    {
        Cache::forget("role_permissions_{$roleId}");
        if (app()->bound(self::class)) {
            $inst = app(self::class);
            $inst->permissions = [];
        }
    }
}
