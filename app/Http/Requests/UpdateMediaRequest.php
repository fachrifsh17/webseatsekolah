<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateMediaRequest extends FormRequest
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
            'album_id'    => ['bail','required','integer','exists:album,id'],
            'jenis_media' => ['bail','required','in:foto,video'],
            'media'       => ['bail','sometimes','nullable','mimes:jpg,jpeg,png,webp,mp4','max:10240'],
            'media_path'  => ['bail','required_if:jenis_media,video','string','url'],
            'keterangan'  => ['nullable','string','max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required'       => 'Album wajib dipilih.',
            'album_id.integer'        => 'Album ID harus berupa angka.',
            'album_id.exists'         => 'Album tidak ditemukan.',

            'jenis_media.required'    => 'Jenis media wajib dipilih.',
            'jenis_media.in'          => 'Jenis media hanya boleh foto atau video.',

            'media.mimes'             => 'Format media yang didukung: JPG, JPEG, PNG, WEBP, atau MP4.',
            'media.max'               => 'Ukuran media maksimal 10MB.',

            'media_path.required_if'  => 'Media path wajib diisi untuk jenis video.',
            'media_path.string'       => 'Media path harus berupa teks atau URL.',
            'media_path.url'          => 'Media path harus berupa URL yang valid.',

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
