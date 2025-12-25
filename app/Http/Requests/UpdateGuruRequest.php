<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guruId = $this->route('guru');

        return [
            'user_id'            => ['sometimes', 'required', 'integer', 'exists:users,id', 'unique:guru_staf,user_id,' . $guruId],
            'nip'                => ['nullable', 'string', 'max:18', 'unique:guru_staf,nip,' . $guruId],
            'nuptk'              => ['nullable', 'string', 'max:16', 'unique:guru_staf,nuptk,' . $guruId],
            'nama'               => ['sometimes', 'required', 'string', 'max:100'],
            'jabatan_fungsional' => ['nullable', 'string', 'max:100'],
            'status_kepegawaian' => ['nullable', 'string', 'max:50'],
            'foto'               => ['nullable', 'file', 'image', 'max:5120'],
            'jurusan_id'         => ['nullable', 'integer', 'exists:jurusan,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Akun pengguna wajib dihubungkan.',
            'user_id.integer'  => 'User ID harus berupa angka.',
            'user_id.exists'   => 'User tidak ditemukan.',
            'user_id.unique'   => 'User ID sudah digunakan oleh guru lain.',

            'nip.string' => 'NIP harus berupa teks.',
            'nip.max'    => 'NIP tidak boleh lebih dari 18 karakter.',
            'nip.unique' => 'NIP sudah terdaftar.',

            'nuptk.string' => 'NUPTK harus berupa teks.',
            'nuptk.max'    => 'NUPTK tidak boleh lebih dari 16 karakter.',
            'nuptk.unique' => 'NUPTK sudah terdaftar.',

            'nama.required' => 'Nama guru wajib diisi.',
            'nama.string'   => 'Nama guru harus berupa teks.',
            'nama.max'      => 'Nama guru tidak boleh lebih dari 100 karakter.',

            'jabatan_fungsional.string' => 'Jabatan fungsional harus berupa teks.',
            'jabatan_fungsional.max'    => 'Jabatan fungsional tidak boleh lebih dari 100 karakter.',

            'status_kepegawaian.string' => 'Status kepegawaian harus berupa teks.',
            'status_kepegawaian.max'    => 'Status kepegawaian tidak boleh lebih dari 50 karakter.',

            'foto.file'  => 'Foto harus berupa file.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max'   => 'Ukuran foto maksimal 5MB.',

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
}