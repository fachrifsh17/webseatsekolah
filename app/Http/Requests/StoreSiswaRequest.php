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
            'kelas_id'        => ['required', 'string', 'exists:kelas,id'],
            'nis'             => ['required', 'string', 'size:8', 'unique:siswa,nis'],
            'nisn'            => ['required', 'string', 'size:10', 'unique:siswa,nisn'],
            'nik'             => ['required', 'string', 'size:16', 'unique:siswa,nik'],
            'nama_lengkap'    => ['required', 'string', 'max:100'],
            'tempat_lahir'    => ['required', 'string', 'max:100'],
            'tanggal_lahir'   => ['required', 'date'],
            'jenis_kelamin'   => ['required', 'in:Laki-laki,Perempuan'],
            'agama'           => ['required', 'string', 'max:20'],
            'tahun_angkatan'  => ['required', 'digits:4'],
            'alamat'          => ['required', 'string'],
            'no_telp_siswa'   => ['required', 'string', 'max:15'],
            'foto'            => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'is_active'       => ['required', 'integer', 'in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nis'           => $this->filled('nis') ? trim($this->nis) : null,
            'nisn'          => $this->filled('nisn') ? trim($this->nisn) : null,
            'nik'           => $this->filled('nik') ? trim($this->nik) : null,
            'nama_lengkap'  => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'tempat_lahir'  => $this->filled('tempat_lahir') ? trim($this->tempat_lahir) : null,
            'no_telp_siswa' => $this->filled('no_telp_siswa') ? trim($this->no_telp_siswa) : null,
            'jenis_kelamin' => $this->filled('jenis_kelamin') ? trim($this->jenis_kelamin) : null,
            'agama'         => $this->filled('agama') ? trim($this->agama) : null,
            'is_active'     => $this->has('is_active') ? (int) $this->is_active : 1,
        ]);
    }

    public function messages(): array
    {
        return [
            'nis.required'             => 'NIS tidak boleh kosong.',
            'nis.size'                 => 'NIS harus tepat 8 karakter.',
            'nis.unique'               => 'NIS sudah terdaftar.',
            'nisn.required'            => 'NISN wajib diisi.',
            'nisn.size'                => 'NISN harus tepat 10 karakter.',
            'nisn.unique'              => 'NISN sudah terdaftar.',
            'nik.required'             => 'NIK wajib diisi.',
            'nik.size'                 => 'NIK harus tepat 16 karakter.',
            'nik.unique'               => 'NIK sudah terdaftar.',
            'nama_lengkap.required'    => 'Nama lengkap wajib diisi.',
            'tempat_lahir.required'    => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required'   => 'Tanggal lahir wajib diisi.',
            'jenis_kelamin.required'   => 'Jenis kelamin wajib dipilih.',
            'kelas_id.required'        => 'Kelas wajib dipilih.',
            'kelas_id.exists'          => 'Kelas tidak ditemukan.',
            'agama.required'           => 'Agama wajib diisi.',
            'tahun_angkatan.required'  => 'Tahun angkatan wajib diisi.',
            'tahun_angkatan.digits'    => 'Tahun angkatan harus berupa 4 digit angka.',
            'alamat.required'          => 'Alamat wajib diisi.',
            'no_telp_siswa.required'   => 'Nomor telepon wajib diisi.',
            'foto.image'               => 'File harus berupa gambar.',
            'foto.max'                 => 'Ukuran foto maksimal adalah 2MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kelas_id'        => 'Kelas',
            'nis'             => 'NIS',
            'nisn'            => 'NISN',
            'nik'             => 'NIK',
            'nama_lengkap'    => 'Nama lengkap',
            'tempat_lahir'    => 'Tempat lahir',
            'tanggal_lahir'   => 'Tanggal lahir',
            'jenis_kelamin'   => 'Jenis kelamin',
            'agama'           => 'Agama',
            'tahun_angkatan'  => 'Tahun angkatan',
            'alamat'          => 'Alamat',
            'no_telp_siswa'   => 'Nomor Telepon Siswa',
            'foto'            => 'Foto siswa',
            'is_active'       => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}