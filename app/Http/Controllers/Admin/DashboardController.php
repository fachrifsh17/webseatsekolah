<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{
    Berita, Pengumuman, Siswa, GuruStaf, Pesan, LogAktivitas, Orangtua,
    Kelas, Jurusan, TahunAjaran, Ekstrakurikuler, Fasilitas, Semester, KalenderAkademik
};
use App\Http\Resources\{
    BeritaResource, LogAktivitasResource
};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
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
            $hariIni = today();
            $tigaHariLagi = today()->addDays(3);
            $activeSemesterIds = Semester::where('is_active', 1)->pluck('id');

            $data = [
                'user_info' => [
                    'name' => $user->nama_lengkap ?? $user->username ?? $user->name,
                    'role' => 'Admin',
                ],
                'statistics' => $this->getStats($activeSemesterIds),
                'common' => [
                    'kalender_akademik' => $this->getKalenderData($activeSemesterIds, $hariIni, $tigaHariLagi),
                    'recent_pengumuman' => Pengumuman::latest()->take(5)->get()->map(function($item) {
                        return [
                            'id' => $item->id,
                            'judul' => $item->judul,
                            'isi_pengumuman' => $item->isi_pengumuman,
                            'tanggal_publikasi' => $item->tanggal_publikasi ? Carbon::parse($item->tanggal_publikasi)->format('Y-m-d H:i:s') : null,
                            'penting' => $item->penting,
                            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                            'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
                        ];
                    }),
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

    private function getStats($activeSemesterIds): array
    {
        return [
            'total_berita'         => Berita::count(),
            'total_pengumuman'     => Pengumuman::count(),
            'guru_aktif'           => GuruStaf::where('is_active', 1)->count(),
            'siswa_aktif'          => Siswa::where('is_active', 1)
                ->whereHas('riwayatKelas', function($q) use ($activeSemesterIds) {
                    $q->whereIn('semester_id', $activeSemesterIds)
                      ->where('is_active', 1);
                })->count(),
            'orangtua_aktif'       => Orangtua::where('is_active', 1)
                ->whereHas('siswa', function($q) use ($activeSemesterIds) {
                    $q->where('is_active', 1)
                      ->whereHas('riwayatKelas', function($sq) use ($activeSemesterIds) {
                        $sq->whereIn('semester_id', $activeSemesterIds)
                           ->where('is_active', 1);
                    });
                })->count(),
            'total_kelas'           => Kelas::where('is_active', 1)->count(),
            'total_jurusan'         => Jurusan::count(),
            'semester_aktif'        => $activeSemesterIds->count(),
            'total_ekstrakurikuler' => Ekstrakurikuler::count(),
            'total_fasilitas'       => Fasilitas::count(),
            'pesan_baru'            => Pesan::where('status', 'belum_dibaca')->count(),
        ];
    }

    private function getKalenderData($semesterIds, $hariIni, $tigaHariLagi)
    {
        return KalenderAkademik::whereIn('semester_id', $semesterIds)
            ->where(function ($q) use ($hariIni, $tigaHariLagi) {
                $q->whereBetween('tanggal_mulai', [$hariIni, $tigaHariLagi])
                  ->orWhere(function($sub) use ($hariIni) {
                      $sub->where('tanggal_mulai', '<=', $hariIni)
                          ->where('tanggal_selesai', '>=', $hariIni);
                  });
            })
            ->orderBy('tanggal_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'kegiatan'      => $item->kegiatan,
                'tanggal_mulai' => Carbon::parse($item->tanggal_mulai)->format('Y-m-d'),
                'status'        => $hariIni->between(Carbon::parse($item->tanggal_mulai), Carbon::parse($item->tanggal_selesai)) 
                                   ? "Sedang Berlangsung" 
                                   : "H-" . (int)$hariIni->diffInDays(Carbon::parse($item->tanggal_mulai), false)
            ]);
    }
}