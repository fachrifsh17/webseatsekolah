<?php

namespace App\Exports;

use App\Models\GuruStaf;
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

class GuruExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filters;

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $search  = $this->filters['q'] ?? null;
        $jabatan = $this->filters['jabatan_fungsional'] ?? null;
        $status  = $this->filters['status_kepegawaian'] ?? null;
        $jurusan = $this->filters['jurusan_id'] ?? null;
        $active  = $this->filters['is_active'] ?? null;

        return GuruStaf::query()
            ->with(['jurusan'])
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%") // Menggunakan 'nama'
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('nuptk', 'like', "%{$search}%");
                });
            })
            ->when($jabatan, fn($q) => $q->where('jabatan_fungsional', $jabatan)) // Tambah filter jabatan
            ->when($status, fn($q) => $q->where('status_kepegawaian', $status))   // Tambah filter status
            ->when($jurusan, fn($q) => $q->where('jurusan_id', $jurusan))
            ->when(isset($active), fn($q) => $q->where('is_active', $active));
    }

    public function headings(): array
    {
        return [
            'NIP',
            'NUPTK',
            'Nama Lengkap',
            'Jabatan Fungsional',
            'Status Kepegawaian',
            'Jurusan',
            'Status Aktif'
        ];
    }

    public function map($guru): array
    {
        return [
            "'" . $guru->nip,
            "'" . $guru->nuptk,
            $guru->nama, // Sesuai kolom database
            $guru->jabatan_fungsional,
            $guru->status_kepegawaian,
            $guru->jurusan->nama_jurusan ?? '-',
            $guru->is_active ? 'Aktif' : 'Non-Aktif',
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

        // Tambahkan logic warna seperti di SiswaExport (Kolom G)
        for ($row = 2; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("G{$row}")->getValue();
            if ($status == 'Aktif') {
                $sheet->getStyle("G{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("G{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }
        
        $sheet->getStyle("A1:G{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }
}