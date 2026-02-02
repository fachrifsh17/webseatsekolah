<?php

namespace App\Exports;

use App\Models\Orangtua;
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

class OrangtuaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $search = $this->filters['q'] ?? null;

        return Orangtua::query()
            ->with(['anak'])
            ->when($search, function ($query, $search) {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('telepon', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
            });
    }

    public function headings(): array
    {
        return [
            'ID Orang Tua',
            'Nama Lengkap',
            'No. Telepon',
            'Daftar Anak (NIS)',
            'Status Aktif'
        ];
    }

    public function map($orangtua): array
    {
        return [
            $orangtua->id,
            $orangtua->nama_lengkap,
            "'" . $orangtua->telepon,
            $orangtua->anak->pluck('nis')->implode(', '),
            $orangtua->is_active ? 'Aktif' : 'Non-Aktif',
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
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $lastRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("E{$row}")->getValue();
            if ($status == 'Aktif') {
                $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("E{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }
        
        $sheet->getStyle("A1:E{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }
}