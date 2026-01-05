<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['bail','required','integer','exists:users,id'],
            'kelas_id'      => ['required','integer','exists:kelas,id'],
            'jurusan_id'    => ['required','integer','exists:jurusan,id'],
            'orangtua_id'   => ['nullable','integer','exists:orangtua,id'],
            'nis'           => ['required','string','max:20'],
            'nama_lengkap'  => ['required','string','max:100'],
            'tempat_lahir'  => ['nullable','string','max:100'],
            'tanggal_lahir' => ['nullable','date'],
            'jenis_kelamin' => ['required','in:Laki-laki,Perempuan'],
            'alamat'        => ['nullable','string'],
            'no_telp_siswa' => ['nullable','string','max:15'],
            'foto'          => ['nullable','file','image','mimes:jpg,jpeg,png','max:2048'],
            'status_aktif'  => ['required','in:Aktif,Lulus,Pindah,Keluar'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nis'          => $this->filled('nis') ? trim($this->nis) : null,
            'nama_lengkap' => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'tempat_lahir' => $this->filled('tempat_lahir') ? trim($this->tempat_lahir) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'user_id.required'       => 'Akun pengguna wajib dihubungkan.',
            'user_id.integer'        => 'User ID harus berupa angka.',
            'user_id.exists'         => 'User tidak ditemukan.',
            'nis.required'           => 'NIS tidak boleh kosong.',
            'nis.max'                => 'NIS tidak boleh lebih dari 20 karakter.',
            'nama_lengkap.required'  => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'       => 'Nama lengkap tidak boleh lebih dari 100 karakter.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in'       => 'Jenis kelamin harus Laki-laki atau Perempuan.',
            'status_aktif.required'  => 'Status aktif siswa wajib diisi.',
            'status_aktif.in'        => 'Status aktif harus salah satu dari Aktif, Lulus, Pindah, atau Keluar.',
            'foto.file'              => 'File harus berupa berkas.',
            'foto.image'             => 'File harus berupa gambar.',
            'foto.mimes'             => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'               => 'Ukuran foto maksimal adalah 2MB.',
            'kelas_id.required'      => 'Kelas wajib dipilih.',
            'kelas_id.exists'        => 'Kelas tidak ditemukan.',
            'jurusan_id.required'    => 'Jurusan wajib dipilih.',
            'jurusan_id.exists'      => 'Jurusan tidak ditemukan.',
            'orangtua_id.exists'     => 'Orang Tua tidak ditemukan.',
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
            'alamat'        => 'Alamat',
            'no_telp_siswa' => 'Nomor Telepon Siswa',
            'foto'          => 'Foto siswa',
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
