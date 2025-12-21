<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user yang lewat middleware controller boleh akses
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['required', 'exists:users,id', 'unique:siswa,user_id'],
            'kelas_id'      => ['required', 'exists:kelas,id'],
            'jurusan_id'    => ['required', 'exists:jurusan,id'],
            'nis'           => ['required', 'string', 'max:20', 'unique:siswa,nis'],
            'nama_lengkap'  => ['required', 'string', 'max:150'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'alamat'        => ['nullable', 'string'],
            'no_hp'         => ['nullable', 'string', 'max:15'],
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Akun pengguna wajib dihubungkan.',
            'user_id.exists'   => 'Akun pengguna tidak ditemukan.',
            'user_id.unique'   => 'Akun user ini sudah digunakan oleh siswa lain.',

            'kelas_id.required' => 'Kelas harus dipilih.',
            'kelas_id.exists'   => 'Kelas tidak ditemukan.',

            'jurusan_id.required' => 'Jurusan harus dipilih.',
            'jurusan_id.exists'   => 'Jurusan tidak ditemukan.',

            'nis.required' => 'NIS tidak boleh kosong.',
            'nis.string'   => 'NIS harus berupa teks.',
            'nis.max'      => 'NIS tidak boleh lebih dari 20 karakter.',
            'nis.unique'   => 'NIS sudah terdaftar di sistem.',

            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.string'   => 'Nama lengkap harus berupa teks.',
            'nama_lengkap.max'      => 'Nama lengkap tidak boleh lebih dari 150 karakter.',

            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in'       => 'Pilih jenis kelamin yang valid.',

            'tempat_lahir.string' => 'Tempat lahir harus berupa teks.',
            'tempat_lahir.max'    => 'Tempat lahir tidak boleh lebih dari 100 karakter.',

            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',

            'alamat.string' => 'Alamat harus berupa teks.',

            'no_hp.string' => 'Nomor HP harus berupa teks.',
            'no_hp.max'    => 'Nomor HP tidak boleh lebih dari 15 karakter.',

            'foto.image' => 'File yang diunggah harus berupa gambar.',
            'foto.mimes' => 'Format foto hanya boleh JPG, JPEG, atau PNG.',
            'foto.max'   => 'Ukuran foto maksimal 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'       => 'Akun pengguna',
            'kelas_id'      => 'Kelas',
            'jurusan_id'    => 'Jurusan',
            'nis'           => 'NIS',
            'nama_lengkap'  => 'Nama lengkap',
            'jenis_kelamin' => 'Jenis kelamin',
            'tempat_lahir'  => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'alamat'        => 'Alamat',
            'no_hp'         => 'Nomor HP',
            'foto'          => 'Foto siswa',
        ];
    }
}