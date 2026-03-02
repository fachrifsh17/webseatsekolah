<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, PresensiDetail, Siswa, Kelas, Semester, GuruStaf, SiswaKelas, KelasWaliKelas, KalenderAkademik};
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

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        try {
            $semesterId = $request->semester_id ?? Semester::where('is_active', true)->first()?->id;

            if (!$semesterId) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $today = Carbon::today();
            $bulan = (int) ($request->bulan ?? $today->month);
            $tahunFilter = (int) ($request->tahun ?? $today->year);

            $semester = Semester::with('tahunAjaran')->findOrFail($semesterId);
            $semesterNama = strtolower($semester->nama);
            
            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($semesterNama === 'ganjil' && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($semesterNama === 'genap' && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = Kelas::query();

            if ($request->filled('kelas_id')) {
                $query->where('id', $request->kelas_id);
            }

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', "%{$request->search}%");
            }

            $kelasPaginated = $query->orderBy('nama_kelas', 'asc')->paginate(50);
            
            $data = $kelasPaginated->map(function ($kelas) use ($bulan, $tahunFilter, $semesterId) {
                
                $jumlahSiswa = SiswaKelas::where('kelas_id', $kelas->id)
                    ->where('semester_id', $semesterId)
                    ->where('is_active', 1)
                    ->count();

                $totalPresensi = PresensiDetail::whereHas('presensi', function($query) use ($kelas, $semesterId, $tahunFilter, $bulan) {
                        $query->where('kelas_id', $kelas->id)
                              ->where('semester_id', $semesterId)
                              ->whereYear('tanggal', $tahunFilter)
                              ->whereMonth('tanggal', $bulan);
                    })
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->toArray();

                $tanggalSudahAbsen = Presensi::where('kelas_id', $kelas->id)
                    ->where('semester_id', $semesterId)
                    ->whereYear('tanggal', $tahunFilter)
                    ->whereMonth('tanggal', $bulan)
                    ->orderBy('tanggal', 'desc')
                    ->get()
                    ->map(function($presensi) {
                        return Carbon::parse($presensi->tanggal)->format('Y-m-d');
                    })
                    ->unique()
                    ->values()
                    ->toArray();

                return [
                    'kelas_id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'tanggal_absen' => $tanggalSudahAbsen,
                    'total_siswa' => $jumlahSiswa,
                    'rekap' => [
                        'hadir' => $totalPresensi['Hadir'] ?? 0,
                        'izin'  => $totalPresensi['Izin'] ?? 0,
                        'sakit' => $totalPresensi['Sakit'] ?? 0,
                        'alpa'  => $totalPresensi['Alpa'] ?? 0,
                    ],
                    'status_absen' => !empty($tanggalSudahAbsen) ? 'Sudah Absen' : 'Belum Absen',
                ];
            });

            $filteredData = $data->where('status_absen', 'Sudah Absen')->values();

            return response()->json([
                'success' => true,
                'data'    => $filteredData,
                'meta'    => [
                    'current_page' => $kelasPaginated->currentPage(),
                    'last_page'    => $kelasPaginated->lastPage(),
                    'per_page'     => $kelasPaginated->perPage(),
                    'total'        => $filteredData->count(),
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
            Log::error('Admin Presensi Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data presensi'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);

        try {
            if (!$request->filled('kelas_id') || !$request->filled('bulan')) {
                return response()->json(['success' => false, 'message' => 'Kelas dan Bulan wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $semesterId = $request->semester_id ?? Semester::where('is_active', true)->first()?->id;

            if (!$semesterId) {
                return response()->json(['success' => false, 'message' => 'Semester aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $kelas = Kelas::findOrFail($request->kelas_id);
            $semester = Semester::with('tahunAjaran')->findOrFail($semesterId);
            $bulan = (int) $request->bulan;
            
            $tahunDasar = (int) substr($semester->tahunAjaran->nama, 0, 4);
            $semesterNama = strtolower($semester->nama);
            $tahunAjaranFormatted = str_replace('/', '_', $semester->tahunAjaran->nama);

            if ($semesterNama === 'genap' && $bulan >= 1 && $bulan <= 6) {
                $tahunInput = $tahunDasar + 1;
            } else {
                $tahunInput = $tahunDasar;
            }

            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($semesterNama === 'ganjil' && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($semesterNama === 'genap' && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $namaKelasClean = str_replace([' ', '/'], '_', strtoupper($kelas->nama_kelas));
            $semesterClean = str_replace([' ', '/'], '_', strtoupper($semester->nama));
            
            $fileName = "REKAP_PRESENSI_{$namaKelasClean}_BULAN_{$bulan}_{$tahunAjaranFormatted}_{$semesterClean}.xlsx";

            return Excel::download(
                new PresensiExport(
                    $semester->id, 
                    $kelas->nama_kelas, 
                    "Bulan-{$bulan}-Tahun-{$tahunInput}", 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    "admin", 
                    $semester
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Admin Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listKelas(Request $request): JsonResponse
    {
        try {
            $context = $this->applyContext($request);
            if (!$context['semester']) {
                return response()->json(['success' => false, 'message' => 'Semester aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $kelas = Kelas::where('is_active', 1)
                ->select('id', 'nama_kelas')
                ->withCount(['riwayatKelas as siswa_count' => function($q) use ($context) {
                    $q->where('semester_id', $context['semester']->id)
                      ->where('is_active', 1);
                }])
                ->orderBy('nama_kelas', 'asc')
                ->get();

            $data = $kelas->map(function ($item) use ($context) {
                $sudahAbsen = Presensi::where('tanggal', $context['tanggal'])
                    ->where('semester_id', $context['semester']->id)
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
            Log::error('Admin List Kelas Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request, $kelas_id): JsonResponse
    {
        $this->authorize('viewAny', Siswa::class);

        try {
            $tanggal = $request->get('tanggal', date('Y-m-d'));
            $mode = $request->get('mode', 'edit'); 
            
            $semesterId = $request->get('semester_id');
            $semester = $semesterId ? Semester::find($semesterId) : Semester::where('is_active', true)->first();

            if (!$semester) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswa = Siswa::whereHas('riwayatKelas', function($q) use ($kelas_id, $semester, $mode) {
                    $q->where('kelas_id', $kelas_id)
                      ->where('semester_id', $semester->id);
                    
                    if ($mode === 'input') {
                        $q->where('siswa_kelas.is_active', 1);
                    }
                })
                ->with(['presensiDetail' => function($q) use ($tanggal, $semester) {
                    $q->whereHas('presensi', function($query) use ($tanggal, $semester) {
                        $query->whereDate('tanggal', $tanggal)
                              ->where('semester_id', $semester->id);
                    });
                }])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            if ($siswa->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa tidak ditemukan pada semester ini.',
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
                    'kelas'           => Kelas::find($kelas_id)?->nama_kelas,
                    'tanggal'         => $tanggal,
                    'semester'        => $semester->nama,
                    'sudah_isi_absen' => $siswa->contains(fn($s) => $s->presensiDetail->isNotEmpty())
                ],
                'data'    => $collection
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('List Siswa Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $this->authorize('create', Presensi::class);

        try {
            $semesterActive = Semester::where('is_active', true)->firstOrFail();
            $tanggalInput = $request->get('tanggal', date('Y-m-d'));
            $requestKelasId = $request->input('kelas_id');
            $carbonDate = Carbon::parse($tanggalInput);

            if ($carbonDate->isWeekend()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak dapat melakukan presensi pada hari libur (Sabtu/Minggu).'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $isLiburKalender = KalenderAkademik::where('semester_id', $semesterActive->id)
                ->where('kategori', 'Libur')
                ->whereDate('tanggal_mulai', '<=', $tanggalInput)
                ->whereDate('tanggal_selesai', '>=', $tanggalInput)
                ->exists();

            if ($isLiburKalender) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak dapat melakukan presensi karena tanggal tersebut ditandai sebagai libur di kalender akademik.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $siswaIdWajib = SiswaKelas::where('kelas_id', $requestKelasId)
                ->where('semester_id', $semesterActive->id)
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
                    'message' => 'Tidak ada siswa aktif di kelas ini untuk semester berjalan.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $siswaIdInput = collect($dataInput)->pluck('siswa_id')->toArray();

            $siswaBelumInput = array_diff($siswaIdWajib, $siswaIdInput);
            if (count($siswaBelumInput) > 0) {
                $namaSiswaTerlewat = Siswa::whereIn('id', $siswaBelumInput)->pluck('nama_lengkap')->implode(', ');
                return response()->json(['success' => false, 'message' => "Siswa belum diisi: [{$namaSiswaTerlewat}]."], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($dataInput, $tanggalInput, $semesterActive, $requestKelasId, $siswaIdWajib) {
                
                $waliKelasRecord = KelasWaliKelas::where('kelas_id', $requestKelasId)
                    ->where('semester_id', $semesterActive->id)
                    ->where('is_active', 1)
                    ->whereHas('guruStaf', function ($query) {
                        $query->where('is_active', 1);
                    })
                    ->first();
                    
                if ($waliKelasRecord && !empty($waliKelasRecord->guru_staf_id)) {
                    $waliKelasId = $waliKelasRecord->guru_staf_id;
                } else {
                    $waliKelasId = Auth::user()->guru_staf_id;
                }
                
                $presensiHeader = Presensi::updateOrCreate(
                    [
                        'tanggal' => $tanggalInput,
                        'kelas_id' => $requestKelasId,
                        'semester_id' => $semesterActive->id
                    ],
                    [
                        'guru_staf_id' => $waliKelasId,
                    ]
                );

                foreach ($dataInput as $item) {
                    if (in_array($item['siswa_id'], $siswaIdWajib)) {
                        PresensiDetail::updateOrCreate(
                            [
                                'presensi_id' => $presensiHeader->id,
                                'siswa_id' => $item['siswa_id']
                            ],
                            [
                                'status' => $item['status'],
                                'keterangan' => $item['keterangan'] ?? 'Diinput Admin: ' . Auth::user()->username,
                            ]
                        );
                    }
                }
            });

            return response()->json(['success' => true, 'message' => 'Presensi berhasil disimpan.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, $presensiId): JsonResponse
    {
        $presensiHeader = Presensi::find($presensiId);

        if (!$presensiHeader) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan.'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->authorize('update', $presensiHeader);

        try {
            $semesterAktif = Semester::where('is_active', true)->first();
            
            if (!$semesterAktif || $presensiHeader->semester_id !== $semesterAktif->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data presensi ini sudah tidak dapat diubah karena semester telah berganti atau tidak aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            if ($request->has('tanggal')) {
                $tanggalBaru = $request->tanggal;
                $carbonDate = Carbon::parse($tanggalBaru);

                if ($carbonDate->isWeekend()) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Tidak dapat mengubah presensi ke hari libur (Sabtu/Minggu).'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $isLiburKalender = KalenderAkademik::where('semester_id', $semesterAktif->id)
                    ->where('kategori', 'Libur')
                    ->whereDate('tanggal_mulai', '<=', $tanggalBaru)
                    ->whereDate('tanggal_selesai', '>=', $tanggalBaru)
                    ->exists();

                if ($isLiburKalender) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Tidak dapat mengubah presensi ke tanggal libur kalender akademik.'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            DB::beginTransaction();
            
            $siswaIdWajib = SiswaKelas::where('kelas_id', $presensiHeader->kelas_id)
                ->where('semester_id', $presensiHeader->semester_id)
                ->where('is_active', 1)
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
                                'keterangan' => $item['keterangan'] ?? 'Diupdate Admin: ' . Auth::user()->username,
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
                    'message' => 'Beberapa siswa tidak terdaftar atau tidak aktif di kelas/semester ini.',
                    'siswa_tidak_terdaftar' => $siswaTidakTerdaftar
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diperbarui.',
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Update Presensi Massal Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyContext(Request $request): array
    {
        $semester = Semester::where('is_active', true)->first();
        $tanggal = $request->get('tanggal', date('Y-m-d'));
        
        return [
            'semester' => $semester, 
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
                'semester' => $context['semester']?->nama
            ],
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 
                'last_page' => $paginator->lastPage(), 
                'per_page' => $paginator->perPage(),                
                'total' => $paginator->total()
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], Response::HTTP_OK);
    }
}