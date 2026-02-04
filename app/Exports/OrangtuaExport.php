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
    protected $queryBuilder, $profil, $kontak, $kelasData;

    /**
     * Menerima Query Builder, Profil, Kontak, dan Objek Kelas (opsional)
     */
    public function __construct($queryBuilder, $profil, $kontak, $kelasData = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->kelasData = $kelasData;
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
        /** * Karena di Controller query relasi 'anak' sudah difilter:
         * with(['anak' => fn($q) => $q->where('kelas_id', $kelasId)])
         * Maka $orangtua->anak di sini hanya akan berisi anak yang sesuai filter kelas.
         */
        $daftarAnak = $orangtua->anak->map(function($anak) {
            $namaKelas = $anak->kelas->nama_kelas ?? '-';
            return "{$anak->nama_lengkap} ({$namaKelas})";
        })->implode(', ');

        return [
            $orangtua->id,
            $orangtua->nama_lengkap,
            "'" . $orangtua->telepon, // Melindungi angka nol di depan
            $daftarAnak,
            $orangtua->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'E';

        // Header Tabel Style
        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Border & Alignment untuk seluruh data
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);

        // Wrap Text khusus kolom Data Anak
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
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? 'Jl. Pendidikan No. 55') . " | Telp: " . ($this->kontak->telepon ?? '0265-119382'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? 'info@sekolahkita.sch.id') . " | NPSN: " . ($this->profil->npsn ?? '20251234'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL LAPORAN ---
                $sheet->mergeCells("A7:{$lastCol}8"); 
                $sheet->setCellValue('A7', 'DATA ORANG TUA / WALI MURID');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // --- INFORMASI FILTER & TANGGAL ---
                $labelKelas = $this->kelasData ? $this->kelasData->nama_kelas : 'Semua Kelas';
                $sheet->setCellValue('A9', "Kelas: " . $labelKelas);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                
                $sheet->setCellValue('A10', "Tanggal Export: " . Carbon::now()->format('d/m/Y H:i'));
            },
        ];
    }
}