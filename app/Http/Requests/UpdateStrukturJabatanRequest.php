<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStrukturJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id' => 'required|integer|exists:guru_staf,id',
            'nama_jabatan_struktural' => 'required|string|max:100',
            'periode_mulai' => 'nullable|date',
            'urutan_tampil' => 'nullable|integer',
        ];
    }
}