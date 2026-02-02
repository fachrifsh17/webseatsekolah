<?php

namespace App\Exports;

use App\Models\JamSekolah;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JamSekolahExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
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
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2E75B6']
                ]
            ],
        ];
    }
}