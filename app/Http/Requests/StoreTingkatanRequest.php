<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTingkatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'id' dihapus karena auto-increment
            'nama_tingkatan' => 'required|string|max:50|unique:tingkatan,nama_tingkatan',
        ];
    }

    public function messages(): array
    {
        return [
            // Pesan untuk 'id' dihapus
            'nama_tingkatan.required' => 'Nama wajib diisi.',
            'nama_tingkatan.unique'   => 'Nama tingkatan sudah digunakan.',
        ];
    }
}