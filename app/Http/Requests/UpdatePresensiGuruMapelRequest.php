<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePresensiGuruMapelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_mapel_id' => 'sometimes|exists:guru_mapel,id',
            'kelas_id' => 'sometimes|exists:kelas,id',
            'mata_pelajaran_id' => 'sometimes|exists:mata_pelajaran,id',
            'tanggal' => 'sometimes|date',
            'jam_masuk' => 'sometimes',
            'jam_keluar' => 'sometimes',
            'materi' => 'nullable|string',
            
            'presensi' => 'sometimes|array',
            'presensi.*.siswa_id' => 'required_with:presensi|exists:siswa,id',
            'presensi.*.status' => 'required_with:presensi|in:Hadir,Sakit,Izin,Alpa',
            'presensi.*.catatan' => 'nullable|string',
        ];
    }
}