<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\Jurusan;

class GuruMapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $query, $profil, $kontak, $filters;
    private $rowNumber = 0;

    public function __construct($query, $profil, $kontak, $filters = [])
    {
        $this->query = $query;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->filters = $filters;
    }

    public function startCell(): string 
    { 
        return 'A16'; 
    }

    public function query()
    {
        return $this->query->with(['guru', 'mapel', 'kelas', 'semester', 'jamMulai', 'jamSelesai']);
    }

    public function headings(): array
    {
        return [
            'NO', 'ID GURU', 'NAMA GURU', 'NIP', 'NUPTK', 'MATA PELAJARAN', 
            'KATEGORI', 'KELAS', 'HARI', 'URUTAN JAM', 'STATUS'
        ];
    }

    public function map($item): array
    {
        $this->rowNumber++;
        $jamKeMulai = $item->jamMulai->jam_ke ?? '-';
        $jamKeSelesai = $item->jamSelesai->jam_ke ?? '-';
        $jamFormatted = ($jamKeMulai !== '-' && $jamKeSelesai !== '-') 
            ? "{$jamKeMulai} - {$jamKeSelesai}" 
            : $jamKeMulai;

        $guru = $item->guru;
        
        return [
            $this->rowNumber,
            $guru->id ?? '-',
            strtoupper($guru->nama ?? '-'),
            $guru && $guru->nip ? "'" . $guru->nip : '-',
            $guru && $guru->nuptk ? "'" . $guru->nuptk : '-',
            strtoupper($item->mapel->nama_mapel ?? '-'),
            strtoupper($item->mapel->kategori_mapel ?? $item->mapel->tipe_mapel ?? '-'),
            strtoupper($item->kelas->nama_kelas ?? '-'),
            strtoupper($item->hari ?? '-'),
            $jamFormatted,
            'AKTIF'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastCol = 'K';

        $sheet->getStyle("A16:{$lastCol}16")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle("A16:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN, 
                    'color' => ['argb' => Color::COLOR_BLACK]
                ]
            ],
            'font' => ['size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]
        ]);

        $sheet->getStyle("A17:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D17:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G17:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $worksheet = $event->sheet->getDelegate(); 
                $lastCol = 'K'; 
                $lastRow = $worksheet->getHighestRow();

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(35);
                        $drawingProv->setOffsetY(10);
                        $drawingProv->setEditAs('oneCell');
                        $drawingProv->setWorksheet($worksheet);
                    }
                }

                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 1)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $wakaKur = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 2)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();

                $prov = $this->kontak->provinsi ?? 'Jawa Barat';
                $alamatFull = ($this->kontak->alamat_jalan ?? '-') . ", DESA " . ($this->kontak->desa_kelurahan ?? '-') . " KEC. " . ($this->kontak->kecamatan ?? '-') . ", " . ($this->kontak->kabupaten_kota ?? 'Tasikmalaya') . " - " . $prov;

                $worksheet->mergeCells("A1:{$lastCol}1"); $worksheet->setCellValue('A1', "PEMERINTAH PROVINSI " . strtoupper($prov));
                $worksheet->mergeCells("A2:{$lastCol}2"); $worksheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $worksheet->mergeCells("A3:{$lastCol}3"); $worksheet->setCellValue('A3', strtoupper($this->profil->cadis ?? ''));
                $worksheet->mergeCells("A4:{$lastCol}4"); $worksheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? ''));
                $worksheet->mergeCells("A5:{$lastCol}5"); $worksheet->setCellValue('A5', $alamatFull);
                $worksheet->mergeCells("A6:{$lastCol}6"); $worksheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $worksheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $worksheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $worksheet->mergeCells("A8:{$lastCol}8"); $worksheet->setCellValue('A8', 'DAFTAR PENUGASAN GURU MATA PELAJARAN');
                $worksheet->mergeCells("A9:{$lastCol}9"); $worksheet->setCellValue('A9', "TAHUN PELAJARAN " . ($this->filters['tahun_ajaran'] ?? '-') . " - SEMESTER " . strtoupper($this->filters['semester'] ?? '-'));
                $worksheet->getStyle("A8:A9")->getFont()->setBold(true)->setSize(11);
                $worksheet->getStyle("A8:A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $guruName = '-';
                if(isset($this->filters['guru_staf_id'])) {
                    $g = GuruStaf::find($this->filters['guru_staf_id']);
                    $guruName = $g ? strtoupper($g->nama) : '-';
                }

                $mapelName = '-';
                if(isset($this->filters['mata_pelajaran_id'])) {
                    $m = MataPelajaran::find($this->filters['mata_pelajaran_id']);
                    $mapelName = $m ? strtoupper($m->nama_mapel) : '-';
                }

                $kelasName = '-';
                if(isset($this->filters['kelas_id'])) {
                    $k = Kelas::find($this->filters['kelas_id']);
                    $kelasName = $k ? strtoupper($k->nama_kelas) : '-';
                }

                $jurusanName = '-';
                if(isset($this->filters['jurusan_id'])) {
                    $j = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $j ? strtoupper($j->nama_jurusan) : '-';
                }

                $worksheet->setCellValue('A10', "GURU      : " . $guruName);
                $worksheet->setCellValue('A11', "MAPEL     : " . $mapelName);
                $worksheet->setCellValue('A12', "KELAS     : " . $kelasName);
                $worksheet->setCellValue('A13', "HARI      : " . strtoupper($this->filters['hari'] ?? 'SEMUA HARI'));
                $worksheet->setCellValue('A14', "KATEGORI  : " . strtoupper($this->filters['kategori_mapel'] ?? 'SEMUA KATEGORI'));
                $worksheet->setCellValue('A15', "JURUSAN   : " . $jurusanName);
                
                $worksheet->getStyle("A10:A15")->getFont()->setBold(false)->setItalic(false)->setSize(9);

                $ttgRow = $lastRow + 2;
                $kota = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $worksheet->mergeCells("I{$ttgRow}:K{$ttgRow}");
                $worksheet->setCellValue("I{$ttgRow}", $kota . ", " . Carbon::now()->translatedFormat('d F Y'));
                $worksheet->getStyle("I{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $worksheet->mergeCells("B{$ttgRow}:D{$ttgRow}"); $worksheet->setCellValue("B{$ttgRow}", "Mengetahui,");
                $worksheet->mergeCells("I{$ttgRow}:K{$ttgRow}"); $worksheet->setCellValue("I{$ttgRow}", "Menyetujui,");
                $worksheet->getStyle("B{$ttgRow}:K{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $worksheet->mergeCells("B{$ttgRow}:D{$ttgRow}"); $worksheet->setCellValue("B{$ttgRow}", "Waka Kurikulum,");
                $worksheet->mergeCells("I{$ttgRow}:K{$ttgRow}"); $worksheet->setCellValue("I{$ttgRow}", "Kepala Sekolah,");
                $worksheet->getStyle("B{$ttgRow}:K{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $imgRow = $ttgRow + 1;

                if ($wakaKur && $wakaKur->file_ttd) {
                    $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $wakaKur->file_ttd));
                    if (file_exists($pathWaka)) {
                        $drawWaka = new Drawing();
                        $drawWaka->setPath($pathWaka);
                        $drawWaka->setHeight(50);
                        $drawWaka->setCoordinates("B{$imgRow}");
                        $drawWaka->setOffsetX(60);
                        $drawWaka->setOffsetY(-5);
                        $drawWaka->setEditAs('oneCell');
                        $drawWaka->setWorksheet($worksheet);
                    }
                }

                if ($kepsek && $kepsek->file_ttd) {
                    $pathKepsek = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $kepsek->file_ttd));
                    if (file_exists($pathKepsek)) {
                        $drawKepsek = new Drawing();
                        $drawKepsek->setPath($pathKepsek);
                        $drawKepsek->setHeight(50);
                        $drawKepsek->setCoordinates("I{$imgRow}");
                        $drawKepsek->setOffsetX(60);
                        $drawKepsek->setOffsetY(-5);
                        $drawKepsek->setEditAs('oneCell');
                        $drawKepsek->setWorksheet($worksheet);
                    }
                }

                $nmRow = $ttgRow + 3; 
                $worksheet->mergeCells("B{$nmRow}:D{$nmRow}");
                $worksheet->setCellValue("B{$nmRow}", "( " . strtoupper($wakaKur->nama ?? '............................') . " )"); 
                $worksheet->mergeCells("I{$nmRow}:K{$nmRow}");
                $worksheet->setCellValue("I{$nmRow}", "( " . strtoupper($kepsek->nama ?? '............................') . " )");
                
                $nipRow = $nmRow + 1;
                $worksheet->mergeCells("B{$nipRow}:D{$nipRow}");
                $worksheet->setCellValue("B{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                $worksheet->mergeCells("I{$nipRow}:K{$nipRow}");
                $worksheet->setCellValue("I{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));

                $worksheet->getStyle("B{$ttgRow}:K{$nipRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $worksheet->getStyle("B{$nmRow}:K{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle("B{$nmRow}:K{$nmRow}")->getFont()->setBold(true);

                $worksheet->setCellValue("A" . ($nipRow + 1), "Dicetak pada: " . Carbon::now()->format('d/m/Y H:i'));
                $worksheet->getStyle("A" . ($nipRow + 1))->getFont()->setItalic(true)->setSize(8);
            },
        ];
    }
}