<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username'   => ['bail','required','string','max:50','unique:users,username'],
            'password'   => ['bail','required','string','min:6','confirmed'],
            'role_ids'   => ['bail','required','array','min:1'],
            'role_ids.*' => ['bail','string','exists:roles,id'],
            'is_active'  => ['nullable','integer','in:0,1'],
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

            'role_ids.required'   => 'Minimal satu role wajib dipilih.',
            'role_ids.array'      => 'Format role harus berupa array.',
            'role_ids.*.string'   => 'Role ID harus berupa ID string.',
            'role_ids.*.exists'   => 'Salah satu role tidak ditemukan dalam sistem.',

            'is_active.integer'   => 'Status aktif harus berupa angka.',
            'is_active.in'        => 'Status tidak valid. Gunakan 0 atau 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'username'  => 'Username',
            'password'  => 'Password',
            'role_ids'  => 'Role',
            'is_active' => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
