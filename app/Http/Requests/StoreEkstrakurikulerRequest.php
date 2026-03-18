<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreEkstrakurikulerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_ekskul' => ['required', 'string', 'max:100', 'unique:ekstrakurikuler,nama_ekskul'],
            'deskripsi'   => ['nullable', 'string'],
            // Diperpanjang ke 100 agar bisa menampung teks seperti "Senin - Kamis"
            'hari'        => ['required', 'string', 'max:100'], 
            'pembina_id'  => ['required', 'string', 'exists:guru_staf,id'],
            'foto'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_ekskul.required' => 'Nama ekstrakurikuler wajib diisi.',
            'nama_ekskul.unique'   => 'Ekstrakurikuler dengan nama ini sudah terdaftar.',
            'nama_ekskul.string'   => 'Nama ekstrakurikuler harus berupa teks.',
            'nama_ekskul.max'      => 'Nama ekstrakurikuler tidak boleh lebih dari 100 karakter.',
            
            'hari.required'        => 'Hari pelaksanaan wajib diisi.',
            'hari.string'          => 'Hari harus berupa teks.',
            'hari.max'             => 'Hari tidak boleh lebih dari 100 karakter.',

            'pembina_id.required'  => 'Pembina wajib dipilih.',
            'pembina_id.string'    => 'ID Pembina tidak valid.',
            'pembina_id.exists'    => 'Pembina tidak terdaftar di sistem.',

            'foto.image'           => 'File harus berupa gambar.',
            'foto.mimes'           => 'Format didukung: JPG, JPEG, PNG, WEBP.',
            'foto.max'             => 'Ukuran foto maksimal 2MB.',

            'keterangan.string'    => 'Keterangan harus berupa teks.',
            'keterangan.max'       => 'Keterangan maksimal 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_ekskul' => 'Nama ekstrakurikuler',
            'deskripsi'   => 'Deskripsi',
            'hari'        => 'Hari',
            'pembina_id'  => 'Pembina',
            'foto'        => 'Foto',
            'keterangan'  => 'Keterangan',
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