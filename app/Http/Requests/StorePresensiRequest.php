<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hanya admin dan guru yang boleh membuat presensi
        return Auth::check() && (Auth::user()->role === 'admin' || Auth::user()->role === 'guru');
    }

    public function rules(): array
    {
        return [
            'siswa_id'        => ['required', 'integer', 'exists:siswa,id'],
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,id'],
            'tanggal'         => ['required', 'date'],
            'status'          => ['required', 'in:Hadir,Izin,Sakit,Alfa'],
            'keterangan'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required'        => 'Siswa harus dipilih.',
            'siswa_id.integer'         => 'ID siswa harus berupa angka.',
            'siswa_id.exists'          => 'Data siswa tidak ditemukan.',
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib ditentukan.',
            'tahun_ajaran_id.integer'  => 'ID tahun ajaran harus berupa angka.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak ditemukan.',
            'tanggal.required'         => 'Tanggal absensi tidak boleh kosong.',
            'tanggal.date'             => 'Format tanggal absensi tidak valid.',
            'status.required'          => 'Status kehadiran harus diisi.',
            'status.in'                => 'Status harus berupa Hadir, Izin, Sakit, atau Alfa.',
            'keterangan.string'        => 'Keterangan harus berupa teks.',
            'keterangan.max'           => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'        => 'Siswa',
            'tahun_ajaran_id' => 'Tahun ajaran',
            'tanggal'         => 'Tanggal absensi',
            'status'          => 'Status kehadiran',
            'keterangan'      => 'Keterangan',
        ];
    }
}