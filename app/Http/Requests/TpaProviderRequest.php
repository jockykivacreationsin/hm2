<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add / Update TPA company modal (Master > TPA Companies).
 */
class TpaProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ps = app(PermissionService::class);
        $button = $this->route('id') ? 'b_tpa_edit' : 'b_tpa_add';

        return $ps->hasPermission('m_master') || $ps->hasPermission($button);
    }

    public function rules(): array
    {
        return [
            'tpa_name'       => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'address'        => ['required', 'string'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email'          => ['required', 'email', 'max:255'],
        ];
    }
}
