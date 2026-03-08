<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;

class GuruMapelPdfExport implements FromView
{
    use Exportable;

    protected $query, $profil, $kontak, $filters;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function view(): View
    {
        // Eksekusi query untuk mendapatkan data
        $data = $this->query->get();

        return view('exports.jadwal_mapel_kelas_pdf', [
            'profil'        => $this->profil,
            'kontak'        => $this->kontak,
            'data'          => $data,
            
            // PEMISAHAN TA DAN SEMESTER (SINKRON DENGAN CONTROLLER)
            'semester'      => strtoupper($this->filters['semester'] ?? '-'),
            'tahun_ajaran'  => strtoupper($this->filters['tahun_ajaran'] ?? '-'),
            
            'kelas'         => $this->filters['kelas'] ?? 'Kelas Siswa',
            'hari'          => $this->filters['hari'] ?? 'SEMUA HARI',
            'kategori'      => strtoupper($this->filters['kategori'] ?? 'SEMUA KATEGORI'),
            
            // Status Aktif
            'status_aktif'  => $this->filters['status_aktif'] ?? 'TIDAK AKTIF',

            // Data Kepala Sekolah
            'kepsek'        => $this->filters['kepsek'] ?? $this->profil->nama_kepala_sekolah ?? '...........................',
            'nipKepsek'     => $this->filters['nipKepsek'] ?? $this->profil->nip_kepala_sekolah ?? '...........................',
            'fileTtdKepsek' => $this->filters['fileTtdKepsek'] ?? $this->profil->ttd_kepala_sekolah ?? null,

            // Data Waka Kurikulum
            'wakaKur'       => $this->filters['wakaKur'] ?? '...........................',
            'nipWakaKur'    => $this->filters['nipWakaKur'] ?? '...........................',
            'fileTtdWaka'   => $this->filters['fileTtdWaka'] ?? $this->profil->ttd_waka_kurikulum ?? null,
        ]);
    }
}