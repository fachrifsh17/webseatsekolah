<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class StoreTahunAjaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'      => ['required', 'string', 'max:50'],
            'semester'  => ['required', 'in:Ganjil,Genap'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'     => 'Nama tahun ajaran wajib diisi.',
            'nama.string'       => 'Nama tahun ajaran harus berupa teks.',
            'nama.max'          => 'Nama tahun ajaran tidak boleh lebih dari 50 karakter.',
            'semester.required' => 'Semester harus dipilih.',
            'semester.in'       => 'Pilihan semester hanya boleh Ganjil atau Genap.',
            'is_active.required' => 'Status aktif harus ditentukan.',
            'is_active.boolean'  => 'Status aktif harus berupa nilai boolean (true/false).',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'      => 'Nama tahun ajaran',
            'semester'  => 'Semester',
            'is_active' => 'Status aktif',
        ];
    }
}
