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
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

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
                    'per_page'     => $data->perPage(),
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
        $this->authorize('viewAny', Presensi::class);

        try {
            if (!$request->filled('kelas_id')) {
                return response()->json(['success' => false, 'message' => 'ID Kelas wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::where('is_active', 1)->findOrFail($request->kelas_id);
            
            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $exportContext = "admin"; 

            $bulan = (int) $request->get('bulan', date('m'));
            $pureYearName = trim(str_replace(['Ganjil', 'Genap'], '', $ta->nama));
            $parts = explode('/', $pureYearName);
            $tahunAwal = (int) $parts[0];
            $tahunAkhir = isset($parts[1]) ? (int) $parts[1] : $tahunAwal;
            $tahun = ($bulan >= 7 && $bulan <= 12) ? $tahunAwal : $tahunAkhir;

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $labelWaktu = "Bulan-{$bulan}-Tahun-{$tahun}";

            $namaKelas = str_replace([' ', '/'], '_', strtoupper($kelas->nama_kelas));
            $tahunAjaranName = str_replace([' ', '/'], '_', strtoupper($ta->nama));
            $semester = strtoupper($ta->semester);
            
            $fileName = "REKAP_PRESENSI_{$namaKelas}_{$tahunAjaranName}_{$semester}.xlsx";

            return Excel::download(
                new PresensiExport(
                    $ta->id, 
                    $kelas->nama_kelas, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    $exportContext, 
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
        $kelas = Kelas::where('is_active', 1)
            ->select('id', 'nama_kelas')
            ->withCount(['riwayatKelas as siswa_count' => function($q) use ($context) {
                $q->where('siswa_kelas.tahun_ajaran_id', $context['ta']->id)
                  ->where('siswa_kelas.is_active', 1);
            }])
            ->orderBy('nama_kelas', 'asc')
            ->get();

        $data = $kelas->map(function ($item) use ($context) {
            $sudahAbsen = Presensi::where('tanggal', $context['tanggal'])
                ->where('tahun_ajaran_id', $context['ta']->id)
                ->where('kelas_id', $item->id)
                ->exists();

            return [
                'id' => $item->id,
                'nama_kelas' => $item->nama_kelas,
                'siswa_count' => $item->siswa_count,
                'status_presensi' => $sudahAbsen ? 'Sudah Absen' : 'Belum Absen'
            ];
        });

        if ($statusFilter) {
            return $data->filter(fn($val) => $val['status_presensi'] == $statusFilter)->values();
        }

        return $data;
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
                        'kelas'           => Kelas::find($request->kelas_id)?->nama_kelas,
                        'tanggal'          => $tanggal,
                        'is_libur'         => true,
                        'sudah_isi_absen'  => false
                    ],
                    'data'    => [],
                    'message' => 'Hari libur.'
                ], Response::HTTP_OK);
            }

            $siswa = Siswa::whereHas('riwayatKelas', function($q) use ($request, $ta) {
                    $q->where('kelas_id', $request->kelas_id)
                      ->where('tahun_ajaran_id', $ta->id)
                      ->where('is_active', 1);
                })
                ->where('is_active', true)
                ->with(['presensi' => fn($q) => $q->whereDate('tanggal', $tanggal)->where('tahun_ajaran_id', $ta->id)])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            $adaData = $siswa->contains(fn($s) => $s->presensi->isNotEmpty());

            $collection = $siswa->map(fn($item) => [
                'siswa_id' => $item->id,
                'nama'     => $item->nama_lengkap,
                'nisn'     => $item->nisn,
                'status'   => $item->presensi->first()?->status ?? null,
                'catatan'  => $item->presensi->first()?->keterangan ?? null
            ]);

            return response()->json([
                'success' => true, 
                'info'    => [
                    'kelas'            => Kelas::find($request->kelas_id)?->nama_kelas,
                    'tanggal'          => $tanggal,
                    'is_libur'         => false,
                    'sudah_isi_absen'  => $adaData
                ],
                'data'    => $collection
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return response()->json(['success' => false, 'message' => 'Input ditolak pada hari libur.'], Response::HTTP_BAD_REQUEST);
            }

            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $requestKelasId)
                ->where('tahun_ajaran_id', $taActive->id)
                ->where('is_active', 1)
                ->pluck('siswa_id')
                ->toArray();

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];
            $siswaIdInput = collect($dataInput)->pluck('siswa_id')->toArray();

            $siswaBelumInput = array_diff($siswaIdWajib, $siswaIdInput);
            if (count($siswaBelumInput) > 0) {
                $namaSiswaTerlewat = Siswa::whereIn('id', $siswaBelumInput)->pluck('nama_lengkap')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Siswa belum diisi: [{$namaSiswaTerlewat}]."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $results = DB::transaction(function () use ($dataInput, $tanggalInput, $taActive, $requestKelasId, $siswaIdWajib) {
                $savedData = [];
                $waliKelasId = DB::table('kelas')->where('id', $requestKelasId)->value('wali_kelas_id');

                foreach ($dataInput as $item) {
                    if (!isset($item['siswa_id'], $item['status'])) continue;
                    
                    $savedData[] = Presensi::updateOrCreate(
                        ['siswa_id' => $item['siswa_id'], 'tanggal' => $tanggalInput, 'tahun_ajaran_id' => $taActive->id],
                        [
                            'status' => $item['status'],
                            'kelas_id' => $requestKelasId,
                            'keterangan' => $item['keterangan'] ?? 'Diinput Admin: ' . Auth::user()->username,
                            'guru_staf_id' => $waliKelasId ?? Auth::user()->guru_staf_id,
                        ]
                    );
                }
                return $savedData;
            });

            return response()->json(['success' => true, 'message' => 'Berhasil simpan ' . count($results) . ' data.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Admin Store Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $query->where('tahun_ajaran_id', $ta->id);
            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            } else {
                $bulan = (int) $request->get('bulan', date('m'));
                $query->whereMonth('tanggal', $bulan);
            }

            $query->whereHas('siswa.riwayatKelas', function($q) use ($request, $ta) {
                $q->where('kelas_id', $request->kelas_id)
                  ->where('tahun_ajaran_id', $ta->id);
            });
        }

        $query->where('kelas_id', $request->kelas_id);

        if ($request->filled('search')) {
            $query->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$request->search}%"));
        }

        return $query->with(['siswa', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'desc');
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

    private function applyLiburResponse(array $context): JsonResponse
    {
        return response()->json([
            'success' => true,
            'info' => [
                'tanggal' => $context['tanggal'],
                'hari' => $context['hari'],
                'tahun_ajaran' => $context['ta']->nama ?? '',
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

        return response()->json([
            'success' => true,
            'info' => [
                'tanggal' => $context['tanggal'],
                'hari' => $context['hari'],
                'is_libur' => false
            ],
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ]
        ], Response::HTTP_OK);
    }
}