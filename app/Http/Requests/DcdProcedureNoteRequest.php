<?php

namespace App\Http\Requests;

/** Procedure note editor on dcd/procedures. */
class DcdProcedureNoteRequest extends DcdFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string'],
        ];
    }
}
