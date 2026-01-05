<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userParam = $this->route('user');
        $userId = is_object($userParam) ? $userParam->id : $userParam;

        return [
            'username'     => ['bail','required','string','max:50','unique:users,username,' . $userId],
            'password'     => ['bail','nullable','string','min:6'],
            'nama_lengkap' => ['nullable','string','max:100'],
            'role_ids'     => ['bail','required','array','min:1'],
            'role_ids.*'   => ['bail','integer','exists:roles,id'],
            'is_active'    => ['nullable','integer','in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required'   => 'Username wajib diisi.',
            'username.string'     => 'Username harus berupa teks.',
            'username.max'        => 'Username tidak boleh lebih dari 50 karakter.',
            'username.unique'     => 'Username sudah digunakan, silakan pilih username lain.',

            'password.string'     => 'Password harus berupa teks.',
            'password.min'        => 'Password minimal 6 karakter.',

            'nama_lengkap.string' => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'    => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'role_ids.required'   => 'Minimal satu role wajib dipilih.',
            'role_ids.array'      => 'Format role harus berupa array.',
            'role_ids.*.integer'  => 'Role ID harus berupa angka.',
            'role_ids.*.exists'   => 'Salah satu role tidak ditemukan.',

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
