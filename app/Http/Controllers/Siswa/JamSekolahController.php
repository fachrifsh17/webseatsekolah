<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, Semester, ProfilSekolah, DataKontak}; // Ubah TahunAjaran ke Semester
use App\Http\Resources\JamSekolahResource;
use Illuminate\Support\Facades\{Log, DB};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    // Fungsi pembantu untuk mendapatkan Semester ID
    private function resolveSemesterId(Request $request)
    {
        if ($request->filled('semester_id')) {
            return $request->semester_id;
        }
        // Ambil ID dari semester yang sedang aktif
        return Semester::where('is_active', true)->value('id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $semesterId = $this->resolveSemesterId($request);
            
            // Memanggil relasi nested 'semester.tahunAjaran' karena JamSekolah tidak punya kolom tahun_ajaran_id
            $data = JamSekolah::with(['semester.tahunAjaran'])
                ->when($semesterId, fn($q) => $q->where('semester_id', $semesterId))
                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
                ->orderBy('waktu_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'filter_semester_id' => $semesterId,
                    'is_auto_selected'   => !$request->has('semester_id')
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('JamSekolah Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $semesterId = $this->resolveSemesterId($request);
            // Ambil data semester beserta tahun ajarannya untuk keperluan header PDF
            $semester = Semester::with('tahunAjaran')->find($semesterId);
            
            if (!$semester) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data Semester tidak ditemukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();

            // Tetap sama: Mengambil KS & Waka
            $ks = DB::table('struktur_jabatan')
                ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                ->where('struktur_jabatan.jabatan_id', 1) 
                ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                ->first();

            $waka = DB::table('struktur_jabatan')
                ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                ->where('struktur_jabatan.jabatan_id', 2) 
                ->select('guru_staf.nama', 'guru_staf.nip', 'struktur_jabatan.file_ttd')
                ->first();

            $alamat_lengkap = ($kontak->alamat_jalan ?? '') . 
                              ", Desa " . ($kontak->desa_kelurahan ?? '') . 
                              ", Kec. " . ($kontak->kecamatan ?? '') . 
                              ", " . ($kontak->kabupaten_kota ?? '') . 
                              " - " . ($kontak->provinsi ?? '');

            $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
            
            // Filter berdasarkan semester_id
            $dataPerHari = JamSekolah::where('semester_id', $semester->id)
                ->whereIn('hari', $hariList)
                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
                ->orderBy('waktu_mulai')
                ->get()
                ->groupBy('hari');

            // Penamaan file menggunakan nama Semester dan Tahun Ajaran
            $namaTA = str_replace(['/', '\\', ' '], '-', $semester->tahunAjaran->nama ?? 'TA');
            $namaSem = str_replace(' ', '-', $semester->nama ?? 'Semester');
            $fileName = strtoupper("JAM_PELAJARAN_{$namaSem}_{$namaTA}.PDF");

            $pdf = Pdf::loadView('exports.jam_sekolah_pdf', [
                'profil'         => $profil,
                'kontak'         => $kontak,
                'alamat_lengkap' => $alamat_lengkap,
                'ta'             => $semester->tahunAjaran, // Kirim objek Tahun Ajaran ke view
                'semester'       => $semester,             // Kirim objek Semester ke view
                'dataPerHari'    => $dataPerHari,
                'hariList'       => $hariList,
                'ks'             => $ks,
                'waka'           => $waka,
                'tanggal_cetak'  => Carbon::now()->translatedFormat('d F Y')
            ])->setPaper('a4', 'landscape');

            return $pdf->download($fileName);

        } catch (Throwable $e) {
            Log::error('Export PDF Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor ke PDF: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}