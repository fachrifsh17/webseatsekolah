<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTahunAjaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama'         => ['sometimes', 'required', 'string', 'max:50'],
            // 'semester' dihapus karena akan diatur di controller Semester
            'is_active'    => ['sometimes', 'required', 'boolean'],
            'kurikulum_id' => ['sometimes', 'nullable', 'integer', 'exists:kurikulum,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama tahun ajaran wajib diisi.',
            'nama.string'   => 'Nama tahun ajaran harus berupa teks.',
            'nama.max'      => 'Nama tahun ajaran maksimal 50 karakter.',

            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean'  => 'Format status aktif tidak valid.',

            'kurikulum_id.integer' => 'ID kurikulum harus berupa angka.',
            'kurikulum_id.exists'  => 'Kurikulum yang dipilih tidak ditemukan.',
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