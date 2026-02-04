<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, Orangtua, KalenderAkademik};
use App\Http\Resources\{BeritaResource};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Orangtua');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Ambil data orang tua untuk mendapatkan nama asli dari table orangtua
            $orangtuaData = Orangtua::where('user_id', $user->id)->first();
            
            // Ambil data setting sekolah
            $setting = DB::table('sekolah_setting')->first();

            $data = [
                'user_info' => [
                    // Prioritas: nama dari table orangtua, lalu nama di table users
                    'nama' => $orangtuaData->nama_lengkap ?? $orangtuaData->nama ?? $user->name,
                    'role' => 'Orang Tua',
                ],
                'sekolah' => [
                    'buku_poin' => $setting->buku_poin_path ? asset('storage/' . $setting->buku_poin_path) : null,
                    'wa_kesiswaan' => $setting->no_wa_kesiswaan ?? null,
                ],
                'anak_statistics' => $this->getDataAnak($user->id),
                'akademik' => [
                    'kalender' => KalenderAkademik::whereDate('tanggal_mulai', '>=', today())
                        ->orderBy('tanggal_mulai', 'asc')
                        ->take(3)
                        ->get(),
                    'pengumuman_terbaru' => Pengumuman::latest()->first(),
                    'berita_terbaru' => BeritaResource::collection(Berita::latest()->take(1)->get()),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard',
                'errors' => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getDataAnak($userId)
    {
        $orangtua = Orangtua::with(['anak.kelas.waliKelas'])->where('user_id', $userId)->first();

        if (!$orangtua || !$orangtua->anak) {
            return [];
        }

        return $orangtua->anak->map(function ($siswa) {
            $statsPresensi = Presensi::where('siswa_id', $siswa->id)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $poinPositif = (int) PoinSiswa::where('siswa_id', $siswa->id)->sum('poin_positif');
            $poinNegatif = (int) PoinSiswa::where('siswa_id', $siswa->id)->sum('poin_negatif');

            return [
                'nama_anak' => $siswa->nama_lengkap ?? $siswa->nama,
                'kelas' => $siswa->kelas->nama_kelas ?? '-',
                'wali_kelas' => $siswa->kelas->waliKelas->nama ?? '-',
                'statistics' => [
                    'presensi' => [
                        'hadir' => $statsPresensi['Hadir'] ?? 0,
                        'izin' => $statsPresensi['Izin'] ?? 0,
                        'sakit' => $statsPresensi['Sakit'] ?? 0,
                        'alpa' => $statsPresensi['Alpa'] ?? 0,
                    ],
                    'poin' => [
                        'total_positif' => $poinPositif,
                        'total_negatif' => $poinNegatif,
                        'akumulasi' => $poinPositif - $poinNegatif,
                    ]
                ]
            ];
        });
    }
}