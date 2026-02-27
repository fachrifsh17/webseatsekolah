<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, PresensiDetail, Siswa, Kelas, TahunAjaran};
use App\Http\Requests\{StorePresensiRequest, UpdatePresensiRequest};
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth, Schema};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update']);
    }

    private function getGuruId()
    {
        $user = Auth::user();
        if (!$user->relationLoaded('guruStaf')) {
            $user->load('guruStaf');
        }
        return $user->guruStaf ? trim($user->guruStaf->id) : null;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);
        $guruId = $this->getGuruId();

        if (!$request->filled('tahun_ajaran_id') || !$request->filled('bulan')) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun Ajaran dan Bulan wajib dipilih.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $taId = $request->tahun_ajaran_id;
            $bulan = (int) $request->bulan;
            $ta = TahunAjaran::findOrFail($taId);

            $semester = strtolower($ta->semester);
            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($semester === 'ganjil' && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($semester === 'genap' && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tahunDasar = substr($ta->nama, 0, 4);
            $tahunFilter = ($semester === 'genap' && $bulan <= 6) ? (int)$tahunDasar + 1 : (int)$tahunDasar;

            $query = Kelas::query();
            $query->where('wali_kelas_id', $guruId);

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', "%{$request->search}%");
            }

            $kelasPaginated = $query->orderBy('nama_kelas', 'asc')->paginate(50);
            
            $dataCollection = $kelasPaginated->getCollection()->map(function ($kelas) use ($bulan, $tahunFilter, $taId) {
                
                $jumlahSiswa = DB::table('siswa_kelas')
                    ->where('kelas_id', $kelas->id)
                    ->where('tahun_ajaran_id', $taId)
                    ->count();

                $sudahAbsen = Presensi::where('kelas_id', $kelas->id)
                    ->where('tahun_ajaran_id', $taId)
                    ->whereYear('tanggal', $tahunFilter)
                    ->whereMonth('tanggal', $bulan)
                    ->exists();

                return [
                    'kelas_id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'bulan' => $bulan,
                    'tahun' => $tahunFilter,
                    'total_siswa' => $jumlahSiswa,
                    'status_absen' => $sudahAbsen ? 'Sudah Absen' : 'Belum Absen',
                ];
            });

            $filteredData = $dataCollection->where('status_absen', 'Sudah Absen')->values();

            return response()->json([
                'success' => true,
                'data'    => $filteredData,
                'meta'    => [
                    'current_page' => $kelasPaginated->currentPage(),
                    'last_page'    => $kelasPaginated->lastPage(),
                    'per_page'     => $kelasPaginated->perPage(),
                    'total'        => $kelasPaginated->total(),
                    'path'         => $kelasPaginated->path(),
                ],
                'links' => [
                    'first' => $kelasPaginated->url(1),
                    'last'  => $kelasPaginated->url($kelasPaginated->lastPage()),
                    'prev'  => $kelasPaginated->previousPageUrl(),
                    'next'  => $kelasPaginated->nextPageUrl(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Walikelas Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data presensi'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);
        $guruId = $this->getGuruId();

        try {
            if (!$request->filled('kelas_id') || !$request->filled('bulan') || !$request->filled('tahun_ajaran_id')) {
                return response()->json(['success' => false, 'message' => 'Kelas, Bulan, dan Tahun Ajaran wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::where('id', trim($request->kelas_id))
                ->where('wali_kelas_id', $guruId)
                ->firstOrFail();

            $ta = TahunAjaran::findOrFail($request->tahun_ajaran_id);
            $bulan = (int) $request->bulan;
            
            $tahunDasar = (int) substr($ta->nama, 0, 4);
            $semester = strtolower($ta->semester);

            if ($semester === 'genap' && $bulan >= 1 && $bulan <= 6) {
                $tahunInput = $tahunDasar + 1;
            } else {
                $tahunInput = $tahunDasar;
            }

            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($semester === 'ganjil' && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($semester === 'genap' && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $namaKelasClean = str_replace([' ', '/'], '_', strtoupper($kelas->nama_kelas));
            $taClean = str_replace([' ', '/'], '_', strtoupper($ta->nama));
            $semesterClean = strtoupper($ta->semester);
            
            $fileName = "REKAP_PRESENSI_{$namaKelasClean}_BULAN_{$bulan}_{$taClean}_{$semesterClean}.xlsx";

            return Excel::download(
                new PresensiExport(
                    $ta->id, 
                    $kelas->nama_kelas, 
                    "Bulan-{$bulan}-Tahun-{$tahunInput}", 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    "walikelas", 
                    $ta
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Walikelas Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listKelas(Request $request): JsonResponse
    {
        try {
            $context = $this->applyContext($request);
            if (!$context['ta']) {
                return response()->json(['success' => false, 'message' => 'Tahun Ajaran aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }
            
            $guruId = $this->getGuruId();

            $kelas = Kelas::where('is_active', 1)
                ->where('wali_kelas_id', $guruId)
                ->select('id', 'nama_kelas', 'wali_kelas_id')
                ->withCount(['riwayatKelas as siswa_count' => function($q) use ($context) {
                    $q->where('tahun_ajaran_id', $context['ta']->id);
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

            return $this->applyPaginationResponse($data, $request, $context);
        } catch (Throwable $e) {
            Log::error('Walikelas List Kelas Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request, $kelas_id): JsonResponse
    {
        $this->authorize('viewAny', Siswa::class);
        $guruId = $this->getGuruId();
        $cleanKelasId = trim($kelas_id);

        try {
            Log::info("DEBUG - listSiswaPresensi | Guru: [{$guruId}] | KelasReq: [{$cleanKelasId}]");

            $kelas = Kelas::where('id', $cleanKelasId)
                ->where('wali_kelas_id', $guruId)
                ->where('is_active', 1)
                ->firstOrFail();

            $tanggal = $request->get('tanggal', date('Y-m-d'));
            
            $taId = $request->get('tahun_ajaran_id');
            $ta = $taId ? TahunAjaran::find($taId) : TahunAjaran::where('is_active', true)->first();

            if (!$ta) {
                return response()->json(['success' => false, 'message' => 'Tahun Ajaran tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswa = Siswa::whereHas('riwayatKelas', function($q) use ($cleanKelasId, $ta) {
                    $q->where('kelas_id', $cleanKelasId)
                      ->where('tahun_ajaran_id', $ta->id);
                })
                ->with(['presensiDetail' => function($q) use ($tanggal, $ta) {
                    $q->whereHas('presensi', function($query) use ($tanggal, $ta) {
                        $query->whereDate('tanggal', $tanggal)
                              ->where('tahun_ajaran_id', $ta->id);
                    });
                }])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            if ($siswa->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa tidak ditemukan pada tahun ajaran ini.',
                    'data' => []
                ], Response::HTTP_NOT_FOUND);
            }

            $collection = $siswa->map(fn($item) => [
                'siswa_id' => $item->id,
                'nama'     => $item->nama_lengkap,
                'nisn'     => $item->nisn,
                'status'   => $item->presensiDetail->first()?->status ?? null,
                'catatan'  => $item->presensiDetail->first()?->keterangan ?? null
            ]);

            return response()->json([
                'success' => true, 
                'info'    => [
                    'kelas'           => $kelas->nama_kelas,
                    'tanggal'         => $tanggal,
                    'tahun_ajaran'    => $ta->nama,
                    'sudah_isi_absen' => $siswa->contains(fn($s) => $s->presensiDetail->isNotEmpty())
                ],
                'data'    => $collection
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Walikelas List Siswa Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $this->authorize('create', Presensi::class);
        $guruId = $this->getGuruId();

        try {
            $now = Carbon::now('Asia/Jakarta');
            $startAllowed = Carbon::createFromTime(6, 30, 0, 'Asia/Jakarta');
            $endAllowed = Carbon::createFromTime(10, 0, 0, 'Asia/Jakarta');

            if ($now->lt($startAllowed) || $now->gt($endAllowed)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presensi hanya dapat diinput pada jam 06:30 hingga 10:00 WIB.'
                ], Response::HTTP_FORBIDDEN);
            }

            $taActive = TahunAjaran::where('is_active', true)->firstOrFail();
            $tanggalInput = $request->get('tanggal', date('Y-m-d'));
            $requestKelasId = trim($request->input('kelas_id'));

            $kelas = Kelas::where('id', $requestKelasId)
                ->where('wali_kelas_id', $guruId)
                ->firstOrFail();

            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $requestKelasId)
                ->where('tahun_ajaran_id', $taActive->id)
                ->where('is_active', 1) 
                ->pluck('siswa_id')
                ->toArray();

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];
            
            if (empty($dataInput) || (count($dataInput) == 1 && empty($dataInput[0]))) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Data presensi tidak ditemukan atau kosong.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (empty($siswaIdWajib)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak ada siswa di kelas ini untuk tahun ajaran berjalan.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $siswaIdInput = collect($dataInput)->pluck('siswa_id')->toArray();
            
            $siswaSalah = array_diff($siswaIdInput, $siswaIdWajib);
            
            if (count($siswaSalah) > 0) {
                $namaSiswaSalah = Siswa::whereIn('id', $siswaSalah)->pluck('nama_lengkap')->implode(', ');
                return response()->json([
                    'success' => false, 
                    'message' => "Siswa berikut tidak terdaftar atau tidak aktif di kelas ini: [{$namaSiswaSalah}]."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($dataInput, $tanggalInput, $taActive, $requestKelasId, $guruId) {
                
                $presensiHeader = Presensi::updateOrCreate(
                    [
                        'tanggal' => $tanggalInput,
                        'kelas_id' => $requestKelasId,
                        'tahun_ajaran_id' => $taActive->id
                    ],
                    [
                        'guru_staf_id' => $guruId,
                    ]
                );

                foreach ($dataInput as $item) {
                    PresensiDetail::updateOrCreate(
                        [
                            'presensi_id' => $presensiHeader->id,
                            'siswa_id' => $item['siswa_id']
                        ],
                        [
                            'status' => $item['status'],
                            'keterangan' => $item['keterangan'] ?? 'Diinput Wali Kelas: ' . Auth::user()->username,
                        ]
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Presensi berhasil disimpan.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Walikelas Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, $presensiId): JsonResponse
    {
        $guruId = $this->getGuruId();
        $presensiHeader = Presensi::where('id', trim($presensiId))
            ->where('guru_staf_id', $guruId)
            ->first();

        if (!$presensiHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan atau Anda tidak berhak mengubahnya.'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $presensiHeader);

        try {
            DB::beginTransaction();
            
            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $presensiHeader->kelas_id)
                ->where('tahun_ajaran_id', $presensiHeader->tahun_ajaran_id)
                ->pluck('siswa_id')
                ->toArray();

            $siswaTidakTerdaftar = [];

            if ($request->has('data_presensi')) {
                foreach ($request->data_presensi as $item) {
                    if (in_array($item['siswa_id'], $siswaIdWajib)) {
                        PresensiDetail::updateOrCreate(
                            [
                                'presensi_id' => $presensiHeader->id,
                                'siswa_id' => $item['siswa_id']
                            ],
                            [
                                'status' => $item['status'],
                                'keterangan' => $item['keterangan'] ?? 'Diupdate Wali Kelas: ' . Auth::user()->username,
                            ]
                        );
                    } else {
                        $siswaTidakTerdaftar[] = $item['siswa_id'];
                    }
                }
            }

            DB::commit();

            if (!empty($siswaTidakTerdaftar)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beberapa siswa tidak terdaftar di kelas/tahun ajaran ini.',
                    'siswa_tidak_terdaftar' => $siswaTidakTerdaftar
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diperbarui.',
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Walikelas Update Presensi Massal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyContext(Request $request): array
    {
        $ta = TahunAjaran::where('is_active', true)->first();
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        
        return [
            'ta' => $ta, 
            'tanggal' => $tanggal, 
            'hari' => Carbon::parse($tanggal)->locale('id')->dayName
        ];
    }

    private function applyPaginationResponse($collection, Request $request, array $context): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $currentPage = (int) $request->get('page', 1);
        $paginator = new LengthAwarePaginator(
            $collection->forPage($currentPage, $perPage)->values(),
            $collection->count(), $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return response()->json([
            'success' => true,
            'info' => [
                'tanggal' => $context['tanggal'], 
                'hari' => $context['hari'], 
                'tahun_ajaran' => $context['ta']?->nama
            ],
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 
                'last_page' => $paginator->lastPage(), 
                'total' => $paginator->total()
            ]
        ], Response::HTTP_OK);
    }
}