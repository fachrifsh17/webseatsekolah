<?php

namespace App\Exports;

use App\Models\TahunAjaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class GuruMapelPdfExport implements FromView, WithEvents
{
    use Exportable;

    protected $profil, $kontak, $query, $filters;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query; // Query Builder yang sudah difilter dari Controller
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function view(): View
    {
        // Mengambil data dari query builder yang dilempar oleh Controller
        $data = $this->query->get();

        // Mengambil Tahun Ajaran Aktif secara otomatis
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        // Pastikan nama view sinkron dengan file di folder resources/views/exports/
        return view('exports.jadwal_mapel_kelas_pdf', [
            'profil'     => $this->profil,
            'kontak'     => $this->kontak,
            'tahun'      => $tahunAktif, 
            'data'       => $data,
            'kelas'      => $this->filters['kelas'] ?? 'Semua Kelas',
            'hari'       => $this->filters['hari'] ?? '-',
            'kategori'   => $this->filters['kategori_mapel'] ?? 'Semua',
            
            // TANDA TANGAN OTOMATIS (Diambil dari array filters di Controller)
            'wali'       => $this->filters['wali'] ?? '...........................',
            'nipWali'    => $this->filters['nipWali'] ?? '...........................',
            
            'kepsek'     => $this->filters['kepsek'] ?? '...........................',
            'nipKepsek'  => $this->filters['nipKepsek'] ?? '...........................',
            
            'wakaKur'    => $this->filters['wakaKur'] ?? '...........................',
            'nipWakaKur' => $this->filters['nipWakaKur'] ?? '...........................',
        ]);
    }

    /**
     * Konfigurasi Page Setup untuk PDF/Excel
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set orientasi ke Landscape (Tidur)
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

                // Set ukuran kertas ke A4
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                
                // Auto-fit agar tabel tidak terpotong ke samping
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);

                // Menyesuaikan margin (dalam satuan inchi)
                // Memberikan ruang yang cukup di bawah agar tanda tangan tidak terpotong ke halaman baru
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.5);
                $sheet->getPageMargins()->setLeft(0.5);
                $sheet->getPageMargins()->setBottom(0.75); 
            },
        ];
    }
}