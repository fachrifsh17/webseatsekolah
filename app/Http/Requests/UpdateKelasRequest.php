<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'jurusan_id'      => ['sometimes', 'required', 'integer', 'exists:jurusan,id'],
            'tahun_ajaran_id' => ['sometimes', 'required', 'integer', 'exists:tahun_ajaran,id'],
            'wali_kelas_id'   => ['sometimes', 'nullable', 'integer', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kelas.required' => 'Nama kelas wajib diisi.',
            'nama_kelas.string'   => 'Nama kelas harus berupa teks.',
            'nama_kelas.max'      => 'Nama kelas tidak boleh lebih dari 50 karakter.',

            'jurusan_id.required' => 'Jurusan wajib dipilih.',
            'jurusan_id.integer'  => 'Jurusan harus berupa angka.',
            'jurusan_id.exists'   => 'Jurusan yang dipilih tidak valid.',

            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.integer'  => 'Tahun ajaran harus berupa angka.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',

            'wali_kelas_id.integer' => 'Wali kelas harus berupa angka.',
            'wali_kelas_id.exists'  => 'Wali kelas yang dipilih tidak ditemukan.',
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
