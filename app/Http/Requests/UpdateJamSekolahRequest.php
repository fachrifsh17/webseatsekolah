<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateJamSekolahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'     => ['sometimes', 'required', 'string', 'max:150'],
            'file_path' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul.required'  => 'Judul wajib diisi.',
            'judul.string'    => 'Judul harus berupa teks.',
            'judul.max'       => 'Judul maksimal 150 karakter.',
            'file_path.file'  => 'File jadwal harus berupa file.',
            'file_path.mimes' => 'Format file harus PDF, JPG, atau PNG.',
            'file_path.max'   => 'Ukuran file maksimal 5MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'judul'     => 'Judul',
            'file_path' => 'File jadwal',
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