<?php

namespace App\Exports;

use App\Models\JamSekolah;
use App\Models\Semester;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JamSekolahPdfExport implements FromView
{
    use Exportable;

    protected $profil, $kontak, $semesterId; // Ubah tahunAjaranId menjadi semesterId

    public function __construct($profil, $kontak, $semesterId)
    {
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->semesterId = $semesterId;
    }

    public function view(): View
    {
        // Filter berdasarkan semester_id
        $dataPerHari = JamSekolah::where('semester_id', $this->semesterId)
            ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
            ->orderBy('waktu_mulai')
            ->get()
            ->groupBy('hari');

        // Ambil data semester dan tahun ajaran
        $semester = Semester::with('tahunAjaran')->find($this->semesterId);

        // Ambil data KS
        $ks = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 1) 
            ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
            ->first();

        // Ambil data Waka
        $waka = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 2) 
            ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
            ->first();

        $alamatLengkap = ($this->kontak->alamat_jalan ?? '') . 
                         ", Desa " . ($this->kontak->desa_kelurahan ?? '') . 
                         ", Kec. " . ($this->kontak->kecamatan ?? '') . 
                         ", " . ($this->kontak->kabupaten_kota ?? '') . 
                         " - " . ($this->kontak->provinsi ?? '');

        return view('exports.jam_sekolah_pdf', [
            'profil'         => $this->profil,
            'kontak'         => $this->kontak,
            'alamat_lengkap' => $alamatLengkap,
            'semester'       => $semester,
            'ta'             => $semester?->tahunAjaran, // Otomatis ditarik dari relasi semester
            'dataPerHari'    => $dataPerHari,
            'hariList'       => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'waka'           => $waka,
            'ks'             => $ks,
            'tanggal_cetak'  => Carbon::now()->translatedFormat('d F Y')
        ]);
    }
}