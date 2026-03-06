<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SiswaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell, WithDrawings
{
    protected $query, $profil, $kontak, $kelasObj, $filters;
    private $rowNumber = 0;
    private $lastRow = 0;

    public function __construct($query, $profil, $kontak, $kelas = null, $filters = [])
    {
        $this->query = $query;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->kelasObj = $kelas;
        $this->filters = $filters;
    }

    public function startCell(): string { return 'A15'; }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID SISWA', 'NIS', 'NISN', 'NAMA LENGKAP', 'TEMPAT LAHIR', 
            'TANGGAL LAHIR', 'JENIS KELAMIN', 'KELAS', 'NO TELP SISWA', 
            'ALAMAT', 'STATUS AKTIF' 
        ];
    }

    public function map($siswa): array
    {
        $this->rowNumber++;
        $riwayatAktif = $siswa->riwayatKelas ? $siswa->riwayatKelas->where('is_active', 1)->first() : null;
        $namaKelasSiswa = $riwayatAktif && $riwayatAktif->kelas ? $riwayatAktif->kelas->nama_kelas : '-';
        $jkRaw = strtolower($siswa->jenis_kelamin);
        $jkLengkap = ($jkRaw === 'l' || $jkRaw === 'laki-laki') ? 'Laki-laki' : 'Perempuan';

        return [
            $this->rowNumber,
            $siswa->id, 
            $siswa->nis ? "'" . $siswa->nis : '-',
            $siswa->nisn ? "'" . $siswa->nisn : '-',
            strtoupper($siswa->nama_lengkap),
            strtoupper($siswa->tempat_lahir),
            $siswa->tanggal_lahir ? date('d-m-Y', strtotime($siswa->tanggal_lahir)) : '-',
            $jkLengkap,
            $namaKelasSiswa,
            $siswa->no_telp_siswa, 
            $siswa->alamat,
            $siswa->is_active ? 'Aktif' : 'Tidak Aktif', 
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function drawings()
    {
        $drawings = [];

        if (!empty($this->profil->logo_provinsi)) {
            $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
            if (file_exists($pathProv)) {
                $drawingProv = new Drawing();
                $drawingProv->setName('Logo Provinsi');
                $drawingProv->setPath($pathProv);
                $drawingProv->setHeight(75);
                $drawingProv->setCoordinates('A1');
                $drawingProv->setOffsetX(5);
                $drawingProv->setOffsetY(5);
                $drawings[] = $drawingProv;
            }
        }

        $ks = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'kepala-sekolah')
            ->select('struktur_jabatan.file_ttd')
            ->first();

        $waka = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'waka-kesiswaan')
            ->select('struktur_jabatan.file_ttd')
            ->first();

        $dataCount = $this->query->count();
        $this->lastRow = 15 + ($dataCount > 0 ? $dataCount : 0);
        $imageRow = $this->lastRow + 2;

        if ($ks && $ks->file_ttd) {
            $path = storage_path('app/' . $ks->file_ttd);
            if (file_exists($path)) {
                $drawing = new Drawing();
                $drawing->setName('TTD Kepala Sekolah');
                $drawing->setPath($path);
                $drawing->setHeight(50);
                $drawing->setCoordinates('K' . $imageRow);
                $drawings[] = $drawing;
            }
        }

        if ($waka && $waka->file_ttd) {
            $pathWaka = storage_path('app/' . $waka->file_ttd);
            if (file_exists($pathWaka)) {
                $drawingWaka = new Drawing();
                $drawingWaka->setName('TTD Waka Kesiswaan');
                $drawingWaka->setPath($pathWaka);
                $drawingWaka->setHeight(50);
                $drawingWaka->setCoordinates('B' . $imageRow);
                $drawings[] = $drawingWaka;
            }
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'L'; 
                $actualLastRow = $sheet->getHighestRow();

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(18);

                $prov = strtoupper($this->kontak->provinsi ?? 'JAWA BARAT');
                $cabdin = strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                $alamatLengkap = trim(($this->kontak->alamat_jalan ?? '') . " Des. " . ($this->kontak->desa_kelurahan ?? '') . " Kec. " . ($this->kontak->kecamatan ?? '') . " " . ($this->kontak->kabupaten_kota ?? ''));

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$prov}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $cabdin);
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $namaSekolah);
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', $alamatLengkap);
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '') . " | Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? ''));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', 'DATA INDUK PESERTA DIDIK');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);

                $sem = DB::table('semesters')->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                        ->where('semesters.is_active', 1)->select('semesters.nama', 'tahun_ajaran.nama as thn')->first();
                $txtTahun = $sem ? "TAHUN PELAJARAN " . $sem->thn . " - SEMESTER " . strtoupper($sem->nama) : "TAHUN PELAJARAN -";
                
                $sheet->mergeCells("A9:{$lastCol}9"); 
                $sheet->setCellValue('A9', $txtTahun);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                $sheet->getStyle("A8:A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusan = $this->filters['nama_jurusan'] ?? ($this->filters['jurusan'] ?? '-');
                if ($jurusan === '-' && $this->kelasObj && $this->kelasObj->jurusan) {
                    $jurusan = $this->kelasObj->jurusan->nama_jurusan;
                } elseif ($jurusan === '-' && !empty($this->filters['jurusan_id'])) {
                    $jurusan = DB::table('jurusans')->where('id', $this->filters['jurusan_id'])->value('nama_jurusan');
                }

                $tingkat = $this->filters['nama_tingkat'] ?? ($this->filters['tingkat'] ?? '-');
                if ($tingkat === '-' && $this->kelasObj && $this->kelasObj->tingkatan) {
                    $tingkat = $this->kelasObj->tingkatan->nama_tingkatan;
                } elseif ($tingkat === '-' && !empty($this->filters['tingkatan_id'])) {
                    $tingkat = DB::table('tingkatan')->where('id', $this->filters['tingkatan_id'])->value('nama_tingkatan');
                }

                $namaKelas = $this->filters['nama_kelas'] ?? ($this->filters['kelas'] ?? 'SEMUA KELAS');
                if ($namaKelas === 'SEMUA KELAS' && $this->kelasObj) {
                    $namaKelas = $this->kelasObj->nama_kelas;
                }

                $isActive = $this->filters['is_active'] ?? 1;
                $statusText = $isActive == 0 ? 'TIDAK AKTIF' : 'AKTIF';

                $sheet->setCellValue('A10', "JURUSAN : " . strtoupper($jurusan ?? '-'));
                $sheet->setCellValue('A11', "TINGKAT : " . strtoupper($tingkat ?? '-'));
                $sheet->setCellValue('A12', "KELAS   : " . strtoupper($namaKelas));
                $sheet->setCellValue('A13', "STATUS  : " . strtoupper($statusText));

                $sheet->getStyle("A15:{$lastCol}15")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
                ]);

                $sheet->getStyle("A15:{$lastCol}{$actualLastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN
                        ]
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                for ($row = 16; $row <= $actualLastRow; $row++) {
                    $sheet->getStyle("A{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    if ($sheet->getCell("L{$row}")->getValue() === 'Tidak Aktif') {
                        $sheet->getStyle("L{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                }

                $ttdRow = $actualLastRow + 2; 
                $kabKota = strtoupper($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');
                
                $waka = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                    ->where('jabatans.slug', 'waka-kesiswaan')
                    ->select('guru_staf.nama', 'guru_staf.nip')
                    ->first();
                
                $ks = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                    ->where('jabatans.slug', 'kepala-sekolah')
                    ->select('guru_staf.nama', 'guru_staf.nip')
                    ->first();

                $sheet->setCellValue("B" . $ttdRow, "Mengetahui,");
                $sheet->setCellValue("B" . ($ttdRow + 1), "Waka Kesiswaan,");
                $sheet->setCellValue("B" . ($ttdRow + 5), "( " . strtoupper($waka->nama ?? '____________________') . " )");
                $sheet->setCellValue("B" . ($ttdRow + 6), "NIP. " . ($waka->nip ?? '........................'));
                
                $sheet->setCellValue("K" . $ttdRow, $kabKota . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue("K" . ($ttdRow + 1), "Kepala Sekolah,");
                $sheet->setCellValue("K" . ($ttdRow + 5), "( " . strtoupper($ks->nama ?? '____________________') . " )");
                $sheet->setCellValue("K" . ($ttdRow + 6), "NIP. " . ($ks->nip ?? '........................'));

                $sheet->getStyle("B" . ($ttdRow + 5) . ":K" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("B" . $ttdRow . ":B" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K" . $ttdRow . ":K" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}