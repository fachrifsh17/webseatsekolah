<?php

namespace App\Imports;

use App\Models\GuruMapel;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Validation\Rule; // Tambahkan ini untuk rule custom

class GuruMapelImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public function model(array $row)
    {
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $tahunAjaranId = $row['id_tahun_ajaran'] ?? ($tahunAktif ? $tahunAktif->id : null);

        // Pengecekan duplikasi data sebelum insert
        $exists = GuruMapel::where([
            'guru_staf_id'      => $row['id_guru'],
            'mata_pelajaran_id' => $row['id_mapel'],
            'kelas_id'          => $row['id_kelas'],
            'tahun_ajaran_id'   => $tahunAjaranId,
            'hari'              => $row['hari'],
            'jam_mulai_id'      => $row['id_jam_mulai'],
        ])->exists();

        if ($exists) {
            return null;
        }

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
            'id_guru'  => 'required|exists:guru_staf,id',
            
            'id_mapel' => [
                'required',
                Rule::exists('mata_pelajarans', 'id')->where(function ($query) {
                    $query->where('is_active', 1);
                }),
            ],

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
            'id_mapel.exists' => 'Mata pelajaran tidak ditemukan atau sudah tidak aktif.',
            '*.exists'        => 'Data ID (:attribute) tidak ditemukan di database.',
            'required'        => 'Kolom :attribute wajib diisi.',
        ];
    }
}