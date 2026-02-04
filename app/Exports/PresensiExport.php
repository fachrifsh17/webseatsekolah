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
    protected $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $daysInMonth, $year, $month, $role, $tahunAjaran, $hariLiburNasional, $taActive;
    private $rowNumber = 0;

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak, $dataKelas, $role = 'walikelas', $tahunAjaran = '-')
    {
        $this->taActive = TahunAjaran::where('is_active', true)->first();
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
                ->where(function($q) {
                    $q->whereMonth('tanggal_mulai', $this->month)
                      ->orWhereMonth('tanggal_selesai', $this->month);
                })
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
        return Siswa::query()
            ->where('kelas_id', $this->dataKelas->id)
            ->where('is_active', true)
            ->with(['presensi' => function($q) {
                $q->where('tahun_ajaran_id', $this->taActive?->id);
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
            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $header[] = $d;
            }
            return array_merge($header, ['S', 'I', 'A', 'KETERANGAN']);
        }
        return ['NO', 'NAMA LENGKAP', 'STATUS', 'KETERANGAN'];
    }

    public function map($siswa): array 
    {
        $this->rowNumber++;

        if ($this->daysInMonth > 0) {
            $row = [$this->rowNumber, $siswa->nama_lengkap];
            $absenSiswa = $siswa->presensi;
            $rekap = ['S' => 0, 'I' => 0, 'A' => 0];

            for ($d = 1; $d <= $this->daysInMonth; $d++) {
                $tglCek = Carbon::create($this->year, $this->month, $d)->format('Y-m-d');
                $tglCarbon = Carbon::parse($tglCek);

                $absen = $absenSiswa->first(fn($item) => Carbon::parse($item->tanggal)->format('Y-m-d') === $tglCek);
                
                if ($absen) {
                    $statusChar = strtoupper(substr($absen->status, 0, 1));
                    $row[] = ($statusChar === 'H') ? '-' : $statusChar;
                    if (isset($rekap[$statusChar])) $rekap[$statusChar]++;
                } else {
                    $isWeekend = $tglCarbon->isWeekend(); 
                    $isLiburKalender = $this->hariLiburNasional->contains(fn($v) => $tglCek >= $v->tanggal_mulai && $tglCek <= $v->tanggal_selesai);
                    $row[] = ($isWeekend || $isLiburKalender) ? 'L' : '-';
                }
            }

            return array_merge($row, [$rekap['S'], $rekap['I'], $rekap['A'], '']);
        }

        return [$this->rowNumber, $siswa->nama_lengkap, '-', '-'];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();

        $sheet->getStyle("A11:{$lastCol}11")->getFont()->setBold(true);
        $sheet->getStyle("A11:{$lastCol}11")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        if ($this->daysInMonth > 0) {
            $sheet->getColumnDimension('B')->setAutoSize(true);
            for ($i = 3; $i <= ($this->daysInMonth + 6); $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(3);
                $sheet->getStyle("{$col}11:{$col}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
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
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A8', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A9', "Tahun Ajaran: {$this->tahunAjaran}"); 
                $sheet->setCellValue($lastCol . "9", "Periode: " . $this->labelWaktu);
                $sheet->getStyle($lastCol . "9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $jabatanLabel = ($this->role === 'admin' || $this->role === 'kesiswaan') ? "Waka Kesiswaan," : "Wali Kelas,";
                $namaTtd = ($this->role === 'admin' || $this->role === 'kesiswaan') ? 
                            (DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 3)->value('guru_staf.nama') ?? 'Nama Kesiswaan') :
                            ($this->dataKelas->waliKelas->nama ?? 'Nama Wali Kelas');
                $nipTtd = ($this->role === 'admin' || $this->role === 'kesiswaan') ? 
                            (DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 3)->value('guru_staf.nip') ?? '-') :
                            ($this->dataKelas->waliKelas->nip ?? '-');

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