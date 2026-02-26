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
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function view(): View
    {
        $data = $this->query->get();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();

        return view('exports.jadwal_mapel_kelas_pdf', [
            'profil'     => $this->profil,
            'kontak'     => $this->kontak,
            'tahun'      => $tahunAktif, 
            'data'       => $data,
            'kelas'      => $this->filters['kelas'] ?? 'Semua Kelas',
            'hari'       => $this->filters['hari'] ?? '-',
            'kategori'   => $this->filters['kategori_mapel'] ?? 'Semua',
            
            'kepsek'     => $this->filters['kepsek'] ?? '...........................',
            'nipKepsek'  => $this->filters['nipKepsek'] ?? '...........................',
            
            'wakaKur'    => $this->filters['wakaKur'] ?? '...........................',
            'nipWakaKur' => $this->filters['nipWakaKur'] ?? '...........................',
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