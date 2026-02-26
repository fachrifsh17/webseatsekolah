<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, TahunAjaran, ProfilSekolah, DataKontak};
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

    private function resolveTahunAjaranId(Request $request)
    {
        if ($request->filled('tahun_ajaran_id')) {
            return $request->tahun_ajaran_id;
        }
        return TahunAjaran::where('is_active', true)->value('id');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $tahunAjaranId = $this->resolveTahunAjaranId($request);
            
            $data = JamSekolah::with('tahunAjaran')
                ->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
                ->orderBy('waktu_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'filter_tahun_ajaran_id' => $tahunAjaranId,
                    'is_auto_selected' => !$request->has('tahun_ajaran_id')
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $tahunAjaranId = $this->resolveTahunAjaranId($request);
            $ta = TahunAjaran::find($tahunAjaranId);
            
            if (!$ta) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data Tahun Ajaran tidak ditemukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();

            $ks = DB::table('struktur_jabatan')
                ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                ->where('struktur_jabatan.jabatan_id', 1) 
                ->select('guru_staf.nama', 'guru_staf.nip')
                ->first();

            $waka = DB::table('struktur_jabatan')
                ->join('guru_staf', 'struktur_jabatan.guru_staf_id', '=', 'guru_staf.id')
                ->where('struktur_jabatan.jabatan_id', 2) 
                ->select('guru_staf.nama', 'guru_staf.nip')
                ->first();

            $alamat_lengkap = ($kontak->alamat_jalan ?? '') . 
                              ", Desa " . ($kontak->desa_kelurahan ?? '') . 
                              ", Kec. " . ($kontak->kecamatan ?? '') . 
                              ", " . ($kontak->kabupaten_kota ?? '') . 
                              " - " . ($kontak->provinsi ?? '');

            $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
            $dataPerHari = JamSekolah::where('tahun_ajaran_id', $ta->id)
                ->whereIn('hari', $hariList)
                ->orderByRaw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat')")
                ->orderBy('waktu_mulai')
                ->get()
                ->groupBy('hari');

            $namaTA = str_replace(['/', '\\', ' '], '-', $ta->nama);
            $fileName = strtoupper("JAM_PELAJARAN_{$namaTA}.PDF");

            $pdf = Pdf::loadView('exports.jam_sekolah_pdf', [
                'profil'         => $profil,
                'kontak'         => $kontak,
                'alamat_lengkap' => $alamat_lengkap,
                'ta'             => $ta,
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