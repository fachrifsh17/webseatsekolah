<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class KenaikanKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'mapping' => ['required','array','min:1'],
            'mapping.*.kelas_lama_id' => ['required','string','exists:kelas,id'],
            'mapping.*.kelas_baru_id' => ['required','string','exists:kelas,id','different:mapping.*.kelas_lama_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mapping.*.kelas_lama_id.required'  => 'Kelas asal wajib diisi.',
            'mapping.*.kelas_lama_id.string'    => 'Kelas asal harus berupa ID string.',
            'mapping.*.kelas_lama_id.exists'    => 'Kelas asal dengan ID ini tidak ditemukan.',

            'mapping.*.kelas_baru_id.required'  => 'Kelas tujuan wajib diisi.',
            'mapping.*.kelas_baru_id.string'    => 'Kelas tujuan harus berupa ID string.',
            'mapping.*.kelas_baru_id.exists'    => 'Kelas tujuan tidak ditemukan di database.',
            'mapping.*.kelas_baru_id.different' => 'Kelas tujuan tidak boleh sama dengan kelas asal.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal, silakan cek kembali data ID kelas Anda.',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
