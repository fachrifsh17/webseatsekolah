<?php

namespace App\Exports;

use App\Models\MataPelajaran;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class MapelExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithCustomStartCell
{
    protected $filters, $profil, $kontak;

    public function __construct($filters, $profil, $kontak)
    {
        $this->filters = $filters;
        $this->profil = $profil;
        $this->kontak = $kontak;
    }

    public function startCell(): string 
    { 
        return 'A12'; 
    }

    public function query()
    {
        $query = MataPelajaran::with(['jurusan']);

        if (isset($this->filters['aktif'])) {
            $query->where('aktif', $this->filters['aktif']);
        }

        if (!empty($this->filters['search'])) {
            $query->where('nama_mapel', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['jurusan_id'])) {
            $query->where('jurusan_id', $this->filters['jurusan_id']);
        }

        if (!empty($this->filters['tipe_mapel'])) {
            $query->where('tipe_mapel', $this->filters['tipe_mapel']);
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
            'TIPE',
            'KATEGORI',
            'STATUS'
        ];
    }

    private $rowNumber = 0;
    public function map($mapel): array
    {
        return [
            ++$this->rowNumber,
            $mapel->id,
            $mapel->nama_mapel,
            $mapel->jurusan->nama_jurusan ?? 'UMUM',
            strtoupper($mapel->tipe_mapel),
            strtoupper($mapel->kategori_mapel),
            $mapel->aktif ? 'AKTIF' : 'NON-AKTIF',
        ];
    }

    public function styles(Worksheet $sheet)
    {
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'G';
                $lastRow = $sheet->getHighestRow();

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'DAFTAR MATA PELAJARAN');
                $sheet->getStyle("A7")->getFont()->setBold(true)->setSize(12);
                
                $sheet->mergeCells("A8:{$lastCol}8"); 
                $ta = TahunAjaran::where('is_active', 1)->first();
                $sheet->setCellValue('A8', 'TAHUN PELAJARAN ' . ($ta->nama ?? '-'));
                $sheet->getStyle("A8")->getFont()->setBold(true)->setSize(10);

                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $jurusanName = 'Semua Jurusan';
                if (!empty($this->filters['jurusan_id'])) {
                    $jurusan = Jurusan::find($this->filters['jurusan_id']);
                    $jurusanName = $jurusan ? $jurusan->nama_jurusan : 'Semua Jurusan';
                }

                $tipeLabel = !empty($this->filters['tipe_mapel']) ? strtoupper($this->filters['tipe_mapel']) : 'SEMUA TIPE';
                $kategoriLabel = !empty($this->filters['kategori_mapel']) ? strtoupper($this->filters['kategori_mapel']) : 'SEMUA KATEGORI';
                $statusLabel = isset($this->filters['aktif']) ? ($this->filters['aktif'] ? 'AKTIF' : 'NON-AKTIF') : 'SEMUA STATUS';

                $filterText = "Filter: Jurusan ($jurusanName) | Tipe ($tipeLabel) | Kategori ($kategoriLabel) | Status ($statusLabel)";
                $sheet->mergeCells("A9:{$lastCol}9");
                $sheet->setCellValue('A9', $filterText);
                $sheet->getStyle("A9")->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A10:{$lastCol}10"); 
                $sheet->setCellValue('A10', "Tanggal Cetak: " . date('d/m/Y H:i'));
                $sheet->getStyle("A10")->getFont()->setSize(9);
                $sheet->getStyle("A10")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->getStyle("A12:{$lastCol}12")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF2E75B6']
                    ],
                ]);

                $sheet->getStyle("A12:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                for ($i = 13; $i <= $lastRow; $i++) {
                    $tipe = $sheet->getCell("E$i")->getValue();
                    if (in_array($tipe, ['PRODUKTIF', 'KHUSUS'])) {
                        $sheet->getStyle("E$i")->getFont()->getColor()->setARGB('FFFF0000');
                    } else {
                        $sheet->getStyle("E$i")->getFont()->getColor()->setARGB('FF0070C0');
                    }

                    $status = $sheet->getCell("G$i")->getValue();
                    if ($status === 'NON-AKTIF') {
                        $sheet->getStyle("G$i")->getFont()->getColor()->setARGB('FFFF0000');
                    }
                }
            },
        ];
    }
}