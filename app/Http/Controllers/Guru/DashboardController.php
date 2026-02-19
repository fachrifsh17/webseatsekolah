<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{
    Berita, Pengumuman, Siswa, Presensi, KalenderAkademik, Kelas, GuruMapel
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

            $guruStafId = $guruStaf->id;
            $kelasWaliIds = Kelas::where('wali_kelas_id', $guruStafId)
                ->where('is_active', 1)
                ->whereHas('tahunAjaran', function($q) {
                    $q->where('is_active', 1);
                })
                ->pluck('id');

            $siswaBinaanIds = Siswa::whereIn('kelas_id', $kelasWaliIds)->pluck('id');

            // 1. Ambil data statistik dasar guru
            $stats = $this->getStats($guruStafId, $siswaBinaanIds);

            // 2. Identifikasi Jabatan
            $struktur = $guruStaf->strukturJabatan->first();
            $jabatanNama = $struktur && $struktur->jabatan 
                ? $struktur->jabatan->nama_jabatan 
                : $guruStaf->jabatan;

            $manajerial = [];
            if (!empty($jabatanNama)) {
                // Ambil data spesifik jabatan (Waka, Kaprog, dll)
                $manajerialData = $this->getManajerialData($guruStaf, $jabatanNama);
                
                // 3. LOGIKA MERGE: Pindahkan isi 'summary' ke dalam 'statistics'
                if (isset($manajerialData['summary'])) {
                    $stats = array_merge($stats, $manajerialData['summary']);
                }

                // Simpan role_jabatan saja untuk bagian manajerial
                $manajerial = [
                    'role_jabatan' => $manajerialData['role_jabatan']
                ];
            }

            // 4. Susun Data Final
            $data = [
                'statistics' => $stats, // Gabungan stats guru & summary jabatan
                'kalender_akademik' => $this->getKalender(),
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

    private function getManajerialData($guruStaf, $jabatan): array
    {
        $res = ['role_jabatan' => $jabatan];
        $today = today();
        $filterAktif = function($q) {
            $q->where('is_active', 1)->whereHas('tahunAjaran', function($sq) {
                $sq->where('is_active', 1);
            });
        };

        switch ($jabatan) {
            case 'Kepala Sekolah':
                $res['summary'] = [
                    'total_siswa_global' => Siswa::whereHas('kelas', $filterAktif)->count(),
                    'presensi_siswa_hari_ini' => Presensi::whereDate('created_at', $today)->count(),
                    'total_guru_aktif' => GuruMapel::distinct('guru_staf_id')->count(),
                ];
                break;

            case 'Waka Kurikulum':
                $res['summary'] = [
                    'total_mapel' => \App\Models\Matapelajaran::count(),
                    'total_guru_mapel' => GuruMapel::count(),
                    'jadwal_produktif' => \App\Models\JadwalProduktif::count(),
                    'agenda_akademik' => KalenderAkademik::whereDate('tanggal_mulai', '>=', $today)->count()
                ];
                break;

            case 'Waka Kesiswaan':
                $res['summary'] = [
                    'total_siswa' => Siswa::whereHas('kelas', $filterAktif)->count(),
                    'pelanggaran_hari_ini' => \App\Models\PoinSiswa::whereDate('created_at', $today)
                        ->where('poin_negatif', '>', 0)
                        ->count(),
                    'total_ekstrakurikuler' => \App\Models\Ekstrakurikuler::count(),
                    'siswa_mangkir' => Presensi::whereDate('created_at', $today)->where('status', 'Alpa')->count()
                ];
                break;

            case 'Waka Sarpras':
                $res['summary'] = [
                    'total_fasilitas' => \App\Models\Fasilitas::count(),
                    'total_ruangan' => Kelas::where('is_active', 1)->count(),
                    'media_sarpras' => \App\Models\Media::count()
                ];
                break;

            case 'Waka Humas':
                $res['summary'] = [
                    'berita_sekolah' => Berita::count(),
                    'total_pengumuman' => Pengumuman::count(),
                    'prestasi_siswa' => \App\Models\Prestasi::count(),
                    'pesan_masuk' => \App\Models\Pesan::where('status', 'Unread')->count()
                ];
                break;

            case 'Ketua Jurusan':
                $jurusanId = $guruStaf->jurusan_id;
                $res['summary'] = [
                    'siswa_jurusan' => Siswa::where('jurusan_id', $jurusanId)->whereHas('kelas', $filterAktif)->count(),
                    'kelas_jurusan' => Kelas::where('jurusan_id', $jurusanId)->where('is_active', 1)->count(),
                    'guru_produktif' => GuruMapel::where('jurusan_id', $jurusanId)->distinct('guru_staf_id')->count()
                ];
                break;
        }

        return $res;
    }

    private function getKalender(): array
    {
        $hariIni = today();
        $tigaHariLagi = today()->addDays(3);

        return KalenderAkademik::where(function ($q) use ($hariIni, $tigaHariLagi) {
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
                
                $status = $hariIni->between($mulai, $selesai) 
                    ? "Sedang Berlangsung" 
                    : "H-" . $hariIni->diffInDays($mulai);

                return [
                    'kegiatan' => $item->kegiatan,
                    'tanggal_mulai' => $mulai->format('Y-m-d'),
                    'tanggal_selesai' => $selesai->format('Y-m-d'),
                    'kategori' => $item->kategori,
                    'status' => $status
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