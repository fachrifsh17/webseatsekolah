<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['sometimes','required','integer','exists:users,id'],
            'nis'           => ['sometimes','required','string','max:20'],
            'nama_lengkap'  => ['sometimes','required','string','max:100'],
            'tempat_lahir'  => ['nullable','string','max:100'],
            'tanggal_lahir' => ['nullable','date'],
            'jenis_kelamin' => ['sometimes','required','in:Laki-laki,Perempuan'],
            'kelas_id'      => ['sometimes','required','integer','exists:kelas,id'],
            'jurusan_id'    => ['sometimes','required','integer','exists:jurusan,id'],
            'orangtua_id'   => ['nullable','integer','exists:orangtua,id'],
            'foto'          => ['nullable','file','image','mimes:jpg,jpeg,png','max:2048'],
            'no_telp_siswa' => ['nullable','string','max:15'],
            'alamat'        => ['nullable','string'],
            'status_aktif'  => ['sometimes','required','in:Aktif,Lulus,Pindah,Keluar'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'       => 'Akun pengguna wajib dihubungkan.',
            'user_id.integer'        => 'User ID harus berupa angka.',
            'user_id.exists'         => 'User tidak ditemukan.',
            'nis.required'           => 'NIS wajib diisi.',
            'nis.max'                => 'NIS tidak boleh lebih dari 20 karakter.',
            'nama_lengkap.required'  => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'       => 'Nama lengkap tidak boleh lebih dari 100 karakter.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in'       => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'kelas_id.required'      => 'Kelas wajib dipilih.',
            'kelas_id.exists'        => 'Kelas tidak ditemukan.',
            'jurusan_id.required'    => 'Jurusan wajib dipilih.',
            'jurusan_id.exists'      => 'Jurusan tidak ditemukan.',
            'orangtua_id.exists'     => 'Orang Tua tidak ditemukan.',
            'status_aktif.required'  => 'Status aktif siswa wajib diisi.',
            'status_aktif.in'        => 'Status aktif harus salah satu dari Aktif, Lulus, Pindah, atau Keluar.',
            'foto.file'              => 'File harus berupa berkas.',
            'foto.image'             => 'File harus berupa gambar.',
            'foto.mimes'             => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'               => 'Ukuran foto maksimal adalah 2MB.',
            'tanggal_lahir.date'     => 'Format tanggal lahir tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'       => 'Akun pengguna',
            'nis'           => 'NIS',
            'nama_lengkap'  => 'Nama lengkap',
            'tempat_lahir'  => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'jenis_kelamin' => 'Jenis kelamin',
            'kelas_id'      => 'Kelas',
            'jurusan_id'    => 'Jurusan',
            'orangtua_id'   => 'Orang Tua',
            'foto'          => 'Foto siswa',
            'no_telp_siswa' => 'Nomor Telepon Siswa',
            'alamat'        => 'Alamat',
            'status_aktif'  => 'Status Aktif',
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
