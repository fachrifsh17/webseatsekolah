<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

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
            'album_id'    => ['bail', 'sometimes', 'required', 'string', 'exists:album,id'],
            'jenis_media' => ['bail', 'sometimes', 'required', 'in:foto,video'],
            'media'       => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4', 'max:10240'],
            'media_path'  => ['sometimes', 'nullable', 'string'],
            'keterangan'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'album_id.required'    => 'Album wajib dipilih.',
            'album_id.exists'      => 'Album tidak ditemukan.',
            'jenis_media.required' => 'Jenis media wajib dipilih.',
            'jenis_media.in'       => 'Jenis media hanya boleh foto atau video.',
            'media.file'           => 'File media harus valid.',
            'media.mimes'          => 'Format didukung: JPG, JPEG, PNG, WEBP, atau MP4.',
            'media.max'            => 'Ukuran media maksimal 10MB.',
            'keterangan.max'       => 'Keterangan tidak boleh lebih dari 255 karakter.',
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