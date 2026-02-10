<?php

namespace App\Exports;

use App\Models\PoinSiswa;
use Maatwebsite\Excel\Concerns\{FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border, Fill};
use Illuminate\Support\Facades\DB;

class PoinSiswaExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $namaKelas, $labelWaktu, $profil, $kontak;
    private $rowNumber = 0;
    private $totalsPeriode = [];

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak)
    {
        $this->query = $query;
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
    }

    public function startCell(): string { return 'A11'; }

    public function query() 
    { 
        return $this->query->with(['siswa.kelas', 'guruStaf']); 
    }

    public function headings(): array 
    {
        return ['NO', 'TANGGAL', 'NIS', 'NISN', 'NAMA LENGKAP', 'POIN (+)', 'POIN (-)', 'KETERANGAN / INDIKATOR', 'GURU PELAPOR'];
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

        return [
            $this->rowNumber,
            $poin->tanggal ? date('d/m/Y', strtotime($poin->tanggal)) : '-',
            "'" . ($poin->siswa->nis ?? '-'),
            "'" . ($poin->siswa->nisn ?? '-'), 
            $poin->siswa->nama_lengkap ?? '-',
            $positif > 0 ? $positif : '',
            $negatif > 0 ? $negatif : '',
            $poin->indikator ?? '-',
            $poin->guruStaf->nama ?? 'Admin' 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A11:I11")->getFont()->setBold(true); 
        $sheet->getStyle("A11:I{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle("A11:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $sheet->getStyle("F11:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

        foreach (range('A', 'I') as $col) { 
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'I'; 
                $dataLastRow = $sheet->getHighestRow();

                $namaSekolah = $this->profil->nama_sekolah ?? 'NAMA SEKOLAH';
                $alamat = $this->kontak->alamat_lengkap ?? '-';
                $telp = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($namaSekolah));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $alamat . " | Telp: " . $telp);
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . $email . " | NPSN: " . $npsn);
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN REKAP POIN KEDISIPLINAN SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A8', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A9', "Periode: " . $this->labelWaktu);

                $isFiltered = (stripos($this->labelWaktu, 'Kumulatif') === false);
                $summaryRow = $dataLastRow + 2;
                $sheet->setCellValue("A{$summaryRow}", $isFiltered ? "RINGKASAN POIN: PERIODE INI VS KUMULATIF (KLAS 10-12)" : "RINGKASAN TOTAL POIN KUMULATIF SISWA");
                $sheet->getStyle("A{$summaryRow}")->getFont()->setBold(true);
                
                $h = $summaryRow + 1;
                $headers = $isFiltered ? 
                    ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'POS (+) PERIODE', 'NEG (-) PERIODE', 'TOTAL (+) KUMULATIF', 'TOTAL (-) KUMULATIF'] : 
                    ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'TOTAL POIN (+)', 'TOTAL POIN (-)'];
                
                $lastSumCol = $isFiltered ? 'H' : 'F';

                foreach ($headers as $key => $val) {
                    $col = chr(65 + $key);
                    $sheet->setCellValue("{$col}{$h}", $val);
                }
                
                $sheet->getStyle("A{$h}:{$lastSumCol}{$h}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFF');
                $sheet->getStyle("A{$h}:{$lastSumCol}{$h}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('444444');

                $siswaIds = array_keys($this->totalsPeriode);
                $akumulasiGlobal = [];
                if (!empty($siswaIds)) {
                    $akumulasiGlobal = DB::table('poin_siswa')
                        ->whereIn('siswa_id', $siswaIds)
                        ->select('siswa_id', DB::raw('SUM(poin_positif) as tot_p'), DB::raw('SUM(poin_negatif) as tot_n'))
                        ->groupBy('siswa_id')->get()->keyBy('siswa_id');
                }

                uasort($this->totalsPeriode, function($a, $b) use ($akumulasiGlobal, $siswaIds) {
                    $idA = array_search($a['nama'], array_column($this->totalsPeriode, 'nama', 'id')); 
                    $idB = array_search($b['nama'], array_column($this->totalsPeriode, 'nama', 'id'));
                    
                    $valA = $akumulasiGlobal[array_search($a, $this->totalsPeriode)]->tot_n ?? 0;
                    $valB = $akumulasiGlobal[array_search($b, $this->totalsPeriode)]->tot_n ?? 0;
                    return $valB <=> $valA;
                });

                $curr = $h + 1;
                $no = 1;
                foreach ($this->totalsPeriode as $id => $data) {
                    $sheet->setCellValue("A{$curr}", $no++);
                    $sheet->setCellValue("B{$curr}", "'" . $data['nis']);
                    $sheet->setCellValue("C{$curr}", "'" . $data['nisn']); 
                    $sheet->setCellValue("D{$curr}", $data['nama']);
                    
                    $pGlobal = $akumulasiGlobal[$id]->tot_p ?? 0;
                    $nGlobal = $akumulasiGlobal[$id]->tot_n ?? 0;

                    if ($isFiltered) {
                        $sheet->setCellValue("E{$curr}", $data['p_periode']);
                        $sheet->setCellValue("F{$curr}", $data['n_periode']);
                        $sheet->setCellValue("G{$curr}", $pGlobal);
                        $sheet->setCellValue("H{$curr}", $nGlobal);
                    } else {
                        $sheet->setCellValue("E{$curr}", $pGlobal);
                        $sheet->setCellValue("F{$curr}", $nGlobal);
                    }
                    $curr++;
                }

                $sheet->getStyle("A{$h}:{$lastSumCol}" . ($curr - 1))->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);

                $waka = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 3)
                    ->where('guru_staf.is_active', true)
                    ->select('guru_staf.nama', 'guru_staf.nip') 
                    ->first();

                $ttdRow = $curr + 2;
                $ttdCol = $lastSumCol;
                $kab = $this->profil->kabupaten ?? 'Kabupaten';
                
                $sheet->setCellValue("{$ttdCol}{$ttdRow}", $kab . ", " . date('d F Y'));
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 1), "Waka Kesiswaan,");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 5), $waka ? "( " . $waka->nama . " )" : "( ____________________ )");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 6), $waka ? "NIP. " . $waka->nip : "NIP. ..........................");
                $sheet->getStyle("{$ttdCol}{$ttdRow}:{$ttdCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("{$ttdCol}" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}