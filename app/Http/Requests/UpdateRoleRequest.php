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
            'role_name'   => ['required', 'string', 'max:50', 'unique:roles,role_name,' . $roleId],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_name.required' => 'Nama role wajib diisi.',
            'role_name.string'   => 'Nama role harus berupa teks.',
            'role_name.max'      => 'Nama role tidak boleh lebih dari 50 karakter.',
            'role_name.unique'   => 'Nama role sudah digunakan, silakan pilih nama lain.',

            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max'    => 'Deskripsi tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'role_name'   => 'Nama role',
            'description' => 'Deskripsi role',
        ];
    }
}