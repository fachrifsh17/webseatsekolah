<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Siswa;

class PresensiExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $daysInMonth, $year, $month, $role, $semesterId, $semesterDisplay, $hariLiburNasional, $waliKelas, $guruId;
    private $rowNumber = 0;
    private $processedSiswa = [];
    private $totalL = 0, $totalP = 0;
    private $grandTotal = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

    public function __construct($semesterId, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $role = 'walikelas', $semesterDisplay = null, $guruId = null)
    {
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->dataKelas = $dataKelas;
        $this->role = $role;
        $this->semesterId = $semesterId;
        $this->semesterDisplay = $semesterDisplay;
        $this->guruId = $guruId;

        $this->waliKelas = DB::table('kelas_wali_kelas')
            ->join('guru_staf', 'kelas_wali_kelas.guru_staf_id', '=', 'guru_staf.id')
            ->where('kelas_wali_kelas.kelas_id', $this->dataKelas->id)
            ->where('kelas_wali_kelas.semester_id', $this->semesterId)
            ->select('guru_staf.nama', 'guru_staf.nip')
            ->first();

        if (str_contains($labelWaktu, 'Bulan-')) {
            $parts = explode('-', $labelWaktu);
            $this->month = (int)($parts[1] ?? date('m'));
            $this->year = (int)($parts[3] ?? ($parts[2] ?? date('Y')));
        } else {
            $this->month = (int)date('m');
            $this->year = (int)date('Y');
        }

        $this->daysInMonth = Carbon::create($this->year, $this->month, 1)->daysInMonth;

        $this->hariLiburNasional = DB::table('kalender_akademik')
            ->where('semester_id', $this->semesterId)
            ->where('kategori', 'Libur')
            ->get();
    }

    public function startCell(): string { return 'A14'; }

    public function query()
    {
        return Siswa::query()
            ->select('siswa.*')
            ->join('siswa_kelas', 'siswa.id', '=', 'siswa_kelas.siswa_id')
            ->where('siswa_kelas.kelas_id', $this->dataKelas->id)
            ->where('siswa_kelas.semester_id', $this->semesterId)
            ->whereExists(function ($query) {
                $q = $query->select(DB::raw(1))
                    ->from('presensi_detail')
                    ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
                    ->whereColumn('presensi_detail.siswa_id', 'siswa.id')
                    ->where('presensi.semester_id', $this->semesterId)
                    ->where('presensi.kelas_id', $this->dataKelas->id)
                    ->whereMonth('presensi.tanggal', $this->month)
                    ->whereYear('presensi.tanggal', $this->year);

                if ($this->guruId) {
                    $q->where('presensi.guru_id', $this->guruId);
                }
            })
            ->orderBy('siswa.nama_lengkap', 'asc');
    }

    public function headings(): array
    {
        $header = ['NO', 'NIS', 'NISN', 'NAMA LENGKAP', 'JK'];
        for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
        return [
            array_merge($header, ['KETERANGAN', '', '', '', 'CATATAN']),
            array_merge(array_fill(0, count($header), ''), ['H', 'S', 'I', 'A', ''])
        ];
    }

    public function map($siswa): array
    {
        if (in_array($siswa->id, $this->processedSiswa)) { return []; }
        $this->processedSiswa[] = $siswa->id;
        $this->rowNumber++;
        $isLaki = (strtolower($siswa->jenis_kelamin ?? '') == 'laki-laki' || strtolower($siswa->jenis_kelamin ?? '') == 'l');
        $jk = $isLaki ? 'L' : 'P';
        $isLaki ? $this->totalL++ : $this->totalP++;

        $row = [$this->rowNumber, "'" . ($siswa->nis ?? '-'), "'" . ($siswa->nisn ?? '-'), strtoupper($siswa->nama_lengkap ?? '-'), $jk];
        $rekap = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

        $presensiQuery = DB::table('presensi_detail')
            ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
            ->where('presensi_detail.siswa_id', $siswa->id)
            ->where('presensi.semester_id', $this->semesterId)
            ->where('presensi.kelas_id', $this->dataKelas->id)
            ->whereMonth('presensi.tanggal', $this->month)
            ->whereYear('presensi.tanggal', $this->year);

        if ($this->guruId) { $presensiQuery->where('presensi.guru_id', $this->guruId); }
        $presensiBulanIni = $presensiQuery->select('presensi.tanggal', 'presensi_detail.status')->get();

        for ($d = 1; $d <= $this->daysInMonth; $d++) {
            $dateObj = Carbon::create($this->year, $this->month, $d);
            $dateString = $dateObj->toDateString();
            $isLibur = $this->hariLiburNasional->contains(fn($item) => $dateString >= $item->tanggal_mulai && $dateString <= $item->tanggal_selesai);

            if ($dateObj->isWeekend() || $isLibur) {
                $row[] = 'L';
            } else {
                $pTgl = $presensiBulanIni->where('tanggal', $dateString)->first();
                if ($pTgl) {
                    $st = strtoupper(substr($pTgl->status, 0, 1));
                    $row[] = $st;
                    if (isset($rekap[$st])) {
                        $rekap[$st]++;
                        $this->grandTotal[$st]++;
                    }
                } else { $row[] = ''; }
            }
        }
        return [array_merge($row, [$rekap['H'], $rekap['S'], $rekap['I'], $rekap['A'], ''])];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet->getStyle("A14:{$lastCol}15")->getFont()->setBold(true);
        $sheet->getStyle("A14:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER]
        ]);
        
        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(10); 
        $sheet->getColumnDimension('C')->setWidth(11);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(4);

        $lastDayColNum = 5 + $this->daysInMonth;
        for ($i = 6; $i <= $lastDayColNum; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(3.5);
        }
        for ($i = $lastDayColNum + 1; $i <= $lastDayColNum + 4; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(4.5);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(15);
        $sheet->getStyle("D16:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $pSheet = $sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                foreach (['A', 'B', 'C', 'D', 'E'] as $col) { $sheet->mergeCells("{$col}14:{$col}15"); }
                $rekapStartColNum = 5 + $this->daysInMonth + 1;
                $rekapStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rekapStartColNum);
                $rekapEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rekapStartColNum + 3);
                $sheet->mergeCells("{$rekapStartCol}14:{$rekapEndCol}14");
                $sheet->setCellValue("{$rekapStartCol}14", "KETERANGAN");
                $sheet->mergeCells("{$lastCol}14:{$lastCol}15");

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setName('Logo Provinsi');
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(35);
                        $drawingProv->setOffsetY(10);
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');
                $pos = $this->kontak->kode_pos ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} {$pos} {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setSize(14);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle('A8')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $tahunAjaranNama = $this->semesterDisplay->tahunAjaran->nama ?? '-';
                $semesterNama = strtoupper($this->semesterDisplay->nama ?? '-');
                $sheet->mergeCells("A9:{$lastCol}9"); $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $tahunAjaranNama . " - SEMESTER " . $semesterNama);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A9")->getFont()->setBold(true);

                $sheet->setCellValue('A10', "Nama Wali Kelas: " . ($this->waliKelas->nama ?? '-'));
                $sheet->setCellValue('A11', "Kelas: " . $this->namaKelas);
                $sheet->setCellValue('A12', "Periode: " . Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y'));

                $rekapLRow = $lastRow + 1;
                $sheet->mergeCells("A{$rekapLRow}:D{$rekapLRow}"); $sheet->setCellValue("A{$rekapLRow}", "JUMLAH SISWA LAKI-LAKI (L)"); $sheet->setCellValue("E{$rekapLRow}", $this->totalL);
                $rekapPRow = $lastRow + 2;
                $sheet->mergeCells("A{$rekapPRow}:D{$rekapPRow}"); $sheet->setCellValue("A{$rekapPRow}", "JUMLAH SISWA PEREMPUAN (P)"); $sheet->setCellValue("E{$rekapPRow}", $this->totalP);
                $totalRow = $lastRow + 3;
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}"); $sheet->setCellValue("A{$totalRow}", "TOTAL KESELURUHAN SISWA"); $sheet->setCellValue("E{$totalRow}", ($this->totalL + $this->totalP));

                $sheet->setCellValue($rekapStartCol . $totalRow, $this->grandTotal['H']);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rekapStartColNum + 1) . $totalRow, $this->grandTotal['S']);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rekapStartColNum + 2) . $totalRow, $this->grandTotal['I']);
                $sheet->setCellValue(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rekapStartColNum + 3) . $totalRow, $this->grandTotal['A']);

                $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                $ttdRow = $totalRow + 3;
                $waka = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'waka-kesiswaan')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();

                $sheet->getRowDimension($ttdRow + 2)->setRowHeight(45); 

                $sheet->mergeCells("A{$ttdRow}:D{$ttdRow}"); $sheet->setCellValue("A{$ttdRow}", ($kotaKab) . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->mergeCells("A" . ($ttdRow + 1) . ":D" . ($ttdRow + 1));
                
                if ($this->role === 'admin') {
                    $sheet->setCellValue("A" . ($ttdRow + 1), "Waka Kesiswaan,");
                    $namaKiri = $waka->nama ?? '................'; $nipKiri = $waka->nip ?? '................';
                    if ($waka && $waka->file_ttd) {
                        $this->insertTtd($sheet, $waka->file_ttd, 'C' . ($ttdRow + 2), 45);
                    }
                } else {
                    $sheet->setCellValue("A" . ($ttdRow + 1), "Wali Kelas,");
                    $namaKiri = $this->waliKelas->nama ?? '................'; $nipKiri = $this->waliKelas->nip ?? '................';
                }

                $sheet->mergeCells("A" . ($ttdRow + 3) . ":D" . ($ttdRow + 3)); $sheet->setCellValue("A" . ($ttdRow + 3), "( " . strtoupper($namaKiri) . " )");
                $sheet->mergeCells("A" . ($ttdRow + 4) . ":D" . ($ttdRow + 4)); $sheet->setCellValue("A" . ($ttdRow + 4), "NIP. " . $nipKiri);

                $startColTTDKananNum = 5 + $this->daysInMonth + 1;
                $startColTTDKanan = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColTTDKananNum);
                $sheet->mergeCells("{$startColTTDKanan}{$ttdRow}:{$lastCol}{$ttdRow}"); $sheet->setCellValue($startColTTDKanan . $ttdRow, "Mengetahui,");
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 1) . ":{$lastCol}" . ($ttdRow + 1)); $sheet->setCellValue($startColTTDKanan . ($ttdRow + 1), "Kepala Sekolah,");
                
                if ($kepsek && $kepsek->file_ttd) {
                    $this->insertTtd($sheet, $kepsek->file_ttd, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($startColTTDKananNum + 1) . ($ttdRow + 2), 25);
                }

                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 3) . ":{$lastCol}" . ($ttdRow + 3)); $sheet->setCellValue($startColTTDKanan . ($ttdRow + 3), "( " . strtoupper($kepsek->nama ?? '................') . " )");
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 4) . ":{$lastCol}" . ($ttdRow + 4)); $sheet->setCellValue($startColTTDKanan . ($ttdRow + 4), "NIP. " . ($kepsek->nip ?? '................'));

                $sheet->getStyle("A{$ttdRow}:{$lastCol}" . ($ttdRow + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($ttdRow + 3) . ":{$lastCol}" . ($ttdRow + 3))->getFont()->setBold(true);

                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }

    private function insertTtd($sheet, $filePath, $coordinates, $offsetX = 10)
    {
        $path = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $filePath));
        if (file_exists($path)) {
            $drawing = new Drawing();
            $drawing->setPath($path);
            $drawing->setHeight(55);
            $drawing->setCoordinates($coordinates);
            $drawing->setOffsetX($offsetX);
            $drawing->setOffsetY(2);
            $drawing->setEditAs('oneCell');
            $drawing->setWorksheet($sheet->getDelegate());
        }
    }
}