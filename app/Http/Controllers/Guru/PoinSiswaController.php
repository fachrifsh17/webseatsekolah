<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, TahunAjaran, Siswa};
use App\Http\Requests\{StorePoinSiswaRequest, UpdatePoinSiswaRequest};
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport; // Import Export Class
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel; // Import Excel Facade
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PoinSiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    private function getGuruStafId()
    {
        $id = Auth::user()->guruStaf?->id;
        if (!$id) {
            abort(response()->json([
                'success' => false,
                'message' => 'Profil guru tidak ditemukan atau Anda bukan staf pengajar.'
            ], Response::HTTP_FORBIDDEN));
        }
        return $id;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $guruStafId = $this->getGuruStafId();

            $query = PoinSiswa::query()
                ->where('guru_staf_id', $guruStafId) // Kunci: Hanya data milik guru ini
                ->whereHas('siswa', function($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            // Filter Search (Nama Siswa, NISN, atau Keterangan)
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('siswa', function ($qS) use ($search) {
                        $qS->where('nama_lengkap', 'like', "%{$search}%")
                           ->orWhere('nisn', 'like', "%{$search}%");
                    })->orWhere('keterangan', 'like', "%{$search}%");
                });
            }

            // Filter Spesifik Siswa
            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            // Statistik Personal (Berdasarkan catatan Guru ini saja)
            $summaryPersonal = (clone $query)->select(
                DB::raw('SUM(poin_positif) as total_positif'),
                DB::raw('SUM(poin_negatif) as total_negatif'),
                DB::raw('COUNT(*) as total_catatan')
            )->first();

            // Statistik Kumulatif Siswa (Total dari semua guru untuk referensi)
            $summaryKumulatif = null;
            if ($request->filled('siswa_id')) {
                $summaryKumulatif = DB::table('poin_siswa')
                    ->where('siswa_id', $request->siswa_id)
                    ->select(
                        DB::raw('SUM(poin_positif) as total_plus'),
                        DB::raw('SUM(poin_negatif) as total_minus'),
                        DB::raw('SUM(poin_positif) - SUM(poin_negatif) as saldo_poin')
                    )->first();
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->with(['siswa.kelas', 'tahunAjaran'])
                          ->latest()
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'summary_personal' => [
                    'total_positif' => (int) ($summaryPersonal->total_positif ?? 0),
                    'total_negatif' => (int) ($summaryPersonal->total_negatif ?? 0),
                    'total_catatan' => (int) ($summaryPersonal->total_catatan ?? 0),
                ],
                'summary_kumulatif' => $summaryKumulatif,
                'data' => PoinSiswaResource::collection($data),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Guru Poin Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], 500);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $guruStafId = $this->getGuruStafId();
            $taActive = TahunAjaran::where('is_active', true)->first();

            if (!$taActive) {
                return response()->json(['success' => false, 'message' => 'Tahun ajaran aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('kelas', fn($q) => $q->where('is_active', true))
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Siswa tidak ditemukan atau kelas sudah tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $guruStafId, $taActive) {
                return PoinSiswa::create(array_merge($request->validated(), [
                    'guru_staf_id' => $guruStafId,
                    'tahun_ajaran_id' => $taActive->id,
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin siswa berhasil dicatat.',
                'data' => new PoinSiswaResource($poin->load(['siswa.kelas', 'tahunAjaran'])),
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Guru Poin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data.'], 500);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        // Proteksi: Guru hanya bisa melihat detail poin buatannya sendiri
        if ($poinSiswa->guru_staf_id !== Auth::user()->guruStaf?->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data' => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'tahunAjaran'])),
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        // Proteksi Kepemilikan
        if ($poinSiswa->guru_staf_id !== Auth::user()->guruStaf?->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $poinSiswa->update($request->validated()));

            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diperbarui.',
                'data' => new PoinSiswaResource($poinSiswa->load(['siswa.kelas', 'tahunAjaran'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Guru Poin Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], 500);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        // Proteksi Kepemilikan
        if ($poinSiswa->guru_staf_id !== Auth::user()->guruStaf?->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $poinSiswa->delete());
            
            return response()->json([
                'success' => true,
                'message' => 'Catatan poin berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Guru Poin Destroy Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $guruStafId = $this->getGuruStafId();
            
            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            // Mendapatkan Nama Tahun Ajaran (Aktif)
            $taActive = TahunAjaran::where('is_active', true)->first();
            $namaTA = $taActive ? $taActive->nama . " " . $taActive->semester : "-";

            $namaKelas = $request->nama_kelas ?? 'Catatan_Pribadi_Guru';
            $namaKelasFile = str_replace([' ', '/', '\\'], '_', $namaKelas);
            
            $labelWaktu = $request->filled('bulan') ? date('F Y', strtotime($request->bulan)) : "Kumulatif";
            $bulanFile = $request->filled('bulan') ? date('M_Y', strtotime($request->bulan)) : "Semua_Waktu";

            // Query: Hanya data milik Guru yang sedang login
            $query = PoinSiswa::with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])
                ->where('guru_staf_id', $guruStafId)
                ->whereHas('siswa', function($q) {
                    $q->where('is_active', true)
                      ->whereHas('kelas', fn($qk) => $qk->where('is_active', true));
                });

            if ($request->filled('kelas_id')) {
                $query->whereHas('siswa', fn($q) => $q->where('kelas_id', $request->kelas_id));
            }

            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            }

            $fileName = "Rekap_Poin_Guru_{$namaKelasFile}_{$bulanFile}_" . date('His') . ".xlsx";

            return Excel::download(
                new PoinSiswaExport(
                    $query->orderBy('tanggal', 'asc'), 
                    $namaKelas, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $namaTA
                ),
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Guru Export Poin Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh laporan.'], 500);
        }
    }
}