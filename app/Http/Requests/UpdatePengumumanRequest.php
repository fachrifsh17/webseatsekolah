<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengumumanRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "sometimes|required|string|max:255",
            "isi_pengumuman" => "sometimes|required|string",
            "tanggal_publikasi" => "nullable|date",
            "penting" => "nullable|boolean",
        ];
    }
}
