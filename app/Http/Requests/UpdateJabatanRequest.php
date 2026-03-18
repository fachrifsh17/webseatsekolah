<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if ($this->has('nama_jabatan')) {
            $this->merge([
                'slug' => Str::slug($this->nama_jabatan),
            ]);
        }
    }

    public function rules(): array
    {
        $jabatanId = $this->route('jabatan');

        return [
            'nama_jabatan' => [
                'sometimes', 
                'required', 
                'string', 
                'max:100', 
                Rule::unique('jabatans', 'nama_jabatan')->ignore($jabatanId)
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('jabatans', 'slug')->ignore($jabatanId)
            ],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_jabatan.required' => 'Nama jabatan wajib diisi.',
            'nama_jabatan.unique'   => 'Nama jabatan sudah digunakan.',
            'nama_jabatan.max'      => 'Nama jabatan maksimal 100 karakter.',
            'slug.unique'           => 'Slug sudah digunakan, silakan gunakan nama lain.',
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