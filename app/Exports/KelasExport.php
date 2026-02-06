<?php

namespace App\Exports;

use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class KelasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string 
    { 
        return 'A12'; 
    }

    public function query()
    {
        $query = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas']);

        if (!empty($this->filters['search'])) {
            $query->where('nama_kelas', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['wali_kelas_id'])) {
            $query->where('wali_kelas_id', $this->filters['wali_kelas_id']);
        }

        if (!empty($this->filters['tahun_ajaran_id'])) {
            $query->where('tahun_ajaran_id', $this->filters['tahun_ajaran_id']);
        }

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', $this->filters['is_active']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID KELAS',
            'NAMA KELAS',
            'JURUSAN',
            'WALI KELAS',
            'STATUS'
        ];
    }

    private $rowNumber = 0;
    public function map($kelas): array
    {
        return [
            ++$this->rowNumber,
            $kelas->id,
            $kelas->nama_kelas,
            $kelas->jurusan->nama_jurusan ?? '-',
            $kelas->waliKelas->nama_lengkap ?? $kelas->waliKelas->nama ?? '-',
            $kelas->is_active ? 'AKTIF' : 'TIDAK AKTIF',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Styling spesifik ditangani di registerEvents
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'F';
                $lastRow = $sheet->getHighestRow();

                // --- 1. KOP SURAT ---
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- 2. JUDUL LAPORAN ---
                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR DATA KELAS');
                $sheet->getStyle("A7")->getFont()->setBold(true)->setSize(12);
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                
                // Cari Tahun Ajaran agar tidak kosong (Gunakan properti 'nama' sesuai database)
                $ta = null;
                if (!empty($this->filters['tahun_ajaran_id'])) {
                    $ta = TahunAjaran::find($this->filters['tahun_ajaran_id']);
                }
                if (!$ta) {
                    $ta = TahunAjaran::where('is_active', 1)->first();
                }

                $sheet->setCellValue('A8', 'TAHUN PELAJARAN ' . ($ta->nama ?? '-'));
                
                // Set ukuran font Tahun Pelajaran agak kecil (10)
                $sheet->getStyle("A8")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- 3. KETERANGAN FILTER ---
                $jurusanName = 'Semua Jurusan';
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $jurusan ? $jurusan->nama_jurusan : 'Semua Jurusan';
                }

                $statusLabel = 'Semua Status';
                if (isset($this->filters['is_active'])) {
                    $statusLabel = $this->filters['is_active'] == 1 ? 'Aktif' : 'Tidak Aktif';
                }

                $filterText = "Filter: Jurusan ($jurusanName) | Status ($statusLabel)";
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', $filterText);
                $sheet->getStyle("A9")->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Tanggal Cetak
                $sheet->mergeCells("A10:{$lastCol}10"); 
                $sheet->setCellValue('A10', "Tanggal Cetak: " . date('d/m/Y H:i'));
                $sheet->getStyle("A10")->getFont()->setSize(9);
                $sheet->getStyle("A10")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- 4. STYLING TABEL ---
                $sheet->getStyle("A12:{$lastCol}12")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF2E75B6']
                    ],
                ]);

                $sheet->getStyle("A12:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                for ($i = 13; $i <= $lastRow; $i++) {
                    $val = $sheet->getCell("F$i")->getValue();
                    if ($val == 'AKTIF') {
                        $sheet->getStyle("F$i")->getFont()->getColor()->setARGB('FF008000');
                    } else {
                        $sheet->getStyle("F$i")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                }
            },
        ];
    }
}