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
    protected $query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA;
    private $rowNumber = 0;
    private $totalsPeriode = [];

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA = null)
    {
        // Menangani nama kelas agar tidak tertulis 'K023' tapi nama aslinya
        $this->query = $query;
        $this->namaKelas = $namaKelas; 
        $this->labelWaktu = $labelWaktu;
        $this->namaTA = $namaTA; 
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
    }

    public function startCell(): string { return 'A11'; }

    public function query() 
    { 
        // Query tetap menggunakan filter yang sudah dibuild di Controller
        return $this->query->with(['siswa.kelasAktif.kelas', 'guruStaf', 'tahunAjaran']); 
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
            strtoupper($poin->siswa->nama_lengkap ?? '-'),
            $positif > 0 ? $positif : '',
            $negatif > 0 ? $negatif : '',
            $poin->indikator ?? '-',
            strtoupper($poin->guruStaf->nama ?? 'ADMIN') 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        // Pastikan styling tidak error jika data kosong
        if ($lastRow < 11) $lastRow = 11;

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

                // Header Kop Surat
                $namaSekolah = $this->profil->nama_sekolah ?? 'NAMA SEKOLAH';
                $alamat = $this->kontak->alamat_lengkap ?? '-';
                $telp = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($namaSekolah));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($alamat . " | TELP: " . $telp));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', strtoupper("EMAIL: " . $email . " | NPSN: " . $npsn));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                // Judul Laporan
                $sheet->mergeCells("A7:{$lastCol}7"); $sheet->setCellValue('A7', 'LAPORAN REKAP POIN KEDISIPLINAN SISWA');
                $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A7")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Info Filter (Menggunakan variabel yang sudah diproses di Controller)
                $sheet->setCellValue('A8', "KELAS: " . strtoupper($this->namaKelas));
                $sheet->setCellValue('A9', "PERIODE: " . strtoupper($this->labelWaktu));
                $sheet->setCellValue('A10', "TAHUN AJARAN: " . strtoupper($this->namaTA ?? '-'));
                $sheet->getStyle('A8:A10')->getFont()->setBold(true);

                // Logika Ringkasan (Summary)
                $isFiltered = (stripos($this->labelWaktu, 'Kumulatif') === false);
                $summaryRow = $dataLastRow + 2;
                $sheet->setCellValue("A{$summaryRow}", $isFiltered ? "RINGKASAN POIN: PERIODE INI VS KUMULATIF" : "RINGKASAN TOTAL POIN KUMULATIF SISWA");
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

                // Ambil Data Akumulasi Global untuk Ringkasan
                $siswaIds = array_keys($this->totalsPeriode);
                $akumulasiGlobal = [];
                if (!empty($siswaIds)) {
                    $akumulasiGlobal = DB::table('poin_siswa')
                        ->whereIn('siswa_id', $siswaIds)
                        ->select('siswa_id', DB::raw('SUM(poin_positif) as tot_p'), DB::raw('SUM(poin_negatif) as tot_n'))
                        ->groupBy('siswa_id')->get()->keyBy('siswa_id');
                }

                // Urutkan ringkasan berdasarkan poin negatif terbanyak secara kumulatif
                uasort($this->totalsPeriode, function($a, $b) use ($akumulasiGlobal) {
                    // Mendapatkan ID siswa dari array totalsPeriode
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

                // Border untuk tabel ringkasan
                if ($curr > $h + 1) {
                    $sheet->getStyle("A{$h}:{$lastSumCol}" . ($curr - 1))->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                }

                // Tanda Tangan (Waka Kesiswaan)
                $waka = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 3)
                    ->where('guru_staf.is_active', true)
                    ->select('guru_staf.nama', 'guru_staf.nip') 
                    ->first();

                $ttdRow = $curr + 2;
                $ttdCol = $lastSumCol;
                $kab = $this->profil->kabupaten ?? 'KABUPATEN';
                
                $sheet->setCellValue("{$ttdCol}{$ttdRow}", strtoupper($kab) . ", " . strtoupper(date('d F Y')));
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 1), "WAKA KESISWAAN,");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 5), $waka ? "( " . strtoupper($waka->nama) . " )" : "( ____________________ )");
                $sheet->setCellValue("{$ttdCol}" . ($ttdRow + 6), $waka ? "NIP. " . $waka->nip : "NIP. ..........................");
                $sheet->getStyle("{$ttdCol}{$ttdRow}:{$ttdCol}" . ($ttdRow + 6))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("{$ttdCol}" . ($ttdRow + 5))->getFont()->setBold(true)->setUnderline(true);
            },
        ];
    }
}