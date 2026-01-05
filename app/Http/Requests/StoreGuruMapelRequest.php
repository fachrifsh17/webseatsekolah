<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreGuruMapelRequest extends FormRequest
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
            'guru_staf_id.required'      => 'Guru wajib dipilih.',
            'guru_staf_id.integer'       => 'Guru harus berupa angka.',
            'guru_staf_id.exists'        => 'Data guru tidak ditemukan.',
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], 422));
    }
}
