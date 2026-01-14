<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StorePoinSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'siswa_id'     => ['required', 'string', 'exists:siswa,id'],
            'indikator'    => ['required', 'string'],
            'poin_positif' => ['nullable', 'integer', 'min:0'],
            'poin_negatif' => ['nullable', 'integer', 'min:0'],
            'tanggal'      => ['required', 'date'],
            'guru_staf_id' => ['nullable', 'string', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required'  => 'Siswa harus dipilih.',
            'siswa_id.string'    => 'ID siswa harus berupa ID string.',
            'siswa_id.exists'    => 'Data siswa tidak ditemukan.',
            'indikator.required' => 'Keterangan indikator atau pelanggaran tidak boleh kosong.',
            'indikator.string'   => 'Indikator harus berupa teks.',
            'poin_positif.integer' => 'Poin positif harus berupa angka.',
            'poin_positif.min'     => 'Poin positif tidak boleh bernilai negatif.',
            'poin_negatif.integer' => 'Poin negatif harus berupa angka.',
            'poin_negatif.min'     => 'Poin negatif tidak boleh bernilai negatif.',
            'tanggal.required'   => 'Tanggal kejadian harus diisi.',
            'tanggal.date'       => 'Format tanggal kejadian tidak valid.',
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
            'tanggal'      => 'Tanggal kejadian',
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