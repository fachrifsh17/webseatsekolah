<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;

class KelasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
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
            $kelas->waliKelas->nama ?? '-', // Sesuaikan dengan kolom nama di tabel guru/wali
            $kelas->tahunAjaran->tahun_ajaran ?? '-',
            $kelas->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ]);

        $lastRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("F{$row}")->getValue(); // Kolom F adalah Status Aktif
            if ($status == 'Aktif') {
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("F{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }

        $sheet->getStyle("A1:F{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }
}