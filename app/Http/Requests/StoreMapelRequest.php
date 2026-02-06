<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_mapel'     => ['bail', 'required', 'string', 'max:100'],
            'jurusan_id'     => ['nullable', 'string', 'exists:jurusan,id'],
            'tipe_mapel'     => ['nullable', 'in:umum,khusus'],
            'kategori_mapel' => ['nullable', 'in:normatif,adaptif,produktif'],
            // Tambahkan is_active di sini
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_mapel.required' => 'Nama mata pelajaran wajib diisi.',
            'nama_mapel.string'   => 'Nama mata pelajaran harus berupa teks.',
            'nama_mapel.max'      => 'Nama mata pelajaran tidak boleh lebih dari 100 karakter.',

            'jurusan_id.string'   => 'Jurusan harus berupa ID string.',
            'jurusan_id.exists'   => 'Jurusan yang dipilih tidak valid.',

            'tipe_mapel.in'       => 'Tipe mata pelajaran harus berupa: umum atau khusus.',
            'kategori_mapel.in'   => 'Kategori mata pelajaran harus berupa: normatif, adaptif, atau produktif.',
            
            // Pesan validasi untuk status aktif
            'is_active.boolean'   => 'Status aktif harus berupa nilai boolean (1 atau 0).',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_mapel'     => 'Nama mata pelajaran',
            'jurusan_id'     => 'Jurusan',
            'tipe_mapel'     => 'Tipe mata pelajaran',
            'kategori_mapel' => 'Kategori mata pelajaran',
            'is_active'      => 'Status Aktif',
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