<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrestasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'    => ['sometimes', 'required', 'string', 'max:255'],
            'tahun'    => ['nullable', 'digits:4', 'integer'],
            'tingkat'  => ['nullable', 'string', 'max:50'],
            'kategori' => ['nullable', 'in:Siswa,Sekolah,Guru'],
            'foto'     => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul prestasi wajib diisi.',
            'judul.string'   => 'Judul prestasi harus berupa teks.',
            'judul.max'      => 'Judul prestasi tidak boleh lebih dari 255 karakter.',

            'tahun.digits'  => 'Tahun harus terdiri dari 4 digit.',
            'tahun.integer' => 'Tahun harus berupa angka.',

            'tingkat.string' => 'Tingkat harus berupa teks.',
            'tingkat.max'    => 'Tingkat tidak boleh lebih dari 50 karakter.',

            'kategori.in' => 'Kategori hanya boleh bernilai Siswa, Sekolah, atau Guru.',

            'foto.file'  => 'Foto harus berupa file.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max'   => 'Ukuran foto maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'    => 'Judul prestasi',
            'tahun'    => 'Tahun',
            'tingkat'  => 'Tingkat',
            'kategori' => 'Kategori',
            'foto'     => 'Foto prestasi',
        ];
    }
}
