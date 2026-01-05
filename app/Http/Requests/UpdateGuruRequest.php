<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'            => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'nip'                => ['nullable', 'string', 'max:18'],
            'nuptk'              => ['nullable', 'string', 'max:16'],
            'nama'               => ['sometimes', 'required', 'string', 'max:100'],
            'jabatan_fungsional' => ['nullable', 'string', 'max:100'],
            'status_kepegawaian' => ['nullable', 'string', 'max:50'],
            'foto'               => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'jurusan_id'         => ['nullable', 'integer', 'exists:jurusan,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Akun pengguna wajib dihubungkan.',
            'user_id.integer'  => 'User ID harus berupa angka.',
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

            'foto.file'   => 'File harus berupa berkas.',
            'foto.image'  => 'File harus berupa gambar.',
            'foto.mimes'  => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'    => 'Ukuran foto maksimal adalah 5MB.',

            'jurusan_id.integer' => 'Jurusan ID harus berupa angka.',
            'jurusan_id.exists'  => 'Jurusan tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'            => 'Akun pengguna',
            'nip'                => 'NIP',
            'nuptk'              => 'NUPTK',
            'nama'               => 'Nama guru',
            'jabatan_fungsional' => 'Jabatan fungsional',
            'status_kepegawaian' => 'Status kepegawaian',
            'foto'               => 'Foto guru',
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
