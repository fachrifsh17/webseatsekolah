<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran};
use App\Http\Requests\UpdatePresensiRequest;
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['update']);
    }

    /**
     * Menampilkan daftar kelas untuk dropdown filter (Mirip fitur Admin)
     */
    public function getKelas(): JsonResponse
    {
        try {
            $data = Kelas::where('is_active', true)
                ->select('id', 'nama_kelas')
                ->orderBy('nama_kelas', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar kelas berhasil diambil.',
                'data'    => $data
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Get Kelas Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil daftar kelas.'], 500);
        }
    }

    private function validateSemesterMonth(Request $request)
    {
        if ($request->filled('semester') && $request->filled('bulan')) {
            $semester = $request->semester;
            $bulan = (int) $request->bulan;

            if ($semester === 'Ganjil' && ($bulan < 7 || $bulan > 12)) {
                return "Untuk Semester Ganjil, pilih bulan antara 7 sampai 12 (Juli - Desember).";
            }

            if ($semester === 'Genap' && ($bulan < 1 || $bulan > 6)) {
                return "Untuk Semester Genap, pilih bulan antara 1 sampai 6 (Januari - Juni).";
            }
        }
        return null;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        if ($error = $this->validateSemesterMonth($request)) {
            return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = Presensi::query();
        $query = $this->applyPresensiFilters($request, $query);

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PresensiResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Presensi $presensi): JsonResponse
    {
        $this->authorize('view', $presensi);
        return response()->json([
            'success' => true, 
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        $this->authorize('update', $presensi);
        try {
            DB::transaction(fn() => $presensi->update($request->validated()));
            return response()->json([
                'success' => true, 
                'message' => 'Data presensi berhasil diperbarui oleh Kesiswaan.',
                'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Update Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            // Kesiswaan wajib pilih kelas untuk ekspor per kelas
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'Silakan pilih kelas terlebih dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->findOrFail($request->kelas_id);
            
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], 404);

            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";
            $fileName = "Rekap_Presensi_Kesiswaan_{$kelas->nama_kelas}_{$labelWaktu}.xlsx";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, $profil, $kontak, $kelas, 'kesiswaan', $ta->nama), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Kesiswaan Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh file.'], 500);
        }
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        // Jika kesiswaan tidak pilih kelas dan tanggal, tampilkan hari ini saja agar data tidak overload
        if (!$request->filled('kelas_id') && !$request->filled('tanggal')) {
            $request->merge(['tanggal' => date('Y-m-d')]);
        }

        $ta = $request->filled('tahun_ajaran_id') 
            ? TahunAjaran::find($request->tahun_ajaran_id) 
            : TahunAjaran::where('is_active', true)->first();

        if ($ta) {
            if ($request->filled('semester')) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)->where('semester', $request->semester)->first();
                if ($taMatched) $ta = $taMatched;
            }

            $query->where('tahun_ajaran_id', $ta->id);
            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        $query->whereHas('siswa', function($q) use ($request) {
            $q->where('is_active', true);
            if ($request->filled('kelas_id')) {
                $q->where('kelas_id', $request->kelas_id);
            }
        });

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }
}