<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'album_id'    => ['required', 'integer', 'exists:album,id'],
            'media_path'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,mp4', 'max:10240'],
            'jenis_media' => ['required', 'in:Foto,Video'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required' => 'Album wajib dipilih.',
            'album_id.integer'  => 'Album ID harus berupa angka.',
            'album_id.exists'   => 'Album tidak ditemukan.',

            'media_path.file'  => 'Media harus berupa file.',
            'media_path.mimes' => 'Format media hanya boleh JPG, JPEG, PNG, atau MP4.',
            'media_path.max'   => 'Ukuran media maksimal 10MB.',

            'jenis_media.required' => 'Jenis media wajib dipilih.',
            'jenis_media.in'       => 'Jenis media hanya boleh Foto atau Video.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
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