<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTingkatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_tingkatan' => 'required|string|max:50|unique:tingkatan,nama_tingkatan,' . $this->tingkatan->id,
        ];
    }

    public function messages(): array
    {
        return [
            'nama_tingkatan.required' => 'Nama wajib diisi.',
            'nama_tingkatan.unique'   => 'Nama tingkatan sudah digunakan.',
        ];
    }
}