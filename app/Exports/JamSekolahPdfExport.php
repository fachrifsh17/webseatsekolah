<?php

namespace App\Exports;

use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JamSekolahPdfExport implements FromView
{
    use Exportable;

    protected $profil, $kontak, $tahunAjaranId;

    public function __construct($profil, $kontak, $tahunAjaranId)
    {
        $this->profil = $profil;
        $this->kontak = $kontak;
        $this->tahunAjaranId = $tahunAjaranId;
    }

    public function view(): View
    {
        // 1. Ambil Data Jam Sekolah grouped by Hari
        $dataPerHari = JamSekolah::where('tahun_ajaran_id', $this->tahunAjaranId)
            ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
            ->orderBy('waktu_mulai')
            ->get()
            ->groupBy('hari');

        // 2. Query Kepala Sekolah (Jabatan ID: 1) tanpa join tabel jabatan
        $ks = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 1) 
            ->select('guru_staf.nama', 'guru_staf.nip')
            ->first();

        // 3. Query Waka Kurikulum (Jabatan ID: 2) tanpa join tabel jabatan
        $waka = DB::table('struktur_jabatan')
            ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
            ->where('struktur_jabatan.jabatan_id', 2) 
            ->select('guru_staf.nama', 'guru_staf.nip')
            ->first();

        // 4. Proses Alamat Lengkap
        $alamatLengkap = ($this->kontak->alamat_jalan ?? '') . 
                         ", Desa " . ($this->kontak->desa_kelurahan ?? '') . 
                         ", Kec. " . ($this->kontak->kecamatan ?? '') . 
                         ", " . ($this->kontak->kabupaten_kota ?? '') . 
                         " - " . ($this->kontak->provinsi ?? '');

        // 5. Kirim semua variabel ke Blade
        return view('exports.jam_sekolah_pdf', [
            'profil'         => $this->profil,
            'kontak'         => $this->kontak,
            'alamat_lengkap' => $alamatLengkap,
            'ta'             => TahunAjaran::find($this->tahunAjaranId),
            'dataPerHari'    => $dataPerHari,
            'hariList'       => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'waka'           => $waka,
            'ks'             => $ks,
            'tanggal_cetak'  => Carbon::now()->translatedFormat('d F Y')
        ]);
    }
}