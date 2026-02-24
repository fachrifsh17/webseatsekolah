<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, Presensi, PoinSiswa, KalenderAkademik, TahunAjaran};
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
            
            // Ambil Tahun Ajaran Aktif
            $activeTa = TahunAjaran::where('is_active', 1)->first();
            $activeTaId = $activeTa ? $activeTa->id : null;

            // 1. Ambil data siswa dengan relasi riwayat kelas (pivot) yang difilter TA Aktif
            $siswa = $this->getSiswa($user->id, $activeTaId);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan atau belum terdaftar di kelas tahun ini'
                ], Response::HTTP_NOT_FOUND);
            }

            // 2. Dapatkan objek kelas saat ini dari pivot
            $riwayatAktif = $siswa->riwayatKelas->first();
            $kelasObj = $riwayatAktif ? $riwayatAktif->kelas : null;
            $kelasId = $kelasObj ? $kelasObj->id : null;

            // 3. Ambil statistik yang sudah difilter TA aktif dan Kelas aktif
            $poin = $this->getPoin($siswa->id, $activeTaId);
            $statsPresensi = $this->getPresensiStats($siswa->id, $kelasId, $activeTaId);
            
            $setting = DB::table('sekolah_setting')->first();
            
            // 4. Ambil data akademik (Kalender, Pengumuman, Berita) dengan format tanggal bersih
            $akademik = $this->getAkademikData($hariIni, $tigaHariLagi, $activeTaId ? [$activeTaId] : []);

            $data = [
                'user_info' => [
                    'nama' => $siswa->nama_lengkap ?? 'Tanpa Nama',
                    'kelas' => $kelasObj->nama_kelas ?? '-',
                    'wali_kelas' => $kelasObj->waliKelas->nama ?? '-',
                ],
                'statistics' => [
                    'presensi' => [
                        'hadir' => $statsPresensi['Hadir'] ?? 0,
                        'izin'  => $statsPresensi['Izin'] ?? 0,
                        'sakit' => $statsPresensi['Sakit'] ?? 0,
                        'alpa'  => $statsPresensi['Alpa'] ?? 0,
                    ],
                    'poin' => $poin
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
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getSiswa($userId, $activeTaId)
    {
        return Siswa::where('user_id', $userId)
            ->with(['riwayatKelas' => function($query) use ($activeTaId) {
                $query->where('tahun_ajaran_id', $activeTaId)
                      ->with(['kelas.waliKelas']); 
            }])
            ->first();
    }

    private function getPoin($siswaId, $activeTaId)
    {
        $query = PoinSiswa::where('siswa_id', $siswaId);
        
        if ($activeTaId) {
            $query->where('tahun_ajaran_id', $activeTaId);
        }

        return [
            'total_positif' => (int) $query->sum('poin_positif'),
            'total_negatif' => (int) $query->sum('poin_negatif'),
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
        // Ambil pengumuman dan hilangkan format "ZZZ"
        $pengumuman = Pengumuman::latest()->first();
        $formattedPengumuman = null;

        if ($pengumuman) {
            $formattedPengumuman = [
                'id' => $pengumuman->id,
                'judul' => $pengumuman->judul,
                'isi_pengumuman' => $pengumuman->isi_pengumuman,
                'tanggal_publikasi' => Carbon::parse($pengumuman->created_at)->format('Y-m-d H:i:s'),
                'penting' => (bool) $pengumuman->penting,
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
                        'kegiatan' => $item->kegiatan,
                        'tanggal_mulai' => $mulai->format('Y-m-d'),
                        'tanggal_selesai' => $selesai->format('Y-m-d'),
                        'kategori' => $item->kategori,
                        'status' => $hariIni->between($mulai, $selesai)
                            ? "Sedang Berlangsung"
                            : "H-" . $hariIni->diffInDays($mulai)
                    ];
                }),
            'pengumuman_terbaru' => $formattedPengumuman,
            'berita_terbaru' => BeritaResource::collection(Berita::latest()->take(1)->get()),
        ];
    }
}