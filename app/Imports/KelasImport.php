<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class KelasImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        return new Kelas([
            'id'              => $row['id'] ?? null,
            'nama_kelas'      => $row['nama_kelas'],
            'jurusan_id'      => $row['jurusan_id'],
            'wali_kelas_id'   => $row['wali_kelas_id'] ?? null,
            'tahun_ajaran_id' => $row['tahun_ajaran_id'] ?? ($tahunAktif->id ?? null),
            'is_active'       => isset($row['is_active']) ? (int)$row['is_active'] : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_kelas'      => 'required|string|max:255',
            'jurusan_id'      => 'required|exists:jurusans,id',
            'wali_kelas_id'   => 'nullable|exists:gurus,id',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajarans,id',
            'is_active'       => 'nullable|in:0,1',
        ];
    }
}