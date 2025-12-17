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
            'album_id'    => 'required|integer|exists:album,id',
            'media_path'  => 'required|file|mimes:jpg,jpeg,png,mp4|max:10240',
            'jenis_media' => 'required|in:Foto,Video',
            'keterangan'  => 'nullable|string|max:255',
        ];
    }
}