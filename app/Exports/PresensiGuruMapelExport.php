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
    protected $query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $daysInMonth, $tahun, $bulan, $isFilterKelas, $dataLibur;
    private $rowNumber = 0;
    private $firstRecord = null;

    public function __construct($query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $isFilterKelas = false, $bulan = null, $tahun = null)
    {
        $this->query = $query;
        $this->labelWaktu = $labelWaktu;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->guruStaf = $guruStaf; 
        $this->tahunAjaran = $tahunAjaran;
        $this->isFilterKelas = $isFilterKelas;
        
        $this->bulan = $bulan ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[1] : date('m'));
        $this->tahun = $tahun ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[2] : date('Y'));

        $this->daysInMonth = Carbon::create($this->tahun, $this->bulan)->daysInMonth;
        $this->dataLibur = DB::table('kalender_akademik')->where('kategori', 'Libur')->get();
    }

    public function startCell(): string { return 'A11'; }

    public function query()
    {
        return $this->query->with(['mataPelajaran', 'kelas', 'presensiSiswaDetail.siswa', 'guruMapel.guru']);
    }

    public function headings(): array
    {
        if ($this->isFilterKelas) {
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

        if ($this->isFilterKelas) {
            $rows = [];
            $details = $presensi->presensiSiswaDetail->sortBy('siswa.nama_lengkap');
            foreach ($details as $detail) {
                $this->rowNumber++;
                $row = [$this->rowNumber, $detail->siswa->nama_lengkap ?? '-'];
                $tglJurnal = (int)Carbon::parse($presensi->tanggal)->day;

                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $currentDate = Carbon::create($this->tahun, $this->bulan, $d);
                    if ($currentDate->isSunday()) { $row[] = 'L'; }
                    elseif ($d === $tglJurnal) { $row[] = strtoupper(substr($detail->status, 0, 1)); }
                    else { $row[] = '-'; }
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
            Carbon::parse($presensi->tanggal)->format('d/m/Y'),
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
        
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle("A11:{$lastCol}11")->getFont()->setBold(true);
        $sheet->getStyle("A11:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B12:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // KOP SURAT (Disesuaikan dengan image_d33b04.png)
                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? 'Jl. Pendidikan No. 55, Bantarkalong, Tasikmalaya') . " | Telp: " . ($this->kontak->telepon ?? '0265-119382'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? 'info@sekolahkita.sch.id') . " | NPSN: " . ($this->profil->npsn ?? '20251234'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // JUDUL LAPORAN
                $judul = $this->isFilterKelas ? 'LAPORAN PRESENSI SISWA' : 'LAPORAN REKAPITULASI MENGAJAR GURU';
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', $judul);
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // INFORMASI FILTER
                if ($this->isFilterKelas) {
                    $sheet->setCellValue('A8', "Kelas: " . ($this->firstRecord->kelas->nama_kelas ?? 'Semua Kelas'));
                    $sheet->setCellValue('A9', "Tahun Ajaran: " . ($this->tahunAjaran ?? '-'));
                    $sheet->setCellValue('A10', "Periode: " . $this->labelWaktu);
                } else {
                    $namaGuru = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? 'Guru');
                    $sheet->setCellValue('A8', "Nama Guru: " . ucwords($namaGuru));
                    $sheet->setCellValue('A9', "Tahun Ajaran: " . ($this->tahunAjaran ?? '-'));
                    $sheet->setCellValue('A10', "Periode: " . $this->labelWaktu);
                }

                // TANDA TANGAN
                $ttdNama = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? 'Guru');
                $ttdNip = $this->guruStaf->nip ?? ($this->firstRecord->guruMapel->guru->nip ?? '-');
                $ttdJabatan = "Guru Mata Pelajaran";

                // Jika Admin/Kesiswaan yang download, TTD Waka Kesiswaan
                if (Auth::user() && Auth::user()->hasAnyRole(['Admin', 'Kesiswaan'])) {
                    $pejabat = DB::table('struktur_jabatan')
                        ->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')
                        ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                        ->where('jabatans.slug', 'waka-kesiswaan')
                        ->select('guru_staf.nama', 'guru_staf.nip', 'jabatans.nama_jabatan')->first();
                    if ($pejabat) {
                        $ttdNama = $pejabat->nama; $ttdNip = $pejabat->nip; $ttdJabatan = $pejabat->nama_jabatan;
                    }
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