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
            'kelas_id'        => 'required|string|exists:kelas,id',
            'guru_staf_id'    => 'required|string|exists:guru_staf,id',
            'tahun_ajaran_id' => 'required|string|exists:tahun_ajaran,id',
            'is_active'       => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.required'        => 'Kelas wajib diisi.',
            'kelas_id.exists'          => 'Kelas tidak ditemukan.',
            'guru_staf_id.required'    => 'Guru wajib diisi.',
            'guru_staf_id.exists'      => 'Guru tidak ditemukan.',
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak ditemukan.',
            'is_active.boolean'        => 'Status aktif harus berupa benar atau salah.',
        ];
    }
}