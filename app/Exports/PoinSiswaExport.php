<?php

namespace App\Exports;

use App\Models\PoinSiswa;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string { return 'A10'; }

    public function query() 
    { 
        return $this->query->with(['siswa.kelas', 'guruStaf'])
            ->whereHas('siswa', function($q) {
                $q->where('is_active', true);
            }); 
    }

    public function headings(): array 
    {
        return ['NO', 'TANGGAL', 'NIS', 'NISN', 'NAMA LENGKAP', 'POIN (+)', 'POIN (-)', 'KETERANGAN / INDIKATOR', 'GURU PELAPOR'];
    }

    public function map($poin): array 
    {
        $this->rowNumber++;
        $idSiswa = $poin->siswa_id;
        $namaSiswa = $poin->siswa->nama_lengkap ?? '-';
        $positif = $poin->poin_positif ?? 0;
        $negatif = $poin->poin_negatif ?? 0;

        if (!isset($this->totalsPeriode[$idSiswa])) {
            $this->totalsPeriode[$idSiswa] = [
                'nama' => $namaSiswa, 
                'nis' => $poin->siswa->nis ?? '-',
                'nisn' => $poin->siswa->nisn ?? '-', 
                'p_bulan_ini' => 0, 
                'n_bulan_ini' => 0
            ];
        }
        $this->totalsPeriode[$idSiswa]['p_bulan_ini'] += $positif;
        $this->totalsPeriode[$idSiswa]['n_bulan_ini'] += $negatif;

        return [
            $this->rowNumber,
            $poin->tanggal ? date('d/m/Y', strtotime($poin->tanggal)) : '-',
            "'" . ($poin->siswa->nis ?? '-'),
            "'" . ($poin->siswa->nisn ?? '-'), 
            $namaSiswa,
            $positif > 0 ? $positif : '',
            $negatif > 0 ? $negatif : '',
            $poin->indikator ?? '-',
            $poin->guruStaf->nama ?? 'Admin' 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A10:I10")->getFont()->setBold(true); 
        $sheet->getStyle("A10:I{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle("A10:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $sheet->getStyle("F10:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

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

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'SMK NEGERI'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? ''));
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN REKAP POIN KEDISIPLINAN SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A1:{$lastCol}7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->setCellValue('A8', "Kelas: {$this->namaKelas}");
                $sheet->setCellValue('A9', "Periode: " . $this->labelWaktu);

                $isFiltered = !str_contains($this->labelWaktu, 'Seluruh');
                $summaryRow = $dataLastRow + 2;
                $sheet->setCellValue("A{$summaryRow}", $isFiltered ? "RINGKASAN POIN: BULAN INI VS KESELURUHAN" : "RINGKASAN TOTAL POIN SISWA");
                $sheet->getStyle("A{$summaryRow}")->getFont()->setBold(true);
                
                $h = $summaryRow + 1;
                if ($isFiltered) {
                    $headers = ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'POS (+) BULAN INI', 'NEG (-) BULAN INI', 'TOTAL (+) KESELURUHAN', 'TOTAL (-) KESELURUHAN'];
                    $lastSumCol = 'H';
                } else {
                    $headers = ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'TOTAL POIN (+)', 'TOTAL POIN (-)'];
                    $lastSumCol = 'F';
                }

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

                uasort($this->totalsPeriode, function($a, $b) {
                    return $b['n_bulan_ini'] <=> $a['n_bulan_ini'];
                });

                $curr = $h + 1;
                $no = 1;
                foreach ($this->totalsPeriode as $id => $data) {
                    $sheet->setCellValue("A{$curr}", $no++);
                    $sheet->setCellValue("B{$curr}", "'" . $data['nis']);
                    $sheet->setCellValue("C{$curr}", "'" . $data['nisn']); 
                    $sheet->setCellValue("D{$curr}", $data['nama']);

                    if ($isFiltered) {
                        $sheet->setCellValue("E{$curr}", $data['p_bulan_ini']);
                        $sheet->setCellValue("F{$curr}", $data['n_bulan_ini']);
                        $sheet->setCellValue("G{$curr}", $akumulasiGlobal[$id]->tot_p ?? 0);
                        $sheet->setCellValue("H{$curr}", $akumulasiGlobal[$id]->tot_n ?? 0);
                    } else {
                        $sheet->setCellValue("E{$curr}", $data['p_bulan_ini']);
                        $sheet->setCellValue("F{$curr}", $data['n_bulan_ini']);
                    }
                    $curr++;
                }

                $sheet->getStyle("A{$h}:{$lastSumCol}" . ($curr - 1))->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                ]);
                $sheet->getStyle("A" . ($h+1) . ":C" . ($curr - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E" . ($h+1) . ":{$lastSumCol}" . ($curr - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $waka = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 3)
                    ->where('guru_staf.is_active', true)
                    ->select('guru_staf.nama', 'guru_staf.nip') 
                    ->first();

                $ttdRow = $curr + 2;
                $ttdCol = $isFiltered ? 'H' : 'F';
                $sheet->setCellValue("{$ttdCol}{$ttdRow}", "Tasikmalaya, " . date('d F Y'));
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 1), "Waka Kesiswaan,");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 5), $waka ? "( " . $waka->nama . " )" : "( ____________________ )");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 6), $waka ? "NIP. " . $waka->nip : "NIP. ..........................");
                $sheet->getStyle("{$ttdCol}{$ttdRow}:{$ttdCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("{$ttdCol}" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}