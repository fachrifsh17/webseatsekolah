<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMapelRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            "nama_mapel" => "required|string|max:100",
            "jurusan_id" => "nullable|integer|exists:jurusan,id",
            "tipe_mapel" => "nullable|in:umum,khusus",
            "kategori_mapel" => "nullable|in:normatif,adaptif,produktif",
        ];
    }
}