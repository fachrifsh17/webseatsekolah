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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['update']);
    }

    public function listKelas(Request $request): JsonResponse
    {
        try {
            $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
            if (!$taId) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);

            $tanggal = $request->get('tanggal', date('Y-m-d'));

            $kelas = Kelas::where('tahun_ajaran_id', $taId)
                ->select('id', 'nama_kelas', 'tahun_ajaran_id')
                ->withCount(['siswa' => fn($q) => $q->where('is_active', true)])
                ->orderBy('nama_kelas', 'asc')
                ->get();

            $dataWithStatus = $kelas->map(function ($item) use ($tanggal, $taId) {
                $sudahAbsen = Presensi::where('tanggal', $tanggal)
                    ->where('tahun_ajaran_id', $taId)
                    ->whereHas('siswa', fn($q) => $q->where('kelas_id', $item->id))
                    ->exists();

                return [
                    'id' => $item->id,
                    'nama_kelas' => $item->nama_kelas,
                    'tahun_ajaran_id' => $item->tahun_ajaran_id,
                    'siswa_count' => $item->siswa_count,
                    'status_presensi' => $sudahAbsen ? 'Sudah Absen' : 'Belum Absen'
                ];
            });

            return response()->json(['success' => true, 'data' => $dataWithStatus], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request): JsonResponse
    {
        if (!$request->filled('kelas_id')) {
            return response()->json(['success' => false, 'message' => 'ID Kelas wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tanggal = $request->get('tanggal', date('Y-m-d'));
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);

            $siswa = Siswa::where('kelas_id', $request->kelas_id)
                ->where('is_active', true)
                ->with(['kelas', 'presensi' => fn($q) => $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $ta->id)])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            $collection = $siswa->map(function ($item) use ($tanggal, $ta) {
                $presensiExisting = $item->presensi->first();
                return [
                    'siswa_id'      => $item->id,
                    'nama_lengkap'  => $item->nama_lengkap,
                    'nisn'          => $item->nisn,
                    'jenis_kelamin' => $item->jenis_kelamin,
                    'presensi'      => [
                        'id'              => $presensiExisting?->id ?? null,
                        'tanggal'         => $tanggal,
                        'status'          => $presensiExisting?->status ?? null,
                        'keterangan'      => $presensiExisting?->keterangan ?? '',
                        'tahun_ajaran_id' => $ta->id
                    ]
                ];
            });

            return response()->json(['success' => true, 'data' => $collection], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        $this->authorize('update', $presensi);

        try {
            DB::transaction(fn() => $presensi->update($request->validated()));
            return response()->json([
                'success' => true, 
                'message' => 'Data diperbarui oleh Kesiswaan.',
                'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kesiswaan Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);

        try {
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'Pilih kelas dahulu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->findOrFail($request->kelas_id);
            $ta = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : TahunAjaran::where('is_active', true)->first();

            if (!$ta) return response()->json(['success' => false, 'message' => 'TA tidak ditemukan.'], 404);

            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;
            $tahun = ($ta->semester === 'Ganjil') ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal) : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, $profil, $kontak, $kelas, 'kesiswaan', $ta->nama), 
                "Rekap_Presensi_Kesiswaan_{$kelas->nama_kelas}_{$labelWaktu}.xlsx"
            );
        } catch (Throwable $e) {
            Log::error('Kesiswaan Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal export.'], 500);
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

    private function applyPresensiFilters(Request $request, $query)
    {
        if (!$request->filled('kelas_id') && !$request->filled('tanggal')) {
            $request->merge(['tanggal' => date('Y-m-d')]);
        }

        $ta = $request->filled('tahun_ajaran_id') ? TahunAjaran::find($request->tahun_ajaran_id) : TahunAjaran::where('is_active', true)->first();

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
            $tahun = ($ta->semester === 'Ganjil') ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal) : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $query->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
            }
        }

        $query->whereHas('siswa', function($q) use ($request) {
            $q->where('is_active', true);
            if ($request->filled('kelas_id')) $q->where('kelas_id', $request->kelas_id);
        });

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }
}