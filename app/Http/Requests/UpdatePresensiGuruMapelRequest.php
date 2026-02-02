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
<<<<<<< HEAD
            'kelas_id'          => ['sometimes', 'string', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['sometimes', 'string', 'exists:mata_pelajaran,id'],
            'tanggal'           => ['sometimes', 'date'],
            'jam_masuk'         => ['sometimes'],
            'jam_keluar'        => ['sometimes'],
            'materi'            => ['nullable', 'string'],

            'presensi'              => ['sometimes', 'array'],
            'presensi.*.siswa_id'   => ['required_with:presensi', 'string', 'exists:siswa,id'],
            'presensi.*.status'     => ['required_with:presensi', 'in:Hadir,Izin,Sakit,Alpa'],
=======
            'guru_mapel_id'     => ['sometimes', 'integer', 'exists:guru_mapel,id'],
            'kelas_id'          => ['nullable', 'string'],
            'mata_pelajaran_id' => ['nullable', 'string'],
            'tanggal'           => ['sometimes', 'date'],
            'jam_masuk'         => ['nullable', 'string'],
            'jam_keluar'        => ['nullable', 'string'],
            'materi'            => ['nullable', 'string'],
            'presensi'              => ['sometimes', 'array'],
            'presensi.*.siswa_id'   => ['required_with:presensi', 'string', 'exists:siswa,id'],
            'presensi.*.status'     => ['required_with:presensi', 'in:hadir,sakit,izin,alfa,Hadir,Sakit,Izin,Alpa'],
>>>>>>> master
            'presensi.*.catatan'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
<<<<<<< HEAD
            'kelas_id.string'            => 'Kelas ID harus berupa ID string.',
            'kelas_id.exists'            => 'Data kelas tidak ditemukan.',

            'mata_pelajaran_id.string'   => 'Mata pelajaran ID harus berupa ID string.',
            'mata_pelajaran_id.exists'   => 'Data mata pelajaran tidak ditemukan.',

            'tanggal.date'               => 'Format tanggal tidak valid.',

            'presensi.array'             => 'Format data presensi harus berupa array.',
            'presensi.*.siswa_id.string' => 'Siswa ID harus berupa ID string.',
            'presensi.*.siswa_id.exists' => 'Data siswa tidak ditemukan.',
            'presensi.*.status.in'       => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',
=======
            'guru_mapel_id.exists'       => 'Data penugasan guru tidak ditemukan.',
            'tanggal.date'               => 'Format tanggal tidak valid.',
            'presensi.array'             => 'Format data presensi harus berupa array.',
            'presensi.*.siswa_id.exists' => 'Data siswa tidak terdaftar.',
            'presensi.*.status.in'       => 'Status harus berupa hadir, sakit, izin, atau alfa.',
>>>>>>> master
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
<<<<<<< HEAD
}
=======
}
>>>>>>> master
