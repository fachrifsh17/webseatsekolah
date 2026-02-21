<?php

namespace App\Exports;

use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents; // Tambahkan ini
use Maatwebsite\Excel\Events\AfterSheet;     // Tambahkan ini
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup; // Tambahkan ini

class JamSekolahPdfExport implements FromView, WithEvents // Tambahkan WithEvents
{
    use Exportable;

    protected $profil, $kontak, $tahunAjaranId;

    public function __construct($profil, $kontak, $tahunAjaranId)
    {
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->tahunAjaranId = $tahunAjaranId;
    }

    public function view(): View
    {
        $dataPerHari = JamSekolah::where('tahun_ajaran_id', $this->tahunAjaranId)
            ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
            ->orderBy('waktu_mulai')
            ->get()
            ->groupBy('hari');

        return view('exports.jam_sekolah_pdf', [
            'profil' => $this->profil,
            'kontak' => $this->kontak,
            'ta' => TahunAjaran::find($this->tahunAjaranId),
            'dataPerHari' => $dataPerHari,
            'hariList' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
        ]);
    }

    /**
     * INI ADALAH KUNCINYA
     * Fungsi ini akan memaksa DomPDF menggunakan mode Landscape
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // 1. Paksa orientasi Landscape
                $event->sheet->getDelegate()->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

                // 2. Set ukuran kertas ke A4
                $event->sheet->getDelegate()->getPageSetup()
                    ->setPaperSize(PageSetup::PAPERSIZE_A4);
                
                // 3. Opsional: Paksa agar tabel muat dalam satu halaman lebar
                $event->sheet->getDelegate()->getPageSetup()->setFitToWidth(1);
                $event->sheet->getDelegate()->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}