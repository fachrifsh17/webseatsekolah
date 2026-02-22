<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, Orangtua, KalenderAkademik, TahunAjaran};
use App\Http\Resources\{BeritaResource};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Log, DB};
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
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
            $hariIni = today();
            $tigaHariLagi = today()->addDays(3);
            
            $setting = DB::table('sekolah_setting')->first();
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            $data = [
                'header' => [
                    'tahun_ajaran' => $tahunAktif?->nama ?? '-',
                    'semester' => $tahunAktif?->semester ?? '-',
                ],
                'sekolah' => [
                    'buku_poin' => $setting->buku_poin_path ? asset('storage/' . $setting->buku_poin_path) : null,
                    'wa_kesiswaan' => $setting->no_wa_kesiswaan ?? null,
                ],
                'anak_statistics' => $this->getDataAnak($user->id, $tahunAktif?->id),
                'akademik' => $this->getAkademikData($hariIni, $tigaHariLagi, $tahunAktif?->id)
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

    private function getAkademikData($hariIni, $tigaHariLagi, $tahunAjaranId)
    {
        $queryKalender = KalenderAkademik::query();
        
        if ($tahunAjaranId) {
            $queryKalender->where('tahun_ajaran_id', $tahunAjaranId);
        }

        return [
            'kalender' => $queryKalender->where(function ($q) use ($hariIni, $tigaHariLagi) {
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
        ];
    }

    private function getDataAnak($userId, $tahunAjaranId)
    {
        $orangtua = Orangtua::with(['anak' => function($query) use ($tahunAjaranId) {
            $query->where('is_active', true)
                  ->whereHas('kelas', function($q) use ($tahunAjaranId) {
                      $q->where('tahun_ajaran_id', $tahunAjaranId);
                  });
        }, 'anak.kelas.waliKelas'])->where('user_id', $userId)->first();

        if (!$orangtua || !$orangtua->anak) {
            return [];
        }

        return $orangtua->anak->map(function ($siswa) use ($tahunAjaranId) {
            // Stats Presensi difilter Tahun Ajaran
            $statsPresensi = Presensi::where('siswa_id', $siswa->id)
                ->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            // Stats Poin difilter Tahun Ajaran
            $queryPoin = PoinSiswa::where('siswa_id', $siswa->id)
                ->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId));
            
            $poinPositif = (int) (clone $queryPoin)->sum('poin_positif');
            $poinNegatif = (int) (clone $queryPoin)->sum('poin_negatif');

            return [
                'nama_anak'  => $siswa->nama_lengkap ?? $siswa->nama,
                'kelas'      => $siswa->kelas->nama_kelas ?? '-',
                'wali_kelas' => $siswa->kelas->waliKelas->nama ?? '-',
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
                        'akumulasi'     => $poinPositif - $poinNegatif,
                    ]
                ]
            ];
        });
    }
}