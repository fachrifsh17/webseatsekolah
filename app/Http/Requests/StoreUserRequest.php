<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'     => ['bail','required','string','max:50','unique:users,username'],
            'password'     => ['bail','required','string','min:6','confirmed'], 
            'nama_lengkap' => ['nullable','string','max:100'],
            'role_ids'     => ['bail','required','array','min:1'],
            'role_ids.*'   => ['bail','integer','exists:roles,id'],
            'is_active'    => ['nullable','integer','in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required'  => 'Username wajib diisi.',
            'username.string'    => 'Username harus berupa teks.',
            'username.max'       => 'Username tidak boleh lebih dari 50 karakter.',
            'username.unique'    => 'Username sudah digunakan, silakan pilih nama lain.',

            'password.required'  => 'Password wajib diisi.',
            'password.string'    => 'Password harus berupa teks.',
            'password.min'       => 'Password minimal harus 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',

            'nama_lengkap.string' => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'    => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'role_ids.required'   => 'Minimal satu role wajib dipilih.',
            'role_ids.array'      => 'Format role harus berupa array.',
            'role_ids.*.integer'  => 'Role ID harus berupa angka.',
            'role_ids.*.exists'   => 'Salah satu role tidak ditemukan dalam sistem.',

            'is_active.integer'   => 'Status aktif harus berupa angka.',
            'is_active.in'        => 'Status tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'username'     => 'Username',
            'password'     => 'Password',
            'nama_lengkap' => 'Nama lengkap',
            'role_ids'     => 'Role',
            'is_active'    => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422));
    }
}
