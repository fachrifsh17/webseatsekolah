<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBeritaRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "sometimes|required|string|max:255",
            "isi_berita" => "sometimes|required|string",
            "tanggal_publikasi" => "nullable|date",
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}
