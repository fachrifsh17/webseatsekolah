<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_album'       => ['sometimes', 'required', 'string', 'max:255'],
            'tanggal_kegiatan' => ['nullable', 'date'],
            'cover_path'       => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_album.required' => 'Nama album wajib diisi.',
            'nama_album.string'   => 'Nama album harus berupa teks.',
            'nama_album.max'      => 'Nama album tidak boleh lebih dari 255 karakter.',

            'tanggal_kegiatan.date' => 'Format tanggal kegiatan tidak valid.',

            'cover_path.file'  => 'Cover harus berupa file.',
            'cover_path.image' => 'Cover harus berupa gambar.',
            'cover_path.max'   => 'Ukuran cover maksimal 5MB.',
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