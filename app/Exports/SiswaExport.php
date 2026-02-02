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

class SiswaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            'NIS',
            'NISN',
            'Nama Lengkap',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Kelas',
            'Status Aktif',
            'No Telp Siswa',
            'Alamat'
        ];
    }

    public function map($siswa): array
    {
        return [
            "'" . $siswa->nis,
            "'" . $siswa->nisn,
            $siswa->nama_lengkap,
            $siswa->tempat_lahir,
            $siswa->tanggal_lahir,
            $siswa->jenis_kelamin,
            $siswa->kelas->nama_kelas ?? '-',
            $siswa->is_active ? 'Aktif' : 'Tidak Aktif',
            $siswa->no_telp_siswa,
            $siswa->alamat,
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
            $status = $sheet->getCell("H{$row}")->getValue();
            if ($status == 'Aktif') {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }

        $sheet->getStyle("A1:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }
}