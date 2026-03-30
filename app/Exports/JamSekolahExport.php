<?php

namespace App\Exports;

use App\Models\JamSekolah;
use App\Models\Semester;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class JamSekolahExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents, WithCustomStartCell
{
    protected $profil, $kontak, $semesterId;

    public function __construct($profil, $kontak, $semesterId)
    {
        $this->profil = is_array($profil) ? (object)$profil : $profil;
        $this->kontak = is_array($kontak) ? (object)$kontak : $kontak;
        $this->semesterId = $semesterId;
    }

    public function startCell(): string 
    { 
        return 'A12'; 
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
                $pSheet = $sheet->getDelegate();
                $lastCol = 'J';

                if (!empty($this->profil->logo_provinsi)) {
                    $pathProv = public_path('uploads/profil/' . str_replace('uploads/profil/', '', $this->profil->logo_provinsi));
                    if (file_exists($pathProv)) {
                        $drawingProv = new Drawing();
                        $drawingProv->setName('Logo Provinsi');
                        $drawingProv->setPath($pathProv);
                        $drawingProv->setHeight(75);
                        $drawingProv->setCoordinates('A1');
                        $drawingProv->setOffsetX(35);
                        $drawingProv->setOffsetY(10);
                        $drawingProv->setEditAs('oneCell');
                        $drawingProv->setWorksheet($pSheet);
                    }
                }

                $kepsek = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 1) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $wakaKur = DB::table('struktur_jabatan')
                    ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                    ->where('struktur_jabatan.jabatan_id', 2) 
                    ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                    ->first();

                $provAsli = $this->kontak->provinsi ?? 'Jawa Barat';
                $provKapital = strtoupper($provAsli);
                $alamatJalan = $this->kontak->alamat_jalan ?? '-';
                $desaKec = "Desa " . ($this->kontak->desa_kelurahan ?? '-') . " Kec. " . ($this->kontak->kecamatan ?? '-');
                $kotaKab = ($this->kontak->kabupaten_kota ?? 'Tasikmalaya');

                $sheet->mergeCells("A1:{$lastCol}1"); $sheet->setCellValue('A1', "PEMERINTAH PROVINSI {$provKapital}");
                $sheet->mergeCells("A2:{$lastCol}2"); $sheet->setCellValue('A2', 'DINAS PENDIDIKAN');
                $sheet->mergeCells("A3:{$lastCol}3"); $sheet->setCellValue('A3', strtoupper($this->profil->cadis ?? 'CABANG DINAS PENDIDIKAN WILAYAH VII'));
                $sheet->mergeCells("A4:{$lastCol}4"); $sheet->setCellValue('A4', strtoupper($this->profil->nama_sekolah ?? 'NAMA SEKOLAH'));
                $sheet->mergeCells("A5:{$lastCol}5"); $sheet->setCellValue('A5', "{$alamatJalan}, {$desaKec}, {$kotaKab} - {$provAsli}");
                $sheet->mergeCells("A6:{$lastCol}6"); $sheet->setCellValue('A6', "Telp: " . ($this->kontak->telepon ?? '-') . " | Email: " . ($this->kontak->email_resmi ?? '-') . " | NPSN: " . ($this->profil->npsn ?? '-'));
                
                $sheet->getStyle("A1:{$lastCol}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}4")->getFont()->setBold(true);
                $sheet->getStyle("A6:{$lastCol}6")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THICK);

                $sheet->mergeCells("A8:{$lastCol}8"); 
                $sheet->setCellValue('A8', 'PENYESUAIAN JAM PELAJARAN');
                
                $selectedSemester = Semester::with('tahunAjaran')->find($this->semesterId);

                $sheet->mergeCells("A9:{$lastCol}9"); 
                $namaTA = $selectedSemester->tahunAjaran->nama ?? '';
                $namaSem = strtoupper($selectedSemester->nama ?? '');
                $textHeader = 'TAHUN PELAJARAN ' . $namaTA . ' - SEMESTER ' . $namaSem;
                $sheet->setCellValue('A9', $textHeader);
                
                $sheet->getStyle("A8:A9")->getFont()->setBold(true);
                $sheet->getStyle("A8:A9")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $hariMap = [
                    'Senin' => ['t' => 'A', 'l' => 'B'], 
                    'Selasa' => ['t' => 'C', 'l' => 'D'], 
                    'Rabu' => ['t' => 'E', 'l' => 'F'], 
                    'Kamis' => ['t' => 'G', 'l' => 'H'], 
                    'Jumat' => ['t' => 'I', 'l' => 'J']
                ];

                $dataPerHari = JamSekolah::where('semester_id', $this->semesterId)
                    ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')")
                    ->orderBy('waktu_mulai')
                    ->get()
                    ->groupBy('hari');

                $rowStart = 13;
                foreach ($hariMap as $namaHari => $cols) {
                    $currentRow = $rowStart;
                    if (isset($dataPerHari[$namaHari])) {
                        foreach ($dataPerHari[$namaHari] as $jam) {
                            $waktu = Carbon::parse($jam->waktu_mulai)->format('H.i') . ' - ' . Carbon::parse($jam->waktu_selesai)->format('H.i');
                            $sheet->setCellValue($cols['t'] . $currentRow, $waktu);
                            
                            $jenisTrim = ucfirst(strtolower(trim($jam->jenis)));
                            if ($jenisTrim === 'Pelajaran') {
                                $label = $jam->jam_ke;
                            } elseif ($jenisTrim === 'Istirahat') {
                                $label = 'ISTIRAHAT';
                            } else {
                                $label = strtoupper($jam->keterangan ?? 'KEGIATAN');
                            }
                            
                            $sheet->setCellValue($cols['l'] . $currentRow, $label);

                            if ($jenisTrim === 'Istirahat') {
                                $sheet->getStyle($cols['t'] . $currentRow . ':' . $cols['l'] . $currentRow)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFFFFF00');
                            } elseif ($jenisTrim === 'Kegiatan') {
                                $sheet->getStyle($cols['t'] . $currentRow . ':' . $cols['l'] . $currentRow)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFC6E0B4');
                            }
                            $currentRow++;
                        }
                    }
                }

                $maxRow = $sheet->getHighestRow();
                if ($maxRow < 12) $maxRow = 12;

                $sheet->getStyle("A12:J$maxRow")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getStyle("A12:J12")->getFont()->setBold(true);

                $ttgRow = $maxRow + 2;
                $sheet->mergeCells("G{$ttgRow}:J{$ttgRow}");
                $lokasiTtd = $this->kontak->kabupaten_kota ?? 'Tasikmalaya';
                $sheet->setCellValue("G{$ttgRow}", $lokasiTtd . ", " . Carbon::now()->translatedFormat('d F Y'));
                $sheet->getStyle("G{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Mengetahui,");
                $sheet->mergeCells("G{$ttgRow}:J{$ttgRow}");
                $sheet->setCellValue("G{$ttgRow}", "Menyetujui,");
                $sheet->getStyle("A{$ttgRow}:J{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $ttgRow++;
                $sheet->mergeCells("A{$ttgRow}:C{$ttgRow}");
                $sheet->setCellValue("A{$ttgRow}", "Waka Kurikulum,");
                $sheet->mergeCells("G{$ttgRow}:J{$ttgRow}");
                $sheet->setCellValue("G{$ttgRow}", "Kepala Sekolah,");
                $sheet->getStyle("A{$ttgRow}:J{$ttgRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $imageRow = $ttgRow + 1;
                $sheet->getRowDimension($imageRow)->setRowHeight(50);
                
                if ($wakaKur && $wakaKur->file_ttd) {
                    $pathWaka = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $wakaKur->file_ttd));
                    if (file_exists($pathWaka)) {
                        $drawWaka = new Drawing();
                        $drawWaka->setPath($pathWaka);
                        $drawWaka->setHeight(60);
                        $drawWaka->setCoordinates("B{$imageRow}");
                        $drawWaka->setOffsetX(-10);
                        $drawWaka->setWorksheet($pSheet);
                    }
                }

                if ($kepsek && $kepsek->file_ttd) {
                    $pathKepsek = storage_path('app/private/' . str_replace(['private/', 'app/private/'], '', $kepsek->file_ttd));
                    if (file_exists($pathKepsek)) {
                        $drawKepsek = new Drawing();
                        $drawKepsek->setPath($pathKepsek);
                        $drawKepsek->setHeight(60);
                        $drawKepsek->setCoordinates("H{$imageRow}");
                        $drawKepsek->setOffsetX(40); 
                        $drawKepsek->setWorksheet($pSheet);
                    }
                }

                $namaRow = $imageRow + 1;
                $sheet->mergeCells("A{$namaRow}:C{$namaRow}");
                $sheet->setCellValue("A{$namaRow}", "( " . strtoupper($wakaKur->nama ?? '____________________') . " )"); 
                $sheet->mergeCells("G{$namaRow}:J{$namaRow}");
                $sheet->setCellValue("G{$namaRow}", "( " . strtoupper($kepsek->nama ?? '____________________') . " )");
                $sheet->getStyle("A{$namaRow}:J{$namaRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$namaRow}:J{$namaRow}")->getFont()->setBold(true);

                $nipRow = $namaRow + 1;
                $sheet->mergeCells("A{$nipRow}:C{$nipRow}");
                $sheet->setCellValue("A{$nipRow}", "NIP. " . ($wakaKur->nip ?? '...........................'));
                $sheet->mergeCells("G{$nipRow}:J{$nipRow}");
                $sheet->setCellValue("G{$nipRow}", "NIP. " . ($kepsek->nip ?? '...........................'));
                $sheet->getStyle("A{$nipRow}:J{$nipRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}