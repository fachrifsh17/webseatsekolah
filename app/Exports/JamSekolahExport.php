<?php

namespace App\Exports;

use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class JamSekolahExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithCustomStartCell
{
    protected $profil, $kontak, $tahunAjaranId;

    public function __construct($profil, $kontak, $tahunAjaranId)
    {
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->tahunAjaranId = $tahunAjaranId;
    }

    public function startCell(): string 
    { 
        return 'A11'; 
    }

    public function collection() 
    { 
        return collect([]); 
    }

    public function headings(): array
    {
        return ['PUKUL', 'SENIN', 'PUKUL', 'SELASA', 'PUKUL', 'RABU', 'PUKUL', 'KAMIS', 'PUKUL', 'JUM\'AT'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $lastCol = 'J';

                $sheet->mergeCells("A1:{$lastCol}1"); 
                $sheet->setCellValue('A1', 'PEMERINTAH PROVINSI JAWA BARAT');
                $sheet->mergeCells("A2:{$lastCol}2"); 
                $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); 
                $sheet->setCellValue('A3', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A4:{$lastCol}4"); 
                $sheet->setCellValue('A4', ($this->kontak->alamat_lengkap ?? '') . " | Telp: " . ($this->kontak->telepon ?? ''));
                $sheet->mergeCells("A5:{$lastCol}5"); 
                $sheet->setCellValue('A5', "Email: " . ($this->kontak->email_resmi ?? '') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}3")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastCol}5")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A7:{$lastCol}7"); 
                $sheet->setCellValue('A7', 'PENYESUAIAN JAM PELAJARAN');
                $sheet->mergeCells("A8:{$lastCol}8"); 
                
                $ta = TahunAjaran::find($this->tahunAjaranId);
                $sheet->setCellValue('A8', 'TAHUN PELAJARAN ' . ($ta->nama ?? ''));
                
                $sheet->getStyle("A7:A8")->getFont()->setBold(true);
                $sheet->getStyle("A7:A8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $hariMap = [
                    'Senin' => ['t' => 'A', 'l' => 'B'],
                    'Selasa' => ['t' => 'C', 'l' => 'D'],
                    'Rabu' => ['t' => 'E', 'l' => 'F'],
                    'Kamis' => ['t' => 'G', 'l' => 'H'],
                    'Jumat' => ['t' => 'I', 'l' => 'J']
                ];

                $dataPerHari = JamSekolah::where('tahun_ajaran_id', $this->tahunAjaranId)
                    ->orderBy('waktu_mulai')
                    ->get()
                    ->groupBy('hari');

                $rowStart = 12;

                foreach ($hariMap as $namaHari => $cols) {
                    $currentRow = $rowStart;
                    if (isset($dataPerHari[$namaHari])) {
                        foreach ($dataPerHari[$namaHari] as $jam) {
                            $waktu = Carbon::parse($jam->waktu_mulai)->format('H.i') . ' - ' . Carbon::parse($jam->waktu_selesai)->format('H.i');
                            $sheet->setCellValue($cols['t'] . $currentRow, $waktu);
                            
                            $label = '';
                            if ($jam->jenis === 'Pelajaran') {
                                $label = $jam->jam_ke;
                            } elseif ($jam->jenis === 'Istirahat') {
                                $label = 'ISTIRAHAT';
                            } else {
                                $label = strtoupper($jam->keterangan ?? 'KEGIATAN');
                            }
                            $sheet->setCellValue($cols['l'] . $currentRow, $label);

                            if ($jam->jenis === 'Istirahat') {
                                $sheet->getStyle($cols['t'] . $currentRow . ':' . $cols['l'] . $currentRow)
                                      ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                            } elseif ($jam->jenis === 'Kegiatan') {
                                $sheet->getStyle($cols['t'] . $currentRow . ':' . $cols['l'] . $currentRow)
                                      ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6E0B4');
                            }

                            $currentRow++;
                        }
                    }
                }

                $maxRow = $sheet->getHighestRow();
                if ($maxRow >= 11) {
                    $sheet->getStyle("A11:J$maxRow")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                    ]);
                    $sheet->getStyle("A11:J11")->getFont()->setBold(true);
                }
            },
        ];
    }
}