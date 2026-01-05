<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'            => ['bail','required','integer','exists:users,id'],
            'nip'                => ['nullable','string','max:18'],
            'nuptk'              => ['nullable','string','max:16'],
            'nama'               => ['required','string','max:100'],
            'jabatan_fungsional' => ['nullable','string','max:100'],
            'status_kepegawaian' => ['nullable','string','max:50'],
            'foto'               => ['nullable','file','image','mimes:jpg,jpeg,png','max:5120'],
            'jurusan_id'         => ['nullable','integer','exists:jurusan,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nip'   => $this->filled('nip') ? trim($this->nip) : null,
            'nuptk' => $this->filled('nuptk') ? trim($this->nuptk) : null,
            'nama'  => $this->filled('nama') ? trim($this->nama) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User wajib dipilih.',
            'user_id.integer'  => 'User harus berupa angka.',
            'user_id.exists'   => 'User tidak ditemukan.',

            'nip.string' => 'NIP harus berupa teks.',
            'nip.max'    => 'NIP tidak boleh lebih dari 18 karakter.',

            'nuptk.string' => 'NUPTK harus berupa teks.',
            'nuptk.max'    => 'NUPTK tidak boleh lebih dari 16 karakter.',

            'nama.required' => 'Nama guru wajib diisi.',
            'nama.string'   => 'Nama guru harus berupa teks.',
            'nama.max'      => 'Nama guru tidak boleh lebih dari 100 karakter.',

            'jabatan_fungsional.string' => 'Jabatan fungsional harus berupa teks.',
            'jabatan_fungsional.max'    => 'Jabatan fungsional tidak boleh lebih dari 100 karakter.',

            'status_kepegawaian.string' => 'Status kepegawaian harus berupa teks.',
            'status_kepegawaian.max'    => 'Status kepegawaian tidak boleh lebih dari 50 karakter.',

            'foto.file'  => 'File harus berupa berkas.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'   => 'Ukuran foto maksimal adalah 5MB.',

            'jurusan_id.integer' => 'Jurusan harus berupa angka.',
            'jurusan_id.exists'  => 'Jurusan yang dipilih tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'            => 'User',
            'nip'                => 'NIP',
            'nuptk'              => 'NUPTK',
            'nama'               => 'Nama guru',
            'jabatan_fungsional' => 'Jabatan fungsional',
            'status_kepegawaian' => 'Status kepegawaian',
            'foto'               => 'Foto',
            'jurusan_id'         => 'Jurusan',
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
