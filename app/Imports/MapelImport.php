<?php

namespace App\Imports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;

class MapelImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $access;
    public $importMessages = [];

    public function __construct($access)
    {
        $this->access = $access;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $namaMapel = trim($row['nama_mata_pelajaran']);
            $jurusanNama = trim($row['jurusan'] ?? '');
            $jurusanId = null;

            if (!empty($jurusanNama) && strtoupper($jurusanNama) !== 'UMUM') {
                $jurusan = Jurusan::where(function($query) use ($jurusanNama) {
                        $query->where('nama_jurusan', 'LIKE', '%' . $jurusanNama . '%')
                              ->orWhere('id', $jurusanNama);
                    })
                    ->where('is_active', 1)
                    ->first();

                if (!$jurusan) {
                    $this->importMessages[] = "Baris {$line}: Jurusan '{$jurusanNama}' tidak ditemukan atau tidak aktif.";
                    continue;
                }
                
                $jurusanId = $jurusan->id;
            }

            $exists = MataPelajaran::where('nama_mapel', $namaMapel)
                ->where(function($q) use ($jurusanId) {
                    return $jurusanId ? $q->where('jurusan_id', $jurusanId) : $q->whereNull('jurusan_id');
                })->exists();

            if ($exists) {
                $this->importMessages[] = "Baris {$line}: Mata Pelajaran '{$namaMapel}' sudah terdaftar.";
                continue;
            }

            try {
                DB::transaction(function () use ($namaMapel, $jurusanId, $row) {
                    $lastMapel = MataPelajaran::where('id', 'like', 'M%')
                        ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                        ->lockForUpdate()
                        ->first();

                    $lastId = $lastMapel ? (int) substr($lastMapel->id, 1) : 0;
                    $newId = 'M' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                    MataPelajaran::create([
                        'id'             => $newId,
                        'nama_mapel'     => $namaMapel,
                        'jurusan_id'     => $jurusanId,
                        'tipe_mapel'     => strtolower(trim($row['tipe_mapel'] ?? $row['tipe'] ?? 'umum')),
                        'kategori_mapel' => strtolower(trim($row['kategori_mapel'] ?? $row['kategori'] ?? 'adaptif')),
                        'is_active'      => 1,
                    ]);
                });
            } catch (\Exception $e) {
                $this->importMessages[] = "Baris {$line}: Gagal menyimpan data. " . $e->getMessage();
            }
        }
    }

    public function rules(): array
    {
        return [
            'nama_mata_pelajaran' => 'required|string',
            'jurusan'             => 'nullable|string',
        ];
    }

    public function getMessages()
    {
        return $this->importMessages;
    }
}