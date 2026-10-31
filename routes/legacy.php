<?php

use App\Http\Controllers\LegacyUrlRedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy URL redirects
|--------------------------------------------------------------------------
|
| One GET route per entry of config/legacy_urls.php, all handled by LegacyUrlRedirectController. Included from routes/web.php
| inside the authenticated group, like the parity routes. Remove this file, the config and the controller together when the old
| `*.php` bookmarks are no longer needed.
|
*/

foreach (config('legacy_urls') as $uri => $rule) {
    Route::get($uri, LegacyUrlRedirectController::class)->name($rule['name']);
}
