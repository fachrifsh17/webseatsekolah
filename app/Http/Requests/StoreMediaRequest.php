<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalisasi jenis_media ke lowercase agar konsisten
        if ($this->has('jenis_media')) {
            $this->merge([
                'jenis_media' => strtolower($this->input('jenis_media')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'album_id'    => ['bail','required','integer','exists:album,id'],
            'jenis_media' => ['bail','required','in:foto,video'],
            'keterangan'  => ['nullable','string','max:255'],
            'media'       => ['bail','required_if:jenis_media,foto','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'media_path'  => ['bail','required_if:jenis_media,video','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required'       => 'Album harus dipilih.',
            'album_id.integer'        => 'Album harus berupa angka.',
            'album_id.exists'         => 'Album tidak ditemukan.',

            'media.required_if'       => 'File media wajib diunggah untuk jenis foto.',
            'media.image'             => 'File harus berupa gambar.',
            'media.mimes'             => 'Format yang didukung untuk foto: JPG, JPEG, PNG, WEBP.',
            'media.max'               => 'Ukuran gambar maksimal adalah 5MB.',

            'media_path.required_if'  => 'Media path wajib diisi untuk jenis video.',
            'media_path.string'       => 'Media path harus berupa teks atau URL embed.',

            'jenis_media.required'    => 'Tentukan jenis media (foto atau video).',
            'jenis_media.in'          => 'Jenis media harus berupa foto atau video.',

            'keterangan.string'       => 'Keterangan harus berupa teks.',
            'keterangan.max'          => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'album_id'    => 'Album',
            'media'       => 'File media',
            'media_path'  => 'Media path',
            'jenis_media' => 'Jenis media',
            'keterangan'  => 'Keterangan',
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
