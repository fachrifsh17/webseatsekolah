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
        $siswa = $this->route('siswa');
        $siswaId = is_object($siswa) ? $siswa->id : $siswa;

        return [
            'nis' => [
                'sometimes', 
                'required', 
                'string', 
                'size:8', // Diubah dari max:20 menjadi size:8
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
            'nis.required'           => 'NIS tidak boleh kosong.',
            'nis.size'               => 'NIS harus tepat 8 karakter.',
            'nis.unique'             => 'NIS sudah digunakan oleh siswa lain.',
            'nisn.required'          => 'NISN tidak boleh kosong.',
            'nisn.size'              => 'NISN harus tepat 10 karakter.',
            'nisn.unique'            => 'NISN sudah terdaftar di sistem.',
            'kelas_id.exists'        => 'Kelas tidak ditemukan.',
            'foto.image'             => 'File harus berupa gambar.',
            'foto.max'               => 'Ukuran foto maksimal adalah 2MB.',
            'orangtua.*.id.exists'   => 'Data orang tua tidak ditemukan.',
            'orangtua.*.id.required' => 'ID orang tua wajib diisi.',
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