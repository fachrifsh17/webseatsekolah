<?php

namespace App\Exports;

use App\Models\GuruStaf;
use App\Models\Jurusan;
use Illuminate\Support\Facades\DB;
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

class GuruExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
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
        return 'A11'; 
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
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('nuptk', 'like', "%{$search}%");
                });
            })
            ->when($jabatan, fn($q) => $q->where('jabatan_fungsional', $jabatan))
            ->when($status, fn($q) => $q->where('status_kepegawaian', $status))
            ->when($jurusan, fn($q) => $q->where('jurusan_id', $jurusan))
            ->when($active !== null && $active !== '', fn($q) => $q->where('is_active', $active));
    }

    public function headings(): array
    {
        return [
            'ID GURU',
            'NIP',
            'NUPTK',
            'NAMA LENGKAP',
            'JABATAN FUNGSIONAL',
            'STATUS KEPEGAWAIAN',
            'JURUSAN',
            'STATUS'
        ];
    }

    public function map($guru): array
    {
        return [
            $guru->id,
            $guru->nip ? "'" . $guru->nip : '-',
            $guru->nuptk ? "'" . $guru->nuptk : '-',
            $guru->nama,
            $guru->jabatan_fungsional,
            $guru->status_kepegawaian,
            $guru->jurusan->nama_jurusan ?? '-',
            $guru->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'H';

        // Header Tabel (Baris 11) - Bold & Center
        $sheet->getStyle('A11:H11')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

        // Border & Alignment Global (Tanpa Pewarnaan Font)
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Atur posisi teks kolom tertentu ke tengah (ID dan STATUS)
        if ($lastRow >= 12) {
            $sheet->getStyle("A12:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H12:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'H';
                $lastRow = $sheet->getHighestRow();

                // 1. KOP SURAT
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG'));
                
                $alamat = $this->kontak->alamat_lengkap ?? 'Alamat Belum Diatur';
                $telp = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A4:{$lastCol}4"); 
                $sheet->setCellValue('A4', "{$alamat} | Telp: {$telp}");
                
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "Email: {$email} | NPSN: {$npsn}");
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // 2. JUDUL & TAHUN AJARAN
                $tahunAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
                $taText = $tahunAktif ? "TAHUN PELAJARAN {$tahunAktif->nama}" : "TAHUN PELAJARAN -";
                $semesterText = $tahunAktif ? " - SEMESTER " . strtoupper($tahunAktif->semester) : "";

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR DATA GURU DAN STAF');
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', $taText . $semesterText);
                
                $sheet->getStyle("A7:A8")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 3. FILTER INFO
                $filterParts = [];
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $filterParts[] = "Jurusan (" . ($jurusan->nama_jurusan ?? 'Semua') . ")";
                } else {
                    $filterParts[] = "Jurusan (Semua Jurusan)";
                }

                if (!empty($this->filters['q'])) {
                    $filterParts[] = "Pencarian (" . strtoupper($this->filters['q']) . ")";
                }

                $statusLabel = ($this->filters['is_active'] ?? '1') == '1' ? 'Aktif' : 'Non-Aktif';
                $filterParts[] = "Status ({$statusLabel})";

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "Filter: " . implode(' | ', $filterParts));
                $sheet->getStyle('A9')->getFont()->setItalic(true)->setSize(10);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 4. FOOTER
                $footerRow = $lastRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(9);
            },
        ];
    }
}