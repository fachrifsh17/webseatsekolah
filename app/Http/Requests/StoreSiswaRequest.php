<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['required', 'exists:users,id', 'unique:siswa,user_id'],
            'kelas_id'      => ['required', 'exists:kelas,id'],
            'jurusan_id'    => ['required', 'exists:jurusan,id'],
            'orangtua_id'   => ['nullable', 'exists:orangtua,id'], // Sesuai kolom di image_096627.png
            'nis'           => ['required', 'string', 'max:20', 'unique:siswa,nis'],
            'nama_lengkap'  => ['required', 'string', 'max:100'], // Disesuaikan menjadi 100 sesuai varchar(100)
            'tempat_lahir'  => ['nullable', 'string', 'max:100'], // Sesuai kolom di image_096627.png
            'tanggal_lahir' => ['nullable', 'date'],           // Sesuai kolom di image_096627.png
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'alamat'        => ['nullable', 'string'],
            'no_telp_siswa' => ['nullable', 'string', 'max:15'], 
            'foto'          => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'status_aktif'  => ['required', 'in:Aktif,Lulus,Pindah,Keluar'], 
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'      => 'Akun pengguna wajib dihubungkan.',
            'user_id.unique'        => 'Akun user ini sudah digunakan oleh siswa lain.',
            'nis.required'          => 'NIS tidak boleh kosong.',
            'nis.unique'            => 'NIS sudah terdaftar di sistem.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'      => 'Nama lengkap tidak boleh lebih dari 100 karakter.',
            'jenis_kelamin.required'=> 'Jenis kelamin wajib dipilih.',
            'status_aktif.required' => 'Status aktif siswa wajib diisi.',
            'foto.image'            => 'File yang diunggah harus berupa gambar.',
            'foto.max'              => 'Ukuran foto maksimal 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'       => 'Akun pengguna',
            'kelas_id'      => 'Kelas',
            'jurusan_id'    => 'Jurusan',
            'orangtua_id'   => 'Orang Tua',
            'nis'           => 'NIS',
            'nama_lengkap'  => 'Nama lengkap',
            'tempat_lahir'  => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'jenis_kelamin' => 'Jenis kelamin',
            'no_telp_siswa' => 'Nomor Telepon Siswa',
            'foto'          => 'Foto siswa',
            'status_aktif'  => 'Status Aktif',
        ];
    }
}