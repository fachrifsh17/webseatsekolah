<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, KalenderAkademik, Semester, GuruMapel};
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
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $hariIni = today();
            $tigaHariLagi = today()->addDays(3);
            
            $semesterAktif = Semester::with('tahunAjaran')
                ->where('is_active', true)
                ->first();
                
            $semesterId = $semesterAktif ? $semesterAktif->id : null;

            if (!$semesterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode semester yang aktif saat ini.'
                ], Response::HTTP_NOT_FOUND);
            }

            $siswa = $this->getSiswa($user->id, $semesterId);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan atau belum terdaftar di kelas periode ini.'
                ], Response::HTTP_NOT_FOUND);
            }

            $riwayatAktif = $siswa->riwayatKelas->first();
            $kelasObj = $riwayatAktif ? $riwayatAktif->kelas : null;
            $kelasId = $kelasObj ? $kelasObj->id : null;

            $poin = $this->getPoinAkumulatif($siswa->id); 
            $statsPresensi = $this->getPresensiStats($siswa->id, $semesterId);
            $jadwalHariIniCount = $this->getJadwalCount($kelasId, $semesterId);

            $setting = DB::table('sekolah_setting')->first();
            $akademik = $this->getAkademikData($hariIni, $tigaHariLagi, $semesterId);

            $data = [
                'header' => [
                    'tahun_ajaran' => $semesterAktif->tahunAjaran?->nama ?? '-',
                    'semester'     => $semesterAktif->nama ?? '-',
                    'is_active'    => true
                ],
                'user_info' => [
                    'nama'       => $siswa->nama_lengkap ?? 'Tanpa Nama',
                    'nis'        => $siswa->nis,
                    'kelas'      => $kelasObj->nama_kelas ?? '-',
                    'wali_kelas' => $kelasObj->waliKelas->where('pivot.semester_id', $semesterId)->first()->nama ?? '-',
                ],
                'statistics' => [
                    'presensi' => [
                        'hadir' => (int)($statsPresensi['Hadir'] ?? 0),
                        'izin'  => (int)($statsPresensi['Izin'] ?? 0),
                        'sakit' => (int)($statsPresensi['Sakit'] ?? 0),
                        'alpa'  => (int)($statsPresensi['Alpa'] ?? 0),
                    ],
                    'poin' => [
                        'total_positif' => $poin['total_positif'],
                        'total_negatif' => $poin['total_negatif'],
                        'akumulasi'     => $poin['total_positif'] - $poin['total_negatif'],
                    ],
                    'total_jadwal_hari_ini' => $jadwalHariIniCount
                ],
                'sekolah' => [
                    'buku_poin'    => ($setting && isset($setting->buku_poin_path)) ? asset('uploads/buku_poin/' . str_replace('uploads/buku_poin/', '', $setting->buku_poin_path)) : null,
                    'wa_kesiswaan' => $setting->no_wa_kesiswaan ?? null,
                ],
                'akademik' => $akademik
            ];

            return response()->json([
                'success' => true,
                'data'    => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Siswa Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getJadwalCount($kelasId, $semesterId)
    {
        if (!$kelasId || !$semesterId) return 0;
        return GuruMapel::where('kelas_id', $kelasId)
            ->where('semester_id', $semesterId)
            ->where('hari', Carbon::now()->translatedFormat('l'))
            ->count();
    }

    private function getSiswa($userId, $semesterId)
    {
        return Siswa::where('user_id', $userId)
            ->where('is_active', true)
            ->with(['riwayatKelas' => function($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)
                      ->with(['kelas' => function($q) {
                          $q->with('waliKelas');
                      }]); 
            }])->first();
    }
    
    private function getPoinAkumulatif($siswaId)
    {
        $summary = PoinSiswa::where('siswa_id', $siswaId)
            ->selectRaw('CAST(SUM(poin_positif) AS SIGNED) as total_plus, CAST(SUM(poin_negatif) AS SIGNED) as total_minus')
            ->first();

        return [
            'total_positif' => (int) ($summary->total_plus ?? 0),
            'total_negatif' => (int) ($summary->total_minus ?? 0),
        ];
    }

    private function getPresensiStats($siswaId, $semesterId)
    {
        return DB::table('presensi_detail')
            ->join('presensi', 'presensi_detail.presensi_id', '=', 'presensi.id')
            ->where('presensi_detail.siswa_id', $siswaId)
            ->where('presensi.semester_id', $semesterId)
            ->select('presensi_detail.status', DB::raw('count(*) as total'))
            ->groupBy('presensi_detail.status')
            ->pluck('total', 'status');
    }

    private function getAkademikData($hariIni, $tigaHariLagi, $semesterId)
    {
        $pengumuman = Pengumuman::latest()->first(); 
        
        $kalender = KalenderAkademik::where('semester_id', $semesterId)
            ->where(function ($q) use ($hariIni, $tigaHariLagi) {
                $q->whereBetween('tanggal_mulai', [$hariIni, $tigaHariLagi])
                  ->orWhere(fn($sub) => $sub->where('tanggal_mulai', '<=', $hariIni)->where('tanggal_selesai', '>=', $hariIni));
            })
            ->orderBy('tanggal_mulai', 'asc')->take(5)->get()
            ->map(fn($item) => [
                'kegiatan' => $item->kegiatan,
                'tanggal_mulai' => Carbon::parse($item->tanggal_mulai)->format('Y-m-d'),
                'status' => $hariIni->between(Carbon::parse($item->tanggal_mulai), Carbon::parse($item->tanggal_selesai)) 
                            ? "Sedang Berlangsung" : "H-" . (int)$hariIni->diffInDays(Carbon::parse($item->tanggal_mulai), false)
            ]);

        return [
            'kalender' => $kalender,
            'pengumuman_terbaru' => $pengumuman ? [
                'judul' => $pengumuman->judul, 
                'isi' => $pengumuman->isi_pengumuman,
                'tanggal' => $pengumuman->created_at->format('d-m-Y')
            ] : null,
            'berita_terbaru' => BeritaResource::collection(Berita::latest()->take(1)->get()),
        ];
    }
}