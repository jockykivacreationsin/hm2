<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

/**
 * Base for the Day Care (DCD) write requests.
 *
 * DcdController actions take a plain {@see Request} (they are still forwarded a plain request by the
 * legacy dispatcher), so they call {@see self::validatedFrom()} which runs authorize() + rules() exactly
 * as route injection would and returns the validated payload.
 */
abstract class DcdFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public static function validatedFrom(Request $request): array
    {
        $form = $request instanceof static
            ? $request
            : static::createFrom($request)->setContainer(app())->setRedirector(app('redirect'));

        $form->validateResolved();

        return $form->validated();
    }
}
