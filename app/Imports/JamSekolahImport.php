<?php

namespace App\Imports;

use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class JamSekolahImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return null;
        }

        $exists = JamSekolah::where([
            'tahun_ajaran_id' => $tahunAktif->id,
            'hari'            => $row['hari'],
            'jam_ke'          => $row['jam_ke'],
        ])->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($row, $tahunAktif) {
            $lastJam = JamSekolah::where('id', 'like', 'JM%')
                ->orderByRaw('CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastId = $lastJam ? (int) substr($lastJam->id, 2) : 0;
            $newId = 'JM' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            return new JamSekolah([
                'id'              => $newId,
                'tahun_ajaran_id' => $tahunAktif->id,
                'hari'            => $row['hari'],
                'jam_ke'          => $row['jam_ke'],
                'waktu_mulai'     => $row['waktu_mulai'],
                'waktu_selesai'   => $row['waktu_selesai'],
                'jenis'           => $row['jenis'] ?? 'Pelajaran',
                'keterangan'      => $row['keterangan'] ?? null,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'hari'          => ['required', Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'])],
            'jam_ke'        => 'nullable|numeric',
            'waktu_mulai'   => 'required',
            'waktu_selesai' => 'required',
            'jenis'         => ['required', Rule::in(['Pelajaran', 'Istirahat', 'Kegiatan'])],
        ];
    }
}