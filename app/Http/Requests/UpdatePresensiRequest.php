<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && (Auth::user()->role === 'admin' || Auth::user()->role === 'guru');
    }

    public function rules(): array
    {
        return [
            'siswa_id'        => ['sometimes', 'required', 'exists:siswa,id'],
            'tahun_ajaran_id' => ['sometimes', 'required', 'exists:tahun_ajaran,id'],
            'tanggal'         => ['sometimes', 'required', 'date'],
            'status'          => ['sometimes', 'required', 'in:Hadir,Izin,Sakit,Alfa'],
            'keterangan'      => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required'     => 'Siswa wajib dipilih.',
            'siswa_id.exists'       => 'Data siswa tidak ditemukan.',

            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.exists'   => 'Tahun ajaran tidak valid.',

            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date'     => 'Format tanggal tidak valid.',

            'status.required' => 'Status presensi wajib dipilih.',
            'status.in'       => 'Status harus berupa Hadir, Izin, Sakit, atau Alfa.',

            'keterangan.string' => 'Keterangan harus berupa teks.',
            'keterangan.max'    => 'Keterangan tidak boleh lebih dari 255 karakter.',
        ];
    }

    public function attributes(): array
    {
        return [
            'siswa_id'        => 'Siswa',
            'tahun_ajaran_id' => 'Tahun ajaran',
            'tanggal'         => 'Tanggal',
            'status'          => 'Status presensi',
            'keterangan'      => 'Keterangan',
        ];
    }
}