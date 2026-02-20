<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, KalenderAkademik};
use App\Http\Resources\{BeritaResource};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $hariIni = today();
            $tigaHariLagi = today()->addDays(3);
            
            $siswa = Siswa::with(['kelas.waliKelas'])
                ->where('user_id', $user->id)
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan'
                ], Response::HTTP_NOT_FOUND);
            }

            $poinPositif = (int) PoinSiswa::where('siswa_id', $siswa->id)->sum('poin_positif');
            $poinNegatif = (int) PoinSiswa::where('siswa_id', $siswa->id)->sum('poin_negatif');

            $statsPresensi = Presensi::where('siswa_id', $siswa->id)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $setting = DB::table('sekolah_setting')->first();

            $data = [
                'user_info' => [
                    'nama' => $siswa->nama_lengkap ?? 'Tanpa Nama',
                    'kelas' => $siswa->kelas->nama_kelas ?? '-',
                    'wali_kelas' => $siswa->kelas->waliKelas->nama ?? '-',
                ],
                'statistics' => [
                    'presensi' => [
                        'hadir' => $statsPresensi['Hadir'] ?? 0,
                        'izin'  => $statsPresensi['Izin'] ?? 0,
                        'sakit' => $statsPresensi['Sakit'] ?? 0,
                        'alpa'  => $statsPresensi['Alpa'] ?? 0,
                    ],
                    'poin' => [
                        'total_positif' => $poinPositif,
                        'total_negatif' => $poinNegatif,
                    ]
                ],
                'sekolah' => [
                    'buku_poin'    => $setting->buku_poin_path ? asset('storage/' . $setting->buku_poin_path) : null,
                    'wa_kesiswaan' => $setting->no_wa_kesiswaan ?? null,
                ],
                'akademik' => [
                    'kalender' => KalenderAkademik::where(function ($q) use ($hariIni, $tigaHariLagi) {
                            $q->whereBetween('tanggal_mulai', [$hariIni, $tigaHariLagi])
                              ->orWhere(function ($sub) use ($hariIni) {
                                  $sub->where('tanggal_mulai', '<=', $hariIni)
                                      ->where('tanggal_selesai', '>=', $hariIni);
                              });
                        })
                        ->orderBy('tanggal_mulai', 'asc')
                        ->take(5)
                        ->get()
                        ->map(function ($item) use ($hariIni) {
                            $mulai = Carbon::parse($item->tanggal_mulai);
                            $selesai = Carbon::parse($item->tanggal_selesai);
                            
                            return [
                                'kegiatan' => $item->kegiatan,
                                'tanggal_mulai' => $mulai->format('Y-m-d'),
                                'tanggal_selesai' => $selesai->format('Y-m-d'),
                                'kategori' => $item->kategori,
                                'status' => $hariIni->between($mulai, $selesai) 
                                    ? "Sedang Berlangsung" 
                                    : "H-" . $hariIni->diffInDays($mulai)
                            ];
                        }),
                    'pengumuman_terbaru' => Pengumuman::latest()->first(),
                    'berita_terbaru' => BeritaResource::collection(Berita::latest()->take(1)->get()),
                ]
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Siswa Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}