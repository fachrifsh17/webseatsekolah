<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTahunAjaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'     => ['sometimes', 'required', 'string', 'max:50'],
            'semester' => ['sometimes', 'required', 'in:Ganjil,Genap'],
            'aktif'    => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama tahun ajaran wajib diisi.',
            'nama.string'   => 'Nama tahun ajaran harus berupa teks.',
            'nama.max'      => 'Nama tahun ajaran maksimal 50 karakter.',

            'semester.required' => 'Semester wajib dipilih.',
            'semester.in'       => 'Pilihan semester hanya boleh Ganjil atau Genap.',

            'aktif.required' => 'Status aktif wajib diisi.',
            'aktif.boolean'  => 'Format status aktif tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'     => 'Nama tahun ajaran',
            'semester' => 'Semester',
            'aktif'    => 'Status aktif',
        ];
    }
}