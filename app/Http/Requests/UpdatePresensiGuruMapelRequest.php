<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class UpdatePresensiGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Hanya perlu guru_mapel_id untuk referensi penugasan
            'guru_mapel_id'     => ['sometimes', 'integer', 'exists:guru_mapel,id'],
            'tanggal'           => ['sometimes', 'date'],
            'materi'            => ['nullable', 'string'],
            
            // Validasi detail presensi siswa
            'presensi'                => ['sometimes', 'array'],
            'presensi.*.siswa_id'     => ['required_with:presensi', 'string', 'exists:siswa,id'],
            'presensi.*.status'       => ['required_with:presensi', 'in:hadir,sakit,izin,alpa,Hadir,Sakit,Izin,Alpa'],
            'presensi.*.catatan'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_mapel_id.exists'      => 'Data penugasan guru tidak ditemukan.',
            'tanggal.date'              => 'Format tanggal tidak valid.',
            'presensi.array'            => 'Format data presensi harus berupa array.',
            'presensi.*.siswa_id.exists' => 'Data siswa tidak terdaftar.',
            'presensi.*.status.in'       => 'Status harus berupa Hadir, Sakit, Izin, atau Alfa.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi pembaruan gagal.',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}