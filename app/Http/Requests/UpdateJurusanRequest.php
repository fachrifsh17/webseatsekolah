<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJurusanRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "nama_jurusan" => "sometimes|required|string|max:100",
            "deskripsi" => "nullable|string",
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}
