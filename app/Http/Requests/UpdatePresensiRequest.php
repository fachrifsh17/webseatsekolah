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
            'data_presensi' => ['required', 'array', 'min:1'],
            'data_presensi.*.siswa_id' => ['required', 'string', 'exists:siswa,id'],
            'data_presensi.*.status'   => ['required', 'string', 'in:Hadir,Izin,Sakit,Alpa'],
            'data_presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data_presensi.required' => 'Data presensi wajib diisi.',
            'data_presensi.array' => 'Format data presensi tidak valid.',
            'data_presensi.min' => 'Minimal satu siswa harus diupdate.',
            'data_presensi.*.siswa_id.required' => 'Siswa tidak ditemukan.',
            'data_presensi.*.siswa_id.exists' => 'Siswa tidak terdaftar di database.',
            'data_presensi.*.status.required' => 'Status wajib diisi.',
            'data_presensi.*.status.in' => 'Status harus Hadir, Izin, Sakit, atau Alpa.',
        ];
    }

    public function attributes(): array
    {
        return [
            'data_presensi.*.siswa_id' => 'Siswa',
            'data_presensi.*.status'   => 'Status',
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