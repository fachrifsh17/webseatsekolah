<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['bail', 'required', 'string', 'exists:users,id', 'unique:siswa,user_id'],
            'kelas_id'      => ['required', 'string', 'exists:kelas,id'],
            'nis'           => ['required', 'string', 'max:20', 'unique:siswa,nis'],
            'nisn'          => ['required', 'string', 'size:10', 'unique:siswa,nisn'],
            'nama_lengkap'  => ['required', 'string', 'max:100'],
            'tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'orangtua'            => ['nullable', 'array'],
            'orangtua.*.id'       => ['nullable', 'string', 'exists:orangtua,id'],
            'orangtua.*.hubungan' => ['nullable', 'in:ayah,ibu,wali'],
            'alamat'        => ['nullable', 'string'],
            'no_telp_siswa' => ['nullable', 'string', 'max:15'],
            'foto'          => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'is_active'     => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nis'           => $this->filled('nis') ? trim($this->nis) : null,
            'nisn'          => $this->filled('nisn') ? trim($this->nisn) : null,
            'nama_lengkap'  => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'tempat_lahir'  => $this->filled('tempat_lahir') ? trim($this->tempat_lahir) : null,
            'no_telp_siswa' => $this->filled('no_telp_siswa') ? trim($this->no_telp_siswa) : null,
            'jenis_kelamin' => $this->filled('jenis_kelamin') ? trim($this->jenis_kelamin) : null,
            'is_active'     => $this->filled('is_active') ? (int) $this->is_active : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'user_id.required'         => 'Akun pengguna wajib dihubungkan.',
            'user_id.string'           => 'User ID harus berupa ID string.',
            'user_id.exists'           => 'User tidak ditemukan.',
            'user_id.unique'           => 'User sudah terhubung dengan siswa lain.',

            'nis.required'             => 'NIS tidak boleh kosong.',
            'nis.max'                  => 'NIS tidak boleh lebih dari 20 karakter.',
            'nis.unique'               => 'NIS sudah terdaftar.',

            'nisn.required'            => 'NISN wajib diisi.',
            'nisn.size'                => 'NISN harus tepat 10 karakter.',
            'nisn.unique'              => 'NISN sudah terdaftar.',

            'nama_lengkap.required'    => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'         => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'jenis_kelamin.required'   => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in'         => 'Jenis kelamin harus Laki-laki atau Perempuan.',

            'foto.file'                => 'File harus berupa berkas.',
            'foto.image'               => 'File harus berupa gambar.',
            'foto.mimes'               => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'                 => 'Ukuran foto maksimal adalah 2MB.',

            'kelas_id.required'        => 'Kelas wajib dipilih.',
            'kelas_id.string'          => 'Kelas harus berupa ID string.',
            'kelas_id.exists'          => 'Kelas tidak ditemukan.',

            'orangtua.array'           => 'Format orang tua tidak valid.',
            'orangtua.*.id.string'     => 'ID orang tua harus berupa ID string.',
            'orangtua.*.id.exists'     => 'Orang tua tidak ditemukan.',
            'orangtua.*.hubungan.in'   => 'Hubungan harus ayah, ibu, atau wali.',

            'is_active.integer'        => 'Status aktif harus berupa angka.',
            'is_active.in'             => 'Status aktif tidak valid. Gunakan 0 atau 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'              => 'Akun pengguna',
            'kelas_id'             => 'Kelas',
            'orangtua'             => 'Orang Tua',
            'orangtua.*.id'        => 'Orang Tua',
            'orangtua.*.hubungan'  => 'Hubungan',
            'nis'                  => 'NIS',
            'nisn'                 => 'NISN',
            'nama_lengkap'         => 'Nama lengkap',
            'tempat_lahir'         => 'Tempat lahir',
            'tanggal_lahir'        => 'Tanggal lahir',
            'jenis_kelamin'        => 'Jenis kelamin',
            'alamat'               => 'Alamat',
            'no_telp_siswa'        => 'Nomor Telepon Siswa',
            'foto'                 => 'Foto siswa',
            'is_active'            => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}