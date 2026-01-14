<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'      => ['bail', 'required', 'integer', 'exists:guru_staf,id'],
            'mata_pelajaran_id' => ['bail', 'required', 'integer', 'exists:mata_pelajaran,id'],
            'kelas_id'          => ['bail', 'required', 'integer', 'exists:kelas,id'],
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
            
            'kelas_id.required'          => 'Kelas wajib dipilih.',
            'kelas_id.integer'           => 'Kelas harus berupa angka.',
            'kelas_id.exists'            => 'Data kelas tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'      => 'Guru',
            'mata_pelajaran_id' => 'Mata pelajaran',
            'kelas_id'          => 'Kelas',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}