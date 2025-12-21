<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'      => ['required', 'integer', 'exists:guru_staf,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required'      => 'Guru harus dipilih.',
            'guru_staf_id.integer'       => 'Guru harus berupa angka.',
            'guru_staf_id.exists'        => 'Data guru tidak ditemukan.',
            'mata_pelajaran_id.required' => 'Mata pelajaran harus dipilih.',
            'mata_pelajaran_id.integer'  => 'Mata pelajaran harus berupa angka.',
            'mata_pelajaran_id.exists'   => 'Data mata pelajaran tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'      => 'Guru',
            'mata_pelajaran_id' => 'Mata pelajaran',
        ];
    }
}