<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class OrangtuaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $queryBuilder, $profil, $kontak, $kelasData, $filters;

    /**
     * Menambahkan parameter $filters untuk menangkap inputan filter
     */
    public function __construct($queryBuilder, $profil, $kontak, $kelasData = null, $filters = [])
    {
        $this->queryBuilder = $queryBuilder;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->kelasData = $kelasData;
        $this->filters = $filters;
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->queryBuilder;
    }

    public function headings(): array
    {
        return [
            'ID Orang Tua',
            'Nama Lengkap',
            'No. Telepon',
            'Data Anak (Nama - Kelas)',
            'Status Aktif'
        ];
    }

    public function map($orangtua): array
    {
        $daftarAnak = $orangtua->anak->map(function($anak) {
            $namaKelas = $anak->kelas->nama_kelas ?? '-';
            return "{$anak->nama_lengkap} ({$namaKelas})";
        })->implode(', ');

        return [
            $orangtua->id,
            $orangtua->nama_lengkap,
            "'" . $orangtua->telepon,
            $daftarAnak,
            $orangtua->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'E';

        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);

        $sheet->getStyle("D12:D{$lastRow}")->getAlignment()->setWrapText(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'E';

                // --- KOP SURAT ---
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL ---
                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DATA ORANG TUA / WALI MURID');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- INFORMASI FILTER DINAMIS ---
                $filterInfo = [];
                $filterInfo[] = "Kelas: " . ($this->kelasData ? $this->kelasData->nama_kelas : 'Semua Kelas');
                
                if (!empty($this->filters['q'])) {
                    $filterInfo[] = "Pencarian: " . $this->filters['q'];
                }
                
                // Cek status aktif (default 1 jika tidak ada di filter)
                $activeStatus = $this->filters['is_active'] ?? '1';
                $filterInfo[] = "Status: " . ($activeStatus == '1' ? 'Aktif' : 'Non-Aktif');

                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', implode(' | ', $filterInfo));
                $sheet->getStyle('A8')->getFont()->setItalic(true);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // --- TANGGAL EXPORT ---
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "Tanggal Cetak: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}