<?php

namespace App\Exports;

use App\Models\GuruMapel;
use App\Models\ProfilSekolah;
use App\Models\KontakSekolah; // PASTIKAN BARIS INI ADA AGAR TIDAK EROR
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

class GuruMapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak;

    // Mengembalikan ke struktur constructor yang Anda minta
    public function __construct($query, $profil, $kontak)
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string 
    { 
        return 'A11'; 
    }

    public function query()
    {
        return $this->query;
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
            'Waktu (Mulai - Selesai)',
            'Tahun Ajaran',
            'Status'
        ];
    }

    public function map($item): array
    {
        return [
            $item->guru->id ?? '-',
            $item->guru->nama ?? '-',
            "'" . ($item->guru->nip ?? '-'),
            $item->mapel->nama_mapel ?? '-',
            $item->kelas->nama_kelas ?? '-',
            $item->hari ?? '-',
            ($item->jam_mulai_id ?? '-') . ' - ' . ($item->jam_selesai_id ?? '-'),
            $item->tahunAjaran->tahun_ajaran ?? '-',
            'Terjadwal'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header Table Style
        $sheet->getStyle('A11:I11')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $lastRow = $sheet->getHighestRow();

        // Border hanya jika ada data
        if ($lastRow >= 11) {
            $sheet->getStyle("A11:I{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCCCCCC'],
                    ],
                ],
            ]);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'I';
                $lastRow = $sheet->getHighestRow();

                // --- KOP SURAT (DIPERTAHANKAN SESUAI REQUEST) ---
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? '-'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL LAPORAN ---
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'JADWAL MENGAJAR GURU MATA PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A9', "Tanggal Cetak: " . date('d/m/Y H:i'));

                // --- STYLING STATUS TERJADWAL ---
                if ($lastRow >= 12) {
                    for ($row = 12; $row <= $lastRow; $row++) {
                        $sheet->getStyle("I{$row}")->getFont()->getColor()->setARGB('FF008000');
                        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
            },
        ];
    }
}