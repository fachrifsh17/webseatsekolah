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
            // Hanya perlu guru_mapel_id untuk referensi penugasan
            'guru_mapel_id'     => ['required', 'integer', 'exists:guru_mapel,id'],
            'tanggal'           => ['required', 'date'],
            'materi'            => ['required', 'string', 'min:5'],

            // Validasi detail presensi siswa
            'presensi'                => ['required', 'array', 'min:1'],
            'presensi.*.siswa_id'     => ['required', 'string', 'exists:siswa,id'],
            'presensi.*.status'       => ['required', 'in:hadir,sakit,izin,alpa,Hadir,Sakit,Izin,Alpa'],
            'presensi.*.catatan'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // Validasi Header
            'guru_mapel_id.required' => 'ID penugasan guru wajib diisi.',
            'guru_mapel_id.exists'   => 'Data penugasan guru tidak ditemukan di sistem.',
            'tanggal.required'       => 'Tanggal presensi wajib diisi.',
            'tanggal.date'           => 'Format tanggal tidak valid.',
            'materi.required'        => 'Materi pembelajaran tidak boleh kosong.',
            'materi.min'             => 'Isi materi minimal 5 karakter.',
            
            // Validasi Array Presensi
            'presensi.required'      => 'Daftar kehadiran siswa wajib dikirim.',
            'presensi.array'         => 'Format data presensi harus berupa array.',
            'presensi.min'           => 'Minimal harus ada satu data siswa yang diabsen.',

            // Validasi Item di dalam Array (Siswa)
            'presensi.*.siswa_id.required' => 'ID Siswa pada baris ke-:position wajib diisi.',
            'presensi.*.siswa_id.exists'   => 'Siswa pada baris ke-:position tidak ditemukan.',
            'presensi.*.status.required'   => 'Status hadir baris ke-:position belum dipilih.',
            'presensi.*.status.in'         => 'Status pada baris ke-:position harus: Hadir, Sakit, Izin, atau Alpa.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi gagal: Periksa kembali data inputan Anda.',
                'errors'  => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}