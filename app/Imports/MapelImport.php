<?php

namespace App\Imports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\DB;

class MapelImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $access;

    public function __construct($access)
    {
        $this->access = $access;
    }

    public function model(array $row)
    {
        $namaMapel = trim($row['nama_mata_pelajaran']);
        $jurusanNama = $row['jurusan'] ?? null;
        $jurusanId = null;

        if (!empty($jurusanNama) && strtoupper($jurusanNama) !== 'UMUM') {
            // Menambahkan pengecekan is_active pada Jurusan
            $jurusan = Jurusan::where(function($query) use ($jurusanNama) {
                    $query->where('nama_jurusan', 'LIKE', '%' . $jurusanNama . '%')
                          ->orWhere('id', $jurusanNama);
                })
                ->where('is_active', 1) // Hanya yang aktif
                ->first();

            $jurusanId = $jurusan ? $jurusan->id : null;
        }

        // Cek duplikasi Mapel berdasarkan nama dan jurusan (aktif/tidak tetap dicek agar tidak ganda)
        $exists = MataPelajaran::where('nama_mapel', $namaMapel)
            ->where('jurusan_id', $jurusanId)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($namaMapel, $jurusanId, $row) {
            $lastMapel = MataPelajaran::where('id', 'like', 'M%')
                ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            $lastId = $lastMapel ? (int) substr($lastMapel->id, 1) : 0;
            $newId = 'M' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

            return new MataPelajaran([
                'id'             => $newId,
                'nama_mapel'     => $namaMapel,
                'jurusan_id'     => $jurusanId,
                'tipe_mapel'     => strtolower($row['tipe_mapel'] ?? $row['tipe'] ?? 'umum'),
                'kategori_mapel' => strtolower($row['kategori_mapel'] ?? $row['kategori'] ?? 'adaptif'),
                'is_active'      => 1,
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'nama_mata_pelajaran' => 'required|string',
            'jurusan'             => 'nullable|string',
        ];
    }
}