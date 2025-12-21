<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id'    => ['required', 'integer', 'exists:album,id'],
            'media_path'  => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:20480'], // 20MB untuk video
            'jenis_media' => ['required', 'in:Foto,Video'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required'    => 'Album harus dipilih.',
            'album_id.integer'     => 'Album harus berupa angka.',
            'album_id.exists'      => 'Album tidak ditemukan.',
            'media_path.required'  => 'File media wajib diunggah.',
            'media_path.file'      => 'File media harus berupa file.',
            'media_path.mimes'     => 'Format yang didukung: JPG, JPEG, PNG, WEBP untuk foto, dan MP4, MOV untuk video.',
            'media_path.max'       => 'Ukuran file maksimal adalah 20MB.',
            'jenis_media.required' => 'Tentukan jenis media (Foto atau Video).',
            'jenis_media.in'       => 'Jenis media harus berupa Foto atau Video.',
            'keterangan.string'    => 'Keterangan harus berupa teks.',
            'keterangan.max'       => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'album_id'    => 'Album',
            'media_path'  => 'File media',
            'jenis_media' => 'Jenis media',
            'keterangan'  => 'Keterangan',
        ];
    }
}