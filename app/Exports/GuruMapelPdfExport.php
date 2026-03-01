<?php

namespace App\Exports;

use App\Models\TahunAjaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Support\Facades\DB; // Tambahkan ini

class GuruMapelPdfExport implements FromView, WithEvents
{
    use Exportable;

    protected $profil, $kontak, $query, $filters;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function view(): View
    {
        $data = $this->query->get();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        // 1. Ambil data TTD Kepala Sekolah dari struktur_jabatan langsung
        $ksData = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 1) 
            ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
            ->first();

        // 2. Ambil data TTD Waka dari struktur_jabatan langsung
        $wakaData = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 2) 
            ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
            ->first();

        return view('exports.jadwal_mapel_kelas_pdf', [
            'profil'     => $this->profil,
            'kontak'     => $this->kontak,
            'tahun'      => $tahunAktif, 
            'data'       => $data,
            'kelas'      => $this->filters['kelas'] ?? 'Semua Kelas',
            'hari'       => $this->filters['hari'] ?? '-',
            'kategori'   => $this->filters['kategori_mapel'] ?? 'Semua',
            
            // Gunakan data dari query join, atau fallback ke nilai default jika null
            'kepsek'     => $ksData->nama ?? ($this->filters['kepsek'] ?? '...........................'),
            'nipKepsek'  => $ksData->nip ?? ($this->filters['nipKepsek'] ?? '...........................'),
            'ttdKepsek'  => $ksData->file_ttd ?? null, // Tambahkan ini
            
            'wakaKur'    => $wakaData->nama ?? ($this->filters['wakaKur'] ?? '...........................'),
            'nipWakaKur' => $wakaData->nip ?? ($this->filters['nipWakaKur'] ?? '...........................'),
            'ttdWakaKur' => $wakaData->file_ttd ?? null, // Tambahkan ini
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);

                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.5);
                $sheet->getPageMargins()->setLeft(0.5);
                $sheet->getPageMargins()->setBottom(0.75); 
            },
        ];
    }
}