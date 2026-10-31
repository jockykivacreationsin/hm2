<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirects an old core-PHP URL (`opd-overview.php?id=5`) to its Laravel route.
 *
 * The mapping for each URL lives in config/legacy_urls.php and the routes are registered by routes/legacy.php; the matched
 * route's name identifies which entry applies. Being a plain invokable controller, none of it prevents `route:cache`.
 */
class LegacyUrlRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $rule = $this->ruleFor($request);

        if ($rule['query'] ?? false) {
            return redirect()->route($rule['to'], $request->query());
        }

        $parameters = $rule['fixed'] ?? [];

        foreach ($rule['params'] ?? [] as $routeParameter => $source) {
            $value = $this->firstPresent($request, (array) $source);

            if (in_array($routeParameter, $rule['int'] ?? [], true)) {
                $value = (int) $value;
            }

            // `optional` parameters are dropped when empty instead of producing `?service_id=`.
            if (in_array($routeParameter, $rule['optional'] ?? [], true) && ($value === null || $value === '' || $value === 0)) {
                continue;
            }

            $parameters[$routeParameter] = $value;
        }

        return redirect()->route($rule['to'], $parameters);
    }

    /** @return array<string,mixed> */
    private function ruleFor(Request $request): array
    {
        $name = $request->route()?->getName();

        foreach (config('legacy_urls') as $rule) {
            if ($rule['name'] === $name) {
                return $rule;
            }
        }

        abort(404);
    }

    private function firstPresent(Request $request, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
