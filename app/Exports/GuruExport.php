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
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'AGAMA',
            'PENDIDIKAN TERAKHIR',
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
            $guru->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan',
            $guru->tempat_lahir,
            $guru->tanggal_lahir ? Carbon::parse($guru->tanggal_lahir)->format('d-m-Y') : '-',
            $guru->agama,
            $guru->pendidikan_terakhir,
            $guru->jabatan_fungsional,
            $guru->status_kepegawaian,
            $guru->jurusan->nama_jurusan ?? '-',
            $guru->is_active ? 'Aktif' : 'Non-Aktif',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'M';

        $sheet->getStyle('A11:M11')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

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

        if ($lastRow >= 12) {
            $sheet->getStyle("A12:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E12:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G12:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M12:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'M';
                $lastRow = $sheet->getHighestRow();

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip')
                    ->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                
                $sheet->mergeCells("A6:{$lastCol}6"); 
                $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $tahunAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
                $taText = $tahunAktif ? "TAHUN PELAJARAN {$tahunAktif->nama}" : "TAHUN PELAJARAN -";
                $semesterText = $tahunAktif ? " - SEMESTER " . strtoupper($tahunAktif->semester) : "";

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR DATA GURU DAN STAF');
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', $taText . $semesterText);
                
                $sheet->getStyle("A7:A8")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow = $lastRow + 3;
                $ttgColStart = 'J';
                $ttgColEnd = 'M';
                
                $sheet->mergeCells("{$ttgColStart}{$ttgRow}:{$ttgColEnd}{$ttgRow}");
                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $sheet->setCellValue("{$ttgColStart}{$ttgRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("{$ttgColStart}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("{$ttgColStart}{$ttgRow}:{$ttgColEnd}{$ttgRow}");
                $sheet->setCellValue("{$ttgColStart}{$ttgRow}", "Menyetujui,\nKepala Sekolah");
                $sheet->getStyle("{$ttgColStart}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("{$ttgColStart}{$ttgRow}")->getFont()->setBold(true);

                $namaRow = $ttgRow + 4;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( ____________________ )"); 
                
                $sheet->mergeCells("{$ttgColStart}{$namaRow}:{$ttgColEnd}{$namaRow}");
                $sheet->setCellValue("{$ttgColStart}{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                
                $sheet->getStyle("A{$namaRow}:{$ttgColEnd}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:{$ttgColEnd}{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. ...........................");
                
                $sheet->mergeCells("{$ttgColStart}{$nipRow}:{$ttgColEnd}{$nipRow}");
                $sheet->setCellValue("{$ttgColStart}{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:{$ttgColEnd}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $footerRow = $nipRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}