<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeritaRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "judul" => "required|string|max:255",
            "isi_berita" => "required|string",
            "tanggal_publikasi" => "nullable|date",
            // Asumsi ini untuk upload file gambar utama berita
            "foto" => "nullable|file|image|max:5120", 
        ];
    }
}