<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKelasWaliKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kelas_id'      => 'required|string|exists:kelas,id,is_active,1',
            'guru_staf_id'  => 'required|string|exists:guru_staf,id,is_active,1',
            'is_active'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.required'     => 'Kelas wajib diisi.',
            'kelas_id.exists'       => 'Kelas tidak ditemukan atau tidak aktif.',
            'guru_staf_id.required' => 'Guru wajib diisi.',
            'guru_staf_id.exists'   => 'Guru tidak ditemukan atau tidak aktif.',
            'is_active.boolean'     => 'Status aktif harus berupa benar atau salah.',
        ];
    }
}