<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, KalenderAkademik, Semester};
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
            
            $semesterAktif = Semester::with('tahunAjaran')
                ->where('is_active', true)
                ->first();

            if (!$semesterAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode semester yang aktif saat ini.'
                ], Response::HTTP_NOT_FOUND);
            }

            $data = [
                'header' => [
                    'tahun_ajaran' => $semesterAktif->tahunAjaran?->nama ?? '-',
                    'semester'     => $semesterAktif->nama ?? '-',
                ],
                'sekolah' => [
                    'buku_poin'    => ($setting && isset($setting->buku_poin_path)) ? asset('uploads/setting/' . str_replace('uploads/setting/', '', $setting->buku_poin_path)) : null,
                    'wa_kesiswaan' => $setting->no_wa_kesiswaan ?? null,
                ],
                'anak_statistics' => $this->getDataAnak($user->id, $semesterAktif->id),
                'akademik'        => $this->getAkademikData($hariIni, $tigaHariLagi, $semesterAktif->id)
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getAkademikData($hariIni, $tigaHariLagi, $semesterId)
    {
        $pengumuman = Pengumuman::latest()->first();

        $kalender = KalenderAkademik::where('semester_id', $semesterId)
            ->where(function ($q) use ($hariIni, $tigaHariLagi) {
                $q->whereBetween('tanggal_mulai', [$hariIni, $tigaHariLagi])
                  ->orWhere(fn($sub) => $sub->where('tanggal_mulai', '<=', $hariIni)->where('tanggal_selesai', '>=', $hariIni));
            })
            ->orderBy('tanggal_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($item) => [
                'kegiatan'        => $item->kegiatan,
                'tanggal_mulai'   => Carbon::parse($item->tanggal_mulai)->format('Y-m-d'),
                'tanggal_selesai' => Carbon::parse($item->tanggal_selesai)->format('Y-m-d'),
                'kategori'        => $item->kategori,
                'status'          => $hariIni->between(Carbon::parse($item->tanggal_mulai), Carbon::parse($item->tanggal_selesai)) 
                                     ? "Sedang Berlangsung" : "H-" . (int)$hariIni->diffInDays(Carbon::parse($item->tanggal_mulai), false)
            ]);

        return [
            'kalender'           => $kalender,
            'pengumuman_terbaru' => $pengumuman ? [
                'judul'   => $pengumuman->judul,
                'isi'     => $pengumuman->isi_pengumuman,
                'tanggal' => $pengumuman->created_at->format('d-m-Y')
            ] : null,
            'berita_terbaru'     => BeritaResource::collection(Berita::latest()->take(1)->get()),
        ];
    }

    private function getDataAnak($userId, $semesterId)
    {
        $anakList = Siswa::whereHas('orangtua', fn($q) => $q->where('user_id', $userId))
            ->where('is_active', true) 
            ->with(['riwayatKelas' => function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId)
                  ->where('is_active', 1)
                  ->with(['kelas' => function($qk) use ($semesterId) {
                      $qk->with(['waliKelas' => function($qw) use ($semesterId) {
                          $qw->where('kelas_wali_kelas.semester_id', $semesterId)
                             ->where('kelas_wali_kelas.is_active', 1);
                      }]);
                  }]);
            }])
            ->get();

        return $anakList->map(function ($siswa) use ($semesterId) {
            $riwayat = $siswa->riwayatKelas->first();
            
            $statsPresensi = DB::table('presensi_detail')
                ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
                ->where('presensi_detail.siswa_id', $siswa->id)
                ->where('presensi.semester_id', $semesterId)
                ->select('presensi_detail.status', DB::raw('count(*) as total'))
                ->groupBy('presensi_detail.status')
                ->pluck('total', 'status');

            $summaryPoin = PoinSiswa::where('siswa_id', $siswa->id)
                ->selectRaw('CAST(SUM(poin_positif) AS SIGNED) as total_plus, CAST(SUM(poin_negatif) AS SIGNED) as total_minus')
                ->first();

            $poinPositif = (int) ($summaryPoin->total_plus ?? 0);
            $poinNegatif = (int) ($summaryPoin->total_minus ?? 0);

            return [
                'id_siswa'   => $siswa->id,
                'nama_anak'  => $siswa->nama_lengkap,
                'kelas'      => $riwayat?->kelas?->nama_kelas ?? '-',
                'wali_kelas' => $riwayat?->kelas?->waliKelas->first()->nama ?? '-',
                'statistics' => [
                    'presensi' => [
                        'hadir' => (int)($statsPresensi['Hadir'] ?? 0),
                        'izin'  => (int)($statsPresensi['Izin'] ?? 0),
                        'sakit' => (int)($statsPresensi['Sakit'] ?? 0),
                        'alpa'  => (int)($statsPresensi['Alpa'] ?? 0),
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