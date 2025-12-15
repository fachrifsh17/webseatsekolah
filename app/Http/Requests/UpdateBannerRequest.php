<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "sometimes|required|string|max:255",
            "url_link" => "nullable|url|max:255",
            "aktif_sampai" => "nullable|date",
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}
