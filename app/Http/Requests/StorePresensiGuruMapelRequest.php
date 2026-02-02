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
            'guru_mapel_id'     => ['required', 'integer', 'exists:guru_mapel,id'],
            'kelas_id'          => ['nullable', 'string', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'string', 'exists:mata_pelajaran,id'],
            'tanggal'           => ['required', 'date'],
            'jam_masuk'         => ['required', 'string'],
            'jam_keluar'        => ['required', 'string'],
            'materi'            => ['nullable', 'string'],

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
            
            'tanggal.required'       => 'Tanggal tidak boleh kosong.',
            'tanggal.date'           => 'Format tanggal tidak valid.',

            'jam_masuk.required'     => 'Jam masuk harus diisi.',
            'jam_keluar.required'    => 'Jam keluar harus diisi.',

            'presensi.required'      => 'Data kehadiran siswa tidak boleh kosong.',
            'presensi.array'         => 'Format presensi tidak valid.',
            'presensi.min'           => 'Minimal harus ada satu data siswa.',

            'presensi.*.siswa_id.required' => 'ID siswa wajib diisi.',
            'presensi.*.siswa_id.exists'   => 'Data siswa tidak terdaftar.',
            
            'presensi.*.status.required'   => 'Status kehadiran wajib diisi.',
            'presensi.*.status.in'         => 'Status harus berupa Hadir, Sakit, Izin, atau Alpa.',
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