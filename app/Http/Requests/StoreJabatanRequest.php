<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class StoreJabatanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation()
    {
        $this->merge([
            'slug' => Str::slug($this->nama_jabatan),
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_jabatan' => ['required', 'string', 'max:100', 'unique:jabatans,nama_jabatan'],
            'keterangan'   => ['nullable', 'string'],
            'slug'         => ['required', 'string', 'unique:jabatans,slug'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_jabatan.required' => 'Nama jabatan harus diisi.',
            'nama_jabatan.unique'   => 'Nama jabatan sudah terdaftar.',
            'slug.unique'           => 'Slug otomatis dari nama ini sudah ada, gunakan nama lain.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors'  => $validator->errors()
        ], 422));
    }
}