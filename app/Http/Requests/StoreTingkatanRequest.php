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
            'id'             => 'required|string|max:10|unique:tingkatan,id',
            'nama_tingkatan' => 'required|string|max:50|unique:tingkatan,nama_tingkatan',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'             => 'ID wajib diisi.',
            'id.unique'               => 'ID tingkatan sudah digunakan.',
            'nama_tingkatan.required' => 'Nama wajib diisi.',
            'nama_tingkatan.unique'   => 'Nama tingkatan sudah digunakan.',
        ];
    }
}