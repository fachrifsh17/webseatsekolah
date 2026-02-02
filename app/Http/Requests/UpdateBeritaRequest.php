<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeritaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'             => ['sometimes', 'required', 'string', 'max:255'],
            'isi_berita'        => ['sometimes', 'required', 'string'],
            'tanggal_publikasi' => ['nullable', 'date'],
            'foto'              => ['nullable', 'file', 'image', 'max:5120'], // maks 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required' => 'Judul berita wajib diisi.',
            'judul.string'   => 'Judul berita harus berupa teks.',
            'judul.max'      => 'Judul berita tidak boleh lebih dari 255 karakter.',

            'isi_berita.required' => 'Isi berita wajib diisi.',
            'isi_berita.string'   => 'Isi berita harus berupa teks.',

            'tanggal_publikasi.date' => 'Format tanggal publikasi tidak valid.',

            'foto.file'  => 'Foto harus berupa file.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max'   => 'Ukuran foto maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'             => 'Judul berita',
            'isi_berita'        => 'Isi berita',
            'tanggal_publikasi' => 'Tanggal publikasi',
            'foto'              => 'Foto berita',
        ];
    }
}