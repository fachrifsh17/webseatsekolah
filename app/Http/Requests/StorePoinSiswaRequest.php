<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePoinSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siswa_id'        => ['required', 'integer', 'exists:siswa,id'],
            'guru_staf_id'    => ['required', 'integer', 'exists:guru_staf,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'indikator'       => ['required', 'string'],
            'poin_positif'    => ['nullable', 'integer', 'min:0'],
            'poin_negatif'    => ['nullable', 'integer', 'min:0'],
            'tanggal'         => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required'        => 'Siswa harus dipilih.',
            'siswa_id.integer'         => 'ID siswa harus berupa angka.',
            'siswa_id.exists'          => 'Data siswa tidak ditemukan.',
            'guru_staf_id.required'    => 'Guru pelapor wajib diisi.',
            'guru_staf_id.integer'     => 'ID guru harus berupa angka.',
            'guru_staf_id.exists'      => 'Data guru tidak ditemukan.',
            'tahun_ajaran_id.required' => 'Tahun ajaran harus dipilih.',
            'tahun_ajaran_id.integer'  => 'ID tahun ajaran harus berupa angka.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak ditemukan.',
            'indikator.required'       => 'Keterangan indikator atau pelanggaran tidak boleh kosong.',
            'indikator.string'         => 'Indikator harus berupa teks.',
            'poin_positif.integer'     => 'Poin positif harus berupa angka.',
            'poin_positif.min'         => 'Poin positif tidak boleh bernilai negatif.',
            'poin_negatif.integer'     => 'Poin negatif harus berupa angka.',
            'poin_negatif.min'         => 'Poin negatif tidak boleh bernilai negatif.',
            'tanggal.required'         => 'Tanggal kejadian harus diisi.',
            'tanggal.date'             => 'Format tanggal kejadian tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'        => 'Siswa',
            'guru_staf_id'    => 'Guru pelapor',
            'tahun_ajaran_id' => 'Tahun ajaran',
            'indikator'       => 'Indikator',
            'poin_positif'    => 'Poin positif',
            'poin_negatif'    => 'Poin negatif',
            'tanggal'         => 'Tanggal kejadian',
        ];
    }
}