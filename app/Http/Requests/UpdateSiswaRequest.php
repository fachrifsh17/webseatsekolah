<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
<<<<<<< HEAD
=======
use Illuminate\Validation\Rule;
>>>>>>> master

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => ['sometimes','required','string','exists:users,id'],
            'nis'           => ['sometimes','required','string','max:20'],
<<<<<<< HEAD
=======
            'nisn'          => [
                'sometimes',
                'required',
                'string',
                'size:10',
                Rule::unique('siswa', 'nisn')->ignore($this->siswa->id ?? $this->route('siswa')),
            ],
>>>>>>> master
            'nama_lengkap'  => ['sometimes','required','string','max:100'],
            'tempat_lahir'  => ['nullable','string','max:100'],
            'tanggal_lahir' => ['nullable','date'],
            'jenis_kelamin' => ['sometimes','required','in:Laki-laki,Perempuan'],
            'kelas_id'      => ['sometimes','required','string','exists:kelas,id'],

            'orangtua'              => ['nullable','array'],
            'orangtua.*.id'         => ['required','string','exists:orangtua,id'],
            'orangtua.*.hubungan'   => ['nullable','in:ayah,ibu,wali'],

            'foto'          => ['nullable','file','image','mimes:jpg,jpeg,png','max:2048'],
            'no_telp_siswa' => ['nullable','string','max:15'],
            'alamat'        => ['nullable','string'],
            'is_active'     => ['sometimes','required','integer','in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nis'           => $this->filled('nis') ? trim($this->nis) : null,
<<<<<<< HEAD
=======
            'nisn'          => $this->filled('nisn') ? trim($this->nisn) : null,
>>>>>>> master
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
            'user_id.required'             => 'Akun pengguna wajib dihubungkan.',
            'user_id.string'               => 'User ID harus berupa ID string.',
            'user_id.exists'               => 'User tidak ditemukan.',

            'nis.required'                 => 'NIS wajib diisi.',
            'nis.max'                      => 'NIS tidak boleh lebih dari 20 karakter.',

<<<<<<< HEAD
=======
            'nisn.required'                => 'NISN wajib diisi.',
            'nisn.size'                    => 'NISN harus tepat 10 karakter.',
            'nisn.unique'                  => 'NISN sudah terdaftar di sistem.',

>>>>>>> master
            'nama_lengkap.required'        => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'             => 'Nama lengkap tidak boleh lebih dari 100 karakter.',

            'jenis_kelamin.required'       => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in'             => 'Jenis kelamin harus Laki-laki atau Perempuan.',

            'kelas_id.required'            => 'Kelas wajib dipilih.',
            'kelas_id.string'              => 'Kelas ID harus berupa ID string.',
            'kelas_id.exists'              => 'Kelas tidak ditemukan.',

            'orangtua.array'               => 'Format orang tua tidak valid.',
            'orangtua.*.id.required'       => 'ID orang tua wajib diisi.',
            'orangtua.*.id.string'         => 'ID orang tua harus berupa ID string.',
            'orangtua.*.id.exists'         => 'Orang tua tidak ditemukan.',
            'orangtua.*.hubungan.in'       => 'Hubungan harus ayah, ibu, atau wali.',

            'is_active.required'           => 'Status aktif siswa wajib diisi.',
            'is_active.integer'            => 'Status aktif harus berupa angka.',
            'is_active.in'                 => 'Status aktif tidak valid. Gunakan 0 atau 1.',

            'foto.file'                    => 'File harus berupa berkas.',
            'foto.image'                   => 'File harus berupa gambar.',
            'foto.mimes'                   => 'Format foto harus jpg, jpeg, atau png.',
            'foto.max'                     => 'Ukuran foto maksimal adalah 2MB.',

            'tanggal_lahir.date'           => 'Format tanggal lahir tidak valid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'              => 'Akun pengguna',
            'nis'                  => 'NIS',
<<<<<<< HEAD
=======
            'nisn'                 => 'NISN',
>>>>>>> master
            'nama_lengkap'         => 'Nama lengkap',
            'tempat_lahir'         => 'Tempat lahir',
            'tanggal_lahir'        => 'Tanggal lahir',
            'jenis_kelamin'        => 'Jenis kelamin',
            'kelas_id'             => 'Kelas',
            'orangtua'             => 'Orang Tua',
            'orangtua.*.id'        => 'Orang Tua',
            'orangtua.*.hubungan'  => 'Hubungan',
            'foto'                 => 'Foto siswa',
            'no_telp_siswa'        => 'Nomor Telepon Siswa',
            'alamat'               => 'Alamat',
            'is_active'            => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> master
