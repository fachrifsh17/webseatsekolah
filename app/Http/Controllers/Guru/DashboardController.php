<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{
    Berita, Pengumuman, Siswa, Presensi, KalenderAkademik, Kelas, GuruMapel, TahunAjaran, GuruStaf, JadwalProduktif
};
use App\Http\Resources\BeritaResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Carbon\Carbon;

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
            $user = $request->user()->load('guruStaf.strukturJabatan.jabatan');
            $guruStaf = $user->guruStaf;
            
            if (!$guruStaf) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil guru tidak ditemukan'
                ], Response::HTTP_NOT_FOUND);
            }

            $activeTaIds = TahunAjaran::where('is_active', 1)->pluck('id');
            $guruStafId = $guruStaf->id;

            $kelasWaliIds = Kelas::where('wali_kelas_id', $guruStafId)
                ->where('is_active', 1)
                ->whereIn('tahun_ajaran_id', $activeTaIds)
                ->pluck('id');

            $siswaBinaanIds = Siswa::whereIn('kelas_id', $kelasWaliIds)
                ->where('is_active', 1)
                ->pluck('id');

            $stats = $this->getStats($guruStafId, $siswaBinaanIds);

            $struktur = $guruStaf->strukturJabatan->first();
            $jabatanNama = $struktur && $struktur->jabatan 
                ? $struktur->jabatan->nama_jabatan 
                : $guruStaf->jabatan;

            $manajerial = [];
            if (!empty($jabatanNama)) {
                $manajerialData = $this->getManajerialData($guruStaf, $jabatanNama, $activeTaIds);
                
                if (isset($manajerialData['summary'])) {
                    $stats = array_merge($stats, $manajerialData['summary']);
                }

                $manajerial = ['role_jabatan' => $manajerialData['role_jabatan']];
            }

            $data = [
                'statistics' => $stats,
                'kalender_akademik' => $this->getKalender($activeTaIds),
                'common' => [
                    'recent_pengumuman' => $this->getPengumuman(),
                    'recent_berita' => BeritaResource::collection(Berita::latest()->take(3)->get()),
                ],
            ];

            if (!empty($jabatanNama)) {
                $data['manajerial'] = $manajerial;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Dashboard Guru error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard guru',
                'errors' => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getStats($guruStafId, $siswaBinaanIds): array
    {
        return [
            'total_siswa_binaan' => $siswaBinaanIds->count(),
            'presensi_hari_ini' => Presensi::whereIn('siswa_id', $siswaBinaanIds)
                ->whereDate('created_at', today())
                ->count(),
            'mapel_diampu' => GuruMapel::where('guru_staf_id', $guruStafId)->count(),
        ];
    }

    private function getManajerialData($guruStaf, $jabatan, $activeTaIds): array
    {
        $res = ['role_jabatan' => $jabatan];
        $today = today();
        
        $filterAktif = function($q) use ($activeTaIds) {
            $q->where('is_active', 1)->whereIn('tahun_ajaran_id', $activeTaIds);
        };

        switch ($jabatan) {
            case 'Kepala Sekolah':
                $res['summary'] = [
                    'total_siswa_global' => Siswa::whereHas('kelas', $filterAktif)->where('is_active', 1)->count(),
                    'presensi_siswa_hari_ini' => Presensi::whereDate('created_at', $today)
                        ->whereHas('siswa', function($q) use ($filterAktif) {
                            $q->whereHas('kelas', $filterAktif)->where('is_active', 1);
                        })->count(),
                    'total_guru_staf' => GuruStaf::where('is_active', 1)->count(),
                ];
                break;

            case 'Waka Kurikulum':
                $res['summary'] = [
                    'total_mapel' => \App\Models\Matapelajaran::where('is_active', 1)->count(),
                    'total_guru_mapel' => GuruMapel::whereHas('kelas', $filterAktif)->distinct('guru_staf_id')->count(),
                    'jadwal_produktif' => JadwalProduktif::whereIn('tahun_ajaran_id', $activeTaIds)->count(),
                    'agenda_akademik' => KalenderAkademik::whereIn('tahun_ajaran_id', $activeTaIds)->whereDate('tanggal_mulai', '>=', $today)->count()
                ];
                break;

            case 'Waka Kesiswaan':
                $res['summary'] = [
                    'total_siswa' => Siswa::whereHas('kelas', $filterAktif)->where('is_active', 1)->count(),
                    'pelanggaran_hari_ini' => \App\Models\PoinSiswa::whereDate('created_at', $today)
                        ->where('poin_negatif', '>', 0)->count(),
                    'total_ekstrakurikuler' => \App\Models\Ekstrakurikuler::count(),
                    'siswa_mangkir' => Presensi::whereDate('created_at', $today)->where('status', 'Alpa')
                        ->whereHas('siswa', function($q) use ($filterAktif) {
                            $q->whereHas('kelas', $filterAktif)->where('is_active', 1);
                        })->count()
                ];
                break;

            case 'Waka Sarpras':
                $res['summary'] = [
                    'total_fasilitas' => \App\Models\Fasilitas::count(),
                    'total_ruangan' => Kelas::where('is_active', 1)->whereIn('tahun_ajaran_id', $activeTaIds)->count(),
                    'media_sarpras' => \App\Models\Media::count()
                ];
                break;

            case 'Waka Humas':
                $res['summary'] = [
                    'berita_sekolah' => Berita::count(),
                    'total_pengumuman' => Pengumuman::count(),
                    'prestasi_siswa' => \App\Models\Prestasi::count(),
                    'pesan_masuk' => \App\Models\Pesan::where('status', 'belum_dibaca')->count()
                ];
                break;

            case 'Ketua Jurusan':
                $jurusanId = $guruStaf->jurusan_id;
                $res['summary'] = [
                    'siswa_jurusan' => Siswa::whereHas('kelas', function($q) use ($jurusanId, $activeTaIds) {
                        $q->where('jurusan_id', $jurusanId)->whereIn('tahun_ajaran_id', $activeTaIds)->where('is_active', 1);
                    })->where('is_active', 1)->count(),
                    'kelas_jurusan' => Kelas::where('jurusan_id', $jurusanId)->whereIn('tahun_ajaran_id', $activeTaIds)->where('is_active', 1)->count(),
                    'guru_jurusan' => GuruMapel::whereHas('mapel', function($q) use ($jurusanId) {
                        $q->where('jurusan_id', $jurusanId);
                    })->distinct('guru_staf_id')->count(),
                    'jadwal_jurusan' => JadwalProduktif::where('jurusan_id', $jurusanId)
                        ->whereIn('tahun_ajaran_id', $activeTaIds)->count()
                ];
                break;
        }

        return $res;
    }

    private function getKalender($activeTaIds): array
    {
        $hariIni = today();
        $tigaHariLagi = today()->addDays(3);

        return KalenderAkademik::whereIn('tahun_ajaran_id', $activeTaIds)
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
                    'status' => $hariIni->between($mulai, $selesai) ? "Sedang Berlangsung" : "H-" . $hariIni->diffInDays($mulai)
                ];
            })->toArray();
    }

    private function getPengumuman()
    {
        return Pengumuman::latest()->take(3)->get()->map(fn($item) => [
            'id' => $item->id,
            'judul' => $item->judul,
            'isi_pengumuman' => $item->isi_pengumuman,
            'tanggal_publikasi' => Carbon::parse($item->tanggal_publikasi)->format('Y-m-d H:i:s'),
            'penting' => $item->penting,
            'created_at' => $item->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $item->updated_at->format('Y-m-d H:i:s'),
        ]);
    }
}