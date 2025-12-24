<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Izin akses ditangani oleh Policy di Controller
        return true;
    }

    public function rules(): array
    {
        // Mengambil ID siswa dari route agar validasi unique mengabaikan ID diri sendiri
        $siswaId = $this->route('siswa'); 

        return [
            'user_id'       => 'sometimes|required|exists:users,id|unique:siswa,user_id,' . $siswaId,
            'nis'           => 'sometimes|required|string|max:20|unique:siswa,nis,' . $siswaId,
            'nama_lengkap'  => 'sometimes|required|string|max:100', // Sesuai varchar(100) di database
            'tempat_lahir'  => 'nullable|string|max:100', // Kolom baru di image_096627.png
            'tanggal_lahir' => 'nullable|date',           // Kolom baru di image_096627.png
            'jenis_kelamin' => 'sometimes|required|in:Laki-laki,Perempuan', 
            'kelas_id'      => 'sometimes|required|exists:kelas,id',
            'jurusan_id'    => 'sometimes|required|exists:jurusan,id',
            'orangtua_id'   => 'nullable|exists:orangtua,id',
            'foto'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'no_telp_siswa' => 'nullable|string|max:15', 
            'alamat'        => 'nullable|string',        
            'status_aktif'  => 'sometimes|required|in:Aktif,Lulus,Pindah,Keluar', 
        ];
    }

    public function messages(): array
    {
        return [
            'nis.unique' => 'NIS sudah digunakan oleh siswa lain.',
            'user_id.unique' => 'User ID ini sudah terhubung dengan siswa lain.',
            'nama_lengkap.max' => 'Nama lengkap tidak boleh lebih dari 100 karakter.',
            'jenis_kelamin.in' => 'Pilih jenis kelamin Laki-laki atau Perempuan.',
            'status_aktif.in' => 'Status harus salah satu dari: Aktif, Lulus, Pindah, atau Keluar.',
            'foto.image' => 'File harus berupa gambar (JPG, JPEG, PNG).',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',
        ];
    }
}