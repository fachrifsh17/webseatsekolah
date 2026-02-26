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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PresensiGuruMapelExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $daysInMonth, $tahun, $bulan, $isFilterKelas, $tahunAjaranId, $role;
    private $rowNumber = 0;
    private $firstRecord = null;
    private $processedSiswa = [];
    private $listLibur = null;
    private $totalL = 0, $totalP = 0;
    private $grandTotal = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

    public function __construct($query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $isFilterKelas = false, $bulan = null, $tahun = null, $tahunAjaranId = null, $role = 'guru')
    {
        $this->query = $query;
        $this->labelWaktu = $labelWaktu;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->guruStaf = $guruStaf;
        $this->tahunAjaran = $tahunAjaran;
        $this->isFilterKelas = $isFilterKelas;
        $this->tahunAjaranId = $tahunAjaranId;
        $this->role = $role;
        
        $this->bulan = $bulan ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[2] : date('m'));
        $this->tahun = $tahun ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[3] : date('Y'));
        $this->daysInMonth = Carbon::create($this->tahun, $this->bulan)->daysInMonth;

        $this->listLibur = DB::table('kalender_akademik')
            ->where('tahun_ajaran_id', $this->tahunAjaranId)
            ->where('kategori', 'Libur')
            ->get();
    }

    public function startCell(): string { return 'A13'; }

    public function query()
    {
        return $this->query->with(['mapel', 'kelas', 'getBySiswaDetil.siswa', 'guruMapel.guru']);
    }

    public function headings(): array
    {
        if ($this->isFilterKelas) {
            $header = ['NO', 'NIS', 'NISN', 'NAMA LENGKAP', 'JK'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
            return [
                array_merge($header, ['KETERANGAN', '', '', '', 'CATATAN']),
                array_merge(array_fill(0, count($header), ''), ['H', 'S', 'I', 'A', ''])
            ];
        }
        return ['NO', 'TANGGAL', 'JAM', 'MATA PELAJARAN', 'KELAS', 'MATERI', 'H', 'S', 'I', 'A', 'TOTAL'];
    }

    public function map($presensi): array
    {
        if (!$this->firstRecord) { $this->firstRecord = $presensi; }

        if ($this->isFilterKelas) {
            $rows = [];
            $presensiBulanIni = DB::table('presensi_guru_mapel')
                ->where('kelas_id', $this->firstRecord->kelas_id)
                ->where('mata_pelajaran_id', $this->firstRecord->mata_pelajaran_id)
                ->whereMonth('tanggal', $this->bulan)
                ->whereYear('tanggal', $this->tahun)
                ->get();

            $presensiIds = $presensiBulanIni->pluck('id');
            $allAttendanceData = DB::table('presensi_siswa_detail')
                ->whereIn('presensi_guru_mapel_id', $presensiIds)
                ->get();

            $siswaList = DB::table('siswa')
                ->join('siswa_kelas', 'siswa.id', '=', 'siswa_kelas.siswa_id')
                ->where('siswa_kelas.kelas_id', $this->firstRecord->kelas_id)
                ->where('siswa_kelas.tahun_ajaran_id', $this->tahunAjaranId)
                ->where('siswa_kelas.is_active', 1)
                ->where('siswa.is_active', 1)
                ->select('siswa.id', 'siswa.nama_lengkap', 'siswa.nis', 'siswa.nisn', 'siswa.jenis_kelamin')
                ->orderBy('siswa.nama_lengkap', 'asc')
                ->get();
            
            foreach ($siswaList as $siswa) {
                if (in_array($siswa->id, $this->processedSiswa)) { continue; }
                $this->processedSiswa[] = $siswa->id;

                $this->rowNumber++;
                $isLaki = (strtolower($siswa->jenis_kelamin) == 'laki-laki' || strtolower($siswa->jenis_kelamin) == 'l');
                $jk = $isLaki ? 'L' : 'P';
                $isLaki ? $this->totalL++ : $this->totalP++;

                $row = [$this->rowNumber, "'" . ($siswa->nis ?? '-'), "'" . ($siswa->nisn ?? '-'), strtoupper($siswa->nama_lengkap ?? '-'), $jk];
                $rekap = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $dateObj = Carbon::create($this->tahun, $this->bulan, $d);
                    $dateString = $dateObj->toDateString();
                    $isLiburKalender = $this->listLibur->contains(fn($item) => $dateString >= $item->tanggal_mulai && $dateString <= $item->tanggal_selesai);

                    if ($dateObj->isWeekend() || $isLiburKalender) {
                        $row[] = 'L';
                    } else {
                        $pTgl = $presensiBulanIni->where('tanggal', $dateString)->first();
                        if ($pTgl) {
                            $statusSiswa = $allAttendanceData->where('presensi_guru_mapel_id', $pTgl->id)->where('siswa_id', $siswa->id)->first();
                            if ($statusSiswa) {
                                $st = strtoupper(substr($statusSiswa->status, 0, 1));
                                $row[] = $st;
                                if (isset($rekap[$st])) {
                                    $rekap[$st]++;
                                    $this->grandTotal[$st]++;
                                }
                            } else { $row[] = '-'; }
                        } else { $row[] = ''; }
                    }
                }
                $rows[] = array_merge($row, [$rekap['H'], $rekap['S'], $rekap['I'], $rekap['A'], '']);
            }
            return $rows;
        }

        $this->rowNumber++;
        return [
            $this->rowNumber,
            Carbon::parse($presensi->tanggal)->format('d/m/Y'),
            $presensi->jam_masuk . '-' . $presensi->jam_keluar,
            $presensi->mapel->nama_mapel ?? '-',
            $presensi->kelas->nama_kelas ?? '-',
            $presensi->materi ?? '-',
            $presensi->getBySiswaDetil->where('status', 'hadir')->count(),
            $presensi->getBySiswaDetil->where('status', 'sakit')->count(),
            $presensi->getBySiswaDetil->where('status', 'izin')->count(),
            $presensi->getBySiswaDetil->where('status', 'alfa')->count(),
            $presensi->getBySiswaDetil->count()
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $headerRange = "A13:{$lastCol}" . ($this->isFilterKelas ? '14' : '13');
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle("A13:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ]);
        $sheet->getStyle("A13:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($this->isFilterKelas) {
            $sheet->getColumnDimension('A')->setWidth(4);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(30);
            $sheet->getColumnDimension('E')->setWidth(4);
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
            $sheet->getStyle("D15:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } else {
            $sheet->getColumnDimension('A')->setWidth(5);
            $sheet->getColumnDimension('D')->setWidth(25);
            $sheet->getColumnDimension('F')->setWidth(35);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                if ($this->isFilterKelas) {
                    foreach(['A','B','C','D','E'] as $col) {
                        $sheet->mergeCells("{$col}13:{$col}14");
                    }
                    $rekapStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 1);
                    $rekapEndCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $this->daysInMonth + 4);
                    $sheet->mergeCells("{$rekapStartCol}13:{$rekapEndCol}13");
                    $sheet->setCellValue("{$rekapStartCol}13", "KETERANGAN");
                    $sheet->mergeCells("{$lastCol}13:{$lastCol}14");
                }

                $prov = strtoupper($this->kontak->provinsi ?? 'JAWA BARAT');
                $cabdin = strtoupper($this->profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN WILAYAH XII');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                $alamat = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$prov}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $cabdin);
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $namaSekolah);
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamat}, {$desaKec}, {$kotaKab} - " . ($this->kontak->provinsi ?? 'Jawa Barat'));
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', 'LAPORAN PRESENSI SISWA (MATA PELAJARAN)');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $namaGuru = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? '-');
                $nipGuru = $this->guruStaf->nip ?? ($this->firstRecord->guruMapel->guru->nip ?? '-');

                $sheet->setCellValue('A10', "Nama Guru: " . $namaGuru);
                $sheet->setCellValue('A11', "Kelas: " . ($this->firstRecord->kelas->nama_kelas ?? '-'));
                $sheet->setCellValue('A12', "Mata Pelajaran: " . ($this->firstRecord->mapel->nama_mapel ?? '-'));
                $sheet->setCellValue($lastCol . '12', "Periode: " . $this->labelWaktu);
                $sheet->getStyle($lastCol . '12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if ($this->isFilterKelas) {
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
                } else {
                    $totalRow = $lastRow;
                    $colH = 'G';
                }

                $ttdRow = $totalRow + 3;
                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                    ->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip')->first();
                
                $kurikulum = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                    ->where('jabatans.slug', 'waka-kurikulum')->select('guru_staf.nama', 'guru_staf.nip')->first();

                if (in_array($this->role, ['admin', 'kesiswaan'])) {
                    $labelKiri = "Waka Kurikulum,";
                    $namaKiri = $kurikulum->nama ?? '........................';
                    $nipKiri = $kurikulum->nip ?? '........................';
                    $labelKanan = "Kepala Sekolah,";
                    $namaKanan = $kepsek->nama ?? '........................';
                    $nipKanan = $kepsek->nip ?? '........................';
                } else {
                    $labelKiri = "Guru Mata Pelajaran,";
                    $namaKiri = $namaGuru;
                    $nipKiri = $nipGuru;
                    $labelKanan = "Waka Kurikulum,";
                    $namaKanan = $kurikulum->nama ?? '........................';
                    $nipKanan = $kurikulum->nip ?? '........................';
                }

                $sheet->mergeCells("A{$ttdRow}:D{$ttdRow}");
                $sheet->setCellValue("A" . $ttdRow, "Mengetahui,");
                $sheet->mergeCells("A" . ($ttdRow + 1) . ":D" . ($ttdRow + 1));
                $sheet->setCellValue("A" . ($ttdRow + 1), $labelKiri);
                $sheet->mergeCells("A" . ($ttdRow + 5) . ":D" . ($ttdRow + 5));
                $sheet->setCellValue("A" . ($ttdRow + 5), "( " . strtoupper($namaKiri) . " )");
                $sheet->mergeCells("A" . ($ttdRow + 6) . ":D" . ($ttdRow + 6));
                $sheet->setCellValue("A" . ($ttdRow + 6), "NIP. " . $nipKiri);

                $startColTTDKanan = $this->isFilterKelas ? $colH : 'H';
                $sheet->mergeCells("{$startColTTDKanan}{$ttdRow}:{$lastCol}{$ttdRow}");
                $sheet->setCellValue($startColTTDKanan . $ttdRow, $kotaKab . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 1) . ":{$lastCol}" . ($ttdRow + 1));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 1), $labelKanan);
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 5), "( " . strtoupper($namaKanan) . " )");
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 6) . ":{$lastCol}" . ($ttdRow + 6));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 6), "NIP. " . $nipKanan);

                $sheet->getStyle("A{$ttdRow}:{$lastCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5))->getFont()->setBold(true);
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
            },
        ];
    }
}