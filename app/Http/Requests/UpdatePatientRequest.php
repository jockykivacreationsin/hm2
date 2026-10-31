<?php

namespace App\Http\Requests;

/**
 * Edit Patient modal.
 */
class UpdatePatientRequest extends PatientRequest
{
    protected function ignoredPatientId(): ?int
    {
        return (int) $this->route('id') ?: null;
    }
}
