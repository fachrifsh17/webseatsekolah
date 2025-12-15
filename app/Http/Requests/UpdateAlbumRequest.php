<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlbumRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "nama_album" => "sometimes|required|string|max:255",
            "tanggal_kegiatan" => "nullable|date",
            "cover_path" => "nullable|file|image|max:5120",
        ];
    }
}
