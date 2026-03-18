<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateEkstrakurikulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_ekskul'  => ['sometimes', 'required', 'string', 'max:100'],
            'deskripsi'    => ['sometimes', 'nullable', 'string'],
            // Diperpanjang ke 100 menyesuaikan migration
            'hari'         => ['sometimes', 'nullable', 'string', 'max:100'], 
            'pembina_id'   => ['sometimes', 'nullable', 'string', 'exists:guru_staf,id'],
            'foto'         => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'keterangan'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_ekskul.required' => 'Nama ekstrakurikuler wajib diisi.',
            'nama_ekskul.string'   => 'Nama ekstrakurikuler harus berupa teks.',
            'nama_ekskul.max'      => 'Nama ekstrakurikuler tidak boleh lebih dari 100 karakter.',

            'deskripsi.string' => 'Deskripsi harus berupa teks.',

            'hari.string' => 'Hari harus berupa teks.',
            'hari.max'    => 'Hari tidak boleh lebih dari 100 karakter.',

            'pembina_id.string' => 'ID pembina harus berupa ID string.',
            'pembina_id.exists' => 'Pembina tidak ditemukan dalam sistem.',

            'foto.image' => 'File foto harus berupa gambar.',
            'foto.mimes' => 'Format foto hanya boleh JPG, JPEG, PNG, atau WEBP.',
            'foto.max'   => 'Ukuran foto maksimal 2MB.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_ekskul' => 'Nama ekstrakurikuler',
            'deskripsi'   => 'Deskripsi',
            'hari'        => 'Hari',
            'pembina_id'  => 'Pembina',
            'foto'        => 'Foto ekstrakurikuler',
            'keterangan'  => 'Keterangan',
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