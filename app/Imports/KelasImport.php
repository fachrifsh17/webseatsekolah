<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;

class KelasImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        $tahunAjaranId = $row['tahun_ajaran_id'] ?? ($tahunAktif->id ?? null);

        $exists = Kelas::where([
            'nama_kelas'      => $row['nama_kelas'],
            'tahun_ajaran_id' => $tahunAjaranId,
        ])->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($row, $tahunAjaranId) {
            $lastKelas = Kelas::where('id', 'like', 'K%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastId = $lastKelas ? (int) substr($lastKelas->id, 1) : 0;
            $newId = 'K' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            return new Kelas([
                'id'              => $newId,
                'nama_kelas'      => $row['nama_kelas'],
                'jurusan_id'      => $row['jurusan_id'],
                'wali_kelas_id'   => $row['wali_kelas_id'] ?? null,
                'tahun_ajaran_id' => $tahunAjaranId,
                'is_active'       => isset($row['is_active']) ? (int)$row['is_active'] : 1,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'nama_kelas'      => 'required|string|max:255',
            'jurusan_id'      => 'required|exists:jurusans,id',
            'wali_kelas_id'   => 'nullable|exists:guru_staf,id',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajarans,id',
            'is_active'       => 'nullable|in:0,1',
        ];
    }
}