<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFasilitasRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "nama_fasilitas" => "required|string|max:150",
            // Asumsi ini untuk upload file gambar fasilitas
            "foto" => "nullable|file|image|max:5120", 
            "keterangan" => "nullable|string|max:255",
        ];
    }
}