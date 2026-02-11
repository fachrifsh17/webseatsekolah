<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, Siswa, Kelas, TahunAjaran};
use App\Http\Requests\{StorePresensiRequest, UpdatePresensiRequest};
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
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
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

        try {
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
        } catch (Throwable $e) {
            Log::error('Admin Presensi Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data presensi'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        // Menambahkan pengecekan Policy untuk export
        $this->authorize('viewAny', Presensi::class);

        try {
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'ID Kelas wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($error = $this->validateSemesterMonth($request)) {
                return response()->json(['success' => false, 'message' => $error], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::with(['waliKelas', 'tahunAjaran'])->findOrFail($request->kelas_id);
            
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            if ($request->filled('semester')) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)
                    ->where('semester', $request->semester)
                    ->first();
                if ($taMatched) $ta = $taMatched;
            }

            $bulan = (int) $request->get('bulan', date('m'));
            $parts = explode('/', str_replace([' Ganjil', ' Genap'], '', $ta->nama));
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($ta->semester === 'Ganjil') 
                ? (($bulan >= 1 && $bulan <= 6) ? $tahunAkhir : $tahunAwal)
                : (($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $taClean = str_replace(['/', ' '], '-', $ta->nama);
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}-Sem-{$ta->semester}";
            $fileName = "Presensi_{$kelas->nama_kelas}_{$labelWaktu}_TA_{$taClean}.xlsx";

            return Excel::download(
                new PresensiExport(null, $kelas->nama_kelas, $labelWaktu, $profil, $kontak, $kelas, 'admin', $ta->nama), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Admin Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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

            return response()->json([
                'success' => true,
                'data'    => $dataWithStatus
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Siswa::class);

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

            return response()->json([
                'success' => true, 
                'data'    => $collection,
                'info'    => [
                    'tanggal' => $tanggal,
                    'kelas'   => Kelas::find($request->kelas_id)?->nama_kelas
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        // Menambahkan pengecekan Policy untuk store
        $this->authorize('create', Presensi::class);

        $tanggalInput = $request->get('tanggal', date('Y-m-d'));

        if ($this->isDayOff($tanggalInput)) {
            return response()->json(['success' => false, 'message' => 'Input ditolak pada hari libur.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $taActive = TahunAjaran::where('is_active', true)->firstOrFail();
            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $taActive) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id'], $item['status'])) continue;
                    $siswa = Siswa::with('kelas')->where('id', $item['siswa_id'])->where('is_active', true)->first();
                    if (!$siswa) continue;

                    $savedData[] = Presensi::updateOrCreate(
                        ['siswa_id' => (string) $item['siswa_id'], 'tanggal' => $tanggalInput, 'tahun_ajaran_id' => (string) $taActive->id],
                        [
                            'status' => $item['status'],
                            'keterangan' => $item['keterangan'] ?? 'Diinput oleh Admin: ' . Auth::user()->username,
                            'guru_staf_id' => (string) ($siswa->kelas->wali_kelas_id ?? Auth::user()->guru_staf_id),
                        ]
                    );
                }
                return $savedData;
            });

            return response()->json(['success' => true, 'message' => 'Berhasil memproses ' . count($results) . ' data.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memproses data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Presensi $presensi): JsonResponse
    {
        $this->authorize('view', $presensi);
        return response()->json([
            'success' => true, 
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePresensiRequest $request, $id = null): JsonResponse
    {
        try {
            if ($request->has('data_presensi')) {
                $tanggal = $request->get('tanggal', date('Y-m-d'));
                
                $results = DB::transaction(function () use ($request, $tanggal) {
                    $updatedData = [];
                    foreach ($request->input('data_presensi') as $item) {
                        $presensi = Presensi::where('siswa_id', $item['siswa_id'])
                                            ->where('tanggal', $tanggal)
                                            ->first();
                        
                        if ($presensi) {
                            $this->authorize('update', $presensi);
                            $presensi->update([
                                'status' => $item['status'],
                                'keterangan' => $item['keterangan'] ?? $presensi->keterangan,
                            ]);
                            $updatedData[] = $presensi->id;
                        }
                    }
                    return $updatedData;
                });

                return response()->json(['success' => true, 'message' => count($results) . ' data diperbarui.'], Response::HTTP_OK);
            }

            $presensi = Presensi::findOrFail($id);
            $this->authorize('update', $presensi);
            $presensi->update($request->validated());
            
            return response()->json(['success' => true, 'data' => new PresensiResource($presensi)], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Admin Update Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Presensi $presensi): JsonResponse
    {
        $this->authorize('delete', $presensi);
        try {
            $presensi->delete();
            return response()->json(['success' => true, 'message' => 'Data dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal hapus.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        if (!$request->filled('kelas_id')) {
            return $query->whereRaw('1 = 0');
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

        $query->whereHas('siswa', fn($q) => $q->where('is_active', true)->where('kelas_id', $request->kelas_id));

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
    }

    private function isDayOff($date): bool
    {
        $libur = DB::table('kalender_akademik')->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)->exists();
        return $libur || date('N', strtotime($date)) >= 6;
    }
}