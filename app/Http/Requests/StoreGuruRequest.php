<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class StoreGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // bail: berhenti mengecek jika satu aturan sudah gagal
            'user_id' => [
                'bail', 
                'required', 
                'string', 
                'exists:users,id', 
                'unique:guru_staf,user_id' // Satu user hanya boleh punya satu profil guru
            ],
            'nip' => [
                'nullable', 
                'string', 
                'size:18', // Harus tepat 18 karakter
                'unique:guru_staf,nip' // Tidak boleh sama dengan guru lain
            ],
            'nuptk' => [
                'nullable', 
                'string', 
                'size:16', // Harus tepat 16 karakter
                'unique:guru_staf,nuptk'
            ],
            // Sesuaikan dengan nama field di database/controller (nama_lengkap)
            'nama' => ['required', 'string', 'max:100'], 
            'jabatan_fungsional' => ['nullable', 'string', 'max:100'],
            'status_kepegawaian' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'jurusan_id' => ['nullable', 'string', 'exists:jurusan,id'],
            'is_active' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nip'     => $this->filled('nip') ? trim($this->nip) : null,
            'nuptk'   => $this->filled('nuptk') ? trim($this->nuptk) : null,
            'nama'    => $this->filled('nama') ? trim($this->nama) : null,
            'user_id' => $this->filled('user_id') ? trim($this->user_id) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User wajib dipilih.',
            'user_id.unique'   => 'User ini sudah terdaftar sebagai guru.',
            
            'nip.size'         => 'NIP harus berjumlah tepat 18 karakter.',
            'nip.unique'       => 'NIP sudah digunakan oleh orang lain.',
            
            'nuptk.size'       => 'NUPTK harus berjumlah tepat 16 karakter.',
            'nuptk.unique'     => 'NUPTK sudah digunakan oleh orang lain.',

            'foto.max'         => 'Ukuran foto maksimal adalah 5MB.',
            'foto.mimes'       => 'Format foto harus jpg, jpeg, atau png.',
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