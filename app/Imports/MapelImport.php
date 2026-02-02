<?php

namespace App\Imports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MapelImport implements ToModel, WithHeadingRow
{
    protected $access;

    public function __construct($access)
    {
        $this->access = $access;
    }

    public function model(array $row)
    {
        $jurusanId = null;
        $namaMapel = $row['nama_mata_pelajaran'];
        $kategori  = $row['kategori_mapel'] ?? 'adaptif';
        $tipe      = $row['tipe_mapel'] ?? 'umum';

        if (!$this->access['isFullAccess']) {
            $jurusanId = $this->access['guruStaf']->jurusan_id;
            $kategori  = 'produktif';
            $tipe      = 'khusus';
        } else {
            if (!empty($row['jurusan']) && strtolower($row['jurusan']) !== 'umum') {
                $jurusan = Jurusan::where('nama_jurusan', $row['jurusan'])->first();
                $jurusanId = $jurusan ? $jurusan->id : null;
            }
        }

        $exists = MataPelajaran::where('nama_mapel', $namaMapel)
            ->where('jurusan_id', $jurusanId)
            ->exists();

        if ($exists) {
            return null;
        }

        return new MataPelajaran([
            'nama_mapel'     => $namaMapel,
            'jurusan_id'     => $jurusanId,
            'tipe_mapel'     => $tipe,
            'kategori_mapel' => $kategori,
        ]);
    }
}