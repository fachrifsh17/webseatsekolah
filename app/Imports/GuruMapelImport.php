<?php

namespace App\Imports;

use App\Models\GuruMapel;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class GuruMapelImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        $tahunAjaranId = $row['id_tahun_ajaran'] ?? ($tahunAktif ? $tahunAktif->id : null);

        return new GuruMapel([
            'guru_staf_id'      => $row['id_guru'],
            'mata_pelajaran_id' => $row['id_mapel'],
            'kelas_id'          => $row['id_kelas'],
            'tahun_ajaran_id'   => $tahunAjaranId,
            'hari'              => $row['hari'],
            'jam_mulai_id'      => $row['id_jam_mulai'],
            'jam_selesai_id'    => $row['id_jam_selesai'],
        ]);
    }

    public function rules(): array
    {
        return [
            'id_guru'         => 'required|exists:guru_staf,id',
            'id_mapel'        => 'required|exists:mata_pelajarans,id',
            'id_kelas'        => 'required|exists:kelas,id',
            'hari'            => 'required|string',
            'id_jam_mulai'    => 'required|exists:jam_sekolahs,id',
            'id_jam_selesai'  => 'required|exists:jam_sekolahs,id',
            'id_tahun_ajaran' => 'nullable|exists:tahun_ajarans,id',
        ];
    }

    public function customValidationMessages()
    {
        return [
            '*.exists'      => 'Data ID (Guru/Mapel/Kelas/Jam) tidak terdaftar di sistem.',
            'hari.required' => 'Kolom hari wajib diisi.',
        ];
    }
}