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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GuruMapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $filters;
    private $rowNumber = 0;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->filters = $filters;
    }

    public function startCell(): string { return 'A13'; }

    public function query()
    {
        return $this->query->with(['guru', 'mapel', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']);
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID GURU',
            'NAMA GURU',
            'NIP',
            'NUPTK',
            'MATA PELAJARAN',
            'TIPE',
            'KELAS',
            'HARI',
            'URUTAN JAM',
            'STATUS'
        ];
    }

    public function map($item): array
    {
        $this->rowNumber++;
        $jamKeMulai = $item->jamMulai->jam_ke ?? '-';
        $jamKeSelesai = $item->jamSelesai->jam_ke ?? '-';

        $jamFormatted = ($jamKeMulai !== '-' && $jamKeSelesai !== '-') 
            ? "{$jamKeMulai} - {$jamKeSelesai}"
            : $jamKeMulai;

        $kategoriMapel = strtoupper($item->mapel->kategori_mapel ?? '-');
        $guru = $item->guru;
        
        return [
            $this->rowNumber,
            $guru->id ?? '-',
            strtoupper($guru->nama ?? '-'),
            $guru && $guru->nip ? "'" . $guru->nip : '-',
            $guru && $guru->nuptk ? "'" . $guru->nuptk : '-',
            strtoupper($item->mapel->nama_mapel ?? '-'),
            $kategoriMapel,
            strtoupper($item->kelas->nama_kelas ?? '-'),
            strtoupper($item->hari ?? '-'),
            $jamFormatted,
            'AKTIF'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'K';

        $sheet->getStyle("A13:{$lastCol}13")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);

        $sheet->getStyle("A13:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, 
                    'color' => ['argb' => Color::COLOR_BLACK]
                ]
            ],
            'font' => ['size' => 10],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true
            ]
        ]);

        $sheet->getStyle("A14:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D14:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G14:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'K'; 
                $lastRow = $sheet->getHighestRow();

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip')
                    ->first();

                $wakaKur = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 2) 
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
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DAFTAR PENUGASAN GURU MATA PELAJARAN');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);

                $semester = isset($this->filters['semester']) ? strtoupper($this->filters['semester']) : '-';
                $ta = $this->filters['tahun_ajaran'] ?? '-';
                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', "TAHUN PELAJARAN $ta - SEMESTER $semester");
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $identitas = $this->filters['identitas_laporan'] ?? 'SEMUA DATA';
                $statusMapel = $this->filters['status_mapel'] ?? 'AKTIF';
                
              $sheet->setCellValue('A10', strtoupper($identitas));
                $sheet->setCellValue('A11', "STATUS : " . strtoupper($statusMapel));
                
                $sheet->getStyle("A10:A11")->getFont()->setItalic(true)->setBold(false)->setSize(9);
                $sheet->getStyle("A10:A11")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $ttgRow = $lastRow + 3;
                $sheet->mergeCells("H{$ttgRow}:{$lastCol}{$ttgRow}");
                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $sheet->setCellValue("H{$ttgRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("H{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Mengetahui,\nWaka Kurikulum");
                $sheet->mergeCells("H{$ttgRow}:{$lastCol}{$ttgRow}");
                $sheet->setCellValue("H{$ttgRow}", "Menyetujui,\nKepala Sekolah");
                
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("H{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getFont()->setBold(true);
                $sheet->getStyle("H{$ttgRow}:{$lastCol}{$ttgRow}")->getFont()->setBold(true);

                $namaRow = $ttgRow + 4;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '____________________') . " )"); 
                $sheet->mergeCells("H{$namaRow}:{$lastCol}{$namaRow}");
                $sheet->setCellValue("H{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                $sheet->mergeCells("H{$nipRow}:{$lastCol}{$nipRow}");
                $sheet->setCellValue("H{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $footerRow = $nipRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}