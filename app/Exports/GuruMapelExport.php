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
        // Eager load relasi jamMulai dan jamSelesai untuk mengambil kolom 'jam_ke'
        return $this->query->with(['guru', 'mapel', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']);
    }

    public function headings(): array
    {
        return [
            'ID Guru',
            'Nama Guru',
            'NIP',
            'Mata Pelajaran',
            'Kelas',
            'Hari',
            'Urutan Jam Pelajaran', // Header lebih relevan
            'Tahun Ajaran',
            'Status'
        ];
    }

    public function map($item): array
    {
        // Mengambil nilai jam_ke dari relasi tabel jam_sekolah
        $jamKeMulai = $item->jamMulai->jam_ke ?? '-';
        $jamKeSelesai = $item->jamSelesai->jam_ke ?? '-';

        // Format: Jam Ke 1 - 3
        $jamFormatted = ($jamKeMulai !== '-' && $jamKeSelesai !== '-') 
            ? "Jam Ke {$jamKeMulai} - {$jamKeSelesai}"
            : "Jam Ke {$jamKeMulai}";

        return [
            $item->guru->id ?? '-',
            $item->guru->nama ?? '-',
            "'" . ($item->guru->nip ?? '-'),
            $item->mapel->nama_mapel ?? '-',
            $item->kelas->nama_kelas ?? '-',
            $item->hari ?? '-',
            $jamFormatted, // Hasil: Jam Ke 1 - 3
            $item->tahunAjaran->nama ?? '-', 
            'Aktif'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'I';

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
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_LEFT
            ]
        ]);

        // Tengahkan kolom ID, Hari, Jam, dan Status agar rapi
        $sheet->getStyle("A12:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F12:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'I';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'JADWAL MENGAJAR GURU MATA PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $filterInfo = [];
                if (!empty($this->filters['q'])) { $filterInfo[] = "Pencarian: " . $this->filters['q']; }
                $filterInfo[] = "Guru: " . ($this->filters['guru'] ?? 'Semua');
                $filterInfo[] = "Kelas: " . ($this->filters['kelas'] ?? 'Semua');
                $filterInfo[] = "Mapel: " . ($this->filters['mapel'] ?? 'Semua');
                $filterInfo[] = "TA: " . ($this->filters['tahun_ajaran'] ?? 'Semua');
                if (!empty($this->filters['hari'])) { $filterInfo[] = "Hari: " . $this->filters['hari']; }

                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', implode(' | ', $filterInfo));
                $sheet->getStyle('A8')->getFont()->setItalic(true)->setSize(10);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "Tanggal Cetak: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}