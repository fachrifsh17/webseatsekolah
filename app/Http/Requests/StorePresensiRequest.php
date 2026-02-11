<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StorePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date'],
            'data_presensi' => ['required', 'array', 'min:1'],
            'data_presensi.*.siswa_id' => ['required', 'string', 'exists:siswa,id'],
            'data_presensi.*.status' => ['required', 'in:Hadir,Izin,Sakit,Alpa'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal absensi wajib diisi.',
            'tanggal.date' => 'Format tanggal tidak valid.',
            'data_presensi.required' => 'Data presensi tidak boleh kosong.',
            'data_presensi.array' => 'Format data harus berupa array.',
            
            'data_presensi.*.siswa_id.required' => 'Siswa harus dipilih.',
            'data_presensi.*.siswa_id.exists' => 'Data siswa tidak ditemukan.',
            'data_presensi.*.status.required' => 'Status kehadiran harus diisi.',
            'data_presensi.*.status.in' => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'tanggal' => 'Tanggal absensi',
            'data_presensi' => 'Daftar Presensi',
            'data_presensi.*.siswa_id' => 'Siswa',
            'data_presensi.*.status' => 'Status kehadiran',
            'data_presensi.*.keterangan' => 'Keterangan',
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