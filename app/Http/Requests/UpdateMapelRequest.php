<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_mapel'     => ['bail','sometimes','required','string','max:100'],
            'jurusan_id'     => ['nullable','string','exists:jurusan,id'],
            'tipe_mapel'     => ['nullable','in:umum,khusus'],
            'kategori_mapel' => ['nullable','in:normatif,adaptif,produktif'],
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

            'tipe_mapel.in'       => 'Tipe mata pelajaran hanya boleh bernilai umum atau khusus.',
            'kategori_mapel.in'   => 'Kategori mata pelajaran hanya boleh normatif, adaptif, atau produktif.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_mapel'     => 'Nama mata pelajaran',
            'jurusan_id'     => 'Jurusan',
            'tipe_mapel'     => 'Tipe mata pelajaran',
            'kategori_mapel' => 'Kategori mata pelajaran',
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
