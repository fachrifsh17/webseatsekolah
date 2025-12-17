<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJurusanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "nama_jurusan" => "required|string|max:100",
            "deskripsi" => "nullable|string",
            // Asumsi ini untuk upload file gambar jurusan
            "foto" => "nullable|file|image|max:5120", 
        ];
    }
}