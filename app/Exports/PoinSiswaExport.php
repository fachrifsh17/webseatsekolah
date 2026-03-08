<?php

namespace App\Exports;

use App\Models\PoinSiswa;
use Maatwebsite\Excel\Concerns\{FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings};
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Border};
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PoinSiswaExport implements FromQuery, WithMapping, WithStyles, WithEvents, WithCustomStartCell, WithHeadings
{
    protected $query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA, $namaSemester;
    private $rowNumber = 0;
    private $totalsPeriode = [];

    public function __construct($query, $namaKelas, $labelWaktu, $profil, $kontak, $namaTA = null, $namaSemester = null)
    {
        $this->query = $query;
        $this->namaKelas = $namaKelas;
        $this->labelWaktu = $labelWaktu;
        $this->namaTA = $namaTA;
        $this->namaSemester = $namaSemester;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        
        Carbon::setLocale('id');
    }

    public function startCell(): string { return 'A13'; }

    public function query() 
    { 
        return $this->query->with(['siswa.riwayatKelas.kelas', 'guruStaf', 'semester']); 
    }

    public function headings(): array 
    {
        return ['NO', 'TANGGAL', 'NIS', 'NISN', 'NAMA LENGKAP', 'KELAS', 'POIN', 'KETERANGAN / INDIKATOR', 'GURU PELAPOR'];
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
                'kelas' => $this->namaKelas,
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
            strtoupper($this->namaKelas),
            $poinTampil,
            $poin->indikator ?? '-',
            strtoupper($poin->guruStaf->nama ?? 'ADMIN') 
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        if ($lastRow < 13) $lastRow = 13;

        $sheet->getStyle("A13:I13")->getFont()->setBold(true); 
        $sheet->getStyle("A13:I{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        $sheet->getStyle("A13:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $sheet->getStyle("F13:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

        foreach (range('B', 'G') as $col) { 
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension('I')->setAutoSize(true);

        $sheet->getColumnDimension('A')->setAutoSize(false);
        $sheet->getColumnDimension('A')->setWidth(3);

        $sheet->getColumnDimension('H')->setAutoSize(false);
        $sheet->getColumnDimension('H')->setWidth(25);
        $sheet->getStyle("H13:H{$lastRow}")->getAlignment()->setWrapText(true);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $pSheet = $sheet->getDelegate();
                $lastCol = 'I'; 
                $dataLastRow = $sheet->getHighestRow();

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(35);
                        $drawingProv->setOffsetY(10);
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); 
                $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN') . " WILAYAH XII");
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); 
                $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); $sheet->setCellValue('A8', 'LAPORAN REKAP POIN KEDISIPLINAN SISWA');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $taClean = preg_replace('/[^0-9\/]/', '', $this->namaTA);
                $sheet->mergeCells("A9:{$lastCol}9"); 
                $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $taClean . " - SEMESTER " . strtoupper($this->namaSemester));
                $sheet->getStyle("A9")->getFont()->setBold(true);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A10:{$lastCol}10"); $sheet->setCellValue('A10', "KELAS : " . strtoupper($this->namaKelas));
                $sheet->mergeCells("A11:{$lastCol}11");
                $periodeTampil = !empty($this->labelWaktu) ? strtoupper(Carbon::parse($this->labelWaktu)->translatedFormat('F Y')) : 'KESELURUHAN';
                $sheet->setCellValue('A11', "PERIODE : " . $periodeTampil);
                
                $sheet->mergeCells("A12:{$lastCol}12");
                $pencarianTampil = request()->filled('search') ? strtoupper(request()->search) : '-';
                $sheet->setCellValue('A12', "PENCARIAN : " . $pencarianTampil);

                $sheet->getStyle("A10:A12")->getFont()->setBold(false);

                $summaryHeaderRow = $dataLastRow + 2; 
                $sheet->setCellValue("A{$summaryHeaderRow}", "RINGKASAN POIN: PERIODE INI VS KUMULATIF");
                $sheet->getStyle("A{$summaryHeaderRow}")->getFont()->setBold(true);
                
                $h = $summaryHeaderRow + 1;
                $headers = ['NO', 'NIS', 'NISN', 'NAMA SISWA', 'KELAS', 'POS (+) PERIODE', 'NEG (-) PERIODE', 'TOTAL (+) KUMULATIF', 'TOTAL (-) KUMULATIF'];
                foreach ($headers as $key => $val) { $sheet->setCellValue(chr(65 + $key) . $h, $val); }
                $sheet->getStyle("A{$h}:I{$h}")->getFont()->setBold(true);
                $sheet->getStyle("A{$h}:I{$h}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $siswaIds = array_keys($this->totalsPeriode);
                $akumulasiGlobal = !empty($siswaIds) ? DB::table('poin_siswa')->whereIn('siswa_id', $siswaIds)->select('siswa_id', DB::raw('SUM(poin_positif) as tot_p'), DB::raw('SUM(poin_negatif) as tot_n'))->groupBy('siswa_id')->get()->keyBy('siswa_id') : [];

                $curr = $h + 1; $no = 1;
                foreach ($this->totalsPeriode as $id => $data) {
                    $sheet->setCellValue("A{$curr}", $no++);
                    $sheet->setCellValue("B{$curr}", "'" . $data['nis']);
                    $sheet->setCellValue("C{$curr}", "'" . $data['nisn']); 
                    $sheet->setCellValue("D{$curr}", strtoupper($data['nama']));
                    $sheet->setCellValue("E{$curr}", strtoupper($data['kelas']));
                    $sheet->setCellValue("F{$curr}", $data['p_periode'] ?: 0);
                    $sheet->setCellValue("G{$curr}", $data['n_periode'] ?: 0);
                    $sheet->setCellValue("H{$curr}", $akumulasiGlobal[$id]->tot_p ?? 0);
                    $sheet->setCellValue("I{$curr}", $akumulasiGlobal[$id]->tot_n ?? 0);
                    $curr++;
                }
                $sheet->getStyle("A{$h}:I" . ($curr - 1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

                $ttdRow = $curr + 2;
                $waka = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'waka-kesiswaan')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->join('jabatans', 'struktur_jabatan.jabatan_id', '=', 'jabatans.id')->where('jabatans.slug', 'kepala-sekolah')->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();

                $sheet->mergeCells("G{$ttdRow}:I{$ttdRow}");
                $sheet->setCellValue("G{$ttdRow}", strtoupper($kotaKab) . ", " . Carbon::now()->translatedFormat('d F Y'));
                
                $labelRow = $ttdRow + 1;
                $sheet->mergeCells("A{$labelRow}:C{$labelRow}");
                $sheet->setCellValue("A{$labelRow}", "Mengetahui,");
                $sheet->mergeCells("G{$labelRow}:I{$labelRow}");
                $sheet->setCellValue("G{$labelRow}", "Menyetujui,");
                
                $jabatanRow = $labelRow + 1;
                $sheet->mergeCells("A{$jabatanRow}:C{$jabatanRow}");
                $sheet->setCellValue("A{$jabatanRow}", "Waka Kesiswaan,");
                $sheet->mergeCells("G{$jabatanRow}:I{$jabatanRow}");
                $sheet->setCellValue("G{$jabatanRow}", "Kepala Sekolah,");

                if ($waka && $waka->file_ttd) {
                    $this->insertTtd($sheet, $waka->file_ttd, 'B' . ($jabatanRow + 1));
                }
                if ($kepsek && $kepsek->file_ttd) {
                    $this->insertTtd($sheet, $kepsek->file_ttd, 'H' . ($jabatanRow + 1));
                }

                $namaRow = $jabatanRow + 4;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($waka->nama ?? '____________________') . " )");
                $sheet->mergeCells("G{$namaRow}:I{$namaRow}");
                $sheet->setCellValue("G{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                
                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($waka->nip ?? '...........................'));
                $sheet->mergeCells("G{$nipRow}:I{$nipRow}");
                $sheet->setCellValue("G{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));

                $sheet->getStyle("A{$ttdRow}:I{$jabatanRow}")->getFont()->setBold(false);
                $sheet->getStyle("A{$namaRow}:I{$namaRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$nipRow}:I{$nipRow}")->getFont()->setBold(false);

                $sheet->getStyle("A{$ttdRow}:I{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }

    private function insertTtd($sheet, $filePath, $coordinates, $offsetX = 20)
    {
        $path = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $filePath));
        if (file_exists($path)) {
            $drawing = new Drawing();
            $drawing->setPath($path);
            $drawing->setHeight(55);
            $drawing->setCoordinates($coordinates);
            $drawing->setOffsetX(50);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet->getDelegate());
        }
    }
}