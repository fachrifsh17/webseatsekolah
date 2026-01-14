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
        return $this->user()?->can('create', \App\Models\PresensiGuruMapel::class) ?? true;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('presensi') && is_string($this->input('presensi'))) {
            $decoded = json_decode($this->input('presensi'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['presensi' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'guru_mapel_id'        => ['nullable', 'string', 'exists:guru_mapel,id'],
            'kelas_id'             => ['required', 'string', 'exists:kelas,id'],
            'mata_pelajaran_id'    => ['required', 'string', 'exists:mata_pelajaran,id'],
            'tanggal'              => ['required', 'date'],
            'jam_masuk'            => ['required', 'date_format:H:i'],
            'jam_keluar'           => ['required', 'date_format:H:i'],
            'materi'               => ['nullable', 'string'],

            'presensi'             => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id'  => ['required', 'string', 'exists:siswa,id'],
            'presensi.*.status'    => ['required', 'in:Hadir,Izin,Sakit,Alpa'],
            'presensi.*.catatan'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_mapel_id.string'         => 'Guru mapel harus berupa ID string.',
            'guru_mapel_id.exists'         => 'Data guru mapel tidak ditemukan.',

            'kelas_id.required'            => 'Kelas harus dipilih.',
            'kelas_id.string'              => 'Kelas harus berupa ID string.',
            'kelas_id.exists'              => 'Data kelas tidak ditemukan.',

            'mata_pelajaran_id.required'   => 'Mata pelajaran harus dipilih.',
            'mata_pelajaran_id.string'     => 'Mata pelajaran harus berupa ID string.',
            'mata_pelajaran_id.exists'     => 'Data mata pelajaran tidak ditemukan.',

            'tanggal.required'             => 'Tanggal tidak boleh kosong.',
            'tanggal.date'                 => 'Format tanggal tidak valid.',

            'jam_masuk.required'           => 'Jam masuk harus diisi.',
            'jam_masuk.date_format'        => 'Format jam masuk harus HH:MM.',
            'jam_keluar.required'          => 'Jam keluar harus diisi.',
            'jam_keluar.date_format'       => 'Format jam keluar harus HH:MM.',

            'presensi.required'            => 'Data kehadiran siswa tidak boleh kosong.',
            'presensi.array'               => 'Format presensi tidak valid.',
            'presensi.min'                 => 'Minimal harus ada satu data siswa.',

            'presensi.*.siswa_id.required' => 'ID siswa wajib diisi untuk setiap entri presensi.',
            'presensi.*.siswa_id.string'   => 'ID siswa harus berupa ID string.',
            'presensi.*.siswa_id.exists'   => 'Data siswa tidak ditemukan.',

            'presensi.*.status.required'   => 'Status kehadiran siswa wajib diisi.',
            'presensi.*.status.in'         => 'Status harus berupa Hadir, Izin, Sakit, atau Alpa.',

            'presensi.*.catatan.string'    => 'Format catatan tidak valid.',
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
