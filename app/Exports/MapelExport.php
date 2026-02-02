<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = array_merge([
            'jurusan_id'     => null,
            'search'         => null,
            'tipe_mapel'     => null,
            'kategori_mapel' => null,
        ], $filters);
    }

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
        $sheet->getStyle('1')->applyFromArray([
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

        $lastRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $lastRow; $row++) {
            $tipe = $sheet->getCell("D{$row}")->getValue();
            
            if ($tipe == 'Produktif') {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FFC00000');
            } elseif ($tipe == 'Normatif' || $tipe == 'Adaptif') {
                $sheet->getStyle("D{$row}")->getFont()->getColor()->setARGB('FF0070C0');
            }
        }

        $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
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
}