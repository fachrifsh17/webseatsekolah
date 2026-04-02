<?php

namespace App\Exports;

use App\Models\Kelas;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class KelasExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;
    private $rowNumber = 0;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 15, 'C' => 12, 'D' => 20, 'E' => 25,
            'F' => 18, 'G' => 18, 'H' => 25, 'I' => 12,
        ];
    }

    public function startCell(): string 
    { 
        return 'A13'; 
    }

    public function query()
    {
        $query = Kelas::query()->with(['jurusan', 'tingkatan']);
        
        $isActive = true;
        if (isset($this->filters['is_active'])) {
            $isActive = filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        $query->where('is_active', $isActive ? 1 : 0);

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['tingkatan_id'])) {
            $query->where('tingkatan_id', $this->filters['tingkatan_id']);
        }

        return $query->orderBy('tingkatan_id', 'asc')->orderBy('nama_kelas', 'asc');
    }

    public function headings(): array
    {
        return ['NO', 'ID KELAS', 'TINGKATAN', 'NAMA KELAS', 'JURUSAN', 'NIP', 'NUPTK', 'WALI KELAS', 'STATUS'];
    }

    public function map($kelas): array
    {
        $semesterId = $this->filters['semester_id'] ?? \App\Models\Semester::where('is_active', 1)->first()?->id;
        
        $wali = DB::table('kelas_wali_kelas')
            ->join('guru_staf', 'kelas_wali_kelas.guru_staf_id', '=', 'guru_staf.id')
            ->where('kelas_wali_kelas.kelas_id', $kelas->id)
            ->where('kelas_wali_kelas.semester_id', $semesterId)
            ->where('kelas_wali_kelas.is_active', 1)
            ->select('guru_staf.nama', 'guru_staf.nip', 'guru_staf.nuptk')
            ->first();
        
        $isActive = true;
        if (isset($this->filters['is_active'])) {
            $isActive = filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        return [
            ++$this->rowNumber,
            $kelas->id,
            $kelas->tingkatan->nama_tingkatan ?? '-',
            $kelas->nama_kelas,
            $kelas->jurusan->nama_jurusan ?? '-',
            $wali && $wali->nip ? "'" . $wali->nip : '-', 
            $wali && $wali->nuptk ? "'" . $wali->nuptk : '-', 
            $wali->nama ?? '-',
            $isActive ? 'AKTIF' : 'NON AKTIF',
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $pSheet = $sheet->getDelegate();
                $lastCol = 'I';
                $lastRow = $sheet->getHighestRow();

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(35); 
                        $drawingProv->setOffsetY(10); 
                        $drawingProv->setEditAs('oneCell');
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $isActive = true;
                if (isset($this->filters['is_active'])) {
                    $isActive = filter_var($this->filters['is_active'], FILTER_VALIDATE_BOOLEAN);
                }

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', 'DAFTAR DATA KELAS ' . ($isActive ? 'AKTIF' : 'NON AKTIF'));
                
                $sheet->mergeCells("A9:{$lastCol}9"); 
                $semId = $this->filters['semester_id'] ?? null;
                $semester = $semId ? \App\Models\Semester::with('tahunAjaran')->find($semId) : \App\Models\Semester::with('tahunAjaran')->where('is_active', 1)->first();
                $semText = $semester ? "TAHUN PELAJARAN {$semester->tahunAjaran->nama} - SEMESTER " . strtoupper($semester->nama) : 'TAHUN PELAJARAN -';
                $sheet->setCellValue('A9', $semText);
                $sheet->getStyle("A8:A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A8:A9")->getFont()->setBold(true);

                $sheet->setCellValue('A10', "JURUSAN : " . strtoupper(str_replace('JURUSAN ', '', ($this->filters['identitas_laporan'] ?? 'SEMUA JURUSAN'))));
                
                $namaTingkatan = 'SEMUA TINGKATAN';
                if (!empty($this->filters['tingkatan_id'])) {
                    $tingkatanObj = \App\Models\Tingkatan::find($this->filters['tingkatan_id']);
                    if ($tingkatanObj) {
                        $namaTingkatan = $tingkatanObj->nama_tingkatan;
                    }
                }
                $sheet->setCellValue('A11', "TINGKATAN : " . strtoupper($namaTingkatan));
                $sheet->setCellValue('A12', "STATUS : " . ($isActive ? 'AKTIF' : 'NON AKTIF'));

                $sheet->getStyle("A13:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER, 
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true
                    ],
                ]);
                $sheet->getStyle("A13:{$lastCol}13")->getFont()->setBold(true);

                $ttgRow = $lastRow + 2;
                $sheet->mergeCells("G{$ttgRow}:{$lastCol}{$ttgRow}"); 
                $sheet->setCellValue("G{$ttgRow}", ($this->kontak->kabupaten_kota ?? 'Tasikmalaya') . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("G{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Mengetahui,");
                $sheet->mergeCells("G{$ttgRow}:{$lastCol}{$ttgRow}");
                $sheet->setCellValue("G{$ttgRow}", "Menyetujui,");
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Waka Kurikulum");
                $sheet->mergeCells("G{$ttgRow}:{$lastCol}{$ttgRow}");
                $sheet->setCellValue("G{$ttgRow}", "Kepala Sekolah");
                $sheet->getStyle("A{$ttgRow}:{$lastCol}{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $imageRow = $ttgRow + 1;
                $sheet->getRowDimension($imageRow)->setRowHeight(40); 

                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 1)->first();
                $wakaKur = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 2)->first();

                if ($wakaKur && $wakaKur->file_ttd) {
                    $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $wakaKur->file_ttd));
                    if (file_exists($pathWaka)) {
                        $drawingW = new Drawing();
                        $drawingW->setPath($pathWaka);
                        $drawingW->setHeight(48);
                        $drawingW->setCoordinates("B{$imageRow}");
                        $drawingW->setOffsetX(35); 
                        $drawingW->setEditAs('oneCell');
                        $drawingW->setWorksheet($pSheet);
                    }
                }

                if ($kepsek && $kepsek->file_ttd) {
                    $pathKepsek = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $kepsek->file_ttd));
                    if (file_exists($pathKepsek)) {
                        $drawingK = new Drawing();
                        $drawingK->setPath($pathKepsek);
                        $drawingK->setHeight(48);
                        $drawingK->setCoordinates("H{$imageRow}");
                        $drawingK->setOffsetX(30); 
                        $drawingK->setEditAs('oneCell');
                        $drawingK->setWorksheet($pSheet);
                    }
                }

                $namaRow = $imageRow + 1;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '____________________') . " )"); 
                $sheet->mergeCells("G{$namaRow}:{$lastCol}{$namaRow}");
                $sheet->setCellValue("G{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                $sheet->mergeCells("G{$nipRow}:{$lastCol}{$nipRow}");
                $sheet->setCellValue("G{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
            },
        ];
    }
}