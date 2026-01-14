<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdateJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jabatanId = $this->route('jabatan')?->id;

        return [
            'nama_jabatan' => [
                'sometimes', 
                'required', 
                'string', 
                'max:100', 
                'unique:jabatans,nama_jabatan,' . $jabatanId
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
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_jabatan' => 'Nama Jabatan',
            'keterangan'   => 'Keterangan',
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