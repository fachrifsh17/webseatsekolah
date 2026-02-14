<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateJadwalProduktifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        //
    }

    public function rules(): array
    {
        return [
            'jurusan_id'        => ['bail', 'sometimes', 'required', 'string', 'exists:jurusan,id'],
            'tahun_ajaran_id'   => ['bail', 'sometimes', 'nullable', 'string', 'exists:tahun_ajaran,id'],
            'judul'             => ['bail', 'sometimes', 'required', 'string', 'max:255'],
            'penjelasan_jadwal' => ['nullable', 'string'],
            'file_jadwal_path'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'jurusan_id.required'       => 'Jurusan wajib dipilih.',
            'jurusan_id.string'         => 'Jurusan harus berupa ID string.',
            'jurusan_id.exists'         => 'Jurusan tidak valid.',

            'tahun_ajaran_id.string'    => 'Tahun ajaran harus berupa ID string.',
            'tahun_ajaran_id.exists'    => 'Tahun ajaran tidak ditemukan.',

            'judul.required'            => 'Judul jadwal wajib diisi.',
            'judul.string'              => 'Judul jadwal harus berupa teks.',
            'judul.max'                 => 'Judul jadwal tidak boleh lebih dari 255 karakter.',

            'penjelasan_jadwal.string'  => 'Penjelasan jadwal harus berupa teks.',

            'file_jadwal_path.file'     => 'File jadwal harus berupa file.',
            'file_jadwal_path.mimes'    => 'Format file harus PDF, JPG, JPEG, PNG, atau WEBP.',
            'file_jadwal_path.max'      => 'Ukuran file maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'jurusan_id'        => 'Jurusan',
            'tahun_ajaran_id'   => 'Tahun ajaran',
            'judul'             => 'Judul jadwal',
            'penjelasan_jadwal' => 'Penjelasan jadwal',
            'file_jadwal_path'  => 'File jadwal',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}