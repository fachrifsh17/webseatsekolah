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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Siswa;
use App\Models\TahunAjaran;

class PresensiExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $daysInMonth, $year, $month, $role, $tahunAjaran, $hariLiburNasional, $selectedTa;
    private $rowNumber = 0;
    private $totalL = 0, $totalP = 0;
    private $grandTotal = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

    public function __construct($tahunAjaranId, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $role = 'walikelas', $tahunAjaranDisplay = '-')
    {
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->dataKelas = $dataKelas;
        $this->role = $role;
        $this->tahunAjaran = $tahunAjaranDisplay;
        $this->selectedTa = TahunAjaran::find($tahunAjaranId);

        if (str_contains($this->labelWaktu, 'Bulan-')) {
            $parts = explode('-', $this->labelWaktu);
            $this->month = (int)$parts[1];
            $this->year = isset($parts[3]) ? (int)$parts[3] : (int)date('Y');
            $this->daysInMonth = Carbon::create($this->year, $this->month)->daysInMonth;
            
            $this->hariLiburNasional = DB::table('kalender_akademik')
                ->where('kategori', 'Libur')
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->where(function($q) {
                    $q->where(function($sq) {
                        $sq->whereMonth('tanggal_mulai', $this->month)->whereYear('tanggal_mulai', $this->year);
                    })->orWhere(function($sq) {
                        $sq->whereMonth('tanggal_selesai', $this->month)->whereYear('tanggal_selesai', $this->year);
                    });
                })->get();
        } else {
            $this->daysInMonth = 0;
            $this->hariLiburNasional = collect();
        }
    }

    public function startCell(): string { return 'A12'; }

    public function query() 
    { 
        return Siswa::query()
            ->whereHas('riwayatKelas', function($q) {
                $q->where('kelas_id', $this->dataKelas->id);
                if ($this->selectedTa) { $q->where('tahun_ajaran_id', $this->selectedTa->id); }
            })
            ->where('is_active', true)
            ->with(['presensi' => function($q) {
                $q->where('kelas_id', $this->dataKelas->id);
                if ($this->selectedTa) { $q->where('tahun_ajaran_id', $this->selectedTa->id); }
                if ($this->daysInMonth > 0) {
                    $q->whereMonth('tanggal', $this->month)->whereYear('tanggal', $this->year);
                }
            }])
            ->orderBy('nama_lengkap', 'asc');
    }

    public function headings(): array 
    {
        if ($this->daysInMonth > 0) {
            $header = ['NO', 'NIS', 'NISN', 'NAMA LENGKAP', 'JK'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
            return [
                array_merge($header, ['KETERANGAN', '', '', '', 'CATATAN']),
                array_merge(array_fill(0, count($header), ''), ['H', 'S', 'I', 'A', ''])
            ];
        }
        return ['NO', 'NIS', 'NISN', 'NAMA LENGKAP', 'JK', 'STATUS', 'KETERANGAN'];
    }

    public function map($siswa): array 
    {
        $this->rowNumber++;
        $isLaki = (strtolower($siswa->jenis_kelamin) == 'laki-laki' || strtolower($siswa->jenis_kelamin) == 'l');
        $jk = $isLaki ? 'L' : 'P';
        $isLaki ? $this->totalL++ : $this->totalP++;
        
        $baseData = [
            $this->rowNumber,
            "'" . ($siswa->nis ?? '-'),
            "'" . ($siswa->nisn ?? '-'),
            strtoupper($siswa->nama_lengkap),
            $jk
        ];

        if ($this->daysInMonth > 0) {
            $rowPresensi = [];
            $absenSiswa = $siswa->presensi;
            $rekapSiswa = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $targetDate = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');
                $absen = $absenSiswa->first(fn($item) => Carbon::parse($item->tanggal)->format('Y-m-d') === $targetDate);
                
                if ($absen) {
                    $statusChar = strtoupper(substr(trim($absen->status), 0, 1));
                    $rowPresensi[] = $statusChar;
                    if (array_key_exists($statusChar, $rekapSiswa)) {
                        $rekapSiswa[$statusChar]++;
                        $this->grandTotal[$statusChar]++;
                    }
                } else {
                    $tglCarbon = Carbon::parse($targetDate);
                    $isWeekend = $tglCarbon->isWeekend(); 
                    $isLiburKalender = $this->hariLiburNasional->contains(function($v) use ($targetDate) {
                        $start = Carbon::parse($v->tanggal_mulai)->format('Y-m-d');
                        $end = Carbon::parse($v->tanggal_selesai)->format('Y-m-d');
                        return $targetDate >= $start && $targetDate <= $end;
                    });
                    $rowPresensi[] = ($isWeekend || $isLiburKalender) ? 'L' : '';
                }
            }
            return array_merge($baseData, $rowPresensi, [$rekapSiswa['H'] ?: '0', $rekapSiswa['S'] ?: '0', $rekapSiswa['I'] ?: '0', $rekapSiswa['A'] ?: '0', '']);
        }
        return array_merge($baseData, ['', '']);
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial');
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(10);

        $sheet->getStyle("A12:{$lastCol}13")->getFont()->setBold(true);
        $sheet->getStyle("A12:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);

        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(4);

        if ($this->daysInMonth > 0) {
            $rekapStartColNum = 5 + $this->daysInMonth + 1;
            for ($i = 6; $i < $rekapStartColNum; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(3);
            }
            for ($i = $rekapStartColNum; $i <= $rekapStartColNum + 3; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(4);
            }
            $sheet->getColumnDimension($lastCol)->setWidth(15);
            $sheet->getStyle("A12:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D14:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                foreach(['A','B','C','D','E'] as $col) {
                    $sheet->mergeCells("{$col}12:{$col}13");
                }

                $rekapStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 1);
                $rekapEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 4);
                $sheet->mergeCells("{$rekapStartCol}12:{$rekapEndCol}12");
                $sheet->setCellValue("{$rekapStartCol}12", "KETERANGAN");
                $sheet->mergeCells("{$lastCol}12:{$lastCol}13");

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
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle("A8")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A10', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A11', "Tahun Ajaran: {$this->tahunAjaran}"); 
                $sheet->setCellValue($lastCol . "11", "Periode: " . str_replace('-', ' ', $this->labelWaktu));
                $sheet->getStyle($lastCol . "11")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $rekapLRow = $lastRow + 1;
                $sheet->mergeCells("A{$rekapLRow}:D{$rekapLRow}");
                $sheet->setCellValue("A{$rekapLRow}", "JUMLAH SISWA LAKI-LAKI (L)");
                $sheet->setCellValue("E{$rekapLRow}", $this->totalL);

                $rekapPRow = $lastRow + 2;
                $sheet->mergeCells("A{$rekapPRow}:D{$rekapPRow}");
                $sheet->setCellValue("A{$rekapPRow}", "JUMLAH SISWA PEREMPUAN (P)");
                $sheet->setCellValue("E{$rekapPRow}", $this->totalP);

                $totalRow = $lastRow + 3;
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", "TOTAL KESELURUHAN SISWA");
                $sheet->setCellValue("E{$totalRow}", ($this->totalL + $this->totalP));
                
                $colH = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 1);
                $colS = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 2);
                $colI = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 3);
                $colA = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 4);

                $sheet->setCellValue($colH . $totalRow, $this->grandTotal['H']);
                $sheet->setCellValue($colS . $totalRow, $this->grandTotal['S']);
                $sheet->setCellValue($colI . $totalRow, $this->grandTotal['I']);
                $sheet->setCellValue($colA . $totalRow, $this->grandTotal['A']);

                $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$rekapLRow}:A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $wakaKesiswaan = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                    ->where('jabatans.slug', 'waka-kesiswaan')
                    ->select('guru_staf.nama', 'guru_staf.nip')->first();

                $waliKelasQuery = DB::table('guru_staf')
                    ->where('id', $this->dataKelas->wali_kelas_id)
                    ->select('nama', 'nip')
                    ->first();

                if (in_array($this->role, ['admin', 'kesiswaan'])) {
                    $jabatanKiri = "Pembina OSIS / Kesiswaan,";
                    $namaKiri = "........................";
                    $nipKiri = "-";
                } else {
                    $jabatanKiri = "Walikelas,";
                    $namaKiri = $waliKelasQuery->nama ?? 'Nama Guru';
                    $nipKiri = $waliKelasQuery->nip ?? '-';
                }

                $ttdRow = $totalRow + 3;
                
                $sheet->mergeCells("A{$ttdRow}:D{$ttdRow}");
                $sheet->setCellValue("A{$ttdRow}", "Mengetahui,");
                $sheet->mergeCells("A" . ($ttdRow + 1) . ":D" . ($ttdRow + 1));
                $sheet->setCellValue("A" . ($ttdRow + 1), $jabatanKiri);
                $sheet->mergeCells("A" . ($ttdRow + 5) . ":D" . ($ttdRow + 5));
                $sheet->setCellValue("A" . ($ttdRow + 5), "( " . strtoupper($namaKiri) . " )");
                $sheet->mergeCells("A" . ($ttdRow + 6) . ":D" . ($ttdRow + 6));
                $sheet->setCellValue("A" . ($ttdRow + 6), "NIP. " . $nipKiri);

                $sheet->mergeCells("{$rekapStartCol}{$ttdRow}:{$lastCol}{$ttdRow}");
                $sheet->setCellValue("{$rekapStartCol}{$ttdRow}", $kotaKab . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->mergeCells("{$rekapStartCol}" . ($ttdRow + 1) . ":{$lastCol}" . ($ttdRow + 1));
                $sheet->setCellValue("{$rekapStartCol}" . ($ttdRow + 1), "Waka Kesiswaan,");
                $sheet->mergeCells("{$rekapStartCol}" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5));
                $sheet->setCellValue("{$rekapStartCol}" . ($ttdRow + 5), "( " . strtoupper($wakaKesiswaan->nama ?? '____________________') . " )");
                $sheet->mergeCells("{$rekapStartCol}" . ($ttdRow + 6) . ":{$lastCol}" . ($ttdRow + 6));
                $sheet->setCellValue("{$rekapStartCol}" . ($ttdRow + 6), "NIP. " . ($wakaKesiswaan->nip ?? '...........................'));

                $sheet->getStyle("A{$ttdRow}:{$lastCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5))->getFont()->setBold(true);

                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}