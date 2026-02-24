<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Mengambil objek atau ID siswa dari route agar validasi unique ignore bekerja
        // Biasanya $this->route('siswa') mengembalikan objek model atau ID tergantung route binding
        $siswa = $this->route('siswa');
        $siswaId = is_object($siswa) ? $siswa->id : $siswa;

        return [
            'nis' => [
                'sometimes', 
                'required', 
                'string', 
                'max:20', 
                Rule::unique('siswa', 'nis')->ignore($siswaId)
            ],
            'nisn' => [
                'sometimes',
                'required',
                'string',
                'size:10',
                Rule::unique('siswa', 'nisn')->ignore($siswaId),
            ],
            'nama_lengkap'  => ['sometimes', 'required', 'string', 'max:100'],
            'tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['sometimes', 'required', 'in:Laki-laki,Perempuan'],
            
            // Validasi untuk tabel riwayat (siswa_kelas)
            // tahun_ajaran_id dihapus karena sudah dihandle otomatis oleh sistem berdasarkan kelas_id
            'kelas_id'      => ['sometimes', 'required', 'string', 'exists:kelas,id'],

            'orangtua'            => ['nullable', 'array'],
            'orangtua.*.id'       => ['required', 'string', 'exists:orangtua,id'],
            'orangtua.*.hubungan' => ['nullable', 'in:ayah,ibu,wali'],
            
            'foto'          => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'no_telp_siswa' => ['nullable', 'string', 'max:15'],
            'alamat'        => ['nullable', 'string'],
            'is_active'     => ['sometimes', 'required', 'integer', 'in:0,1'],
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
            'is_active'     => $this->has('is_active') ? (int) $this->is_active : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'nis.unique'             => 'NIS sudah digunakan oleh siswa lain.',
            'nisn.size'               => 'NISN harus tepat 10 karakter.',
            'nisn.unique'            => 'NISN sudah terdaftar di sistem.',
            'kelas_id.exists'         => 'Kelas tidak ditemukan.',
            'foto.image'              => 'File harus berupa gambar.',
            'foto.max'                => 'Ukuran foto maksimal adalah 2MB.',
            'orangtua.*.id.exists'    => 'Data orang tua tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nis'           => 'NIS',
            'nisn'          => 'NISN',
            'nama_lengkap'  => 'Nama lengkap',
            'kelas_id'      => 'Kelas',
            'is_active'     => 'Status Aktif',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors'  => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}