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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class PresensiGuruMapelExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $tahun, $bulan, $isFilterKelas, $semesterId, $role;
    private $rowNumber = 0;
    private $firstRecord = null;
    
    /** @var \Illuminate\Support\Collection|array */
    private $pertemuanData;
    
    private $mingguGroups = [];
    private $totalL = 0, $totalP = 0;
    private $grandTotal = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

    public function __construct($query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $isFilterKelas = false, $bulan = null, $tahun = null, $semesterId = null, $role = 'guru')
    {
        $this->query = $query;
        $this->labelWaktu = $labelWaktu;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->guruStaf = is_array($guruStaf) ? (object)$guruStaf : $guruStaf;
        $this->tahunAjaran = $tahunAjaran;
        $this->isFilterKelas = $isFilterKelas;
        $this->semesterId = $semesterId;
        $this->role = $role;

        $this->bulan = $bulan ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[2] : date('m'));
        $this->tahun = $tahun ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[3] : date('Y'));

        $sampleQuery = clone $this->query;
        $this->firstRecord = $sampleQuery->with(['guruMapel.mapel', 'guruMapel.kelas', 'guruMapel.guru'])->first();

        $this->pertemuanData = collect();

        if ($this->isFilterKelas) {
            $baseQuery = clone $this->query;
            $this->pertemuanData = $baseQuery->whereMonth('tanggal', $this->bulan)
                ->whereYear('tanggal', $this->tahun)
                ->orderBy('tanggal', 'asc')
                ->select('id', 'tanggal')
                ->get();

            foreach ($this->pertemuanData as $p) {
                $weekOfMonth = Carbon::parse($p->tanggal)->weekOfMonth;
                $this->mingguGroups[$weekOfMonth][] = $p->id;
            }
        }
    }

    public function startCell(): string
    {
        return 'A14';
    }

    public function query()
    {
        if ($this->isFilterKelas) {
            $presensiIds = $this->pertemuanData->pluck('id');
            return DB::table('presensi_siswa_detail')
                ->join('siswa', 'presensi_siswa_detail.siswa_id', '=', 'siswa.id')
                ->whereIn('presensi_guru_mapel_id', $presensiIds)
                ->select('siswa.id', 'siswa.nama_lengkap', 'siswa.nis', 'siswa.nisn', 'siswa.jenis_kelamin')
                ->groupBy('siswa.id', 'siswa.nama_lengkap', 'siswa.nis', 'siswa.nisn', 'siswa.jenis_kelamin')
                ->orderBy('siswa.nama_lengkap', 'asc');
        }
        return $this->query->with(['guruMapel.mapel', 'guruMapel.kelas', 'guruMapel.guru', 'presensiDetail.siswa']);
    }

    public function headings(): array
    {
        if ($this->isFilterKelas) {
            $row1 = ['NO', 'NIS', 'NISN', 'NAMA LENGKAP', 'JK'];
            foreach ($this->mingguGroups as $weekNum => $ids) {
                $row1[] = "MINGGU " . $weekNum;
                for ($i = 1; $i < count($ids); $i++) {
                    $row1[] = '';
                }
            }
            $row2 = ['', '', '', '', ''];
            $pCounter = 1;
            foreach ($this->mingguGroups as $ids) {
                foreach ($ids as $id) {
                    $row2[] = $pCounter++;
                }
            }
            return [
                array_merge($row1, ['KETERANGAN', '', '', '', 'CATATAN']),
                array_merge($row2, ['H', 'S', 'I', 'A', ''])
            ];
        }
        return ['NO', 'TANGGAL', 'JAM', 'MATA PELAJARAN', 'KELAS', 'MATERI', 'H', 'S', 'I', 'A', 'TOTAL'];
    }

    public function map($data): array
    {
        $this->rowNumber++;
        $dataObj = is_array($data) ? (object)$data : $data;

        if ($this->isFilterKelas) {
            $isLaki = (strtolower($dataObj->jenis_kelamin ?? '') == 'laki-laki' || strtolower($dataObj->jenis_kelamin ?? '') == 'l');
            $jk = $isLaki ? 'L' : 'P';
            $isLaki ? $this->totalL++ : $this->totalP++;

            $attendance = DB::table('presensi_siswa_detail')
                ->whereIn('presensi_guru_mapel_id', $this->pertemuanData->pluck('id'))
                ->where('siswa_id', $dataObj->id)
                ->get();

            $row = [$this->rowNumber, "'" . ($dataObj->nis ?? '-'), "'" . ($dataObj->nisn ?? '-'), strtoupper($dataObj->nama_lengkap ?? '-'), $jk];
            $rekap = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

            foreach ($this->mingguGroups as $ids) {
                foreach ($ids as $pId) {
                    $statusSiswa = $attendance->where('presensi_guru_mapel_id', $pId)->first();
                    if ($statusSiswa) {
                        $st = strtoupper(substr($statusSiswa->status, 0, 1));
                        $row[] = $st;
                        if (isset($rekap[$st])) {
                            $rekap[$st]++;
                            $this->grandTotal[$st]++;
                        }
                    } else {
                        $row[] = '-';
                    }
                }
            }
            return array_merge($row, [$rekap['H'], $rekap['S'], $rekap['I'], $rekap['A'], '']);
        }

        return [
            $this->rowNumber,
            Carbon::parse($dataObj->tanggal)->format('d/m/Y'),
            $dataObj->jam_masuk . '-' . $dataObj->jam_keluar,
            $dataObj->guruMapel->mapel->nama_mapel ?? '-',
            $dataObj->guruMapel->kelas->nama_kelas ?? '-',
            $dataObj->materi ?? '-',
            $dataObj->presensiDetail->where('status', 'hadir')->count(),
            $dataObj->presensiDetail->where('status', 'sakit')->count(),
            $dataObj->presensiDetail->where('status', 'izin')->count(),
            $dataObj->presensiDetail->where('status', 'alfa')->count(),
            $dataObj->presensiDetail->count()
        ];
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

        if ($this->isFilterKelas) {
            $sheet->getColumnDimension('A')->setWidth(10);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(30);
            $sheet->getColumnDimension('E')->setWidth(4);

            $jmlP = count($this->pertemuanData);
            $colEndPertemuan = 5 + $jmlP;
            for ($i = 6; $i <= $colEndPertemuan; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(10);
            }

            for ($i = $colEndPertemuan + 1; $i <= $colEndPertemuan + 4; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(5);
            }

            $sheet->getColumnDimension($lastCol)->setWidth(15);
            $sheet->getStyle("D16:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $jmlP = count($this->pertemuanData);

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
                        $drawingProv->setWorksheet($sheet->getDelegate());
                    }
                }

                if ($this->isFilterKelas) {
                    foreach (['A', 'B', 'C', 'D', 'E'] as $c) {
                        $sheet->mergeCells("{$c}14:{$c}15");
                    }
                    $currCol = 6;
                    foreach ($this->mingguGroups as $ids) {
                        $count = count($ids);
                        if ($count > 1) {
                            $s = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currCol);
                            $e = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currCol + $count - 1);
                            $sheet->mergeCells("{$s}14:{$e}14");
                        }
                        $currCol += $count;
                    }
                    $rkpS = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 1);
                    $rkpE = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 4);
                    $sheet->mergeCells("{$rkpS}14:{$rkpE}14");
                    $sheet->setCellValue("{$rkpS}14", "KETERANGAN");
                    $sheet->mergeCells("{$lastCol}14:{$lastCol}15");
                }

                $prov = strtoupper($this->kontak->provinsi ?? 'JAWA BARAT');
                $cabdin = strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                $alamat = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$prov}");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->setCellValue('A3', "{$cabdin} WILAYAH XII");
                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->setCellValue('A4', $namaSekolah);
                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->setCellValue('A5', "{$alamat}, {$desaKec}, {$kotaKab} - " . ($this->kontak->provinsi ?? 'Jawa Barat'));
                $sheet->mergeCells("A6:{$lastCol}6");
                $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));

                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

                $sheet->mergeCells("A8:{$lastCol}8");
                $sheet->setCellValue('A8', 'LAPORAN PRESENSI SISWA (PER PERTEMUAN)');
                $sheet->getStyle('A8')->getFont()->setSize(12)->setBold(true);
                $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $labelTahunAjaran = is_object($this->tahunAjaran) ? $this->tahunAjaran->nama : (is_string($this->tahunAjaran) ? $this->tahunAjaran : $this->tahun . "/" . ($this->tahun + 1));
                $labelSemester = ($this->semesterId == 1) ? 'GANJIL' : 'GENAP';

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $labelTahunAjaran . " - SEMESTER " . $labelSemester);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A9")->getFont()->setBold(true);

                $namaGuru = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? '-');
                $nipGuru = $this->guruStaf->nip ?? ($this->firstRecord->guruMapel->guru->nip ?? '-');
                $namaKelas = $this->firstRecord->guruMapel->kelas->nama_kelas ?? '-';
                $namaMapel = $this->firstRecord->guruMapel->mapel->nama_mapel ?? '-';

                $sheet->setCellValue('A10', "Nama Guru: " . $namaGuru);
                $sheet->setCellValue('A11', "Kelas: " . $namaKelas);
                $sheet->setCellValue('A12', "Mata Pelajaran: " . $namaMapel);
                $sheet->setCellValue('A13', "Periode: " . Carbon::create($this->tahun, $this->bulan, 1)->translatedFormat('F Y'));

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

                    $colH = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 1);
                    $colS = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 2);
                    $colI = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 3);
                    $colA = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 4);

                    $sheet->setCellValue($colH . $totalRow, $this->grandTotal['H']);
                    $sheet->setCellValue($colS . $totalRow, $this->grandTotal['S']);
                    $sheet->setCellValue($colI . $totalRow, $this->grandTotal['I']);
                    $sheet->setCellValue($colA . $totalRow, $this->grandTotal['A']);

                    $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$rekapLRow}:{$lastCol}{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                $ttdRow = (isset($totalRow) ? $totalRow : $lastRow) + 3;
                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $kurikulum = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'waka-kurikulum')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();

                if (in_array($this->role, ['admin', 'kesiswaan'])) {
                    $labelKiri = "Waka Kurikulum,";
                    $namaKiri = $kurikulum->nama ?? '................';
                    $nipKiri = $kurikulum->nip ?? '................';
                    $ttdKiri = $kurikulum->file_ttd ?? null;
                    $labelKanan = "Kepala Sekolah,";
                    $namaKanan = $kepsek->nama ?? '................';
                    $nipKanan = $kepsek->nip ?? '................';
                    $ttdKanan = $kepsek->file_ttd ?? null;
                } else {
                    $labelKiri = "Guru Mata Pelajaran,";
                    $namaKiri = $namaGuru;
                    $nipKiri = $nipGuru;
                    $ttdKiri = null;
                    $labelKanan = "Waka Kurikulum,";
                    $namaKanan = $kurikulum->nama ?? '................';
                    $nipKanan = $kurikulum->nip ?? '................';
                    $ttdKanan = $kurikulum->file_ttd ?? null;
                }

                $sheet->mergeCells("A{$ttdRow}:D{$ttdRow}");
                $sheet->setCellValue("A" . $ttdRow, "Mengetahui,");
                $sheet->mergeCells("A" . ($ttdRow + 1) . ":D" . ($ttdRow + 1));
                $sheet->setCellValue("A" . ($ttdRow + 1), $labelKiri);
                $sheet->mergeCells("A" . ($ttdRow + 5) . ":D" . ($ttdRow + 5));
                $sheet->setCellValue("A" . ($ttdRow + 5), "( " . strtoupper($namaKiri) . " )");
                $sheet->mergeCells("A" . ($ttdRow + 6) . ":D" . ($ttdRow + 6));
                $sheet->setCellValue("A" . ($ttdRow + 6), "NIP. " . $nipKiri);

                $startColTTDKanan = $this->isFilterKelas ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + $jmlP + 1) : 'G';
                $sheet->mergeCells("{$startColTTDKanan}{$ttdRow}:{$lastCol}{$ttdRow}");
                $sheet->setCellValue($startColTTDKanan . $ttdRow, $kotaKab . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 1) . ":{$lastCol}" . ($ttdRow + 1));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 1), $labelKanan);
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 5), "( " . strtoupper($namaKanan) . " )");
                $sheet->mergeCells("{$startColTTDKanan}" . ($ttdRow + 6) . ":{$lastCol}" . ($ttdRow + 6));
                $sheet->setCellValue($startColTTDKanan . ($ttdRow + 6), "NIP. " . $nipKanan);

                if ($ttdKiri && file_exists(storage_path('app/' . $ttdKiri))) {
                    $drawingKiri = new Drawing();
                    $drawingKiri->setPath(storage_path('app/' . $ttdKiri));
                    $drawingKiri->setHeight(50);
                    $drawingKiri->setCoordinates('B' . ($ttdRow + 2));
                    $drawingKiri->setWorksheet($sheet->getDelegate());
                }
                if ($ttdKanan && file_exists(storage_path('app/' . $ttdKanan))) {
                    $drawingKanan = new Drawing();
                    $drawingKanan->setPath(storage_path('app/' . $ttdKanan));
                    $drawingKanan->setHeight(50);
                    $drawingKanan->setCoordinates($startColTTDKanan . ($ttdRow + 2));
                    $drawingKanan->setWorksheet($sheet->getDelegate());
                }

                $sheet->getStyle("A{$ttdRow}:{$lastCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . ($ttdRow + 5) . ":{$lastCol}" . ($ttdRow + 5))->getFont()->setBold(true);
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }
}