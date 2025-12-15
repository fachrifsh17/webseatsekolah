<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuruRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            "nip" => "nullable|string|max:18|unique:guru_staf,nip",
            "nuptk" => "nullable|string|max:16|unique:guru_staf,nuptk",
            "nama" => "required|string|max:100",
            "jabatan_fungsional" => "nullable|string|max:100",
            "status_kepegawaian" => "nullable|string|max:50",
            "foto" => "nullable|file|image|max:5120",
            "jurusan_id" => "nullable|integer|exists:jurusan,id",
        ];
    }
}
