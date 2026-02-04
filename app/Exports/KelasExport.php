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

class KelasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak;

    public function __construct($query, $profil, $kontak)
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    // Menentukan data tabel dimulai dari baris ke-11
    public function startCell(): string 
    { 
        return 'A11'; 
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID Kelas',
            'Nama Kelas',
            'Jurusan',
            'Wali Kelas',
            'Tahun Ajaran',
            'Status Aktif'
        ];
    }

    public function map($kelas): array
    {
        return [
            $kelas->id,
            $kelas->nama_kelas,
            $kelas->jurusan->nama_jurusan ?? '-',
            $kelas->waliKelas->nama ?? '-',
            $kelas->tahunAjaran->tahun_ajaran ?? '-',
            $kelas->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        // Style untuk Header Tabel di Baris 11
        $sheet->getStyle('A11:F11')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Pewarnaan teks status Aktif (Hijau) / Tidak Aktif (Merah)
        for ($row = 12; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("F{$row}")->getValue();
            if ($status == 'Aktif') {
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }

        // Border untuk seluruh tabel
        $sheet->getStyle("A11:F{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'F';

                // --- BAGIAN KOP SURAT ---
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                // Styling Kop
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL LAPORAN ---
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DAFTAR KELAS');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A9', "Tanggal Cetak: " . date('d/m/Y H:i'));
            },
        ];
    }
}