<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStrukturJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'           => ['required', 'integer', 'exists:guru_staf,id'],
            'nama_jabatan_struktural'=> ['required', 'string', 'max:100'],
            'periode_mulai'          => ['nullable', 'date'],
            'urutan_tampil'          => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required' => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.integer'  => 'Guru/Staf ID harus berupa angka.',
            'guru_staf_id.exists'   => 'Data guru/staf tidak ditemukan.',

            'nama_jabatan_struktural.required' => 'Nama jabatan struktural wajib diisi.',
            'nama_jabatan_struktural.string'   => 'Nama jabatan struktural harus berupa teks.',
            'nama_jabatan_struktural.max'      => 'Nama jabatan struktural tidak boleh lebih dari 100 karakter.',

            'periode_mulai.date' => 'Periode mulai harus berupa tanggal yang valid.',

            'urutan_tampil.integer' => 'Urutan tampil harus berupa angka.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'            => 'Guru/Staf',
            'nama_jabatan_struktural' => 'Nama jabatan struktural',
            'periode_mulai'           => 'Periode mulai',
            'urutan_tampil'           => 'Urutan tampil',
        ];
    }
}