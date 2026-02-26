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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SiswaExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $namaKelas, $filters;
    private $rowNumber = 0;

    public function __construct($query, $profil, $kontak, $namaKelas = null, $filters = [])
    {
        $this->query = $query;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->filters = $filters;
        $this->namaKelas = is_object($namaKelas) ? $namaKelas->nama_kelas : $namaKelas;
    }

    // Tabel dimulai dari baris 14 agar info filter di atasnya tidak tertabrak
    public function startCell(): string { return 'A14'; }

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
            "'" . $siswa->nis,
            "'" . $siswa->nisn,
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'L'; 
                $lastRow = $sheet->getHighestRow();

                // --- KOP SURAT ---
                $prov = strtoupper($this->kontak->provinsi ?? 'JAWA BARAT');
                $cabdin = strtoupper($this->profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN WILAYAH XII');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG');
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

                // --- JUDUL ---
                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', 'DATA INDUK PESERTA DIDIK');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);

                $tahunObj = DB::table('tahun_ajaran')->where('id', $this->filters['tahun_ajaran_id'] ?? 0)->first() 
                            ?? DB::table('tahun_ajaran')->where('is_active', 1)->first();
                
                $txtTahun = $tahunObj ? "TAHUN PELAJARAN " . $tahunObj->nama . " - SEMESTER " . strtoupper($tahunObj->semester) : "TAHUN PELAJARAN -";
                $sheet->mergeCells("A9:{$lastCol}9"); 
                $sheet->setCellValue('A9', $txtTahun);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                $sheet->getStyle("A8:A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- INFO FILTER KE BAWAH (Bukan Menyamping) ---
                $namaJurusan = !empty($this->filters['jurusan_id']) 
                    ? DB::table('jurusans')->where('id', $this->filters['jurusan_id'])->value('nama_jurusan') 
                    : '';
                
                $statusVal = $this->filters['is_active'] ?? null;
                $statusText = ($statusVal === '0' || $statusVal === 0) ? 'TIDAK AKTIF' : ($statusVal == 1 ? 'AKTIF' : 'AKTIF');

                $sheet->setCellValue('A10', "JURUSAN : " . strtoupper($namaJurusan));
                $sheet->setCellValue('A11', "KELAS   : " . strtoupper($this->namaKelas ?? 'SEMUA KELAS'));
                $sheet->setCellValue('A12', "STATUS  : " . strtoupper($statusText));
                // getFont()->setBold(true) dihapus agar tidak tebal

                // --- STYLE HEADER TABEL ---
                $sheet->getStyle("A14:{$lastCol}14")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']]
                ]);

                // --- BORDER DAN ALIGNMENT DATA ---
                $sheet->getStyle("A14:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                for ($row = 15; $row <= $lastRow; $row++) {
                    $sheet->getStyle("A{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    if ($sheet->getCell("L{$row}")->getValue() !== 'Aktif') {
                        $sheet->getStyle("L{$row}")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                }

                // --- TANDA TANGAN ---
                $ttdRow = $lastRow + 3;
                $kabKota = strtoupper($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');
                $waka = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'waka-kesiswaan')->select('guru_staf.nama', 'guru_staf.nip')->first();
                $ks = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip')->first();

                $sheet->setCellValue("B" . $ttdRow, "Mengetahui,");
                $sheet->setCellValue("B" . ($ttdRow + 1), "Waka Kesiswaan,");
                $sheet->setCellValue("B" . ($ttdRow + 5), "( " . strtoupper($waka->nama ?? '........................') . " )");
                $sheet->setCellValue("B" . ($ttdRow + 6), "NIP. " . ($waka->nip ?? '........................'));
                $sheet->getStyle("B" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("B" . $ttdRow . ":B" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue("K" . $ttdRow, $kabKota . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue("K" . ($ttdRow + 1), "Kepala Sekolah,");
                $sheet->setCellValue("K" . ($ttdRow + 5), "( " . strtoupper($ks->nama ?? '........................') . " )");
                $sheet->setCellValue("K" . ($ttdRow + 6), "NIP. " . ($ks->nip ?? '........................'));
                $sheet->getStyle("K" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("K" . $ttdRow . ":K" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}