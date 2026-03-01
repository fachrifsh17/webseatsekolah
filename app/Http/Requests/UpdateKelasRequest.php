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
            'nama_kelas'   => ['sometimes', 'required', 'string', 'max:50'],
            'jurusan_id'   => ['sometimes', 'required', 'string', 'exists:jurusan,id'],
            // --- PENYESUAIAN DI SINI ---
            // 'string' diubah menjadi 'integer'
            'tingkatan_id' => ['sometimes', 'required', 'integer', 'exists:tingkatan,id'], 
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kelas.required'  => 'Nama kelas wajib diisi.',
            'nama_kelas.string'    => 'Nama kelas harus berupa teks.',
            'nama_kelas.max'       => 'Nama kelas tidak boleh lebih dari 50 karakter.',

            'jurusan_id.required'  => 'Jurusan wajib dipilih.',
            'jurusan_id.string'    => 'Jurusan harus berupa ID string.',
            'jurusan_id.exists'    => 'Jurusan yang dipilih tidak valid.',

            // --- PENYESUAIAN DI SINI ---
            'tingkatan_id.required'=> 'Tingkatan wajib dipilih.',
            'tingkatan_id.integer' => 'Tingkatan harus berupa angka (ID integer).',
            'tingkatan_id.exists'  => 'Tingkatan yang dipilih tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_kelas'   => 'Nama kelas',
            'jurusan_id'   => 'Jurusan',
            'tingkatan_id' => 'Tingkatan',
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