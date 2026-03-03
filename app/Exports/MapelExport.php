<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use App\Models\Semester;
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
use PhpOffice\PhpSpreadsheet\Style\Color;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class MapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak, $semester;

    public function __construct($filters, $profil, $kontak, $semester)
    {
        $this->filters = $filters;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->semester = $semester;
    }

    public function startCell(): string 
    { 
        return 'A12'; 
    }

    public function query()
    {
        $query = MataPelajaran::with(['jurusan']);

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', $this->filters['is_active']);
        }

        if (!empty($this->filters['search'])) {
            $query->where('nama_mapel', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['tipe_mapel'])) {
            $query->where('tipe_mapel', $this->filters['tipe_mapel']);
        }

        if (!empty($this->filters['kategori_mapel'])) {
            $query->where('kategori_mapel', $this->filters['kategori_mapel']);
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'ID MAPEL',
            'NAMA MATA PELAJARAN',
            'JURUSAN',
            'TIPE',
            'KATEGORI',
            'STATUS'
        ];
    }

    public function map($mapel): array
    {
        return [
            $mapel->id,
            $mapel->nama_mapel,
            $mapel->jurusan->nama_jurusan ?? 'UMUM',
            strtoupper($mapel->tipe_mapel),
            strtoupper($mapel->kategori_mapel),
            $mapel->is_active ? 'AKTIF' : 'NON-AKTIF',
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $pSheet = $sheet->getDelegate();
                $lastCol = 'F'; 
                $lastRow = $sheet->getHighestRow();

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(20);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(12);

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

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR MATA PELAJARAN');
                $sheet->getStyle("A7")->getFont()->setBold(true)->setSize(12);
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $namaTA = $this->semester->tahunAjaran->nama ?? '-';
                $namaSemester = strtoupper($this->semester->nama ?? '-');
                $sheet->setCellValue('A8', 'TAHUN PELAJARAN ' . $namaTA . ' - SEMESTER ' . $namaSemester);
                $sheet->getStyle("A8")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusanName = 'Semua Jurusan';
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $jurusan ? $jurusan->nama_jurusan : 'Semua Jurusan';
                }
                $tipeLabel = !empty($this->filters['tipe_mapel']) ? strtoupper($this->filters['tipe_mapel']) : 'SEMUA TIPE';
                $kategoriLabel = !empty($this->filters['kategori_mapel']) ? strtoupper($this->filters['kategori_mapel']) : 'SEMUA KATEGORI';
                $statusLabel = isset($this->filters['is_active']) ? ($this->filters['is_active'] ? 'AKTIF' : 'NON-AKTIF') : 'SEMUA STATUS';

                $sheet->setCellValue('A9', "Jurusan : " . $jurusanName);
                $sheet->setCellValue('A10', "Tipe : " . $tipeLabel);
                $sheet->setCellValue('A11', "Kategori : " . $kategoriLabel . " | Status : " . $statusLabel);
                $sheet->getStyle("A9:A11")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ]);

                $sheet->getStyle("A12:{$lastCol}12")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_BLACK]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A12:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => Color::COLOR_BLACK],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $ttgRow = $lastRow + 3;
                $sheet->mergeCells("E{$ttgRow}:{$lastCol}{$ttgRow}");
                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $sheet->setCellValue("E{$ttgRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("E{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:B{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Mengetahui,\nWaka Kurikulum");
                
                $sheet->mergeCells("E{$ttgRow}:{$lastCol}{$ttgRow}");
                $sheet->setCellValue("E{$ttgRow}", "Menyetujui,\nKepala Sekolah");
                
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getFont()->setBold(true);

                $imageRow = $ttgRow + 1;
                $sheet->getRowDimension($imageRow)->setRowHeight(70);

                if ($wakaKur && $wakaKur->file_ttd && file_exists(storage_path('app/public/' . $wakaKur->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Waka');
                    $drawing->setDescription('TTD Waka');
                    $drawing->setPath(storage_path('app/public/' . $wakaKur->file_ttd));
                    $drawing->setHeight(55);
                    $drawing->setCoordinates("A{$imageRow}");
                    $drawing->setOffsetX(30);
                    $drawing->setWorksheet($pSheet);
                }

                if ($kepsek && $kepsek->file_ttd && file_exists(storage_path('app/public/' . $kepsek->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Kepsek');
                    $drawing->setDescription('TTD Kepsek');
                    $drawing->setPath(storage_path('app/public/' . $kepsek->file_ttd));
                    $drawing->setHeight(55);
                    $drawing->setCoordinates("E{$imageRow}");
                    $drawing->setOffsetX(20);
                    $drawing->setWorksheet($pSheet);
                }

                $namaRow = $imageRow + 2;
                $sheet->mergeCells("A{$namaRow}:B{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '............................') . " )"); 
                
                $sheet->mergeCells("E{$namaRow}:{$lastCol}{$namaRow}");
                $sheet->setCellValue("E{$namaRow}", "( " . strtoupper($kepsek->nama ?? '............................') . " )");
                
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:B{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                
                $sheet->mergeCells("E{$nipRow}:{$lastCol}{$nipRow}");
                $sheet->setCellValue("E{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $footerRow = $nipRow + 2; 
                $sheet->setCellValue("A{$footerRow}", "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $sheet->getStyle("A{$footerRow}")->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}