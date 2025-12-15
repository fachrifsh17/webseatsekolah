<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePengumumanRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "required|string|max:255",
            "isi_pengumuman" => "required|string",
            "tanggal_publikasi" => "nullable|date",
            "penting" => "nullable|boolean",
        ];
    }
}
