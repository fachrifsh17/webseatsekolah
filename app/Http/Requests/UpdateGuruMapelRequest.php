<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'       => ['required', 'exists:guru_staf,id'],
            'mata_pelajaran_id'  => ['required', 'exists:mata_pelajaran,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required'      => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.exists'        => 'Guru/Staf tidak ditemukan dalam sistem.',

            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists'   => 'Mata pelajaran tidak ditemukan dalam sistem.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'      => 'Guru/Staf',
            'mata_pelajaran_id' => 'Mata pelajaran',
        ];
    }
}