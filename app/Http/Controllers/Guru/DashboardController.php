<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{
    Berita, Pengumuman, Siswa, Presensi, KalenderAkademik, Kelas, GuruMapel
};
use App\Http\Resources\BeritaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $guruStafId = $user->guruStaf->id ?? null;

            // ambil kelas di mana guru jadi wali kelas
            $kelasWali = Kelas::where('wali_kelas_id', $guruStafId)->pluck('id');

            // siswa binaan dari kelas wali
            $siswaBinaan = Siswa::whereIn('kelas_id', $kelasWali)->pluck('id');

            // presensi hari ini untuk siswa binaan
            $presensiHariIni = Presensi::whereIn('siswa_id', $siswaBinaan)
                ->whereDate('created_at', today())
                ->count();

            // mapel yang diajar guru
            $mapelDiampu = GuruMapel::where('guru_staf_id', $guruStafId)->count();

            // ambil kalender akademik yang akan datang
            $kalenderAkademik = KalenderAkademik::where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('kategori', 'Libur')
                            ->whereDate('tanggal_mulai', '=', today()->addDay());
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('kategori', '!=', 'Libur')
                            ->whereDate('tanggal_mulai', '>=', today());
                    });
                })
                ->orderBy('tanggal_mulai', 'asc')
                ->take(5)
                ->get()
                ->map(fn($item) => [
                    'kegiatan'        => $item->kegiatan,
                    'tanggal_mulai'   => $item->tanggal_mulai,
                    'tanggal_selesai' => $item->tanggal_selesai,
                    'kategori'        => $item->kategori,
                ]);

            // pengumuman terbaru
            $pengumuman = Pengumuman::latest()->take(3)->get();

            // berita terbaru
            $berita = BeritaResource::collection(
                Berita::latest()->take(3)->get()
            );

            $data = [
                'statistics' => [
                    'total_siswa_binaan' => $siswaBinaan->count(),
                    'presensi_hari_ini'  => $presensiHariIni,
                    'mapel_diampu'       => $mapelDiampu,
                ],
                'kalender_akademik' => $kalenderAkademik,
                'common' => [
                    'recent_pengumuman' => $pengumuman,
                    'recent_berita'     => $berita,
                ],
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Guru error', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard guru',
                'errors'  => [
                    'exception' => [$e->getMessage()],
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine()
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
