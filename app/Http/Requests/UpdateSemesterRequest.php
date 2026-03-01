<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'      => 'required|string|max:20',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama semester wajib diisi.',
            'nama.string'   => 'Nama semester harus berupa teks.',
            'nama.max'      => 'Nama semester maksimal 20 karakter.',
            'is_active.boolean' => 'Status aktif harus berupa true atau false.',
        ];
    }
}