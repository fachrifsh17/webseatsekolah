<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Carbon\Carbon;

class MapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string 
    { 
        return 'A12'; 
    }

    public function query()
    {
        $query = MataPelajaran::with(['jurusan']);

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', $this->filters['is_active']);
        }

        if (!empty($this->filters['search'])) {
            $query->where('nama_mapel', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['tipe_mapel'])) {
            $query->where('tipe_mapel', $this->filters['tipe_mapel']);
        }

        if (!empty($this->filters['kategori_mapel'])) {
            $query->where('kategori_mapel', $this->filters['kategori_mapel']);
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'ID MAPEL',
            'NAMA MATA PELAJARAN',
            'JURUSAN',
            'TIPE',
            'KATEGORI',
            'STATUS'
        ];
    }

    public function map($mapel): array
    {
        return [
            $mapel->id,
            $mapel->nama_mapel,
            $mapel->jurusan->nama_jurusan ?? 'UMUM',
            strtoupper($mapel->tipe_mapel),
            strtoupper($mapel->kategori_mapel),
            $mapel->is_active ? 'AKTIF' : 'NON-AKTIF',
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'F'; // Berubah ke F karena kolom NO dihapus
                $lastRow = $sheet->getHighestRow();

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR MATA PELAJARAN');
                $sheet->getStyle("A7")->getFont()->setBold(true)->setSize(12);
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $ta = TahunAjaran::where('is_active', 1)->first();
                $sheet->setCellValue('A8', 'TAHUN PELAJARAN ' . ($ta->nama ?? '-'));
                $sheet->getStyle("A8")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusanName = 'Semua Jurusan';
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $jurusan ? $jurusan->nama_jurusan : 'Semua Jurusan';
                }
                $tipeLabel = !empty($this->filters['tipe_mapel']) ? strtoupper($this->filters['tipe_mapel']) : 'SEMUA TIPE';
                $kategoriLabel = !empty($this->filters['kategori_mapel']) ? strtoupper($this->filters['kategori_mapel']) : 'SEMUA KATEGORI';
                $statusLabel = isset($this->filters['is_active']) ? ($this->filters['is_active'] ? 'AKTIF' : 'NON-AKTIF') : 'SEMUA STATUS';

                $filterText = "Filter: Jurusan ($jurusanName) | Tipe ($tipeLabel) | Kategori ($kategoriLabel) | Status ($statusLabel)";
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', $filterText);
                $sheet->getStyle("A9")->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A12:{$lastCol}12")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_BLACK]],
                    'fill' => ['fillType' => Fill::FILL_NONE],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A12:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => Color::COLOR_BLACK],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $footerRow = $lastRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);
            },
        ];
    }
}