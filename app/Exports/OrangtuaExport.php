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
use Illuminate\Support\Facades\DB;

class OrangtuaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    // Tambahkan properti $jurusanData
    protected $queryBuilder, $profil, $kontak, $kelasData, $jurusanData, $filters, $tahunAjaranText, $tahunAjaranId;

    // Tambahkan $jurusanData ke parameter constructor (default null)
    public function __construct($queryBuilder, $profil, $kontak, $kelasData = null, $filters = [], $jurusanData = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->kelasData = $kelasData;
        $this->jurusanData = $jurusanData; // Simpan data jurusan
        $this->filters = $filters;

        $taId = $filters['tahun_ajaran_id'] ?? null;
        
        if ($taId) {
            $ta = DB::table('tahun_ajaran')->where('id', $taId)->first();
            // Langsung dibuat uppercase dan rapi di sini
            $this->tahunAjaranText = $ta ? strtoupper($ta->nama . ' ' . $ta->semester) : '-';
            $this->tahunAjaranId = $taId;
        } else {
            $ta = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            // Hilangkan teks (Aktif) agar judul utama tetap formal
            $this->tahunAjaranText = $ta ? strtoupper($ta->nama . ' ' . $ta->semester) : '-';
            $this->tahunAjaranId = $ta?->id;
        }
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->queryBuilder;
    }

    public function headings(): array
    {
        return [
            'ID Orang Tua',
            'Nama Lengkap',
            'No. Telepon',
            'Data Anak (Nama - Kelas)',
            'Status Aktif'
        ];
    }

    public function map($orangtua): array
    {
        $daftarAnak = $orangtua->anak->filter(function($anak) {
            if ($this->kelasData) {
                return $anak->kelas_id == $this->kelasData->id;
            }
            // Tambahkan filter mapping jika hanya jurusan yang dipilih
            if ($this->jurusanData) {
                return $anak->kelas->jurusan_id == $this->jurusanData->id;
            }
            return $anak->kelas->tahun_ajaran_id == $this->tahunAjaranId;
        })->map(function($anak) {
            $namaKelas = $anak->kelas->nama_kelas ?? '-';
            return "{$anak->nama_lengkap} ({$namaKelas})";
        })->implode(', ');

        return [
            $orangtua->id,
            $orangtua->nama_lengkap,
            "'" . $orangtua->telepon,
            $daftarAnak ?: '-',
            $orangtua->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'E';

        $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE], 'name' => 'Arial', 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['name' => 'Arial', 'size' => 10]
        ]);

        $sheet->getStyle("D12:D{$lastRow}")->getAlignment()->setWrapText(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'E';

                // Header Sekolah
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true)->setName('Arial')->setSize(11);
                $sheet->getStyle("A4:{$lastCol}5")->getFont()->setName('Arial')->setSize(9);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // Judul
                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DATA ORANG TUA / WALI MURID');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12)->setName('Arial');
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Tahun Pelajaran - Sekarang lebih bersih
                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', "TAHUN PELAJARAN " . $this->tahunAjaranText);
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(11)->setName('Arial');
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Baris Info Filter (A9)
                $filterInfo = [];
                // Info Jurusan
                $filterInfo[] = "Jurusan: " . ($this->jurusanData ? $this->jurusanData->nama_jurusan : 'Semua Jurusan');
                // Info Kelas
                $filterInfo[] = "Kelas: " . ($this->kelasData ? $this->kelasData->nama_kelas : 'Semua Kelas');
                // Info Status
                $activeStatus = $this->filters['is_active'] ?? '1';
                $filterInfo[] = "Status: " . ($activeStatus == '1' ? 'Aktif' : 'Non-Aktif');
                
                if (!empty($this->filters['q'])) { $filterInfo[] = "Pencarian: " . $this->filters['q']; }

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', implode(' | ', $filterInfo));
                $sheet->getStyle('A9')->getFont()->setItalic(true)->setName('Arial')->setSize(9);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $sheet->mergeCells("A10:{$lastCol}10");
                $sheet->setCellValue('A10', "Tanggal Cetak: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A10")->getFont()->setName('Arial')->setSize(8);
                $sheet->getStyle("A10")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}