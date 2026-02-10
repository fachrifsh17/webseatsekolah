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
use Carbon\Carbon;

class JamSekolahImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    public array $importMessages = [];
    private int $rows = 0;

    public function model(array $row)
    {
        $this->rows++;
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            $this->importMessages[] = "Baris {$this->rows}: Tidak ada Tahun Ajaran yang aktif.";
            return null;
        }

        // Format waktu agar seragam sebelum dicek
        $mulai = Carbon::parse($row['waktu_mulai'])->format('H:i:s');

        // PERBAIKAN: Cek duplikasi berdasarkan Hari DAN Waktu Mulai
        // Ini supaya Istirahat 1 dan Istirahat 2 yang jam_ke nya sama-sama NULL tetap bisa masuk
        $exists = JamSekolah::where([
            'tahun_ajaran_id' => $tahunAktif->id,
            'hari'            => $row['hari'],
            'waktu_mulai'     => $mulai,
        ])->exists();

        if ($exists) {
            $this->importMessages[] = "Baris {$this->rows}: Jadwal hari {$row['hari']} jam {$row['waktu_mulai']} sudah terdaftar.";
            return null;
        }

        return DB::transaction(function () use ($row, $tahunAktif, $mulai) {
            // Logika Custom ID JM001
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
                'jam_ke'          => $row['jam_ke'] ?? null,
                'waktu_mulai'     => $mulai,
                'waktu_selesai'   => Carbon::parse($row['waktu_selesai'])->format('H:i:s'),
                'jenis'           => ucfirst(strtolower($row['jenis'])), 
                'keterangan'      => $row['keterangan'] ?? null,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'hari'          => ['required', Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'])],
            'jam_ke'        => 'nullable',
            'waktu_mulai'   => 'required',
            'waktu_selesai' => 'required',
            'jenis'         => ['required', Rule::in(['Pelajaran', 'Istirahat', 'Kegiatan', 'pelajaran', 'istirahat', 'kegiatan'])],
        ];
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}