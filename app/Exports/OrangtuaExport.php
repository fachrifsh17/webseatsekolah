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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrangtuaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $queryBuilder, $profil, $kontak, $kelasData, $jurusanData, $filters, $semesterText, $tahunAjaranText, $semesterId;

    public function __construct($queryBuilder, $profil, $kontak, $kelasData = null, $filters = [], $jurusanData = null)
    {
        $this->queryBuilder = $queryBuilder;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->kelasData = $kelasData;
        $this->jurusanData = $jurusanData;
        $this->filters = $filters;

        $semId = $filters['semester_id'] ?? null;
        
        if ($semId) {
            $sem = DB::table('semesters')
                ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                ->where('semesters.id', $semId)
                ->select('semesters.nama as nama_semester', 'tahun_ajaran.nama as nama_tahun_ajaran')
                ->first();
            
            $this->semesterText = $sem ? strtoupper($sem->nama_semester) : '-';
            $this->tahunAjaranText = $sem ? strtoupper($sem->nama_tahun_ajaran) : '-';
            $this->semesterId = $semId;
        } else {
            $sem = DB::table('semesters')
                ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                ->where('semesters.is_active', 1)
                ->select('semesters.nama as nama_semester', 'tahun_ajaran.nama as nama_tahun_ajaran', 'semesters.id')
                ->first();
                
            $this->semesterText = $sem ? strtoupper($sem->nama_semester) : '-';
            $this->tahunAjaranText = $sem ? strtoupper($sem->nama_tahun_ajaran) : '-';
            $this->semesterId = $sem?->id;
        }
    }

    public function startCell(): string { return 'A13'; }

    public function query()
    {
        return $this->queryBuilder;
    }

    public function headings(): array
    {
        return [
            'ID Orang Tua',
            'Nama Orang Tua',
            'No. Telepon',
            'NIS Anak',
            'NISN Anak',
            'Nama Anak',
            'Tingkatan',
            'Kelas',
            'Hubungan',
            'Status Aktif'
        ];
    }

    public function map($orangtua): array
    {
        $rows = [];

        $anakFiltered = $orangtua->anak->filter(function($anak) {
            $riwayat = $anak->riwayatKelas->where('semester_id', $this->semesterId)->first();
            
            if (!$riwayat) return false;
            if ($this->kelasData && $riwayat->kelas_id != $this->kelasData->id) return false;
            if ($this->jurusanData && $riwayat->kelas?->jurusan_id != $this->jurusanData->id) return false;
            
            return true;
        });

        if ($anakFiltered->isEmpty()) {
            $rows[] = [
                $orangtua->id,
                strtoupper($orangtua->nama_lengkap),
                "'" . $orangtua->telepon,
                '-', '-', '-', '-', '-', '-',
                $orangtua->is_active ? 'AKTIF' : 'NON-AKTIF',
            ];
        } else {
            foreach ($anakFiltered as $anak) {
                $riwayat = $anak->riwayatKelas->where('semester_id', $this->semesterId)->first();
                $hubungan = $anak->pivot->hubungan ?? '-';

                $rows[] = [
                    $orangtua->id,
                    strtoupper($orangtua->nama_lengkap),
                    "'" . $orangtua->telepon,
                    "'" . $anak->nis,
                    "'" . $anak->nisn,
                    strtoupper($anak->nama_lengkap),
                    $riwayat->kelas?->tingkatan?->nama_tingkatan ?? '-',
                    $riwayat->kelas->nama_kelas ?? '-',
                    strtoupper($hubungan),
                    $orangtua->is_active ? 'AKTIF' : 'NON-AKTIF',
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate(); 
                $lastCol = 'J';
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
                        $drawingProv->setWorksheet($sheet);
                    }
                }

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $wakaKes = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 3) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $provKapital = strtoupper($this->kontak->provinsi ?? 'Jawa Barat');
                $alamatLengkap = ($this->kontak->alamat_jalan ?? '-') . ", Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-') . ", " . ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', $alamatLengkap);
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'DATA ORANG TUA / WALI MURID');
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', "TAHUN PELAJARAN " . $this->tahunAjaranText . " - SEMESTER " . $this->semesterText);
                
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A7:A8")->getFont()->setBold(true);

                $tingkatanName = $this->kelasData?->tingkatan?->nama_tingkatan ?? 'Semua Tingkatan';
                $jurusanName = $this->jurusanData ? $this->jurusanData->nama_jurusan : 'Semua Jurusan';
                $kelasName = $this->kelasData ? $this->kelasData->nama_kelas : 'Semua Kelas';

                $sheet->setCellValue('A9', "Tingkatan : " . $tingkatanName);
                $sheet->setCellValue('A10', "Jurusan   : " . $jurusanName);
                $sheet->setCellValue('A11', "Kelas     : " . $kelasName);
                
                $sheet->getStyle("A9:A11")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ]);

                $sheet->getStyle("A13:{$lastCol}13")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'F2F2F2']]
                ]);

                $sheet->getStyle("A13:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '000000']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                $ttgRow = $lastRow + 3;
                $lokasi = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                
                $sheet->mergeCells("H{$ttgRow}:J{$ttgRow}");
                $sheet->setCellValue("H{$ttgRow}", $lokasi . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("H{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Mengetahui,\nWaka Kesiswaan");
                $sheet->mergeCells("H{$ttgRow}:J{$ttgRow}");
                $sheet->setCellValue("H{$ttgRow}", "Menyetujui,\nKepala Sekolah");
                
                $sheet->getStyle("A{$ttgRow}:J{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("A{$ttgRow}:J{$ttgRow}")->getFont()->setBold(true);

                $imageRow = $ttgRow + 1;
                
                if ($wakaKes && $wakaKes->file_ttd && file_exists(storage_path('app/' . $wakaKes->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Waka');
                    $drawing->setPath(storage_path('app/' . $wakaKes->file_ttd));
                    $drawing->setHeight(50);
                    $drawing->setCoordinates('B' . $imageRow);
                    $drawing->setOffsetX(20);
                    $drawing->setWorksheet($sheet);
                }

                if ($kepsek && $kepsek->file_ttd && file_exists(storage_path('app/' . $kepsek->file_ttd))) {
                    $drawing = new Drawing();
                    $drawing->setName('TTD Kepsek');
                    $drawing->setPath(storage_path('app/' . $kepsek->file_ttd));
                    $drawing->setHeight(50);
                    $drawing->setCoordinates('I' . $imageRow);
                    $drawing->setOffsetX(20);
                    $drawing->setWorksheet($sheet);
                }

                $namaRow = $imageRow + 3;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->mergeCells("H{$namaRow}:J{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKes->nama ?? '____________________') . " )");
                $sheet->setCellValue("H{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->getStyle("A{$namaRow}:J{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:J{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->mergeCells("H{$nipRow}:J{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKes->nip ?? '...........................'));
                $sheet->setCellValue("H{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:J{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}