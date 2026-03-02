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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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
        return $this->query->with(['guru', 'mapel', 'kelas', 'semester', 'jamMulai', 'jamSelesai']);
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

        // Styling untuk Header (Baris 13) - Background abu-abu dihapus
        $sheet->getStyle("A13:{$lastCol}13")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
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

        $sheet->getStyle("A14:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B14:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D14:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G14:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->getColumnDimension('A')->setAutoSize(false);
        $sheet->getColumnDimension('A')->setWidth(5);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $worksheet = $event->sheet->getDelegate(); 

                $lastCol = 'K'; 
                $lastRow = $worksheet->getHighestRow();

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $wakaKur = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 2) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $worksheet->mergeCells("A1:{$lastCol}1"); $worksheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $worksheet->mergeCells("A2:{$lastCol}2"); $worksheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $worksheet->mergeCells("A3:{$lastCol}3"); $worksheet->setCellValue('A3', strtoupper($this->profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $worksheet->mergeCells("A4:{$lastCol}4"); $worksheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $worksheet->mergeCells("A5:{$lastCol}5"); $worksheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $worksheet->mergeCells("A6:{$lastCol}6"); $worksheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $worksheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $worksheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $worksheet->mergeCells("A7:{$lastCol}7"); 
                $worksheet->setCellValue('A7', 'DAFTAR PENUGASAN GURU MATA PELAJARAN');
                $worksheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);

                $semester = isset($this->filters['semester']) ? strtoupper($this->filters['semester']) : '-';
                $ta = $this->filters['tahun_ajaran'] ?? '-';
                
                $worksheet->mergeCells("A8:{$lastCol}8"); 
                $worksheet->setCellValue('A8', "TAHUN PELAJARAN $ta - SEMESTER $semester");

                $worksheet->getStyle('A8')->getFont()->setBold(true)->setSize(11);
                $worksheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $identitas = $this->filters['identitas_laporan'] ?? 'SEMUA DATA';
                $statusMapel = $this->filters['status_mapel'] ?? 'AKTIF';
                
                $worksheet->setCellValue('A10', strtoupper($identitas));
                $worksheet->setCellValue('A11', "STATUS : " . strtoupper($statusMapel));
                
                $worksheet->getStyle("A10:A11")->getFont()->setItalic(true)->setBold(false)->setSize(9);
                $worksheet->getStyle("A10:A11")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $ttgRow = $lastRow + 3;
                
                $worksheet->mergeCells("I{$ttgRow}:{$lastCol}{$ttgRow}");
                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $worksheet->setCellValue("I{$ttgRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $worksheet->getStyle("I{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $worksheet->mergeCells("B{$ttgRow}:D{$ttgRow}");
                $worksheet->setCellValue("B{$ttgRow}", "Mengetahui,\nWaka Kurikulum");
                
                $worksheet->mergeCells("I{$ttgRow}:{$lastCol}{$ttgRow}");
                $worksheet->setCellValue("I{$ttgRow}", "Menyetujui,\nKepala Sekolah");
                
                $worksheet->getStyle("B{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $worksheet->getStyle("B{$ttgRow}:{$lastCol}{$ttgRow}")->getFont()->setBold(true);

                $imageRow = $ttgRow + 1;
                $worksheet->getRowDimension($imageRow)->setRowHeight(60);
                
                if ($wakaKur && $wakaKur->file_ttd && file_exists(storage_path('app/public/' . $wakaKur->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Waka');
                    $drawing->setDescription('TTD Waka');
                    $drawing->setPath(storage_path('app/public/' . $wakaKur->file_ttd));
                    $drawing->setHeight(50);
                    $drawing->setCoordinates("C{$imageRow}");
                    $drawing->setOffsetX(20); 
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($worksheet);
                }

                if ($kepsek && $kepsek->file_ttd && file_exists(storage_path('app/public/' . $kepsek->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Kepsek');
                    $drawing->setDescription('TTD Kepsek');
                    $drawing->setPath(storage_path('app/public/' . $kepsek->file_ttd));
                    $drawing->setHeight(50);
                    $drawing->setCoordinates("J{$imageRow}");
                    $drawing->setOffsetX(20);
                    $drawing->setOffsetY(5);
                    $drawing->setWorksheet($worksheet);
                }

                $namaRow = $imageRow + 3;
                $worksheet->mergeCells("B{$namaRow}:D{$namaRow}");
                $worksheet->setCellValue("B{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '............................') . " )"); 
                
                $worksheet->mergeCells("I{$namaRow}:{$lastCol}{$namaRow}");
                $worksheet->setCellValue("I{$namaRow}", "( " . strtoupper($kepsek->nama ?? '............................') . " )");
                
                $worksheet->getStyle("B{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle("B{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $worksheet->mergeCells("B{$nipRow}:D{$nipRow}");
                $worksheet->setCellValue("B{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                
                $worksheet->mergeCells("I{$nipRow}:{$lastCol}{$nipRow}");
                $worksheet->setCellValue("I{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                
                $worksheet->getStyle("B{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $footerRow = $nipRow + 2; 
                $worksheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $worksheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}