<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'      => ['bail','required','integer','exists:guru_staf,id'],
            'mata_pelajaran_id' => ['bail','required','integer','exists:mata_pelajaran,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required'      => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.integer'       => 'Guru/Staf harus berupa angka.',
            'guru_staf_id.exists'        => 'Guru/Staf tidak ditemukan dalam sistem.',
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.integer'  => 'Mata pelajaran harus berupa angka.',
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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422));
    }
}
