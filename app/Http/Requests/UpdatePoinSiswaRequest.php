<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdatePoinSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siswa_id'     => ['sometimes', 'required', 'string', 'exists:siswa,id'],
            'indikator'    => ['sometimes', 'required', 'string'],
            'poin_positif' => ['nullable', 'integer', 'min:0'],
            'poin_negatif' => ['nullable', 'integer', 'min:0'],
            'tanggal'      => ['sometimes', 'required', 'date'],
            'guru_staf_id' => ['sometimes', 'nullable', 'string', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required' => 'Siswa wajib dipilih.',
            'siswa_id.string'   => 'Siswa harus berupa ID string.',
            'siswa_id.exists'   => 'Data siswa tidak valid.',
            'indikator.required' => 'Indikator wajib diisi.',
            'indikator.string'   => 'Indikator harus berupa teks.',
            'poin_positif.integer' => 'Poin positif harus berupa angka.',
            'poin_positif.min'     => 'Poin positif minimal bernilai 0.',
            'poin_negatif.integer' => 'Poin negatif harus berupa angka.',
            'poin_negatif.min'     => 'Poin negatif minimal bernilai 0.',
            'tanggal.required'   => 'Tanggal wajib diisi.',
            'tanggal.date'       => 'Tanggal harus berupa format tanggal yang valid.',
            'guru_staf_id.string'  => 'ID guru pelapor harus berupa teks.',
            'guru_staf_id.exists'  => 'Data guru pelapor tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'     => 'Siswa',
            'indikator'    => 'Indikator',
            'poin_positif' => 'Poin positif',
            'poin_negatif' => 'Poin negatif',
            'tanggal'      => 'Tanggal',
            'guru_staf_id' => 'Guru Pelapor',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal.',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}