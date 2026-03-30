<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrangtuaExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithCustomStartCell
{
    protected $queryBuilder, $profil, $kontak, $kelasData, $jurusanData, $filters, $semesterText, $tahunAjaranText, $semesterId, $tingkatanText;

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

        $tingkatId = $filters['tingkatan_id'] ?? null;
        if ($tingkatId) {
            $tingkat = DB::table('tingkatan')->where('id', $tingkatId)->value('nama_tingkatan');
            $this->tingkatanText = $tingkat ? strtoupper($tingkat) : 'SEMUA TINGKATAN';
        } else {
            $this->tingkatanText = $this->kelasData?->tingkatan?->nama_tingkatan ? strtoupper($this->kelasData->tingkatan->nama_tingkatan) : 'SEMUA TINGKATAN';
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
            'ID', 'NAMA ORANG TUA', 'NO. TELEPON', 'NIS', 'NISN', 'NAMA ANAK', 'KELAS', 'HUBUNGAN', 'STATUS'
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

            if (!empty($this->filters['tingkatan_id'])) {
                if ($riwayat->kelas?->tingkatan_id != $this->filters['tingkatan_id']) return false;
            }

            return true;
        });

        if ($anakFiltered->isEmpty()) {
            return [];
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
                    strtoupper($riwayat->kelas->nama_kelas ?? '-'), 
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
                $lastCol = 'I';
                $dataLastRow = $sheet->getHighestRow();

                $columns = ['A'=>8, 'B'=>30, 'C'=>18, 'D'=>12, 'E'=>15, 'F'=>30, 'G'=>15, 'H'=>20, 'I'=>12];
                foreach ($columns as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(80);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(45); 
                        $drawingProv->setOffsetY(15);
                        $drawingProv->setEditAs('oneCell');
                        $drawingProv->setWorksheet($sheet);
                    }
                }

                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 1)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $wakaKes = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 3)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $cabdin = strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $pos = $this->kontak->kode_pos ?? '-';
                $telepon = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $cabdin);
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $namaSekolah);
                
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} {$pos} {$provAsli}");
                
                $sheet->mergeCells("A6:{$lastCol}6"); 
                $sheet->setCellValue('A6', "Telp: {$telepon} | Email: {$email} | NPSN: {$npsn}");
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setSize(14);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', 'DATA ORANG TUA / WALI MURID');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A9:{$lastCol}9"); 
                $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $this->tahunAjaranText . " - SEMESTER " . $this->semesterText);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A10', "JURUSAN : " . strtoupper($this->jurusanData ? $this->jurusanData->nama_jurusan : 'SEMUA JURUSAN'));
                $sheet->setCellValue('A11', "TINGKAT : " . $this->tingkatanText);
                $sheet->setCellValue('A12', "KELAS   : " . strtoupper($this->kelasData ? $this->kelasData->nama_kelas : 'SEMUA KELAS'));

                $sheet->getStyle("A13:{$lastCol}13")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A13:{$lastCol}{$dataLastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                $ttdRow = $dataLastRow + 2; 
                $lokasiTtd = strtoupper($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');
                
                $sheet->setCellValue("B" . $ttdRow, "Mengetahui,");
                $sheet->setCellValue("B" . ($ttdRow + 1), "Waka Kesiswaan,");
                
                $sheet->setCellValue("H" . $ttdRow, $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue("H" . ($ttdRow + 1), "Kepala Sekolah,");

                $sheet->getStyle("B{$ttdRow}:H" . ($ttdRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $imageRow = $ttdRow + 2;
                if ($wakaKes && $wakaKes->file_ttd) {
                    $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $wakaKes->file_ttd));
                    if (file_exists($pathWaka)) {
                        $drawWaka = new Drawing();
                        $drawWaka->setPath($pathWaka);
                        $drawWaka->setHeight(70);
                        $drawWaka->setCoordinates("B{$imageRow}"); 
                        $drawWaka->setOffsetX(40); 
                        $drawWaka->setOffsetY(0);
                        $drawWaka->setEditAs('oneCell');
                        $drawWaka->setWorksheet($sheet);
                    }
                }

                if ($kepsek && $kepsek->file_ttd) {
                    $pathKepsek = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $kepsek->file_ttd));
                    if (file_exists($pathKepsek)) {
                        $drawKepsek = new Drawing();
                        $drawKepsek->setPath($pathKepsek);
                        $drawKepsek->setHeight(70);
                        $drawKepsek->setCoordinates("H{$imageRow}"); 
                        $drawKepsek->setOffsetX(15); 
                        $drawKepsek->setOffsetY(0);
                        $drawKepsek->setEditAs('oneCell');
                        $drawKepsek->setWorksheet($sheet);
                    }
                }

                $namaRow = $ttdRow + 5;
                $sheet->setCellValue("B" . $namaRow, "( " . strtoupper($wakaKes->nama ?? '____________________') . " )");
                $sheet->setCellValue("H" . $namaRow, "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                
                $sheet->getStyle("B{$namaRow}:H{$namaRow}")->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("B{$namaRow}:H{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $nipRow = $namaRow + 1;
                $sheet->setCellValue("B" . $nipRow, "NIP. " . ($wakaKes->nip ?? '........................'));
                $sheet->setCellValue("H" . $nipRow, "NIP. " . ($kepsek->nip ?? '........................'));
                $sheet->getStyle("B{$nipRow}:H{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}