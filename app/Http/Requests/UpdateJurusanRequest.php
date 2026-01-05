<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJurusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_jurusan' => ['required', 'string', 'max:100'],
            'deskripsi'    => ['nullable', 'string'],
            'foto'         => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_jurusan.required' => 'Nama jurusan wajib diisi.',
            'nama_jurusan.string'   => 'Nama jurusan harus berupa teks.',
            'nama_jurusan.max'      => 'Nama jurusan tidak boleh lebih dari 100 karakter.',
            'deskripsi.string'      => 'Deskripsi harus berupa teks.',
            'foto.file'             => 'Foto harus berupa file.',
            'foto.image'            => 'Foto harus berupa gambar.',
            'foto.max'              => 'Ukuran foto maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_jurusan' => 'Nama jurusan',
            'deskripsi'    => 'Deskripsi',
            'foto'         => 'Foto jurusan',
        ];
    }
}
    