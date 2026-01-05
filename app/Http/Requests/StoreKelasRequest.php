<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_kelas'      => ['required', 'string', 'max:50'],
            'jurusan_id'      => ['required', 'integer', 'exists:jurusan,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'wali_kelas_id'   => ['nullable', 'integer', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kelas.required'      => 'Nama kelas tidak boleh kosong.',
            'nama_kelas.string'        => 'Nama kelas harus berupa teks.',
            'nama_kelas.max'           => 'Nama kelas maksimal 50 karakter.',

            'jurusan_id.required'      => 'Silakan pilih jurusan yang tersedia.',
            'jurusan_id.integer'       => 'Jurusan harus berupa angka.',
            'jurusan_id.exists'        => 'Jurusan yang dipilih tidak valid.',

            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.integer'  => 'Tahun ajaran harus berupa angka.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',

            'wali_kelas_id.integer'    => 'Wali kelas harus berupa angka.',
            'wali_kelas_id.exists'     => 'Wali kelas yang dipilih tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_kelas'      => 'Nama kelas',
            'jurusan_id'      => 'Jurusan',
            'tahun_ajaran_id' => 'Tahun ajaran',
            'wali_kelas_id'   => 'Wali kelas',
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
