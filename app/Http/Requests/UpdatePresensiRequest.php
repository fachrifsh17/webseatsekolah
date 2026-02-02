<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdatePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'siswa_id'     => ['sometimes', 'string', 'exists:siswa,id'],
            'tanggal'      => ['nullable', 'date'],  
            'status'       => ['sometimes', 'in:Hadir,Izin,Sakit,Alpa'],
            'keterangan'   => ['nullable', 'string', 'max:255'],
            'guru_staf_id' => ['nullable', 'string', 'exists:guru_staf,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.string'      => 'Siswa harus berupa ID string.',
            'siswa_id.exists'      => 'Data siswa tidak ditemukan.',

            'tanggal.date'         => 'Format tanggal tidak valid.',

            'status.in'            => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',

            'keterangan.string'    => 'Keterangan harus berupa teks.',
            'keterangan.max'       => 'Keterangan tidak boleh lebih dari 255 karakter.',

            'guru_staf_id.string'  => 'ID guru harus berupa ID string.',
            'guru_staf_id.exists'  => 'Data guru tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'     => 'Siswa',
            'tanggal'      => 'Tanggal',
            'status'       => 'Status presensi',
            'keterangan'   => 'Keterangan',
            'guru_staf_id' => 'Guru Penginput',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}