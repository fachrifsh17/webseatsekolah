<?php

namespace App\Exports;

use App\Models\MataPelajaran;
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

class MapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;

    public function __construct(array $filters = [], $profil = null, $kontak = null)
    {
        $this->filters = array_merge([
            'jurusan_id'     => null,
            'search'         => null,
            'tipe_mapel'     => null,
            'kategori_mapel' => null,
        ], $filters);
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        $query = MataPelajaran::query()->with('jurusan');

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['search'])) {
            $query->where('nama_mapel', 'like', '%' . $this->filters['search'] . '%');
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
            'ID',
            'Nama Mata Pelajaran',
            'Jurusan',
            'Tipe Mapel',
            'Kategori Mapel'
        ];
    }

    public function map($mapel): array
    {
        return [
            $mapel->id,
            $mapel->nama_mapel,
            $mapel->jurusan ? $mapel->jurusan->nama_jurusan : 'Umum (Semua Jurusan)',
            ucfirst($mapel->tipe_mapel),
            ucfirst($mapel->kategori_mapel),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'E';

        // Header Tabel di Baris 11
        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF5B9BD5']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);

        // Pewarnaan Tipe Mapel mulai baris 12
        for ($row = 12; $row <= $lastRow; $row++) {
            $tipe = $sheet->getCell("D{$row}")->getValue();
            
            if ($tipe == 'Produktif') {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFC00000');
            } elseif ($tipe == 'Normatif' || $tipe == 'Adaptif') {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF0070C0');
            }
        }

        // Border Tabel
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'E';

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
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DAFTAR MATA PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A9', "Tanggal Export: " . date('d/m/Y H:i'));
            },
        ];
    }
}