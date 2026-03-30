<?php

namespace App\Imports;

use App\Models\JamSekolah;
use App\Models\Semester;
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
    private $semesterId;
    private array $processedInFile = [];

    public function __construct($semesterId = null)
    {
        $this->semesterId = $semesterId;
    }

    public function model(array $row)
    {
        $this->rows++;

        $semesterId = $this->semesterId;
        if (!$semesterId) {
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $semesterAktif ? $semesterAktif->id : null;
        }

        if (!$semesterId) {
            $this->importMessages[] = "Baris {$this->rows}: Tidak ada Semester yang aktif.";
            return null;
        }

        try {
            $mulai = Carbon::parse($row['waktu_mulai'])->format('H:i:s');
            $selesai = Carbon::parse($row['waktu_selesai'])->format('H:i:s');
        } catch (\Exception $e) {
            $this->importMessages[] = "Baris {$this->rows}: Format waktu tidak valid.";
            return null;
        }

        if ($selesai <= $mulai) {
            $this->importMessages[] = "Baris {$this->rows}: Waktu selesai ({$row['waktu_selesai']}) harus lebih besar dari waktu mulai.";
            return null;
        }

        if (!empty($row['jam_ke'])) {
            $keyJamKe = $row['hari'] . '_jam_' . $row['jam_ke'];
            if (isset($this->processedInFile[$keyJamKe])) {
                $this->importMessages[] = "Baris {$this->rows}: Jam ke-{$row['jam_ke']} pada hari {$row['hari']} duplikat dalam file Excel.";
                return null;
            }

            $existsJamKe = JamSekolah::where([
                'semester_id' => $semesterId,
                'hari'        => $row['hari'],
                'jam_ke'      => $row['jam_ke'],
            ])->exists();

            if ($existsJamKe) {
                $this->importMessages[] = "Baris {$this->rows}: Jam ke-{$row['jam_ke']} pada hari {$row['hari']} sudah terdaftar.";
                return null;
            }
            $this->processedInFile[$keyJamKe] = true;
        }

        if (isset($this->processedInFile['waktu'][$row['hari']])) {
            foreach ($this->processedInFile['waktu'][$row['hari']] as $time) {
                if ($mulai < $time['selesai'] && $selesai > $time['mulai']) {
                    $this->importMessages[] = "Baris {$this->rows}: Waktu bertabrakan dengan baris lain di hari yang sama dalam file.";
                    return null;
                }
            }
        }

        $overlap = JamSekolah::where('semester_id', $semesterId)
            ->where('hari', $row['hari'])
            ->where(function ($query) use ($mulai, $selesai) {
                $query->where('waktu_mulai', '<', $selesai)
                      ->where('waktu_selesai', '>', $mulai);
            })->exists();

        if ($overlap) {
            $this->importMessages[] = "Baris {$this->rows}: Waktu ({$row['waktu_mulai']} - {$row['waktu_selesai']}) bertabrakan dengan jadwal lain di database.";
            return null;
        }

        $this->processedInFile['waktu'][$row['hari']][] = ['mulai' => $mulai, 'selesai' => $selesai];

        return DB::transaction(function () use ($row, $semesterId, $mulai, $selesai) {
            $lastJam = JamSekolah::where('id', 'like', 'JM%')
                ->orderByRaw('CAST(SUBSTRING(id, 3) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastId = $lastJam ? (int) substr($lastJam->id, 2) : 0;
            $newId = 'JM' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            return new JamSekolah([
                'id'              => $newId,
                'semester_id'     => $semesterId,
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
            'jenis'         => ['required', Rule::in(['Pelajaran', 'Istirahat', 'Kegiatan', 'pelajaran', 'istirahat', 'kegiatan', 'Upacara', 'upacara'])],
        ];
    }

    public function getMessages(): array
    {
        return $this->importMessages;
    }
}