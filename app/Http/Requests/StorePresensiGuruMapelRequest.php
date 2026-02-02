<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class StorePresensiGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_mapel_id'     => ['required', 'integer', 'exists:guru_mapel,id'],
            'materi'            => ['nullable', 'string'],
            'tanggal'           => ['nullable', 'date'],
            'kelas_id'          => ['nullable', 'string'],
            'mata_pelajaran_id' => ['nullable', 'string'],
            'jam_masuk'         => ['nullable', 'string'],
            'jam_keluar'        => ['nullable', 'string'],
            'presensi'              => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id'   => ['required', 'string', 'exists:siswa,id'],
            'presensi.*.status'     => ['required', 'in:hadir,sakit,izin,alfa,Hadir,Sakit,Izin,Alpa'],
            'presensi.*.catatan'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_mapel_id.required' => 'ID penugasan guru wajib diisi.',
            'guru_mapel_id.exists'   => 'Data penugasan guru tidak ditemukan.',
            'presensi.required'      => 'Data kehadiran siswa tidak boleh kosong.',
            'presensi.min'           => 'Minimal harus ada satu data siswa.',
            'presensi.*.siswa_id.exists' => 'Data siswa tidak terdaftar.',
            'presensi.*.status.in'   => 'Status harus berupa Hadir, Sakit, Izin, atau Alfa.',
        ];
    }

    protected function failedValidation(Validator $validator): void
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