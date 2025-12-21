<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePoinSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && (Auth::user()->role === 'admin' || Auth::user()->role === 'guru');
    }

    public function rules(): array
    {
        return [
            'siswa_id'        => ['sometimes', 'required', 'exists:siswa,id'],
            'guru_staf_id'    => ['sometimes', 'required', 'exists:guru_staf,id'],
            'tahun_ajaran_id' => ['sometimes', 'required', 'exists:tahun_ajaran,id'],
            'indikator'       => ['sometimes', 'required', 'string'],
            'poin_positif'    => ['nullable', 'integer', 'min:0'],
            'poin_negatif'    => ['nullable', 'integer', 'min:0'],
            'tanggal'         => ['sometimes', 'required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required'     => 'Siswa wajib dipilih.',
            'siswa_id.exists'       => 'Data siswa tidak valid.',

            'guru_staf_id.required' => 'Guru wajib dipilih.',
            'guru_staf_id.exists'   => 'Data guru tidak ditemukan.',

            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',

            'indikator.required' => 'Indikator wajib diisi.',
            'indikator.string'   => 'Indikator harus berupa teks.',

            'poin_positif.integer' => 'Poin positif harus berupa angka.',
            'poin_positif.min'     => 'Poin positif minimal bernilai 0.',

            'poin_negatif.integer' => 'Poin negatif harus berupa angka.',
            'poin_negatif.min'     => 'Poin negatif minimal bernilai 0.',

            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date'     => 'Tanggal harus berupa format tanggal yang valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'        => 'Siswa',
            'guru_staf_id'    => 'Guru',
            'tahun_ajaran_id' => 'Tahun ajaran',
            'indikator'       => 'Indikator',
            'poin_positif'    => 'Poin positif',
            'poin_negatif'    => 'Poin negatif',
            'tanggal'         => 'Tanggal',
        ];
    }
}