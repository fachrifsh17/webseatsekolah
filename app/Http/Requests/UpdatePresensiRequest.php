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
            // Field kunci untuk filter keamanan di Controller
            'kelas_id'      => ['sometimes', 'string', 'exists:kelas,id'],

            // Validasi untuk massal
            'data_presensi' => ['sometimes', 'array', 'min:1'],
            'data_presensi.*.siswa_id' => ['required_with:data_presensi', 'string', 'exists:siswa,id'],
            'data_presensi.*.status'   => ['required_with:data_presensi', 'in:Hadir,Izin,Sakit,Alpa'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],

            // Validasi untuk satuan (jika bukan massal/lewat ID di URL)
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
            'kelas_id.exists'     => 'Data kelas tidak ditemukan.',
            'data_presensi.array' => 'Format data harus berupa array.',
            'data_presensi.*.siswa_id.required_with' => 'Siswa wajib diisi dalam data massal.',
            'data_presensi.*.status.required_with'   => 'Status wajib diisi dalam data massal.',
            'data_presensi.*.status.in'              => 'Status massal harus berupa Hadir, Izin, Sakit, atau Alpa.',
            
            'siswa_id.exists'      => 'Data siswa tidak ditemukan.',
            'tanggal.date'         => 'Format tanggal tidak valid.',
            'status.in'            => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',
            'keterangan.max'       => 'Keterangan tidak boleh lebih dari 255 karakter.',
            'guru_staf_id.exists'  => 'Data guru tidak ditemukan.',
        ];
    }

    public function attributes(): array
    {
        return [
            'kelas_id'      => 'ID Kelas',
            'data_presensi' => 'Daftar Presensi',
            'data_presensi.*.siswa_id' => 'Siswa',
            'data_presensi.*.status'   => 'Status presensi',
            'status'                   => 'Status presensi',
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