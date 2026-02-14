<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StoreOrangtuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_lengkap'  => ['required', 'string', 'max:150'],
            // TAMBAHKAN unique agar tidak bentrok di tabel orangtua dan tabel users (username)
            'telepon'       => [
                'required', 
                'string', 
                'max:15', 
                'unique:orangtua,telepon', 
                'unique:users,username'
            ],
            'is_active'     => ['nullable', 'integer', 'in:0,1'],

            'anak'            => ['nullable', 'array'],
            'anak.*.nis'      => ['required', 'string', 'exists:siswa,nis'],
            'anak.*.hubungan' => ['nullable', 'in:ayah,ibu,wali'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Pastikan nomor telepon bersih dari spasi atau karakter aneh
        $telepon = $this->filled('telepon') ? preg_replace('/[^0-9]/', '', $this->telepon) : null;

        $this->merge([
            'nama_lengkap' => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'telepon'      => $telepon,
            'is_active'    => $this->filled('is_active') ? (int) $this->is_active : 1,
        ]);
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required'    => 'Nama lengkap wajib diisi.',
            'telepon.required'         => 'Nomor telepon wajib diisi.',
            'telepon.unique'           => 'Nomor telepon sudah terdaftar sebagai akun orang tua lain.',
            'anak.*.nis.exists'        => 'NIS anak tidak terdaftar atau tidak aktif.',
            'is_active.in'             => 'Status aktif tidak valid.',
        ];
    }

    // ... failedValidation tetap sama seperti kode Anda
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}