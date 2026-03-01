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
            'nama'         => ['required', 'string', 'max:50'],
            // 'semester' dihapus karena akan diatur di controller Semester
            'is_active'    => ['required', 'boolean'],
            'kurikulum_id' => ['nullable', 'integer', 'exists:kurikulum,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'         => 'Nama tahun ajaran wajib diisi.',
            'nama.string'           => 'Nama tahun ajaran harus berupa teks.',
            'nama.max'              => 'Nama tahun ajaran tidak boleh lebih dari 50 karakter.',
            'is_active.required'    => 'Status aktif harus ditentukan.',
            'is_active.boolean'     => 'Status aktif harus berupa nilai boolean (true/false).',
            'kurikulum_id.integer'  => 'ID kurikulum harus berupa angka.',
            'kurikulum_id.exists'   => 'Kurikulum yang dipilih tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama'         => 'Nama tahun ajaran',
            'is_active'    => 'Status aktif',
            'kurikulum_id' => 'Kurikulum',
        ];
    }
}