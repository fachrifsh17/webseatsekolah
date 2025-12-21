<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_mapel'     => ['required', 'string', 'max:100'],
            'jurusan_id'     => ['nullable', 'integer', 'exists:jurusan,id'],
            'tipe_mapel'     => ['nullable', 'in:umum,khusus'],
            'kategori_mapel' => ['nullable', 'in:normatif,adaptif,produktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_mapel.required' => 'Nama mata pelajaran wajib diisi.',
            'nama_mapel.string'   => 'Nama mata pelajaran harus berupa teks.',
            'nama_mapel.max'      => 'Nama mata pelajaran tidak boleh lebih dari 100 karakter.',
            'jurusan_id.integer'  => 'Jurusan harus berupa angka.',
            'jurusan_id.exists'   => 'Jurusan yang dipilih tidak valid.',
            'tipe_mapel.in'       => 'Tipe mapel harus berupa: umum atau khusus.',
            'kategori_mapel.in'   => 'Kategori harus berupa: normatif, adaptif, atau produktif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_mapel'     => 'Nama mata pelajaran',
            'jurusan_id'     => 'Jurusan',
            'tipe_mapel'     => 'Tipe mata pelajaran',
            'kategori_mapel' => 'Kategori mata pelajaran',
        ];
    }
}