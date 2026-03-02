<?php
namespace App\Exports;

use App\Models\PoinSiswa;
use Maatwebsite\Excel\Concerns\{FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings, WithDrawings};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill};
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PoinSiswaExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings, WithDrawings
{
    protected $query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA;
    private $rowNumber = 0;
    private $totalsPeriode = [];
    private $ttdRowOffset = 0;

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA = null)
    {
        $this->query = $query;
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->namaTA = $namaTA;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        
        Carbon::setLocale('id');
    }

    public function startCell(): string { return 'A12'; }

    public function query() 
    { 
        return $this->query->with(['siswa.riwayatKelas.kelas', 'guruStaf', 'semester']); 
    }

    public function headings(): array 
    {
        return ['NO', 'TANGGAL', 'NIS', 'NISN', 'NAMA LENGKAP', 'POIN', 'KETERANGAN / INDIKATOR', 'GURU PELAPOR'];
    }

    public function map($poin): array 
    {
        $this->rowNumber++;
        $idSiswa = $poin->siswa_id;
        $positif = $poin->poin_positif ?? 0;
        $negatif = $poin->poin_negatif ?? 0;

        if (!isset($this->totalsPeriode[$idSiswa])) {
            $this->totalsPeriode[$idSiswa] = [
                'nama' => $poin->siswa->nama_lengkap ?? '-', 
                'nis'  => $poin->siswa->nis ?? '-',
                'nisn' => $poin->siswa->nisn ?? '-', 
                'p_periode' => 0, 
                'n_periode' => 0
            ];
        }
        $this->totalsPeriode[$idSiswa]['p_periode'] += $positif;
        $this->totalsPeriode[$idSiswa]['n_periode'] += $negatif;

        $poinTampil = $positif > 0 ? $positif : ($negatif > 0 ? -$negatif : 0);

        $formattedDate = $poin->tanggal ? Carbon::parse($poin->tanggal)->translatedFormat('d F Y') : '-';

        return [
            $this->rowNumber,
            $formattedDate,
            "'" . ($poin->siswa->nis ?? '-'),
            "'" . ($poin->siswa->nisn ?? '-'), 
            strtoupper($poin->siswa->nama_lengkap ?? '-'),
            $poinTampil,
            $poin->indikator ?? '-',
            strtoupper($poin->guruStaf->nama ?? 'ADMIN') 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        if ($lastRow < 12) $lastRow = 12;

        $sheet->getStyle("A12:H12")->getFont()->setBold(true); 
        $sheet->getStyle("A12:H{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle("A12:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $sheet->getStyle("F12:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

        foreach (range('A', 'H') as $col) { 
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'H'; 
                $dataLastRow = $sheet->getHighestRow();

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $wakaKes = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 3) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cabang_dinas ?? 'CABANG DINAS PENDIDIKAN') . " WILAYAH XII");
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                
                $sheet->mergeCells("A6:{$lastCol}6"); 
                $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN REKAP POIN KEDISIPLINAN SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $tglSemester = "TAHUN PELAJARAN " . ($this->namaTA ?? '2025/2026');
                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', $tglSemester);
                $sheet->getStyle("A8")->getFont()->setBold(true);
                $sheet->getStyle("A8:{$lastCol}8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', "KELAS : " . strtoupper($this->namaKelas));
                
                $sheet->mergeCells("A10:{$lastCol}10");
                
                $periodeTampil = 'KESELURUHAN';
                if (!empty($this->labelWaktu)) {
                    try {
                        $periodeTampil = strtoupper(Carbon::parse($this->labelWaktu)->translatedFormat('F Y'));
                    } catch (\Exception $e) {
                        $periodeTampil = strtoupper($this->labelWaktu);
                    }
                }
                $sheet->setCellValue('A10', "PERIODE : " . $periodeTampil);
                
                $sheet->getStyle("A9:A10")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $summaryHeaderRow = $dataLastRow + 2; 
                
                $sheet->setCellValue("A{$summaryHeaderRow}", "RINGKASAN POIN: PERIODE INI VS KUMULATIF");
                $sheet->getStyle("A{$summaryHeaderRow}")->getFont()->setBold(true);
                
                $h = $summaryHeaderRow + 1;
                $headers = ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'POS (+) PERIODE', 'NEG (-) PERIODE', 'TOTAL (+) KUMULATIF', 'TOTAL (-) KUMULATIF'];
                
                $lastSumCol = 'H';

                foreach ($headers as $key => $val) {
                    $col = chr(65 + $key);
                    $sheet->setCellValue("{$col}{$h}", $val);
                }
                
                $sheet->getStyle("A{$h}:{$lastSumCol}{$h}")->getFont()->setBold(true);
                $sheet->getStyle("A{$h}:{$lastSumCol}{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $siswaIds = array_keys($this->totalsPeriode);
                $akumulasiGlobal = [];
                if (!empty($siswaIds)) {
                    $akumulasiGlobal = DB::table('poin_siswa')
                        ->whereIn('siswa_id', $siswaIds)
                        ->select('siswa_id', DB::raw('SUM(poin_positif) as tot_p'), DB::raw('SUM(poin_negatif) as tot_n'))
                        ->groupBy('siswa_id')->get()->keyBy('siswa_id');
                }

                uasort($this->totalsPeriode, function($a, $b) use ($akumulasiGlobal) {
                    $idA = array_search($a, $this->totalsPeriode); 
                    $idB = array_search($b, $this->totalsPeriode);
                    $valA = $akumulasiGlobal[$idA]->tot_n ?? 0;
                    $valB = $akumulasiGlobal[$idB]->tot_n ?? 0;
                    return $valB <=> $valA;
                });

                $curr = $h + 1;
                $no = 1;
                foreach ($this->totalsPeriode as $id => $data) {
                    $sheet->setCellValue("A{$curr}", $no++);
                    $sheet->setCellValue("B{$curr}", "'" . $data['nis']);
                    $sheet->setCellValue("C{$curr}", "'" . $data['nisn']); 
                    $sheet->setCellValue("D{$curr}", strtoupper($data['nama']));
                    
                    $pGlobal = $akumulasiGlobal[$id]->tot_p ?? 0;
                    $nGlobal = $akumulasiGlobal[$id]->tot_n ?? 0;

                    $sheet->setCellValue("E{$curr}", $data['p_periode'] ?: 0);
                    $sheet->setCellValue("F{$curr}", $data['n_periode'] ?: 0);
                    $sheet->setCellValue("G{$curr}", $pGlobal ?: 0);
                    $sheet->setCellValue("H{$curr}", $nGlobal ?: 0);
                    
                    $curr++;
                }

                if ($curr > $h + 1) {
                    $sheet->getStyle("A{$h}:{$lastSumCol}" . ($curr - 1))->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getStyle("B" . ($h + 1) . ":C" . ($curr - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E" . ($h + 1) . ":{$lastSumCol}" . ($curr - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $ttgRowLabels = $curr + 5; 
                $this->ttdRowOffset = $ttgRowLabels;

                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $sheet->mergeCells("F{$ttgRowLabels}:{$lastCol}{$ttgRowLabels}");
                
                $formattedDate = $this->labelWaktu ? Carbon::parse($this->labelWaktu)->translatedFormat('d F Y') : Carbon::now()->translatedFormat('d F Y');
                $sheet->setCellValue("F{$ttgRowLabels}", strtoupper($lokasiTtd) . ", " . $formattedDate);
                
                $sheet->getStyle("F{$ttgRowLabels}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $labelRow = $ttgRowLabels + 1;
                $sheet->mergeCells("A{$labelRow}:C{$labelRow}");
                $sheet->setCellValue("A{$labelRow}", "MENGETAHUI,\nWAKA KESISWAAN");
                
                $sheet->mergeCells("F{$labelRow}:{$lastCol}{$labelRow}");
                $sheet->setCellValue("F{$labelRow}", "MENYETUJUI,\nKEPALA SEKOLAH");
                
                $sheet->getStyle("A{$labelRow}:{$lastCol}{$labelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                $sheet->getStyle("A{$labelRow}:{$lastCol}{$labelRow}")->getFont()->setBold(true);

                $imageSpaceRow = $labelRow + 2;
                $sheet->getRowDimension($imageSpaceRow)->setRowHeight(80);

                $namaRow = $labelRow + 5;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKes->nama ?? '____________________') . " )"); 
                
                $sheet->mergeCells("F{$namaRow}:{$lastCol}{$namaRow}");
                $sheet->setCellValue("F{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true)->setUnderline(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKes->nip ?? '...........................'));
                
                $sheet->mergeCells("F{$nipRow}:{$lastCol}{$nipRow}");
                $sheet->setCellValue("F{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }

    public function drawings()
    {
        $drawings = [];
        $imageRow = $this->ttdRowOffset + 3;

        $wakaKes = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 3)
            ->select('struktur_jabatan.file_ttd')
            ->first();
            
        $kepsek = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 1)
            ->select('struktur_jabatan.file_ttd')
            ->first();

        if ($wakaKes && $wakaKes->file_ttd && file_exists(storage_path('app/public/' . $wakaKes->file_ttd))) {
            $drawing = new Drawing();
            $drawing->setName('TTD Waka');
            $drawing->setPath(storage_path('app/public/' . $wakaKes->file_ttd));
            $drawing->setHeight(70);
            $drawing->setCoordinates('B' . $imageRow);
            $drawings[] = $drawing;
        }
        
        if ($kepsek && $kepsek->file_ttd && file_exists(storage_path('app/public/' . $kepsek->file_ttd))) {
            $drawing = new Drawing();
            $drawing->setName('TTD Kepsek');
            $drawing->setPath(storage_path('app/public/' . $kepsek->file_ttd));
            $drawing->setHeight(70);
            $drawing->setCoordinates('G' . $imageRow);
            $drawings[] = $drawing;
        }

        return $drawings;
    }
}