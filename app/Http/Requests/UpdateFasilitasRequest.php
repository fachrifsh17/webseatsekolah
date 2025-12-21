<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFasilitasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_fasilitas' => ['sometimes', 'required', 'string', 'max:150'],
            'foto'           => ['nullable', 'file', 'image', 'max:5120'],
            'keterangan'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_fasilitas.required' => 'Nama fasilitas wajib diisi.',
            'nama_fasilitas.string'   => 'Nama fasilitas harus berupa teks.',
            'nama_fasilitas.max'      => 'Nama fasilitas tidak boleh lebih dari 150 karakter.',

            'foto.file'  => 'Foto harus berupa file.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max'   => 'Ukuran foto maksimal 5MB.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_fasilitas' => 'Nama fasilitas',
            'foto'           => 'Foto fasilitas',
            'keterangan'     => 'Keterangan',
        ];
    }
}