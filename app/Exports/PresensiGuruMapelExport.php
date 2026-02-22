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
    protected $query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $daysInMonth, $tahun, $bulan, $isFilterKelas, $tahunAjaranId;
    private $rowNumber = 0;
    private $firstRecord = null;
    private $processedSiswa = [];

    public function __construct($query, $labelWaktu, $profil, $kontak, $guruStaf, $tahunAjaran, $isFilterKelas = false, $bulan = null, $tahun = null, $tahunAjaranId = null)
    {
        $this->query = $query;
        $this->labelWaktu = $labelWaktu;
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->guruStaf = $guruStaf;
        $this->tahunAjaran = $tahunAjaran;
        $this->isFilterKelas = $isFilterKelas;
        $this->tahunAjaranId = $tahunAjaranId;
        
        $this->bulan = $bulan ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[2] : date('m'));
        $this->tahun = $tahun ?? (str_contains($labelWaktu, 'Bulan-') ? explode('-', $labelWaktu)[3] : date('Y'));

        $this->daysInMonth = Carbon::create($this->tahun, $this->bulan)->daysInMonth;
    }

    public function startCell(): string { return 'A13'; }

    public function query()
    {
        return $this->query->with(['mapel', 'kelas', 'getBySiswaDetil.siswa', 'guruMapel.guru']);
    }

    public function headings(): array
    {
        if ($this->isFilterKelas) {
            $header = ['NO', 'NAMA LENGKAP'];
            for ($d = 1; $d <= $this->daysInMonth; $d++) { $header[] = $d; }
            return array_merge($header, ['H', 'S', 'I', 'A', 'KETERANGAN']);
        }
        return ['NO', 'TANGGAL', 'JAM', 'MATA PELAJARAN', 'KELAS', 'MATERI', 'H', 'S', 'I', 'A', 'TOTAL'];
    }

    public function map($presensi): array
    {
        if (!$this->firstRecord) { $this->firstRecord = $presensi; }

        if ($this->isFilterKelas) {
            $rows = [];
            
            $presensiIds = DB::table('presensi_guru_mapel')
                ->where('kelas_id', $this->firstRecord->kelas_id)
                ->where('mata_pelajaran_id', $this->firstRecord->mata_pelajaran_id)
                ->whereMonth('tanggal', $this->bulan)
                ->whereYear('tanggal', $this->tahun)
                ->pluck('id');

            $allAttendanceData = DB::table('presensi_siswa_detail')
                ->whereIn('presensi_guru_mapel_id', $presensiIds)
                ->get();

            $details = $presensi->getBySiswaDetil->sortBy('siswa.nama_lengkap');
            
            foreach ($details as $detail) {
                $siswaId = $detail->siswa_id;

                if (in_array($siswaId, $this->processedSiswa)) {
                    continue; 
                }
                $this->processedSiswa[] = $siswaId;

                $this->rowNumber++;
                $row = [$this->rowNumber, $detail->siswa->nama_lengkap ?? '-'];
                
                $rekap = ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alfa' => 0];

                for ($d = 1; $d <= $this->daysInMonth; $d++) {
                    $dateObj = Carbon::create($this->tahun, $this->bulan, $d);
                    $dateString = $dateObj->toDateString();
                    
                    $isLiburKalender = DB::table('kalender_akademik')
                        ->where('tahun_ajaran_id', $this->tahunAjaranId)
                        ->where('kategori', 'Libur')
                        ->whereDate('tanggal_mulai', '<=', $dateString)
                        ->whereDate('tanggal_selesai', '>=', $dateString)
                        ->exists();

                    if ($dateObj->isWeekend() || $isLiburKalender) {
                        $row[] = 'L';
                    } else {
                        $pTgl = DB::table('presensi_guru_mapel')
                            ->where('kelas_id', $this->firstRecord->kelas_id)
                            ->where('mata_pelajaran_id', $this->firstRecord->mata_pelajaran_id)
                            ->where('tanggal', $dateString)
                            ->first();

                        if ($pTgl) {
                            $statusSiswa = $allAttendanceData->where('presensi_guru_mapel_id', $pTgl->id)
                                                            ->where('siswa_id', $siswaId)
                                                            ->first();
                            if ($statusSiswa) {
                                $st = strtolower($statusSiswa->status);
                                $row[] = strtoupper(substr($st, 0, 1));
                                if (isset($rekap[$st])) $rekap[$st]++;
                            } else {
                                $row[] = '-';
                            }
                        } else {
                            $row[] = ''; 
                        }
                    }
                }
                
                $row[] = $rekap['hadir'] ?: '';
                $row[] = $rekap['sakit'] ?: '';
                $row[] = $rekap['izin'] ?: '';
                $row[] = $rekap['alfa'] ?: '';
                $row[] = ''; 

                $rows[] = $row;
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
            $presensi->count()
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = $sheet->getHighestColumn();
        
        $sheet->getStyle("A13:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        $sheet->getStyle("A13:{$lastCol}13")->getFont()->setBold(true);
        $sheet->getStyle("A13:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B14:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if ($this->isFilterKelas) {
            $sheet->getColumnDimension('A')->setWidth(4);
            $sheet->getColumnDimension('B')->setWidth(30);

            $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
            for ($i = 3; $i < $highestColIndex; $i++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($col)->setWidth(3.2); 
            }
            $sheet->getColumnDimension($lastCol)->setWidth(15); 
        } else {
            $sheet->getColumnDimension('A')->setWidth(5);
            $sheet->getColumnDimension('B')->setAutoSize(true);
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
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMKN 1 BANTARKALONG'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '-') . " | Telp: " . ($this->kontak->telepon ?? '-'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN PRESENSI SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $namaGuru = $this->guruStaf->nama ?? ($this->firstRecord->guruMapel->guru->nama ?? '-');
                $sheet->setCellValue('A8', "Nama Guru: " . $namaGuru);
                $sheet->setCellValue('A9', "Kelas: " . ($this->firstRecord->kelas->nama_kelas ?? '-'));
                $sheet->setCellValue('A10', "Mata Pelajaran: " . ($this->firstRecord->mapel->nama_mapel ?? '-'));
                
                $sheet->setCellValue('A11', "Tahun Ajaran: " . ($this->tahunAjaran ?? '-'));
                $sheet->setCellValue($lastCol . '11', "Periode: " . $this->labelWaktu);
                $sheet->getStyle($lastCol . '11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $ttdRow = $lastRow + 3;
                $sheet->setCellValue($lastCol . $ttdRow, "Tasikmalaya, " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->setCellValue($lastCol . ($ttdRow + 1), "Guru Mata Pelajaran,");
                $sheet->setCellValue($lastCol . ($ttdRow + 5), "( " . $namaGuru . " )");
                $sheet->setCellValue($lastCol . ($ttdRow + 6), "NIP. " . ($this->guruStaf->nip ?? '-'));

                $sheet->getStyle($lastCol . $ttdRow . ":" . $lastCol . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($lastCol . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}