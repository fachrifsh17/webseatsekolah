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

class GuruMapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $filters;
    private $rowNumber = 0;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->query->with(['guru', 'mapel', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']);
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID GURU',
            'NAMA GURU',
            'NIP',
            'MATA PELAJARAN',
            'TIPE',
            'KELAS',
            'HARI',
            'URUTAN JAM',
            'STATUS'
        ];
    }

    public function map($item): array
    {
        $this->rowNumber++;
        $jamKeMulai = $item->jamMulai->jam_ke ?? '-';
        $jamKeSelesai = $item->jamSelesai->jam_ke ?? '-';

        $jamFormatted = ($jamKeMulai !== '-' && $jamKeSelesai !== '-') 
            ? "{$jamKeMulai} - {$jamKeSelesai}"
            : $jamKeMulai;

        $tipeMapel = $item->mapel->tipe_mapel ?? '-';
        $tipeFormatted = ($tipeMapel === 'khusus') ? 'PRODUKTIF' : (($tipeMapel === 'umum') ? 'NORMATIF/ADAPTIF' : strtoupper($tipeMapel));

        return [
            $this->rowNumber,
            $item->guru->id ?? '-',
            strtoupper($item->guru->nama ?? '-'),
            "'" . ($item->guru->nip ?? '-'),
            strtoupper($item->mapel->nama_mapel ?? '-'),
            $tipeFormatted,
            strtoupper($item->kelas->nama_kelas ?? '-'),
            strtoupper($item->hari ?? '-'),
            $jamFormatted,
            'AKTIF'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'J';

        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, 
                    'color' => ['argb' => Color::COLOR_BLACK]
                ]
            ],
            'font' => ['size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);

        $sheet->getStyle("A12:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F12:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'J';
                $lastRow = $sheet->getHighestRow();

                // --- KOP SURAT ---
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A4:{$lastCol}5")->getFont()->setSize(9);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL LAPORAN ---
                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR PENUGASAN GURU MATA PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);

                // --- TAHUN PELAJARAN & SEMESTER ---
                // Mengambil semester dari filter yang dikirim controller
                $semester = isset($this->filters['semester']) ? strtoupper($this->filters['semester']) : '-';
                $ta = $this->filters['tahun_ajaran'] ?? '-';
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', "TAHUN PELAJARAN $ta - SEMESTER $semester");
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(11);

                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- BARIS FILTER ---
                $filterLine = "Filter: Guru (" . ($this->filters['guru'] ?? 'Semua') . ") | " .
                              "Mapel (" . ($this->filters['mapel'] ?? 'Semua') . ") | " .
                              "Kelas (" . ($this->filters['kelas'] ?? 'Semua') . ") | " .
                              "Status (Aktif)";

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', $filterLine);
                $sheet->getStyle('A9')->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- FOOTER ---
                $footerRow = $lastRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}