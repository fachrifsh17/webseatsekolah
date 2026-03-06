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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;

class GuruExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = is_array($this->profil) ? (object)$this->profil : $profil;
        $this->kontak = is_array($this->kontak) ? (object)$this->kontak : $kontak;
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
            'ID GURU', 'NIP', 'NUPTK', 'NAMA LENGKAP', 'JENIS KELAMIN',
            'TEMPAT LAHIR', 'TANGGAL LAHIR', 'AGAMA', 'PENDIDIKAN TERAKHIR',
            'JABATAN FUNGSIONAL', 'STATUS KEPEGAWAIAN', 'JURUSAN', 'STATUS'
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
                $pSheet = $sheet->getDelegate();
                $lastCol = 'M';
                $lastRow = $sheet->getHighestRow();

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setName('Logo Provinsi');
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(10);
                        $drawingProv->setOffsetY(5);
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatFull = ($this->kontak->alamat_jalan ?? '-') . ", Kec. " . ($this->kontak->kecamatan ?? '-') . ", " . ($this->kontak->kabupaten_kota ?? '');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', $alamatFull . " - " . $provAsli);
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $semesterId = $this->filters['semester_id'] ?? DB::table('semesters')->where('is_active', 1)->value('id');
                
                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', "DAFTAR DATA GURU DAN STAF");

                if ($semesterId) {
                    $sem = DB::table('semesters')->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                            ->where('semesters.id', $semesterId)->select('semesters.nama', 'tahun_ajaran.nama as ta')->first();
                    
                    if ($sem) {
                        $sheet->mergeCells("A8:{$lastCol}8");
                        $sheet->setCellValue('A8', "TAHUN PELAJARAN " . $sem->ta . " - SEMESTER " . strtoupper($sem->nama));
                    }
                }

                $sheet->getStyle("A7:{$lastCol}8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A7:{$lastCol}8")->getFont()->setBold(true)->setSize(11);

                $statusAktif = 'Semua';
                if (isset($this->filters['is_active'])) {
                    $statusAktif = $this->filters['is_active'] == 1 ? 'Aktif' : 'Non-Aktif';
                }

                $namaJurusan = 'Semua Jurusan';
                if (!empty($this->filters['jurusan_id'])) {
                    $namaJurusan = Jurusan::where('id', $this->filters['jurusan_id'])->value('nama_jurusan') ?? 'Semua';
                }

                $filterRow1 = "Kriteria Filter: ";
                $filterRow1 .= "Jabatan: " . ($this->filters['jabatan_fungsional'] ?? 'Semua') . " | ";
                $filterRow1 .= "Kepegawaian: " . ($this->filters['status_kepegawaian'] ?? 'Semua') . " | ";
                $filterRow1 .= "Status Akun: " . $statusAktif;

                $filterRow2 = "Jurusan: " . $namaJurusan . " | ";
                $filterRow2 .= "Pencarian: " . ($this->filters['q'] ?? '(Tanpa Pencarian)');

                $sheet->setCellValue('A9', $filterRow1);
                $sheet->setCellValue('A10', $filterRow2);
                $sheet->getStyle('A9:A10')->getFont()->setItalic(true)->setSize(9);

                $ttgRow = $lastRow + 3;
                $sheet->setCellValue("J{$ttgRow}", ($this->kontak->kabupaten_kota ?? 'Tasikmalaya') . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue("J" . ($ttgRow + 1), "Menyetujui,\nKepala Sekolah");
                
                $sheet->getStyle("J{$ttgRow}:M" . ($ttgRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("J" . ($ttgRow + 1))->getFont()->setBold(true);

                if ($kepsek && $kepsek->file_ttd && file_exists(storage_path('app/' . $kepsek->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setPath(storage_path('app/' . $kepsek->file_ttd));
                    $drawing->setHeight(55);
                    $drawing->setCoordinates('J' . ($ttgRow + 2));
                    $drawing->setWorksheet($pSheet);
                }

                $namaRow = $ttgRow + 5;
                $sheet->setCellValue("J{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->setCellValue("J" . ($namaRow + 1), "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("J{$namaRow}:M" . ($namaRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J{$namaRow}")->getFont()->setBold(true);

                $sheet->setCellValue("A{$namaRow}", "( ____________________ )");
                $sheet->setCellValue("A" . ($namaRow + 1), "NIP. ...........................");
                $sheet->getStyle("A{$namaRow}:C" . ($namaRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("A" . ($namaRow + 3), "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A" . ($namaRow + 3))->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}