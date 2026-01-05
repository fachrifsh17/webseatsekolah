<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_album'       => ['bail','required','string','max:255'],
            'tanggal_kegiatan' => ['nullable','date'],
            'cover'            => ['bail','sometimes','nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_album.required'       => 'Nama album harus diisi.',
            'nama_album.string'         => 'Nama album harus berupa teks.',
            'nama_album.max'            => 'Nama album tidak boleh lebih dari 255 karakter.',
            'tanggal_kegiatan.date'     => 'Tanggal kegiatan harus berupa format tanggal yang valid.',
            'cover.image'               => 'File cover harus berupa gambar.',
            'cover.mimes'               => 'Format gambar yang didukung: JPG, JPEG, PNG, dan WEBP.',
            'cover.max'                 => 'Ukuran gambar maksimal adalah 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_album'       => 'Nama album',
            'tanggal_kegiatan' => 'Tanggal kegiatan',
            'cover'            => 'Cover album',
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
