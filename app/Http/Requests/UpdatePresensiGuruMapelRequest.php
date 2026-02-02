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
            'guru_mapel_id'     => ['sometimes', 'integer', 'exists:guru_mapel,id'],
            'kelas_id'          => ['nullable', 'string', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'string', 'exists:mata_pelajaran,id'],
            'tanggal'           => ['sometimes', 'date'],
            'jam_masuk'         => ['nullable', 'string'],
            'jam_keluar'        => ['nullable', 'string'],
            'materi'            => ['nullable', 'string'],
            
            'presensi'              => ['sometimes', 'array'],
            'presensi.*.siswa_id'   => ['required_with:presensi', 'string', 'exists:siswa,id'],
            'presensi.*.status'     => ['required_with:presensi', 'in:hadir,sakit,izin,alfa,Hadir,Sakit,Izin,Alpa'],
            'presensi.*.catatan'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_mapel_id.exists'       => 'Data penugasan guru tidak ditemukan.',
            'kelas_id.exists'            => 'Data kelas tidak ditemukan.',
            'mata_pelajaran_id.exists'   => 'Data mata pelajaran tidak ditemukan.',
            'tanggal.date'               => 'Format tanggal tidak valid.',
            'presensi.array'             => 'Format data presensi harus berupa array.',
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