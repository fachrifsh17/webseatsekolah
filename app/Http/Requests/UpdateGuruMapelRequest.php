<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuruMapelRequest extends FormRequest
{
    public function authorize()
    {
        // Pastikan diubah menjadi true agar request tidak ditolak
        return true; 
    }

    public function rules()
    {
        return [
            'guru_staf_id' => 'required|exists:guru_staf,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
        ];
    }
}