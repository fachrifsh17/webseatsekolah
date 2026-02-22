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

    public function __construct($tahunAjaranId, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $role = 'walikelas', $tahunAjaranDisplay = '-')
    {
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->profil = $profil;
        $this->kontak = $kontak;
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

    public function startCell(): string { return 'A11'; }

    public function query() 
    { 
        return Siswa::query()
            ->where('kelas_id', $this->dataKelas->id)
            ->where('is_active', true)
            ->with(['presensi' => function($q) {
                if ($this->selectedTa) {
                    $q->where('tahun_ajaran_id', $this->selectedTa->id);
                }
                if ($this->daysInMonth > 0) {
                    $q->whereMonth('tanggal', $this->month)->whereYear('tanggal', $this->year);
                }
            }])
            ->orderBy('nama_lengkap', 'asc');
    }

    public function headings(): array 
    {
        if ($this->daysInMonth > 0) {
            $header = ['NO', 'NAMA LENGKAP'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
            return array_merge($header, ['H', 'S', 'I', 'A', 'KETERANGAN']);
        }
        return ['NO', 'NAMA LENGKAP', 'STATUS', 'KETERANGAN'];
    }

    public function map($siswa): array 
    {
        $this->rowNumber++;
        if ($this->daysInMonth > 0) {
            $row = [$this->rowNumber, $siswa->nama_lengkap];
            $absenSiswa = $siswa->presensi;
            $rekap = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $targetDate = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');
                $absen = $absenSiswa->first(fn($item) => Carbon::parse($item->tanggal)->format('Y-m-d') === $targetDate);
                
                if ($absen) {
                    $statusChar = strtoupper(substr(trim($absen->status), 0, 1));
                    $row[] = $statusChar;
                    if (array_key_exists($statusChar, $rekap)) {
                        $rekap[$statusChar]++;
                    }
                } else {
                    $tglCarbon = Carbon::parse($targetDate);
                    $isWeekend = $tglCarbon->isWeekend(); 
                    $isLiburKalender = $this->hariLiburNasional->contains(function($v) use ($targetDate) {
                        $start = Carbon::parse($v->tanggal_mulai)->format('Y-m-d');
                        $end = Carbon::parse($v->tanggal_selesai)->format('Y-m-d');
                        return $targetDate >= $start && $targetDate <= $end;
                    });
                    $row[] = ($isWeekend || $isLiburKalender) ? 'L' : '';
                }
            }
            return array_merge($row, [$rekap['H'] ?: '', $rekap['S'] ?: '', $rekap['I'] ?: '', $rekap['A'] ?: '', '']);
        }
        return [$this->rowNumber, $siswa->nama_lengkap, '', ''];
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
            $sheet->getColumnDimension('A')->setWidth(4);
            $sheet->getColumnDimension('B')->setWidth(30);
            
            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
            for ($i = 3; $i < $highestColIndex; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(3.2);
            }
            
            $sheet->getColumnDimension($lastCol)->setWidth(15);
            $sheet->getStyle("A11:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B12:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
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
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                
                $alamat = $this->kontak->alamat ?? $this->kontak->alamat_lengkap ?? '';
                $telepon = $this->kontak->telepon ?? $this->kontak->no_telp ?? '';
                $email = $this->kontak->email ?? $this->kontak->email_resmi ?? '';
                
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $alamat . " | Telp: " . $telepon);
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . $email . " | NPSN: " . ($this->profil->npsn ?? '-'));
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A8', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A9', "Tahun Ajaran: {$this->tahunAjaran}"); 
                $sheet->setCellValue($lastCol . "9", "Periode: " . str_replace('-', ' ', $this->labelWaktu));
                $sheet->getStyle($lastCol . "9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                if (in_array($this->role, ['admin', 'kesiswaan'])) {
                    $jabatanLabel = "Waka Kesiswaan,";
                    $ttd = DB::table('struktur_jabatan')
                        ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                        ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                        ->where('jabatans.slug', 'waka-kesiswaan')->select('guru_staf.nama', 'guru_staf.nip')->first();
                    $namaTtd = $ttd->nama ?? 'Nama Kesiswaan';
                    $nipTtd = $ttd->nip ?? '-';
                } else {
                    $jabatanLabel = "Walikelas,";
                    $namaTtd = $this->dataKelas->waliKelas->nama ?? 'Nama Guru';
                    $nipTtd = $this->dataKelas->waliKelas->nip ?? '-';
                }

                $ttdRow = $lastRow + 3;
                $sheet->setCellValue($lastCol . $ttdRow, "Tasikmalaya, " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue($lastCol . ($ttdRow + 1), $jabatanLabel);
                $sheet->setCellValue($lastCol . ($ttdRow + 5), "( " . ucwords($namaTtd) . " )");
                $sheet->setCellValue($lastCol . ($ttdRow + 6), "NIP. " . $nipTtd);
                $sheet->getStyle($lastCol . $ttdRow . ":" . $lastCol . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($lastCol . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}