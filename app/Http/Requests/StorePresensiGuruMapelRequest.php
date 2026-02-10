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
            'guru_mapel_id'     => ['required', 'integer', 'exists:guru_mapel,id'],
            'materi'            => ['required', 'string', 'min:5'],

            'kelas_id'          => ['nullable', 'string', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'string', 'exists:mata_pelajaran,id'],
            'tahun_ajaran_id'   => ['nullable', 'string', 'exists:tahun_ajaran,id'],
            'tanggal'           => ['nullable', 'date'],
            'jam_masuk'         => ['nullable', 'string'],
            'jam_keluar'        => ['nullable', 'string'],

            'presensi'              => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id'   => ['required', 'string', 'exists:siswa,id'],
            'presensi.*.status'     => ['required', 'in:hadir,sakit,izin,alpa,Hadir,Sakit,Izin,Alpa'],
            'presensi.*.catatan'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'guru_mapel_id.required' => 'ID penugasan guru wajib diisi.',
            'guru_mapel_id.exists'   => 'Data penugasan guru tidak ditemukan.',
            'materi.required'        => 'Materi pembelajaran tidak boleh kosong.',
            'presensi.required'      => 'Data kehadiran siswa tidak boleh kosong.',
            'presensi.min'           => 'Minimal harus ada satu data siswa.',
            'presensi.*.status.in'   => 'Status harus berupa Hadir, Sakit, Izin, atau Alpa.',
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