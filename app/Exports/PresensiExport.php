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

class PresensiExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $daysInMonth, $year, $month, $role, $tahunAjaran, $hariLiburNasional;
    private $rowNumber = 0;
    private $processedSiswa = [];

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $role = 'walikelas', $tahunAjaran = '-')
    {
        $this->query = $query;
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->dataKelas = $dataKelas;
        $this->role = $role;
        $this->tahunAjaran = $tahunAjaran;

        if (str_contains($this->labelWaktu, 'Bulan-')) {
            $parts = explode('-', $this->labelWaktu);
            $this->month = (int)$parts[1];
            $this->year = (int)$parts[2];
            $this->daysInMonth = Carbon::create($this->year, $this->month)->daysInMonth;

            $this->hariLiburNasional = DB::table('kalender_akademik')
                ->where('kategori', 'Libur')
                ->whereMonth('tanggal_mulai', $this->month)
                ->whereYear('tanggal_mulai', $this->year)
                ->get();
        } else {
            $this->daysInMonth = 0;
            $this->hariLiburNasional = collect();
        }
    }

    public function startCell(): string { return 'A11'; }

    public function query() 
    { 
        return $this->query->with(['siswa.presensi' => function($q) {
                if ($this->daysInMonth > 0) {
                    $q->whereMonth('tanggal', $this->month)->whereYear('tanggal', $this->year);
                }
            }, 'guruStaf']);
    }

    public function headings(): array 
    {
        if ($this->daysInMonth > 0) {
            $header = ['NO', 'NAMA LENGKAP'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $header[] = $d;
            }
            return array_merge($header, ['PETUGAS']);
        }
        return ['NO', 'NAMA LENGKAP', 'STATUS', 'KETERANGAN', 'PETUGAS'];
    }

    public function map($presensi): array 
    {
        $namaPetugas = ucwords($presensi->guruStaf->nama ?? '-');

        if ($this->daysInMonth > 0) {
            if (in_array($presensi->siswa_id, $this->processedSiswa)) {
                return []; 
            }
            $this->processedSiswa[] = $presensi->siswa_id;
            
            $this->rowNumber++;
            $row = [$this->rowNumber, $presensi->siswa->nama_lengkap ?? '-'];
            $semuaAbsenSiswa = $presensi->siswa->presensi;

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $tglCarbon = Carbon::create($this->year, $this->month, $d);
                $tglCek = $tglCarbon->format('Y-m-d');

                $absen = $semuaAbsenSiswa->filter(function($item) use ($tglCek) {
                    return Carbon::parse($item->tanggal)->format('Y-m-d') === $tglCek;
                })->first();
                
                if ($absen) {
                    $row[] = strtoupper(substr($absen->status, 0, 1));
                } else {
                    $isWeekend = $tglCarbon->isWeekend();
                    $isLiburKalender = $this->hariLiburNasional->contains(function($value) use ($tglCek) {
                        return $tglCek >= $value->tanggal_mulai && $tglCek <= $value->tanggal_selesai;
                    });

                    $row[] = ($isWeekend || $isLiburKalender) ? 'L' : '-';
                }
            }
            $row[] = $namaPetugas;
            return $row;
        }

        $this->rowNumber++;
        return [
            $this->rowNumber,
            $presensi->siswa->nama_lengkap ?? '-',
            strtoupper(substr($presensi->status, 0, 1)),
            $presensi->keterangan ?? '-',
            $namaPetugas
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A11:{$lastCol}11")->getFont()->setBold(true);
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        if ($this->daysInMonth > 0) {
            for ($i = 3; $i <= ($this->daysInMonth + 2); $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(3);
                $sheet->getStyle("{$col}11:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension($lastCol)->setAutoSize(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-') . " | Akreditasi: " . ($this->profil->akreditasi ?? '-'));
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A1:{$lastCol}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A8', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A9', "Tahun Ajaran: {$this->tahunAjaran}"); 
                $sheet->setCellValue($lastCol . "9", "Periode: " . $this->labelWaktu);
                $sheet->getStyle($lastCol . "9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $jabatanLabel = "";
                $namaTtd = "";
                $nipTtd = "";

                if ($this->role === 'admin' || $this->role === 'kesiswaan') {
                    $jabatanLabel = "Waka Kesiswaan,";
                    $dataKesiswaan = DB::table('struktur_jabatan')
                        ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                        ->where('struktur_jabatan.jabatan_id', 3)
                        ->where('guru_staf.is_active', true)
                        ->select('guru_staf.nama', 'guru_staf.nip') 
                        ->first();
                    
                    $namaTtd = ucwords($dataKesiswaan->nama ?? 'Nama Kesiswaan');
                    $nipTtd = $dataKesiswaan->nip ?? '..........................';
                } else {
                    $jabatanLabel = "Wali Kelas,";
                    $namaTtd = ucwords($this->dataKelas->waliKelas->nama ?? 'Nama Wali Kelas');
                    $nipTtd = $this->dataKelas->waliKelas->nip ?? '..........................';
                }

                $ttdRow = $lastRow + 3;
                $sheet->setCellValue($lastCol . $ttdRow, "Tasikmalaya, " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue($lastCol . ($ttdRow + 1), $jabatanLabel);
                $sheet->setCellValue($lastCol . ($ttdRow + 5), "( " . $namaTtd . " )");
                $sheet->setCellValue($lastCol . ($ttdRow + 6), "NIP. " . $nipTtd);

                $sheet->getStyle($lastCol . $ttdRow . ":" . $lastCol . ($ttdRow + 6))
                      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($lastCol . ($ttdRow + 5))
                      ->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}