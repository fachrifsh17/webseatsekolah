<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "required|string|max:255",
            "url_link" => "nullable|url|max:255",
            "aktif_sampai" => "nullable|date",
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}
