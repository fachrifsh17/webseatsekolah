<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePrestasiRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "judul" => "required|string|max:255",
            "tahun" => "nullable|digits:4|integer",
            "tingkat" => "nullable|string|max:50",
            "kategori" => "nullable|in:Siswa,Sekolah",
            "foto" => "nullable|file|image|max:5120",
        ];
    }
}
