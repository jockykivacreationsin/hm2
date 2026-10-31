<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Keeps the JSON body the bed/floor modals already read ({success:false, message}) when a
 * button permission (b_*) is missing, instead of Laravel's generic "This action is unauthorized.".
 */
trait DeniesWithJson
{
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => 'Permission denied.'], 403)
        );
    }
}
