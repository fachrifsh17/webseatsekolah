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
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PresensiGuruMapelExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $daysInMonth, $year, $month, $isFilterKelas, $dataLibur;
    private $rowNumber = 0;
    private $firstRecord = null;

    public function __construct($query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $isFilterKelas = false)
    {
        $this->query = $query;
        $this->labelWaktu = $labelWaktu;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->guruStaf = $guruStaf; 
        $this->tahunAjaran = $tahunAjaran;
        $this->isFilterKelas = $isFilterKelas;

        if (str_contains($this->labelWaktu, 'Bulan-')) {
            $parts = explode('-', $this->labelWaktu);
            $this->month = (int)$parts[1];
            $this->year = (int)$parts[2];
            $this->daysInMonth = Carbon::create($this->year, $this->month)->daysInMonth;

            $this->dataLibur = DB::table('kalender_akademik')
                ->where('kategori', 'Libur')
                ->get();
        } else {
            $this->daysInMonth = 0;
        }
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->query->with(['mataPelajaran', 'kelas', 'presensiSiswaDetail.siswa', 'guruMapel.guru']);
    }

    public function headings(): array
    {
        if ($this->isFilterKelas && $this->daysInMonth > 0) {
            $header = ['NO', 'NAMA LENGKAP'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
            return array_merge($header, ['PETUGAS']);
        }
        return ['NO', 'TANGGAL', 'JAM', 'MATA PELAJARAN', 'KELAS', 'MATERI', 'H', 'S', 'I', 'A', 'TOTAL'];
    }

    public function map($presensi): array
    {
        if (!$this->firstRecord) { $this->firstRecord = $presensi; }

        $namaPetugas = $presensi->guruMapel->guru->nama ?? ($this->guruStaf->nama ?? '-');

        if ($this->isFilterKelas && $this->daysInMonth > 0) {
            $rows = [];
            $details = $presensi->presensiSiswaDetail->sortBy('siswa.nama_lengkap');
            
            foreach ($details as $detail) {
                $this->rowNumber++;
                $row = [$this->rowNumber, $detail->siswa->nama_lengkap ?? '-'];
                $tglJurnal = Carbon::parse($presensi->tanggal)->day;

                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $currentDate = Carbon::create($this->year, $this->month, $d);
                    $isWeekend = $currentDate->isSaturday() || $currentDate->isSunday();
                    $isHoliday = $this->dataLibur->contains(fn($v) => $currentDate->between($v->tanggal_mulai, $v->tanggal_selesai));

                    if ($isWeekend || $isHoliday) {
                        $row[] = 'L';
                    } elseif ($d === $tglJurnal) {
                        $row[] = strtoupper(substr($detail->status, 0, 1));
                    } else {
                        $row[] = '-';
                    }
                }
                $row[] = $namaPetugas; 
                $rows[] = $row;
            }
            return $rows;
        }

        $this->rowNumber++;
        $details = $presensi->presensiSiswaDetail;
        return [
            $this->rowNumber,
            Carbon::parse($presensi->tanggal)->format('d-m-Y'),
            $presensi->jam_masuk . '-' . $presensi->jam_keluar,
            $presensi->mataPelajaran->nama_mapel ?? '-',
            $presensi->kelas->nama_kelas ?? '-',
            $presensi->materi ?? '-',
            $details->where('status', 'hadir')->count(),
            $details->where('status', 'sakit')->count(),
            $details->where('status', 'izin')->count(),
            $details->where('status', 'alfa')->count(),
            $details->count()
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        
        if ($lastRow >= 11) {
            $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]);
            $sheet->getStyle("A11:{$lastCol}11")->getFont()->setBold(true);
            $sheet->getStyle("A11:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getColumnDimension('B')->setAutoSize(true);
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
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? 'Jl. Pendidikan No. 55') . " | Telp: " . ($this->kontak->telepon ?? '-'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $judul = $this->isFilterKelas ? 'LAPORAN PRESENSI SISWA' : 'LAPORAN REKAPITULASI MENGAJAR GURU';
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', $judul);
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A1:{$lastCol}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttdNama = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? 'Guru');
                $ttdNip = $this->guruStaf->nip ?? ($this->firstRecord->guruMapel->guru->nip ?? '-');
                $ttdJabatan = "Guru Mata Pelajaran";

                if (Auth::user() && Auth::user()->hasAnyRole(['Admin', 'Kesiswaan'])) {
                    $pejabat = DB::table('struktur_jabatan')
                        ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                        ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                        ->where('jabatans.slug', 'waka-kesiswaan')
                        ->select('guru_staf.nama', 'guru_staf.nip', 'jabatans.nama_jabatan')
                        ->first();
                    
                    if ($pejabat) {
                        $ttdNama = $pejabat->nama;
                        $ttdNip = $pejabat->nip;
                        $ttdJabatan = $pejabat->nama_jabatan;
                    }
                }

                if ($this->isFilterKelas) {
                    $sheet->setCellValue('A8', "Kelas: " . ($this->firstRecord->kelas->nama_kelas ?? 'Semua Kelas'));
                    $sheet->setCellValue('A9', "Tahun Ajaran: " . ($this->tahunAjaran ?? '-'));
                    $sheet->setCellValue($lastCol . "9", "Periode: " . $this->labelWaktu);
                } else {
                    $sheet->setCellValue('A8', "Nama Guru: " . ucwords($ttdNama));
                    $sheet->setCellValue('A9', "NIP: " . $ttdNip);
                    $sheet->setCellValue('A10', "Tahun Ajaran: " . ($this->tahunAjaran ?? '-'));
                    $sheet->setCellValue($lastCol . "10", "Periode: " . $this->labelWaktu);
                }

                $ttdRow = $lastRow + 3;
                $sheet->setCellValue($lastCol . $ttdRow, "Tasikmalaya, " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue($lastCol . ($ttdRow + 1), $ttdJabatan . ",");
                $sheet->setCellValue($lastCol . ($ttdRow + 5), "( " . $ttdNama . " )");
                $sheet->setCellValue($lastCol . ($ttdRow + 6), "NIP. " . $ttdNip);

                $sheet->getStyle($lastCol . $ttdRow . ":" . $lastCol . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($lastCol . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}