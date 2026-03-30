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

    public function __construct($query, $profil, $kontak, $kelas = null, $filters = [])
    {
        $this->query = $query;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->kelasObj = $kelas;
        $this->filters = $filters;
    }

    public function startCell(): string { return 'A17'; }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID SISWA', 'NIS', 'NISN', 'NIK', 'NAMA LENGKAP', 'AGAMA', 'TEMPAT LAHIR', 
            'TANGGAL LAHIR', 'JENIS KELAMIN', 'KELAS', 'TAHUN ANGKATAN', 'NO TELP SISWA', 
            'ALAMAT', 'STATUS AKTIF' 
        ];
    }

    public function map($siswa): array
    {
        $this->rowNumber++;
        $riwayatAktif = $siswa->riwayatKelas ? $siswa->riwayatKelas->where('is_active', 1)->first() : null;
        $namaKelasSiswa = $riwayatAktif && $riwayatAktif->kelas ? $riwayatAktif->kelas->nama_kelas : '-';
        
        $jkRaw = strtolower($siswa->jenis_kelamin);
        $jkLengkap = ($jkRaw === 'l' || $jkRaw === 'laki-laki') ? 'LAKI-LAKI' : 'PEREMPUAN';

        return [
            $this->rowNumber,
            $siswa->id, 
            $siswa->nis ? "'" . $siswa->nis : '-',
            $siswa->nisn ? "'" . $siswa->nisn : '-',
            $siswa->nik ? "'" . $siswa->nik : '-',
            strtoupper($siswa->nama_lengkap),
            strtoupper($siswa->agama ?? '-'),
            strtoupper($siswa->tempat_lahir),
            $siswa->tanggal_lahir ? date('d-m-Y', strtotime($siswa->tanggal_lahir)) : '-',
            $jkLengkap,
            $namaKelasSiswa,
            $siswa->tahun_angkatan ?? '-',
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
                $drawingProv->setHeight(80);
                $drawingProv->setCoordinates('A1');
                $drawingProv->setOffsetX(45); 
                $drawingProv->setOffsetY(15); 
                $drawingProv->setEditAs('oneCell'); 
                $drawings[] = $drawingProv;
            }
        }

        $imageRow = 17 + $this->rowNumber + 4;

        $waka = DB::table('struktur_jabatan')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'waka-kesiswaan')
            ->select('struktur_jabatan.file_ttd')
            ->first();

        if ($waka && $waka->file_ttd) {
            $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $waka->file_ttd));
            if (file_exists($pathWaka)) {
                $drawingWaka = new Drawing();
                $drawingWaka->setPath($pathWaka);
                $drawingWaka->setHeight(70); 
                $drawingWaka->setCoordinates('C' . $imageRow);
                $drawingWaka->setOffsetX(0);
                $drawingWaka->setOffsetY(-5);
                $drawingWaka->setEditAs('oneCell'); 
                $drawings[] = $drawingWaka;
            }
        }

        $ks = DB::table('struktur_jabatan')
            ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
            ->where('jabatans.slug', 'kepala-sekolah')
            ->select('struktur_jabatan.file_ttd')
            ->first();

        if ($ks && $ks->file_ttd) {
            $path = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $ks->file_ttd));
            if (file_exists($path)) {
                $drawing = new Drawing();
                $drawing->setPath($path);
                $drawing->setHeight(70);
                $drawing->setCoordinates('N' . $imageRow);
                $drawing->setOffsetX(50);
                $drawing->setOffsetY(-5);
                $drawing->setEditAs('oneCell'); 
                $drawings[] = $drawing;
            }
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'O'; 
                $dataLastRow = 17 + $this->rowNumber;

                $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
                $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(10);
                $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(15);
                $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(15);
                $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(20);
                $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(40);

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
                $sheet->setCellValue('A8', 'DATA INDUK PESERTA DIDIK');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sem = DB::table('semesters')->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                        ->where('semesters.is_active', 1)->select('semesters.nama', 'tahun_ajaran.nama as thn')->first();
                $txtTahun = $sem ? "TAHUN PELAJARAN " . $sem->thn . " - SEMESTER " . strtoupper($sem->nama) : "TAHUN PELAJARAN -";
                
                $sheet->mergeCells("A9:{$lastCol}9"); 
                $sheet->setCellValue('A9', $txtTahun);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusan = $this->filters['nama_jurusan'] ?? 'SEMUA';
                $tingkat = $this->filters['nama_tingkatan'] ?? 'SEMUA';
                $namaKelas = $this->filters['nama_kelas'] ?? 'SEMUA KELAS';
                $agamaFilter = $this->filters['agama'] ?? 'SEMUA';
                $angkatanFilter = $this->filters['tahun_angkatan'] ?? 'SEMUA';
                $jkFilter = $this->filters['jenis_kelamin'] ?? 'SEMUA';
                
                $statusText = 'SEMUA';
                if(isset($this->filters['is_active'])) {
                    $statusText = $this->filters['is_active'] == 1 ? 'AKTIF' : 'TIDAK AKTIF';
                }

                $sheet->setCellValue('A10', "JURUSAN : " . strtoupper($jurusan));
                $sheet->setCellValue('A11', "TINGKAT : " . strtoupper($tingkat));
                $sheet->setCellValue('A12', "KELAS   : " . strtoupper($namaKelas));
                $sheet->setCellValue('A13', "AGAMA   : " . strtoupper($agamaFilter));
                $sheet->setCellValue('A14', "ANGKATAN: " . strtoupper($angkatanFilter));
                $sheet->setCellValue('A15', "JENIS KELAMIN: " . strtoupper($jkFilter));
                $sheet->setCellValue('A16', "STATUS  : " . strtoupper($statusText));

                $sheet->getStyle("A17:{$lastCol}17")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A17:{$lastCol}{$dataLastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                for ($row = 18; $row <= $dataLastRow; $row++) {
                    $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    if ($sheet->getCell("O{$row}")->getValue() === 'Tidak Aktif') {
                        $sheet->getStyle("O{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                }

                $ttdRow = $dataLastRow + 2; 
                $lokasiTtd = strtoupper($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');
                
                $waka = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'waka-kesiswaan')->select('guru_staf.nama', 'guru_staf.nip')->first();
                $ks = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip')->first();

                $sheet->setCellValue("C" . $ttdRow, "Mengetahui,");
                $sheet->setCellValue("C" . ($ttdRow + 1), "Waka Kesiswaan,");
                
                $sheet->setCellValue("N" . $ttdRow, $lokasiTtd . ", " . strtoupper(Carbon::now()->translatedFormat('d F Y')));
                $sheet->setCellValue("N" . ($ttdRow + 1), "Kepala Sekolah,");

                $sheet->getStyle("C{$ttdRow}:N" . ($ttdRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $namaRow = $ttdRow + 5;
                $sheet->setCellValue("C" . $namaRow, "( " . strtoupper($waka->nama ?? '____________________') . " )");
                $sheet->setCellValue("N" . $namaRow, "( " . strtoupper($ks->nama ?? '____________________') . " )");
                
                $sheet->getStyle("C{$namaRow}:N{$namaRow}")->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("C{$namaRow}:N{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $nipRow = $namaRow + 1;
                $sheet->setCellValue("C" . $nipRow, "NIP. " . ($waka->nip ?? '........................'));
                $sheet->setCellValue("N" . $nipRow, "NIP. " . ($ks->nip ?? '........................'));
                $sheet->getStyle("C{$nipRow}:N{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}