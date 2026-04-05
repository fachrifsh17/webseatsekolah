<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class MapelExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak, $semester;
    private $rowNumber = 0;

    public function __construct($filters, $profil, $kontak, $semester)
    {
        $this->filters = $filters;
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->semester = $semester;
        
        Carbon::setLocale('id');
    }

    public function startCell(): string 
    { 
        return 'A13'; 
    }

    public function query()
    {
        $query = MataPelajaran::with(['jurusan']);

        if (isset($this->filters['is_active'])) {
            $query->where('is_active', $this->filters['is_active']);
        }

        if (!empty($this->filters['search'])) {
            $query->where('nama_mapel', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['kategori_mapel'])) {
            $query->where('kategori_mapel', $this->filters['kategori_mapel']);
        }

        return $query->latest();
    }

    public function headings(): array
    {
        return [
            'NO',
            'ID MAPEL',
            'NAMA MATA PELAJARAN',
            'JURUSAN',
            'KATEGORI',
            'STATUS'
        ];
    }

    public function map($mapel): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $mapel->id,
            strtoupper($mapel->nama_mapel),
            $mapel->jurusan->nama_jurusan ?? 'UMUM',
            strtoupper($mapel->kategori_mapel),
            $mapel->is_active ? 'AKTIF' : 'NON-AKTIF',
        ];
    }

    public function styles(Worksheet $sheet) {}

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'F'; 
                $dataLastRow = $sheet->getHighestRow();

                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setAutoSize(true); 
                $sheet->getColumnDimension('C')->setWidth(35);
                $sheet->getColumnDimension('D')->setWidth(25);
                $sheet->getColumnDimension('E')->setWidth(20);
                $sheet->getColumnDimension('F')->setWidth(15);

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(80);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(45); 
                        $drawingProv->setOffsetY(15);
                        $drawingProv->setEditAs('oneCell');
                        $drawingProv->setWorksheet($sheet);
                    }
                }

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $cabdin = strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN');
                $namaSekolah = strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH');
                
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $pos = $this->kontak->kode_pos ?? '-';
                $telepon = $this->kontak->telepon ?? '-';
                $email = $this->kontak->email_resmi ?? '-';
                $npsn = $this->profil->npsn ?? '-';

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', $cabdin);
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', $namaSekolah);
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} {$pos} {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); 
                $sheet->setCellValue('A6', "Telp: {$telepon} | Email: {$email} | NPSN: {$npsn}");
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setSize(14);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', 'DAFTAR MATA PELAJARAN');
                $sheet->getStyle('A8')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A9:{$lastCol}9"); 
                $namaTA = $this->semester->tahunAjaran->nama ?? '-';
                $namaSemester = strtoupper($this->semester->nama ?? '-');
                $sheet->setCellValue('A9', "TAHUN PELAJARAN " . $namaTA . " - SEMESTER " . $namaSemester);
                $sheet->getStyle('A9')->getFont()->setBold(true);
                $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusanName = 'SEMUA JURUSAN';
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $jurusan ? strtoupper($jurusan->nama_jurusan) : 'SEMUA JURUSAN';
                }
                $sheet->setCellValue('A10', "JURUSAN : " . $jurusanName);
                $sheet->setCellValue('A11', "KATEGORI : " . (!empty($this->filters['kategori_mapel']) ? strtoupper($this->filters['kategori_mapel']) : 'SEMUA KATEGORI'));
                $sheet->setCellValue('A12', "STATUS : " . (isset($this->filters['is_active']) ? ($this->filters['is_active'] ? 'AKTIF' : 'NON-AKTIF') : 'SEMUA STATUS'));

                $sheet->getStyle("A13:{$lastCol}13")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
                ]);

                $sheet->getStyle("A13:{$lastCol}{$dataLastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
                ]);

                $ttdRow = $dataLastRow + 2;
                $kepsek = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 1)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $wakaKur = DB::table('struktur_jabatan')->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')->where('struktur_jabatan.jabatan_id', 2)->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')->first();
                $lokasiTtd = strtoupper($this->kontak->kabupaten_kota ?? 'TASIKMALAYA');

                $sheet->mergeCells("A{$ttdRow}:B{$ttdRow}");
                $sheet->setCellValue("A{$ttdRow}", "Mengetahui,");
                $sheet->mergeCells("E{$ttdRow}:{$lastCol}{$ttdRow}"); 
                $sheet->setCellValue("E{$ttdRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                
                $ttdRow++;
                $sheet->mergeCells("A{$ttdRow}:B{$ttdRow}");
                $sheet->setCellValue("A{$ttdRow}", "Waka Kurikulum,");
                $sheet->mergeCells("E{$ttdRow}:{$lastCol}{$ttdRow}");
                $sheet->setCellValue("E{$ttdRow}", "Kepala Sekolah,");
                $sheet->getStyle("A" . ($ttdRow-1) . ":{$lastCol}{$ttdRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $imageRow = $ttdRow + 1;
                if ($wakaKur && $wakaKur->file_ttd) {
                    $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $wakaKur->file_ttd));
                    if (file_exists($pathWaka)) {
                        $drawWaka = new Drawing();
                        $drawWaka->setPath($pathWaka);
                        $drawWaka->setHeight(70);
                        $drawWaka->setCoordinates("A{$imageRow}"); 
                        $drawWaka->setOffsetX(45); 
                        $drawWaka->setEditAs('oneCell');
                        $drawWaka->setWorksheet($sheet);
                    }
                }

                if ($kepsek && $kepsek->file_ttd) {
                    $pathKepsek = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $kepsek->file_ttd));
                    if (file_exists($pathKepsek)) {
                        $drawKepsek = new Drawing();
                        $drawKepsek->setPath($pathKepsek);
                        $drawKepsek->setHeight(70);
                        $drawKepsek->setCoordinates("E{$imageRow}"); 
                        $drawKepsek->setOffsetX(60); 
                        $drawKepsek->setEditAs('oneCell');
                        $drawKepsek->setWorksheet($sheet);
                    }
                }

                $namaRow = $ttdRow + 4;
                $sheet->mergeCells("A{$namaRow}:B{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '____________________') . " )");
                $sheet->mergeCells("E{$namaRow}:{$lastCol}{$namaRow}");
                $sheet->setCellValue("E{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getFont()->setBold(true)->setUnderline(true);
                $sheet->getStyle("A{$namaRow}:{$lastCol}{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:B{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKur->nip ?? '........................'));
                $sheet->mergeCells("E{$nipRow}:{$lastCol}{$nipRow}");
                $sheet->setCellValue("E{$nipRow}", "NIP. " . ($kepsek->nip ?? '........................'));
                $sheet->getStyle("A{$nipRow}:{$lastCol}{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}