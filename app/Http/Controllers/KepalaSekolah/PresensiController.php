<?php

namespace App\Http\Controllers\KepalaSekolah;

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
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
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
            
            // Mengambil array paginasi lengkap
            $pagination = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => PresensiResource::collection($data),
                'meta'    => [
                    'current_page' => $pagination['current_page'],
                    'last_page'    => $pagination['last_page'],
                    'per_page'     => $pagination['per_page'],
                    'total'        => $pagination['total'],
                    'from'         => $pagination['from'],
                    'to'           => $pagination['to'],
                    'path'         => $pagination['path'],
                    'next_page_url'=> $pagination['next_page_url'],
                    'prev_page_url'=> $pagination['prev_page_url'],
                    'links'        => $pagination['links'],
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

            if ($request->filled('semester') && $ta->semester !== $request->semester) {
                $taMatched = TahunAjaran::where('nama', $ta->nama)
                    ->where('semester', $request->semester)
                    ->first();
                if ($taMatched) $ta = $taMatched;
            }

            $bulan = (int) $request->get('bulan', date('m'));
            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;

            $tahun = ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}";
            
            $taClean = str_replace(['/', ' '], '_', $ta->nama);
            $fileName = "Rekap_Presensi_" . 
                        str_replace([' ', '/'], '_', $kelas->nama_kelas) . "_" . 
                        $labelWaktu . "_TA_" . 
                        $taClean . "_" . 
                        $ta->semester . ".xlsx";

            return Excel::download(
                new PresensiExport(
                    $ta->id, 
                    $kelas->nama_kelas, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    'admin', 
                    $ta->nama . ' ' . $ta->semester
                ), 
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
        $context = $this->applyContext($request);
        
        if (!$context['ta']) {
            return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        if ($this->isDayOff($context['tanggal'], $context['ta']->id)) {
            return $this->applyLiburResponse($context);
        }

        $rawData = $this->processKelasStatus($context, $request->status);

        return $this->applyPaginationResponse($rawData, $request, $context);

    } catch (Throwable $e) {
        Log::error('Admin List Kelas Error: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Gagal memuat daftar kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

private function applyContext(Request $request): array
{
    $ta = $request->filled('tahun_ajaran_id') 
        ? TahunAjaran::find($request->tahun_ajaran_id) 
        : TahunAjaran::where('is_active', true)->first();

    $tanggal = $request->get('tanggal', date('Y-m-d'));

    return [
        'ta' => $ta,
        'tanggal' => $tanggal,
        'hari' => Carbon::parse($tanggal)->locale('id')->dayName
    ];
}

private function processKelasStatus(array $context, $statusFilter)
{
    $kelas = Kelas::where('tahun_ajaran_id', $context['ta']->id)
        ->select('id', 'nama_kelas', 'tahun_ajaran_id')
        ->withCount(['siswa' => fn($q) => $q->where('is_active', true)])
        ->orderBy('nama_kelas', 'asc')
        ->get();

    $data = $kelas->map(function ($item) use ($context) {
        $sudahAbsen = Presensi::where('tanggal', $context['tanggal'])
            ->where('tahun_ajaran_id', $context['ta']->id)
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

    if ($statusFilter) {
        return $data->filter(fn($val) => $val['status_presensi'] == $statusFilter)->values();
    }

    return $data;
}

private function applyLiburResponse(array $context): JsonResponse
{
    return response()->json([
        'success' => true,
        'info' => [
            'tanggal' => $context['tanggal'],
            'hari' => $context['hari'],
            'tahun_ajaran' => $context['ta']->nama . ' ' . $context['ta']->semester,
            'is_libur' => true
        ],
        'data' => [],
        'message' => 'Hari libur atau akhir pekan.'
    ], Response::HTTP_OK);
}

private function applyPaginationResponse($collection, Request $request, array $context): JsonResponse
{
    $perPage = (int) $request->get('per_page', 20);
    $currentPage = (int) $request->get('page', 1);
    
    $paginator = new LengthAwarePaginator(
        $collection->forPage($currentPage, $perPage)->values(),
        $collection->count(),
        $perPage,
        $currentPage,
        ['path' => $request->url(), 'query' => $request->query()]
    );

    $meta = $paginator->toArray();

    return response()->json([
        'success' => true,
        'info' => [
            'tanggal' => $context['tanggal'],
            'hari' => $context['hari'],
            'tahun_ajaran' => $context['ta']->nama . ' ' . $context['ta']->semester,
            'is_libur' => false
        ],
        'data' => $meta['data'],
        'meta' => [
            'current_page' => $meta['current_page'],
            'last_page'    => $meta['last_page'],
            'per_page'     => $meta['per_page'],
            'total'        => $meta['total'],
            'from'         => $meta['from'],
            'to'           => $meta['to'],
            'path'         => $meta['path'],
            'next_page_url'=> $meta['next_page_url'],
            'prev_page_url'=> $meta['prev_page_url'],
            'links'        => $meta['links'],
        ]
    ], Response::HTTP_OK);
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

            if ($this->isDayOff($tanggal, $ta->id)) {
                return response()->json([
                    'success' => true, 
                    'info'    => [
                        'kelas'            => Kelas::find($request->kelas_id)?->nama_kelas,
                        'tanggal'          => $tanggal,
                        'is_libur'         => true,
                        'sudah_isi_absen'  => false
                    ],
                    'data'    => [],
                    'message' => 'Pemuatan daftar siswa dihentikan karena hari libur.'
                ], Response::HTTP_OK);
            }

            $siswa = Siswa::where('kelas_id', $request->kelas_id)
                ->where('is_active', true)
                ->with(['presensi' => fn($q) => $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $ta->id)])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            $adaData = $siswa->contains(fn($s) => $s->presensi->isNotEmpty());

            $collection = $siswa->map(function ($item) {
                $presensiExisting = $item->presensi->first();
                
                return [
                    'siswa_id' => $item->id,
                    'nama'     => $item->nama_lengkap,
                    'nisn'     => $item->nisn,
                    'status'   => $presensiExisting?->status ?? null,
                    'catatan'  => $presensiExisting?->keterangan ?? null
                ];
            });

            return response()->json([
                'success' => true, 
                'info'    => [
                    'presensi_id'      => $siswa->first()?->presensi->first()?->id ?? null,
                    'kelas'            => Kelas::find($request->kelas_id)?->nama_kelas,
                    'tanggal'          => $tanggal,
                    'is_libur'         => false,
                    'sudah_isi_absen'  => $adaData
                ],
                'data'    => $collection
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat daftar siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $this->authorize('create', Presensi::class);

        try {
            $taActive = TahunAjaran::where('is_active', true)->firstOrFail();
            $tanggalInput = $request->get('tanggal', date('Y-m-d'));
            $requestKelasId = $request->input('kelas_id');

            if ($this->isDayOff($tanggalInput, $taActive->id)) {
                return response()->json(['success' => false, 'message' => 'Input ditolak pada hari libur atau akhir pekan.'], Response::HTTP_BAD_REQUEST);
            }

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $taActive, $requestKelasId) {
                $savedData = [];
                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id'], $item['status'])) continue;
                    
                    $siswa = Siswa::with('kelas')
                        ->where('id', $item['siswa_id'])
                        ->where('is_active', true)
                        ->first();

                    if (!$siswa || ($requestKelasId && $siswa->kelas_id != $requestKelasId)) {
                        throw new \Exception("Siswa dengan ID {$item['siswa_id']} tidak terdaftar di kelas yang dipilih.");
                    }

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
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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
                $requestKelasId = $request->input('kelas_id');
                
                $results = DB::transaction(function () use ($request, $tanggal, $requestKelasId) {
                    $updatedData = [];
                    foreach ($request->input('data_presensi') as $item) {
                        $query = Presensi::where('siswa_id', $item['siswa_id'])->where('tanggal', $tanggal);
                        
                        if ($requestKelasId) {
                            $query->whereHas('siswa', function($q) use ($requestKelasId) {
                                $q->where('kelas_id', $requestKelasId);
                            });
                        }

                        $presensi = $query->first();
                        
                        if ($presensi) {
                            $this->authorize('update', $presensi);
                            $presensi->update([
                                'status' => $item['status'],
                                'keterangan' => $item['keterangan'] ?? $presensi->keterangan,
                            ]);
                            $updatedData[] = $presensi->id;
                        } else {
                            throw new \Exception("Data presensi siswa ID {$item['siswa_id']} tidak ditemukan di kelas ini.");
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

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            
            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;
            
            $tahun = ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;

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

    private function isDayOff($date, $tahunAjaranId): bool
    {
        $libur = DB::table('kalender_akademik')
            ->where('kategori', 'Libur')
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->whereDate('tanggal_mulai', '<=', $date)
            ->whereDate('tanggal_selesai', '>=', $date)
            ->exists();

        return $libur || date('N', strtotime($date)) >= 6;
    }
}