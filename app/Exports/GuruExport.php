<?php

namespace App\Exports;

use App\Models\GuruStaf;
use App\Models\Jurusan;
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
            'ID Guru',
            'NIP',
            'NUPTK',
            'Nama Lengkap',
            'Jabatan Fungsional',
            'Status Kepegawaian',
            'Jurusan',
            'Status Aktif'
        ];
    }

    /**
    * @var GuruStaf $guru
    */
    public function map($guru): array
    {
        return [
            $guru->id, // Mengambil ID asli dari tabel (misal: G015)
            $guru->nip ? "'" . $guru->nip : '-', // Menambahkan petik agar angka panjang tidak berantakan di excel
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
        $sheet->getStyle('A11:H11')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2E75B6']
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $lastRow = $sheet->getHighestRow();

        if ($lastRow >= 12) {
            for ($row = 12; $row <= $lastRow; $row++) {
                // Kolom H adalah Status Aktif
                $status = $sheet->getCell("H{$row}")->getValue();
                if ($status == 'Aktif') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FF008000');
                } else {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                }
            }
        }
        
        $sheet->getStyle("A11:H{$lastRow}")->applyFromArray([
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
                $lastCol = 'H';

                // Header Kop Surat
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                
                // Alamat dan Kontak dari data_kontak
                $alamat = $this->kontak->alamat_lengkap ?? 'Alamat Belum Diatur';
                $telp = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A4:{$lastCol}4"); 
                $sheet->setCellValue('A4', $alamat . " | Telp: " . $telp);
                
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "Email: " . $email . " | NPSN: " . $npsn);
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                
                $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THICK,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                // Judul Dokumen
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DATA GURU DAN STAF');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Informasi Filter
                $filterText = [];
                if (!empty($this->filters['q'])) $filterText[] = "Pencarian: " . $this->filters['q'];
                if (!empty($this->filters['jabatan_fungsional'])) $filterText[] = "Jabatan: " . $this->filters['jabatan_fungsional'];
                if (!empty($this->filters['status_kepegawaian'])) $filterText[] = "Status Kepegawaian: " . $this->filters['status_kepegawaian'];
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    if ($jurusan) $filterText[] = "Jurusan: " . $jurusan->nama_jurusan;
                }
                
                $statusAktif = ($this->filters['is_active'] ?? '1') == '1' ? 'Aktif' : 'Non-Aktif';
                $filterText[] = "Status Data: " . $statusAktif;

                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', "Filter: " . (empty($filterText) ? 'Semua Data' : implode(' | ', $filterText)));
                $sheet->getStyle('A8')->getFont()->setItalic(true)->setSize(10);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "Tanggal Cetak: " . date('d/m/Y H:i'));
                $sheet->getStyle('A9')->getFont()->setSize(10);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}