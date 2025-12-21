<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user') ? $this->route('user')->id : null;

        return [
            'username'     => ['required', 'string', 'max:50', 'unique:users_admin,username,' . $userId],
            'password'     => ['nullable', 'string', 'min:6'],
            'nama_lengkap' => ['nullable', 'string', 'max:100'],
            'role_id'      => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi.',
            'username.string'   => 'Username harus berupa teks.',
            'username.max'      => 'Username tidak boleh lebih dari 50 karakter.',
            'username.unique'   => 'Username sudah digunakan, silakan pilih username lain.',

            'password.string' => 'Password harus berupa teks.',
            'password.min'    => 'Password minimal 6 karakter.',

            'nama_lengkap.string' => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'    => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'role_id.required' => 'Role wajib dipilih.',
            'role_id.integer'  => 'Role ID harus berupa angka.',
            'role_id.exists'   => 'Role tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'username'     => 'Username',
            'password'     => 'Password',
            'nama_lengkap' => 'Nama lengkap',
            'role_id'      => 'Role',
        ];
    }
}