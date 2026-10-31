<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline permission check for every authenticated route, driven by config/route_permissions.php.
 * The most specific (longest) matching route-name prefix decides; any one of its permissions is enough.
 */
class ModulePermission
{
    public function __construct(private PermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $required = self::requiredFor($request->route()?->getName());

        $allowed = $required !== null && collect($required)->contains(fn (string $p) => $this->permissions->hasPermission($p));
        if (!$allowed) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
            }
            abort(403, 'Access Denied');
        }

        return $next($request);
    }

    /** @return string[]|null null when the route name matches nothing in the map (access is refused). */
    public static function requiredFor(?string $routeName): ?array
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        $map = config('route_permissions', []);
        $best = null;
        foreach ($map as $prefix => $permissions) {
            if (($routeName === $prefix || str_starts_with($routeName, $prefix . '.'))
                && ($best === null || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best === null ? null : $map[$best];
    }
}
