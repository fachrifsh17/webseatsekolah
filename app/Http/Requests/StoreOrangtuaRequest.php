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
            'user_id'       => ['required','string','exists:users,id','unique:orangtua,user_id'],
            'nama_lengkap'  => ['required','string','max:150'],
            'telepon'       => ['required','string','max:15'],
            'is_active'     => ['nullable','integer','in:0,1'],

            'anak'                => ['nullable','array'],
            'anak.*.siswa_id'     => ['required','string','exists:siswa,id'],
            'anak.*.hubungan'     => ['nullable','in:ayah,ibu,wali'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nama_lengkap' => $this->filled('nama_lengkap') ? trim($this->nama_lengkap) : null,
            'telepon'      => $this->filled('telepon') ? trim($this->telepon) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'user_id.required'        => 'Akun pengguna wajib dihubungkan.',
            'user_id.string'          => 'Akun pengguna harus berupa ID string.',
            'user_id.exists'          => 'Akun pengguna tidak ditemukan.',
            'user_id.unique'          => 'Akun ini sudah terdaftar sebagai orang tua.',

            'nama_lengkap.required'   => 'Nama lengkap wajib diisi.',
            'nama_lengkap.max'        => 'Nama lengkap maksimal 150 karakter.',

            'telepon.required'        => 'Nomor telepon wajib diisi.',
            'telepon.max'             => 'Nomor telepon maksimal 15 karakter.',

            'is_active.integer'       => 'Status aktif harus berupa angka.',
            'is_active.in'            => 'Status aktif tidak valid. Gunakan 0 atau 1.',

            'anak.array'              => 'Format data anak tidak valid.',
            'anak.*.siswa_id.required'=> 'Siswa wajib dipilih.',
            'anak.*.siswa_id.string'  => 'Siswa harus berupa ID string.',
            'anak.*.siswa_id.exists'  => 'Data siswa tidak ditemukan.',
            'anak.*.hubungan.in'      => 'Hubungan harus ayah, ibu, atau wali.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id'            => 'Akun pengguna',
            'nama_lengkap'       => 'Nama lengkap',
            'telepon'            => 'Telepon',
            'is_active'          => 'Status Aktif',
            'anak'               => 'Anak',
            'anak.*.siswa_id'    => 'Siswa',
            'anak.*.hubungan'    => 'Hubungan',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
