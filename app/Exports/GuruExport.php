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
    protected $filters, $profil, $kontak, $rowNumber = 0;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
    }

    public function startCell(): string 
    { 
        return 'A17'; 
    }

    public function query()
    {
        $search  = $this->filters['q'] ?? null;
        $jabatan = $this->filters['jabatan_fungsional'] ?? null;
        $status  = $this->filters['status_kepegawaian'] ?? null;
        $jurusan = $this->filters['jurusan_id'] ?? null;
        $active  = $this->filters['is_active'] ?? null;
        $jk      = $this->filters['jenis_kelamin'] ?? null;
        $agama   = $this->filters['agama'] ?? null;

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
            ->when($jk, fn($q) => $q->where('jenis_kelamin', $jk))
            ->when($agama, fn($q) => $q->where('agama', $agama))
            ->when($active !== null && $active !== '', fn($q) => $q->where('is_active', $active));
    }

    public function headings(): array
    {
        return [
            'NO', 'ID GURU', 'NIP', 'NUPTK', 'NAMA LENGKAP', 'EMAIL', 'JENIS KELAMIN',
            'TEMPAT LAHIR', 'TANGGAL LAHIR', 'AGAMA', 'PENDIDIKAN TERAKHIR',
            'JABATAN FUNGSIONAL', 'STATUS KEPEGAWAIAN', 'JURUSAN', 'STATUS'
        ];
    }

    public function map($guru): array
    {
        $this->rowNumber++;
        $formattedId = 'G' . str_pad(substr($guru->id, -3), 3, '0', STR_PAD_LEFT);

        return [
            $this->rowNumber,
            $formattedId,
            $guru->nip ? "'" . $guru->nip : '-',
            $guru->nuptk ? "'" . $guru->nuptk : '-',
            $guru->nama,
            $guru->email ?? '-',
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
        $lastCol = 'O';

        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
        $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(10);
        $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(22);
        $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(20);

        $sheet->getStyle('A17:O17')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);

        $sheet->getStyle("A17:{$lastCol}{$lastRow}")->applyFromArray([
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

        if ($lastRow >= 18) {
            $sheet->getStyle("A18:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G18:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I18:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O18:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $pSheet = $sheet->getDelegate();
                $lastCol = 'O';
                $lastRow = $sheet->getHighestRow();

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(80);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(45);
                        $drawingProv->setOffsetY(10);
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? ''));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', "DAFTAR DATA GURU DAN STAF");

                $semesterId = $this->filters['semester_id'] ?? DB::table('semesters')->where('is_active', 1)->value('id');
                if ($semesterId) {
                    $sem = DB::table('semesters')->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                            ->where('semesters.id', $semesterId)->select('semesters.nama', 'tahun_ajaran.nama as ta')->first();
                    if ($sem) {
                        $sheet->mergeCells("A9:{$lastCol}9");
                        $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $sem->ta . " - SEMESTER " . strtoupper($sem->nama));
                    }
                }

                $sheet->getStyle("A8:{$lastCol}9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A8:{$lastCol}9")->getFont()->setBold(true)->setSize(11);

                $statusAktif = isset($this->filters['is_active']) ? ($this->filters['is_active'] == 1 ? 'AKTIF' : 'NON-AKTIF') : 'SEMUA';
                $namaJurusan = !empty($this->filters['jurusan_id']) ? (Jurusan::where('id', $this->filters['jurusan_id'])->value('nama_jurusan') ?? 'SEMUA') : 'SEMUA JURUSAN';
                $pencarian = !empty($this->filters['q']) ? strtoupper($this->filters['q']) : '-';
                $teksJK = !empty($this->filters['jenis_kelamin']) ? ($this->filters['jenis_kelamin'] == 'L' ? 'LAKI-LAKI' : 'PEREMPUAN') : 'SEMUA';

                $sheet->setCellValue('A10', "JABATAN: " . strtoupper($this->filters['jabatan_fungsional'] ?? 'SEMUA'));
                $sheet->setCellValue('A11', "KEPEGAWAIAN: " . strtoupper($this->filters['status_kepegawaian'] ?? 'SEMUA'));
                $sheet->setCellValue('A12', "STATUS: " . $statusAktif);
                $sheet->setCellValue('A13', "JURUSAN: " . strtoupper($namaJurusan));
                $sheet->setCellValue('A14', "JENIS KELAMIN: " . $teksJK);
                $sheet->setCellValue('A15', "AGAMA: " . strtoupper($this->filters['agama'] ?? 'SEMUA'));
                $sheet->setCellValue('A16', "PENCARIAN: " . $pencarian);
                $sheet->getStyle('A10:A16')->getFont()->setBold(false)->setSize(9);

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $ttgRow = $lastRow + 3;
                
                $sheet->setCellValue("L{$ttgRow}", ($this->kontak->kabupaten_kota ?? 'Tasikmalaya') . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue("L" . ($ttgRow + 1), "Menyetujui,\nKepala Sekolah");
                $sheet->getStyle("L{$ttgRow}:O" . ($ttgRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

                if ($kepsek && $kepsek->file_ttd) {
                    $cleanPath = str_replace('private/', '', $kepsek->file_ttd);
                    $fullPath = storage_path('app/private/' . $cleanPath);
                    if (file_exists($fullPath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($fullPath);
                        $drawing->setHeight(70);
                        $drawing->setCoordinates('L' . ($ttgRow + 2));
                        $drawing->setOffsetX(60);
                        $drawing->setWorksheet($pSheet);
                    }
                }

                $namaRow = $ttgRow + 6;
                $sheet->setCellValue("L{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->setCellValue("L" . ($namaRow + 1), "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("L{$namaRow}:O" . ($namaRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$namaRow}")->getFont()->setBold(true);

                $sheet->setCellValue("D" . ($ttgRow + 1), "Verifikator,");
                $sheet->getStyle("D" . ($ttgRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue("D{$namaRow}", "( ____________________ )");
                $sheet->setCellValue("D" . ($namaRow + 1), "NIP. ...........................");
                $sheet->getStyle("D{$namaRow}:D" . ($namaRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$namaRow}")->getFont()->setBold(false);

                $sheet->setCellValue("A" . ($namaRow + 3), "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A" . ($namaRow + 3))->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}