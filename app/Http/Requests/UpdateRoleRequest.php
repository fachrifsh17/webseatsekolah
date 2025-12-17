<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role') ? $this->route('role')->id : null;

        return [
            'role_name' => 'required|string|max:50|unique:roles,role_name,' . $roleId,
            'description' => 'nullable|string|max:255',
        ];
    }
}