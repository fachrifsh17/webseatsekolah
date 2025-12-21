<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user diizinkan, bisa diganti dengan logika otorisasi jika perlu
        return true;
    }

    public function rules(): array
    {
        return [
            'username'     => ['required', 'string', 'max:50', 'unique:users_admin,username'],
            'password'     => ['required', 'string', 'min:6'],
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
            'username.unique'   => 'Username sudah digunakan, silakan pilih nama lain.',

            'password.required' => 'Password wajib diisi.',
            'password.string'   => 'Password harus berupa teks.',
            'password.min'      => 'Password minimal harus 6 karakter.',

            'nama_lengkap.string' => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'    => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'role_id.required' => 'Role wajib dipilih.',
            'role_id.integer'  => 'Role ID harus berupa angka.',
            'role_id.exists'   => 'Role tidak ditemukan dalam sistem.',
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