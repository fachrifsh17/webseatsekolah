<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{
    Berita, Pengumuman, Siswa, GuruStaf, Pesan, LogAktivitas, Orangtua,
    Kelas, Jurusan, TahunAjaran, Ekstrakurikuler, Fasilitas
};
use App\Http\Resources\{
    BeritaResource, LogAktivitasResource
};
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
        $this->middleware('role:Admin');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $activeTaIds = TahunAjaran::where('is_active', 1)->pluck('id');

            $data = [
                'user_info' => [
                    'name' => $user->nama_lengkap ?? $user->username ?? $user->name,
                    'role' => 'Admin',
                ],
                'statistics' => $this->getStats($activeTaIds),
                'common' => [
                    'recent_pengumuman' => Pengumuman::latest()->take(5)->get(),
                    'recent_berita'     => BeritaResource::collection(Berita::latest()->take(5)->get()),
                ],
                'recent_logs' => LogAktivitasResource::collection(
                    LogAktivitas::with('user')->latest()->take(5)->get()
                ),
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard error', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getStats($activeTaIds): array
    {
        return [
            'total_berita'         => Berita::count(),
            'total_pengumuman'     => Pengumuman::count(),
            'guru_aktif'           => GuruStaf::where('is_active', 1)->count(),
            'siswa_aktif'          => Siswa::where('is_active', 1)
                ->whereHas('riwayatKelas', function($q) use ($activeTaIds) {
                    $q->whereIn('tahun_ajaran_id', $activeTaIds)
                      ->where('is_active', 1);
                })->count(),
            'orangtua_aktif'       => Orangtua::where('is_active', 1)
                ->whereHas('siswa', function($q) use ($activeTaIds) {
                    $q->where('is_active', 1)
                      ->whereHas('riwayatKelas', function($sq) use ($activeTaIds) {
                        $sq->whereIn('tahun_ajaran_id', $activeTaIds)
                           ->where('is_active', 1);
                    });
                })->count(),
            'total_kelas'          => Kelas::where('is_active', 1)->count(),
            'total_jurusan'        => Jurusan::count(),
            'tahun_ajaran_aktif'   => $activeTaIds->count(),
            'total_ekstrakurikuler'=> Ekstrakurikuler::count(),
            'total_fasilitas'      => Fasilitas::count(),
            'pesan_baru'           => Pesan::where('status', 'belum_dibaca')->count(),
        ];
    }
}