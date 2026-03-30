<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreGuruRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nip' => [
                'nullable', 
                'string', 
                'size:18', 
                'unique:guru_staf,nip' 
            ],
            'nuptk' => [
                'nullable', 
                'string', 
                'size:16', 
                'unique:guru_staf,nuptk'
            ],
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:100', 'unique:guru_staf,email'],
            'alamat_lengkap' => ['required', 'string'],
            'jenis_kelamin' => ['required', 'string', 'in:Laki-laki,Perempuan'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date'],
            'agama' => ['required', 'string', 'max:20'],
            'pendidikan_terakhir' => ['required', 'string', 'max:50'],
            'jabatan_fungsional' => ['required', 'string', 'max:100'],
            'status_kepegawaian' => ['required', 'string', 'max:50'],
            'foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'jurusan_id' => ['nullable', 'string', 'exists:jurusan,id'],
            'is_active' => ['required', 'integer', 'in:0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nip'   => $this->filled('nip') ? trim($this->nip) : null,
            'nuptk' => $this->filled('nuptk') ? trim($this->nuptk) : null,
            'nama'  => $this->filled('nama') ? trim($this->nama) : null,
            'email' => $this->filled('email') ? trim($this->email) : null,
            'is_active' => $this->has('is_active') ? (int) $this->is_active : 1,
        ]);
    }

    public function messages(): array
    {
        return [
            'nip.size' => 'NIP harus berjumlah tepat 18 karakter.',
            'nip.unique' => 'NIP sudah digunakan.',
            'nuptk.size' => 'NUPTK harus berjumlah tepat 16 karakter.',
            'nuptk.unique' => 'NUPTK sudah digunakan.',
            'nama.required' => 'Nama lengkap wajib diisi.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah terdaftar.',
            'alamat_lengkap.required' => 'Alamat lengkap wajib diisi.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in' => 'Pilihan jenis kelamin tidak valid.',
            'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.date' => 'Format tanggal tidak valid.',
            'agama.required' => 'Agama wajib diisi.',
            'pendidikan_terakhir.required' => 'Pendidikan terakhir wajib diisi.',
            'jabatan_fungsional.required' => 'Jabatan wajib diisi.',
            'status_kepegawaian.required' => 'Status kepegawaian wajib diisi.',
            'foto.max' => 'Ukuran foto maksimal 5MB.',
            'foto.mimes' => 'Format foto harus jpg, jpeg, atau png.',
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