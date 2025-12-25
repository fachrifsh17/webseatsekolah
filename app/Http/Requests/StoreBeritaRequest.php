<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeritaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'             => ['required', 'string', 'max:255'],
            'isi_berita'        => ['required', 'string'],
            'tanggal_publikasi' => ['nullable', 'date'],
            'foto'              => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'             => 'Judul berita wajib diisi.',
            'judul.string'               => 'Judul berita harus berupa teks.',
            'judul.max'                  => 'Judul berita tidak boleh lebih dari 255 karakter.',
            'isi_berita.required'        => 'Konten berita tidak boleh kosong.',
            'isi_berita.string'          => 'Konten berita harus berupa teks.',
            'tanggal_publikasi.date'     => 'Tanggal publikasi harus berupa format tanggal yang valid.',
            'foto.image'                 => 'File harus berupa gambar.',
            'foto.mimes'                 => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'foto.max'                   => 'Ukuran gambar maksimal adalah 5MB.',
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