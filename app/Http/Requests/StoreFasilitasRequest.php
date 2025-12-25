<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFasilitasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_fasilitas' => ['required', 'string', 'max:150'],
            'foto'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'keterangan'     => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_fasilitas.required' => 'Nama fasilitas wajib diisi.',
            'nama_fasilitas.string'   => 'Nama fasilitas harus berupa teks.',
            'nama_fasilitas.max'      => 'Nama fasilitas tidak boleh lebih dari 150 karakter.',
            'foto.image'              => 'File harus berupa gambar.',
            'foto.mimes'              => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'foto.max'                => 'Ukuran gambar maksimal adalah 5MB.',
            'keterangan.string'       => 'Keterangan harus berupa teks.',
            'keterangan.max'          => 'Keterangan tidak boleh lebih dari 255 karakter.',
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