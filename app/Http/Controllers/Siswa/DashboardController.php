<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, KalenderAkademik, TahunAjaran, GuruMapel};
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
            
            $activeTa = TahunAjaran::where('is_active', 1)->first();
            $activeTaId = $activeTa ? $activeTa->id : null;

            // Memanggil fungsi untuk mendapatkan data siswa
            $siswa = $this->getSiswa($user->id, $activeTaId);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan atau belum terdaftar di kelas tahun ini'
                ], Response::HTTP_NOT_FOUND);
            }

            // Relasi ke tabel pivot kelas_wali_kelas diabaikan is_active-nya di dalam Model/Query
            $riwayatAktif = $siswa->riwayatKelas->first();
            $kelasObj = $riwayatAktif ? $riwayatAktif->kelas : null;
            $kelasId = $kelasObj ? $kelasObj->id : null;

            $poin = $this->getPoinAkumulatif($siswa->id);
            $statsPresensi = $this->getPresensiStats($siswa->id, $kelasId, $activeTaId);
            $jadwalHariIniCount = $this->getJadwalCount($kelasId, $activeTaId);

            $setting = DB::table('sekolah_setting')->first();
            $akademik = $this->getAkademikData($hariIni, $tigaHariLagi, $activeTaId ? [$activeTaId] : []);

            $data = [
                'header' => [
                    'tahun_ajaran' => $activeTa?->nama ?? '-',
                    'semester'     => $activeTa?->semester ?? '-',
                ],
                'user_info' => [
                    'nama'       => $siswa->nama_lengkap ?? 'Tanpa Nama',
                    'kelas'      => $kelasObj->nama_kelas ?? '-',
                    // Mengambil nama wali kelas berdasarkan relasi baru
                    'wali_kelas' => $kelasObj->waliKelas->nama ?? '-',
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
                    'buku_poin'    => ($setting && isset($setting->buku_poin_path)) ? asset('storage/' . $setting->buku_poin_path) : null,
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
                'message' => 'Gagal memuat dashboard',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getJadwalCount($kelasId, $activeTaId)
    {
        if (!$kelasId || !$activeTaId) return 0;

        return GuruMapel::where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $activeTaId)
            ->where('hari', Carbon::now()->translatedFormat('l'))
            ->whereHas('mapel', function($q) {
                $q->where('is_active', 1);
            })
            ->count();
    }

    /**
     * Mendapatkan data siswa dan kelas berdasarkan tahun ajaran aktif
     */
    private function getSiswa($userId, $activeTaId)
    {
        return Siswa::where('user_id', $userId)
            ->with(['riwayatKelas' => function($query) use ($activeTaId) {
                // Di sini kita abaikan is_active pada riwayat kelas
                $query->where('tahun_ajaran_id', $activeTaId)
                      ->with(['kelas' => function($q) {
                          // Tetap cek is_active pada tabel Kelas jika perlu
                          $q->where('is_active', 1)->with('waliKelas'); 
                      }]); 
            }])
            ->first();
    }
    
    // ... fungsi-fungsi lainnya tetap sama
    private function getPoinAkumulatif($siswaId)
    {
        $summary = PoinSiswa::where('siswa_id', $siswaId)
            ->select(
                DB::raw('CAST(SUM(poin_positif) AS SIGNED) as total_plus'),
                DB::raw('CAST(SUM(poin_negatif) AS SIGNED) as total_minus')
            )->first();

        return [
            'total_positif' => (int) ($summary->total_plus ?? 0),
            'total_negatif' => (int) ($summary->total_minus ?? 0),
        ];
    }

    private function getPresensiStats($siswaId, $kelasId, $activeTaId)
    {
        $query = Presensi::where('siswa_id', $siswaId);

        if ($activeTaId) {
            $query->where('tahun_ajaran_id', $activeTaId);
        }
        
        if ($kelasId) {
            $query->where('kelas_id', $kelasId);
        }

        return $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    private function getAkademikData($hariIni, $tigaHariLagi, $activeTaIds)
    {
        $pengumuman = Pengumuman::latest()->first();
        $formattedPengumuman = null;

        if ($pengumuman) {
            $formattedPengumuman = [
                'id'                => $pengumuman->id,
                'judul'             => $pengumuman->judul,
                'isi_pengumuman'    => $pengumuman->isi_pengumuman,
                'tanggal_publikasi' => Carbon::parse($pengumuman->created_at)->format('Y-m-d H:i:s'),
                'penting'           => (bool) $pengumuman->penting,
            ];
        }

        return [
            'kalender' => KalenderAkademik::whereIn('tahun_ajaran_id', $activeTaIds)
                ->where(function ($q) use ($hariIni, $tigaHariLagi) {
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
                        'kegiatan'        => $item->kegiatan,
                        'tanggal_mulai'   => $mulai->format('Y-m-d'),
                        'tanggal_selesai' => $selesai->format('Y-m-d'),
                        'kategori'        => $item->kategori,
                        'status'          => $hariIni->between($mulai, $selesai)
                            ? "Sedang Berlangsung"
                            : "H-" . $hariIni->diffInDays($mulai)
                    ];
                }),
            'pengumuman_terbaru' => $formattedPengumuman,
            'berita_terbaru'     => BeritaResource::collection(Berita::latest()->take(1)->get()),
        ];
    }
}