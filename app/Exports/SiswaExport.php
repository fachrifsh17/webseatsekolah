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

class SiswaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $namaKelas, $filters;

    public function __construct($query, $profil, $kontak, $namaKelas = null, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
        
        if (is_object($namaKelas)) {
            $this->namaKelas = $namaKelas->nama_kelas;
        } else {
            $this->namaKelas = $namaKelas;
        }
    }

    public function startCell(): string { return 'A11'; }

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
            $siswa->tanggal_lahir ? date('d-m-Y', strtotime($siswa->tanggal_lahir)) : '-',
            $siswa->jenis_kelamin,
            $siswa->kelas->nama_kelas ?? '-',
            $siswa->is_active ? 'Aktif' : 'Tidak Aktif',
            $siswa->no_telp_siswa,
            $siswa->alamat,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'J';

        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        for ($row = 12; $row <= $lastRow; $row++) {
            $status = $sheet->getCell("H{$row}")->getValue();
            if ($status == 'Aktif') {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF008000');
            } else {
                $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFFF0000');
            }
        }

        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'J';

                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                $alamatLengkap = $this->kontak->alamat_lengkap ?? 'Alamat belum diatur';
                $telepon = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $namaSekolah);
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', "{$alamatLengkap} | Telp: {$telepon}");
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: {$email} | NPSN: {$npsn}");
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DATA INDUK PESERTA DIDIK');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- LOGIKA FILTER DINAMIS ---
                $filterTexts = [];
                $filterTexts[] = "Kelas: " . ($this->namaKelas ?? 'Semua Kelas');
                
                if (!empty($this->filters['q'])) {
                    $filterTexts[] = "Pencarian: " . $this->filters['q'];
                }

                $statusAktif = (isset($this->filters['is_active']) && $this->filters['is_active'] == '0') ? 'Tidak Aktif' : 'Aktif';
                $filterTexts[] = "Status: " . $statusAktif;

                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', implode(' | ', $filterTexts));
                $sheet->getStyle('A8')->getFont()->setItalic(true);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "Tanggal Cetak: " . date('d/m/Y H:i'));
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}