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
        // Ambil data sesuai filter yang sudah di-apply di Controller
        $data = $this->query->get();

        return view('exports.jadwal_mapel_kelas_pdf', [
            'profil'        => $this->profil,
            'kontak'        => $this->kontak,
            'data'          => $data,
            // Mengambil semester_nama (Hasil gabungan Semester + TA)
            'semester_nama' => $this->filters['semester_nama'] ?? 
                               (($this->filters['semester'] ?? '-') . ' ' . ($this->filters['tahun_ajaran'] ?? '')),
            'kelas'         => $this->filters['kelas'] ?? 'Semua Kelas',
            'hari'          => $this->filters['hari'] ?? 'SEMUA HARI',
            'kategori'      => $this->filters['kategori_mapel'] ?? 'Semua',
            'wali'          => $this->filters['wali'] ?? '...........................',
            'nipWali'       => $this->filters['nipWali'] ?? '...........................',
            'kepsek'        => $this->filters['kepsek'] ?? '...........................',
            'nipKepsek'     => $this->filters['nipKepsek'] ?? '...........................',
            'fileTtdKepsek' => $this->filters['fileTtdKepsek'] ?? null,
            'wakaKur'       => $this->filters['wakaKur'] ?? '...........................',
            'nipWakaKur'    => $this->filters['nipWakaKur'] ?? '...........................',
            'fileTtdWaka'   => $this->filters['fileTtdWaka'] ?? null,
        ]);
    }
}