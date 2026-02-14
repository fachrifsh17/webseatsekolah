<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class UpdateOrangtuaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Mengambil ID orang tua dari route parameter
        $orangtua = $this->route('orangtua');
        $orangtuaId = $orangtua ? $orangtua->id : null;
        $userId = $orangtua ? $orangtua->user_id : null;

        return [
            'nama_lengkap'  => ['sometimes', 'required', 'string', 'max:150'],
            'telepon'       => [
                'sometimes', 
                'required', 
                'string', 
                'max:15',
                // Cek unik di tabel orangtua kecuali ID saat ini
                Rule::unique('orangtua', 'telepon')->ignore($orangtuaId),
                // Cek unik di tabel users (username) kecuali ID user terkait
                Rule::unique('users', 'username')->ignore($userId)
            ],
            'is_active'     => ['nullable', 'integer', 'in:0,1'],

            'anak'            => ['nullable', 'array'],
            'anak.*.nis'      => ['required', 'string', 'exists:siswa,nis'],
            'anak.*.hubungan' => ['nullable', 'in:ayah,ibu,wali'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Bersihkan nomor telepon dari karakter non-angka agar konsisten dengan username
        $telepon = $this->filled('telepon') ? preg_replace('/[^0-9]/', '', $this->telepon) : null;

        $this->merge([
            'nama_lengkap' => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'telepon'      => $telepon,
            'is_active'    => $this->has('is_active') ? (int) $this->is_active : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required'    => 'Nama lengkap wajib diisi.',
            'telepon.required'         => 'Nomor telepon wajib diisi.',
            'telepon.unique'           => 'Nomor telepon/username sudah digunakan oleh orang tua lain.',
            'anak.*.nis.exists'        => 'NIS anak tidak ditemukan di sistem.',
            'is_active.in'             => 'Status aktif tidak valid.',
        ];
    }

    // failedValidation & attributes tetap sama seperti kode Anda
    public function attributes(): array
    {
        return [
            'nama_lengkap'     => 'Nama lengkap',
            'telepon'          => 'Telepon',
            'is_active'        => 'Status Aktif',
            'anak'             => 'Anak',
            'anak.*.nis'       => 'NIS Anak',
            'anak.*.hubungan'  => 'Hubungan',
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