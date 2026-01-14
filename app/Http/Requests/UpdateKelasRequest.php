<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_kelas'      => ['sometimes', 'required', 'string', 'max:50'],
            'jurusan_id'      => ['sometimes', 'required', 'string', 'exists:jurusan,id'],
            'wali_kelas_id'   => ['sometimes', 'nullable', 'string', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kelas.required' => 'Nama kelas wajib diisi.',
            'nama_kelas.string'   => 'Nama kelas harus berupa teks.',
            'nama_kelas.max'      => 'Nama kelas tidak boleh lebih dari 50 karakter.',

            'jurusan_id.required' => 'Jurusan wajib dipilih.',
            'jurusan_id.string'   => 'Jurusan harus berupa ID string.',
            'jurusan_id.exists'   => 'Jurusan yang dipilih tidak valid.',
           
            'wali_kelas_id.string' => 'Wali kelas harus berupa ID string.',
            'wali_kelas_id.exists' => 'Wali kelas yang dipilih tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_kelas'      => 'Nama kelas',
            'jurusan_id'      => 'Jurusan',
            'wali_kelas_id'   => 'Wali kelas',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
