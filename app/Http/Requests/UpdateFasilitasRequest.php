<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFasilitasRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "nama_fasilitas" => "sometimes|required|string|max:150",
            "foto" => "nullable|file|image|max:5120",
            "keterangan" => "nullable|string|max:255",
        ];
    }
}