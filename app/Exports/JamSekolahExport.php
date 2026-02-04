<?php

namespace App\Exports;

use App\Models\JamSekolah;
use Maatwebsite\Excel\Concerns\FromCollection;
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

class JamSekolahExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $profil, $kontak;

    public function __construct($profil, $kontak)
    {
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string { return 'A11'; }

    public function collection()
    {
        return JamSekolah::with('tahunAjaran')->get();
    }

    public function headings(): array
    {
        return ['ID', 'HARI', 'JAM KE', 'MULAI', 'SELESAI', 'TAHUN AJARAN', 'KETERANGAN'];
    }

    public function map($jam): array
    {
        return [
            $jam->id,
            $jam->hari,
            $jam->jam_ke,
            $jam->waktu_mulai,
            $jam->waktu_selesai,
            $jam->tahunAjaran->tahun_ajaran ?? '-',
            $jam->keterangan ?? '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        // Header Tabel (Baris 11)
        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        // Border untuk seluruh isi tabel
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Center alignment untuk kolom ID, Jam Ke, dan Waktu
        $sheet->getStyle("A12:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C12:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'G';

                // Kop Surat
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // Judul Laporan
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DAFTAR ALOKASI WAKTU / JAM PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A9', "Tanggal Unduh: " . date('d/m/Y H:i'));
            },
        ];
    }
}