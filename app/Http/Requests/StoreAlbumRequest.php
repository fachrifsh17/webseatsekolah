<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_album'       => ['required', 'string', 'max:255'],
            'tanggal_kegiatan' => ['nullable', 'date'],
            'cover_path'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_album.required' => 'Nama album harus diisi.',
            'nama_album.string'   => 'Nama album harus berupa teks.',
            'nama_album.max'      => 'Nama album tidak boleh lebih dari 255 karakter.',
            'tanggal_kegiatan.date' => 'Tanggal kegiatan harus berupa format tanggal yang valid.',
            'cover_path.image'    => 'File harus berupa gambar.',
            'cover_path.mimes'    => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'cover_path.max'      => 'Ukuran gambar maksimal adalah 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_album'       => 'Nama album',
            'tanggal_kegiatan' => 'Tanggal kegiatan',
            'cover_path'       => 'Cover album',
        ];
    }
}