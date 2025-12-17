<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlbumRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "nama_album" => "required|string|max:255",
            "tanggal_kegiatan" => "nullable|date",
            // Asumsi ini untuk upload file
            "cover_path" => "nullable|file|image|max:5120", 
        ];
    }
}