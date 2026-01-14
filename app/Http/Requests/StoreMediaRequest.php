<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('jenis_media')) {
            $this->merge([
                'jenis_media' => strtolower($this->input('jenis_media')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'album_id'    => ['bail','required','string','exists:album,id'],
            'jenis_media' => ['bail','required','in:foto,video'],
            'keterangan'  => ['nullable','string','max:255'],
            'media'       => ['required_if:jenis_media,foto','array'],
            'media.*'     => ['file','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'media_path'  => ['required_if:jenis_media,video','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required'       => 'Album harus dipilih.',
            'album_id.string'         => 'Album harus berupa ID string.',
            'album_id.exists'         => 'Album tidak ditemukan.',
            'media.required_if'       => 'File media wajib diunggah untuk jenis foto.',
            'media.array'             => 'Media harus berupa array file.',
            'media.*.file'            => 'Setiap file harus valid.',
            'media.*.image'           => 'Setiap file harus berupa gambar.',
            'media.*.mimes'           => 'Format yang didukung untuk foto: JPG, JPEG, PNG, WEBP.',
            'media.*.max'             => 'Ukuran maksimal tiap gambar adalah 5MB.',
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
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
