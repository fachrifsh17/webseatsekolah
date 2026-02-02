<?php

namespace App\Imports;

use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class JamSekolahImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        return new JamSekolah([
            'hari'            => $row['hari'],
            'jam_ke'          => $row['jam_ke'],
            'waktu_mulai'     => $row['waktu_mulai'],
            'waktu_selesai'   => $row['waktu_selesai'],
            'keterangan'      => $row['keterangan'] ?? null,
            'tahun_ajaran_id' => $tahunAktif->id ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'hari'          => 'required|string',
            'jam_ke'        => 'required|numeric',
            'waktu_mulai'   => 'required',
            'waktu_selesai' => 'required',
        ];
    }
}