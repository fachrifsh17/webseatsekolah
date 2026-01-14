<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_staf_id'      => ['bail', 'required', 'string', 'exists:guru_staf,id'],
            'mata_pelajaran_id' => ['bail', 'required', 'string', 'exists:mata_pelajaran,id'],
            'kelas_id'          => ['bail', 'required', 'string', 'exists:kelas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_staf_id.required'      => 'Guru/Staf wajib dipilih.',
            'guru_staf_id.string'        => 'Guru/Staf harus berupa ID string.',
            'guru_staf_id.exists'        => 'Guru/Staf tidak ditemukan dalam sistem.',
            
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.string'   => 'Mata pelajaran harus berupa ID string.',
            'mata_pelajaran_id.exists'   => 'Mata pelajaran tidak ditemukan dalam sistem.',
            
            'kelas_id.required'          => 'Kelas wajib dipilih.',
            'kelas_id.string'            => 'Kelas harus berupa ID string.',
            'kelas_id.exists'            => 'Kelas tidak ditemukan dalam sistem.',
        ];
    }

    public function attributes(): array
    {
        return [
            'guru_staf_id'      => 'Guru/Staf',
            'mata_pelajaran_id' => 'Mata pelajaran',
            'kelas_id'          => 'Kelas',
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
