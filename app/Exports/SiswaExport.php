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

class SiswaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $namaKelas, $filters;

    public function __construct($query, $profil, $kontak, $namaKelas = null, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
        
        $this->namaKelas = is_object($namaKelas) ? $namaKelas->nama_kelas : $namaKelas;
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID SISWA',
            'NIS',
            'NISN',
            'Nama Lengkap',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Kelas',
            'No Telp Siswa',
            'Alamat',
            'Status Aktif' 
        ];
    }

    public function map($siswa): array
    {
        // Ambil kelas aktif dari relasi riwayatKelas (pivot)
        $riwayatAktif = $siswa->riwayatKelas ? $siswa->riwayatKelas->where('is_active', 1)->first() : null;
        $namaKelasSiswa = $riwayatAktif && $riwayatAktif->kelas ? $riwayatAktif->kelas->nama_kelas : '-';

        return [
            $siswa->id, 
            "'" . $siswa->nis,
            "'" . $siswa->nisn,
            strtoupper($siswa->nama_lengkap),
            $siswa->tempat_lahir,
            $siswa->tanggal_lahir ? date('d-m-Y', strtotime($siswa->tanggal_lahir)) : '-',
            $siswa->jenis_kelamin,
            $namaKelasSiswa,
            $siswa->no_telp_siswa, 
            $siswa->alamat,
            $siswa->is_active ? 'Aktif' : 'Tidak Aktif', 
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'K';
                $lastRow = $sheet->getHighestRow();

                $tahunAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
                $txtTahun = $tahunAktif ? "TAHUN PELAJARAN " . $tahunAktif->nama . " - SEMESTER " . strtoupper($tahunAktif->semester) : "TAHUN PELAJARAN -";

                // --- HEADER / KOP SURAT ---
                // Penyesuaian berdasarkan image_d3d41d.png dan image_d3d41f.png
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG');
                $alamatSekolah = $this->kontak->alamat_lengkap ?? ''; 
                $telepon = $this->kontak->telepon ?? '';
                $email = $this->kontak->email_resmi ?? '';
                $npsn = $this->profil->npsn ?? '';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $namaSekolah);
                
                // Baris 4: Alamat dan Telepon
                $sheet->mergeCells("A4:{$lastCol}4"); 
                $sheet->setCellValue('A4', $alamatSekolah . " | Telp: " . $telepon);
                
                // Baris 5: Email dan NPSN
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "Email: " . $email . " | NPSN: " . $npsn);
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // --- JUDUL ---
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DATA INDUK PESERTA DIDIK');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- TAHUN AJARAN ---
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', $txtTahun);
                $sheet->getStyle('A8')->getFont()->setBold(true);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- FILTER INFO ---
                $filterTexts = ["Kelas: " . ($this->namaKelas ?? 'Semua Kelas')];
                $filterTexts[] = "Status: " . ((isset($this->filters['is_active']) && $this->filters['is_active'] == '0') ? 'Tidak Aktif' : 'Aktif');
                
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', implode(' | ', $filterTexts));
                $sheet->getStyle('A9')->getFont()->setItalic(true);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- TABLE STYLING ---
                $sheet->getStyle("A11:{$lastCol}11")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                for ($row = 12; $row <= $lastRow; $row++) {
                    $val = $sheet->getCell("K{$row}")->getValue();
                    $color = ($val == 'Aktif') ? Color::COLOR_BLACK : 'FFFF0000';
                    
                    $sheet->getStyle("K{$row}")->getFont()->getColor()->setARGB($color);
                    $sheet->getStyle("K{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $footerRow = $lastRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);
            },
        ];
    }
}