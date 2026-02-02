<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreJurusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_jurusan' => ['required', 'string', 'max:100'],
            'deskripsi'    => ['nullable', 'string'],
            'foto'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_jurusan.required' => 'Nama jurusan wajib diisi.',
            'nama_jurusan.string'   => 'Nama jurusan harus berupa teks.',
            'nama_jurusan.max'      => 'Nama jurusan maksimal 100 karakter.',
            'deskripsi.string'      => 'Deskripsi harus berupa teks.',
            'foto.image'            => 'File harus berupa gambar.',
            'foto.mimes'            => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'foto.max'              => 'Ukuran gambar maksimal adalah 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_jurusan' => 'Nama jurusan',
            'deskripsi'    => 'Deskripsi',
            'foto'         => 'Foto jurusan',
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
