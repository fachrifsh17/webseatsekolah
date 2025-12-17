<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // 'sometimes' memastikan validasi 'required' hanya diterapkan jika 'judul' ada dalam request.
            "judul" => "sometimes|required|string|max:255",
            "url_link" => "nullable|url|max:255",
            "aktif_sampai" => "nullable|date",
            // 'foto' bersifat opsional ('nullable'), harus berupa file gambar, maks 5MB.
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}