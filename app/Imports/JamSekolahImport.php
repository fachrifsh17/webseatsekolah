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

        // Format waktu agar seragam
        $mulai = Carbon::parse($row['waktu_mulai'])->format('H:i:s');
        $selesai = Carbon::parse($row['waktu_selesai'])->format('H:i:s');

        // 1. VALIDASI: Waktu Selesai harus lebih besar dari Waktu Mulai
        if ($selesai <= $mulai) {
            $this->importMessages[] = "Baris {$this->rows}: Waktu selesai ({$row['waktu_selesai']}) harus lebih besar dari waktu mulai.";
            return null;
        }

        // 2. VALIDASI: Cek duplikasi Jam Ke (jika tidak null)
        if (!empty($row['jam_ke'])) {
            $existsJamKe = JamSekolah::where([
                'tahun_ajaran_id' => $tahunAktif->id,
                'hari'            => $row['hari'],
                'jam_ke'          => $row['jam_ke'],
            ])->exists();

            if ($existsJamKe) {
                $this->importMessages[] = "Baris {$this->rows}: Jam ke-{$row['jam_ke']} pada hari {$row['hari']} sudah terdaftar.";
                return null;
            }
        }

        // 3. VALIDASI: Cek Tabrakan Waktu (Overlap)
        // Logika: (Mulai_Baru < Selesai_DB) DAN (Selesai_Baru > Mulai_DB)
        $overlap = JamSekolah::where('tahun_ajaran_id', $tahunAktif->id)
            ->where('hari', $row['hari'])
            ->where(function ($query) use ($mulai, $selesai) {
                $query->where('waktu_mulai', '<', $selesai)
                      ->where('waktu_selesai', '>', $mulai);
            })->exists();

        if ($overlap) {
            $this->importMessages[] = "Baris {$this->rows}: Waktu ({$row['waktu_mulai']} - {$row['waktu_selesai']}) bertabrakan dengan jadwal lain di hari {$row['hari']}.";
            return null;
        }

        return DB::transaction(function () use ($row, $tahunAktif, $mulai, $selesai) {
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
                'waktu_selesai'   => $selesai,
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