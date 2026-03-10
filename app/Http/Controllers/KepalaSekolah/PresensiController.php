<?php

namespace App\Http\Controllers\KepalaSekolah;

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

            $query = Presensi::with(['kelas', 'semester', 'guru'])
                ->where('semester_id', $semesterId);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', (string) $request->kelas_id);
            }

            if ($request->filled('bulan')) {
                $query->whereMonth('tanggal', $request->bulan);
            }

            if ($request->filled('search')) {
                $query->whereHas('kelas', function($q) use ($request) {
                    $q->where('nama_kelas', 'like', "%{$request->search}%");
                });
            }

            $presensiPaginated = $query->orderBy('tanggal', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            $data = $presensiPaginated->getCollection()->map(function ($presensi) {
                $rekapData = PresensiDetail::where('presensi_id', $presensi->id)
                    ->select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->toArray();

                return [
                    'presensi_id' => $presensi->id,
                    'tanggal' => Carbon::parse($presensi->tanggal)->format('Y-m-d'),
                    'hari' => Carbon::parse($presensi->tanggal)->locale('id')->dayName,
                    'kelas_id' => (string) $presensi->kelas_id,
                    'nama_kelas' => $presensi->kelas->nama_kelas ?? '-',
                    'guru_id' => $presensi->guru_id,
                    'nama_guru' => $presensi->guru->nama ?? '-',
                    'rekap_harian' => [
                        'hadir' => $rekapData['Hadir'] ?? $rekapData['H'] ?? 0,
                        'izin'  => $rekapData['Izin'] ?? $rekapData['I'] ?? 0,
                        'sakit' => $rekapData['Sakit'] ?? $rekapData['S'] ?? 0,
                        'alpa'  => $rekapData['Alpa'] ?? $rekapData['A'] ?? 0,
                        'total' => array_sum($rekapData)
                    ],
                    'status_jurnal' => 'Selesai'
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'current_page' => $presensiPaginated->currentPage(),
                    'last_page'    => $presensiPaginated->lastPage(),
                    'per_page'     => $presensiPaginated->perPage(),
                    'total'        => $presensiPaginated->total(),
                ],
                'links' => [
                    'first' => $presensiPaginated->url(1),
                    'last' => $presensiPaginated->url($presensiPaginated->lastPage()),
                    'prev' => $presensiPaginated->previousPageUrl(),
                    'next' => $presensiPaginated->nextPageUrl(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Presensi Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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

            $kelas = Kelas::findOrFail((string) $request->kelas_id);
            $semester = Semester::with('tahunAjaran')->findOrFail($semesterId);
            $bulan = (int) $request->bulan;
            
            $namaTA = $semester->tahunAjaran->nama;
            $tahunInput = $semester->tahun;
            $tahunAjaranFormatted = str_replace(['/', ' '], '_', $namaTA);

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

            $tanggal = $context['tanggal'];
            $carbonDate = Carbon::parse($tanggal);

            if ($carbonDate->isWeekend()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak dapat memuat daftar kelas: Hari Sabtu dan Minggu adalah hari libur.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $isLiburKalender = KalenderAkademik::where('semester_id', $context['semester']->id)
                ->where('kategori', 'Libur')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists();

            if ($isLiburKalender) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Tidak dapat memuat daftar kelas: Tanggal tersebut adalah hari libur di kalender akademik.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::where('is_active', 1)
                ->select('id', 'nama_kelas')
                ->withCount(['riwayatKelas as siswa_count' => function($q) use ($context) {
                    $q->where('semester_id', $context['semester']->id)
                      ->where('is_active', 1)
                      ->whereHas('siswa', function($sq) {
                          $sq->where('is_active', 1);
                      });
                }])
                ->orderBy('nama_kelas', 'asc')
                ->get();

            $data = $kelas->map(function ($item) use ($context, $tanggal) {
                $sudahAbsen = Presensi::where('tanggal', $tanggal)
                    ->where('kelas_id', (string) $item->id)
                    ->where('semester_id', $context['semester']->id)
                    ->exists();

                return [
                    'id' => (string) $item->id,
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
            $tanggal = $request->query('tanggal', date('Y-m-d'));
            $semesterId = $request->query('semester_id');
            $semester = $semesterId ? Semester::find($semesterId) : Semester::where('is_active', true)->first();

            if (!$semester) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $carbonDate = Carbon::parse($tanggal);
            
            $header = Presensi::whereDate('tanggal', $tanggal)
                ->where('kelas_id', (string) $kelas_id)
                ->where('semester_id', $semester->id)
                ->first();

            if (!$header) {
                if ($carbonDate->isWeekend()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat memuat siswa: Hari ' . $carbonDate->locale('id')->dayName . ' adalah hari libur.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $liburKalender = KalenderAkademik::where('semester_id', $semester->id)
                    ->where('kategori', 'Libur')
                    ->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal)
                    ->first();

                if ($liburKalender) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak dapat memuat siswa: Tanggal tersebut adalah hari libur (' . ($liburKalender->keterangan ?? 'Kalender Akademik') . ').',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $querySiswa = Siswa::query();

            if ($header) {
                $querySiswa->whereHas('presensiDetail', function($q) use ($header) {
                    $q->where('presensi_id', $header->id);
                });
            } else {
                $querySiswa->whereHas('riwayatKelas', function($q) use ($kelas_id, $semester) {
                    $q->where('kelas_id', (string) $kelas_id)
                      ->where('semester_id', $semester->id)
                      ->where('is_active', 1);
                })->where('is_active', 1);
            }

            $siswa = $querySiswa->with(['presensiDetail' => function($q) use ($header) {
                if ($header) {
                    $q->where('presensi_id', $header->id);
                } else {
                    $q->whereRaw('1 = 0'); 
                }
            }])
            ->orderBy('nama_lengkap', 'asc')
            ->get();

            $collection = $siswa->map(fn($item) => [
                'siswa_id'   => $item->id,
                'nama'       => $item->nama_lengkap,
                'nisn'       => $item->nisn,
                'status'     => $item->presensiDetail->first()?->status ?? null,
                'keterangan' => $item->presensiDetail->first()?->keterangan ?? null,
                'is_active'  => $item->is_active
            ]);

            return response()->json([
                'success' => true, 
                'info'    => [
                    'kelas'           => Kelas::find((string) $kelas_id)?->nama_kelas,
                    'tanggal'         => $carbonDate->format('Y-m-d'),
                    'hari'            => $carbonDate->locale('id')->dayName,
                    'semester'        => $semester->nama,
                    'mode'            => $header ? 'Histori Jurnal' : 'Presensi Harian',
                    'sudah_isi_absen' => $header ? true : false,
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
            $tanggalInput = $request->query('tanggal', date('Y-m-d'));
            $requestKelasId = (string) $request->query('kelas_id');

            $this->validateTanggalPresensi($tanggalInput, $semesterActive->id);

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [];
            
            $this->validateSiswaPresensi($dataInput, $requestKelasId, $semesterActive->id);

            $result = DB::transaction(function () use ($dataInput, $tanggalInput, $semesterActive, $requestKelasId, $request) {
                $waliKelasRecord = $this->getWaliKelasOrFail($requestKelasId, $semesterActive->id);
                
                $presensiHeader = Presensi::updateOrCreate(
                    [
                        'tanggal' => $tanggalInput, 
                        'kelas_id' => $requestKelasId, 
                        'semester_id' => $semesterActive->id
                    ],
                    [
                        'kelas_wali_id' => $waliKelasRecord->id,
                        'guru_id' => $waliKelasRecord->guru_staf_id,
                        'kegiatan' => $request->kegiatan,
                        'materi' => $request->materi,
                        'jam_mulai' => $request->jam_mulai,
                        'jam_selesai' => $request->jam_selesai,
                    ]
                );

                foreach ($dataInput as $item) {
                    PresensiDetail::updateOrCreate(
                        ['presensi_id' => $presensiHeader->id, 'siswa_id' => $item['siswa_id']],
                        ['status' => $item['status'], 'keterangan' => $item['keterangan'] ?? 'Diinput Admin: ' . Auth::user()->username]
                    );
                }

                return $presensiHeader->load(['semester', 'kelas', 'guru', 'details.siswa']);
            });

            return response()->json([
                'data' => $this->formatPresensiResponse($result),
                'success' => true,
                'message' => 'Presensi berhasil disimpan.'
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validateTanggalPresensi($tanggal, $semesterId): void
    {
        $carbonDate = Carbon::parse($tanggal);

        if ($carbonDate->isWeekend()) {
            throw new \Exception('Tidak dapat melakukan presensi pada hari libur (Sabtu/Minggu).');
        }

        $isLiburKalender = KalenderAkademik::where('semester_id', $semesterId)
            ->where('kategori', 'Libur')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->exists();

        if ($isLiburKalender) {
            throw new \Exception('Tanggal tersebut adalah libur di kalender akademik.');
        }
    }

    private function validateSiswaPresensi(array $dataInput, string $kelasId, $semesterId): void
    {
        $inputSiswaIds = collect($dataInput)->pluck('siswa_id')->toArray();

        $totalSiswaSeharusnya = SiswaKelas::where('kelas_id', $kelasId)
            ->where('semester_id', $semesterId)
            ->where('is_active', 1)
            ->whereHas('siswa', function($q) {
                $q->where('is_active', 1);
            })
            ->count();

        if (count($dataInput) < $totalSiswaSeharusnya) {
            throw new \Exception('Gagal simpan: Seluruh siswa di kelas ini wajib diisi status presensinya.');
        }

        $siswaNonAktif = Siswa::whereIn('id', $inputSiswaIds)->where('is_active', 0)->pluck('nama_lengkap')->toArray();
        if (!empty($siswaNonAktif)) {
            throw new \Exception('Gagal simpan: Siswa berikut sudah tidak aktif: ' . implode(', ', $siswaNonAktif));
        }

        $siswaLuarKelas = [];
        foreach ($inputSiswaIds as $sId) {
            $isMember = SiswaKelas::where('siswa_id', $sId)
                ->where('kelas_id', $kelasId)
                ->where('semester_id', $semesterId)
                ->where('is_active', 1)
                ->exists();
            
            if (!$isMember) {
                $namaSiswa = Siswa::find($sId)?->nama_lengkap ?? $sId;
                $siswaLuarKelas[] = $namaSiswa;
            }
        }

        if (!empty($siswaLuarKelas)) {
            throw new \Exception('Gagal simpan: Siswa berikut tidak terdaftar di kelas ini: ' . implode(', ', $siswaLuarKelas));
        }
    }

    private function getWaliKelasOrFail(string $kelasId, $semesterId)
    {
        $waliKelasRecord = KelasWaliKelas::where('kelas_id', $kelasId)
            ->where('semester_id', $semesterId)
            ->where('is_active', 1)
            ->first();
        
        if (!$waliKelasRecord) {
            throw new \Exception('Data Wali Kelas aktif tidak ditemukan.');
        }

        return $waliKelasRecord;
    }

    private function formatPresensiResponse($result): array
    {
        return [
            'id' => $result->id,
            'tanggal' => Carbon::parse($result->tanggal)->format('Y-m-d'),
            'semester' => [
                'id' => $result->semester->id,
                'nama' => $result->semester->nama,
                'tahun_ajaran_id' => $result->semester->tahun_ajaran_id,
                'tahun' => $result->semester->tahun
            ],
            'rincian_siswa' => $result->details->map(fn($d) => [
                'id' => $d->id,
                'siswa_id' => $d->siswa_id,
                'nama_siswa' => $d->siswa->nama_lengkap,
                'status' => $d->status,
                'catatan' => $d->keterangan
            ]),
            'created_at' => $result->created_at->format('d-m-Y H:i'),
            'updated_at' => $result->updated_at->format('d-m-Y H:i')
        ];
    }

    public function update(UpdatePresensiRequest $request, $presensiId): JsonResponse
    {
        $presensiHeader = Presensi::with('semester')->find($presensiId);

        if (!$presensiHeader) {
            return response()->json(['success' => false, 'message' => 'Data presensi tidak ditemukan.'], Response::HTTP_NOT_FOUND);
        }

        if (!$presensiHeader->semester || $presensiHeader->semester->is_active != 1) {
            return response()->json(['success' => false, 'message' => 'Akses Ditolak: Data presensi pada semester yang sudah tidak aktif tidak dapat diubah.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('update', $presensiHeader);

        try {
            $result = DB::transaction(function () use ($request, $presensiHeader) {
                $presensiHeader->update($request->only(['kegiatan', 'materi', 'jam_mulai', 'jam_selesai']));

                if ($request->has('data_presensi')) {
                    foreach ($request->data_presensi as $item) {
                        $detail = PresensiDetail::where('presensi_id', $presensiHeader->id)
                            ->where('siswa_id', $item['siswa_id'])
                            ->first();

                        if ($detail) {
                            $detail->update([
                                'status' => $item['status'],
                                'keterangan' => $item['keterangan'] ?? 'Diupdate Admin: ' . Auth::user()->username
                            ]);
                        }
                    }
                }

                return $presensiHeader->load(['semester', 'kelas', 'guru', 'details.siswa']);
            });

            $responseData = [
                'id' => $result->id,
                'tanggal' => Carbon::parse($result->tanggal)->format('Y-m-d'),
                'semester' => [
                    'id' => $result->semester->id,
                    'nama' => $result->semester->nama,
                    'tahun_ajaran_id' => $result->semester->tahun_ajaran_id,
                    'tahun' => $result->semester->tahun
                ],
                'rincian_siswa' => $result->details->map(fn($d) => [
                    'id' => $d->id,
                    'siswa_id' => $d->siswa_id,
                    'nama_siswa' => $d->siswa->nama_lengkap,
                    'status' => $d->status,
                    'catatan' => $d->keterangan
                ]),
                'created_at' => $result->created_at->format('d-m-Y H:i'),
                'updated_at' => $result->updated_at->format('d-m-Y H:i')
            ];

            return response()->json([
                'data' => $responseData,
                'success' => true,
                'message' => 'Data presensi berhasil diperbarui.'
            ], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            Log::error('Update Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $presensi = Presensi::findOrFail($id);
            $this->authorize('delete', $presensi);

            if ($presensi->semester->is_active != 1) {
                return response()->json(['success' => false, 'message' => 'Gagal: Data presensi pada semester yang tidak aktif tidak dapat dihapus.'], Response::HTTP_FORBIDDEN);
            }

            DB::transaction(function () use ($presensi) {
                PresensiDetail::where('presensi_id', $presensi->id)->delete();
                $presensi->delete();
            });

            return response()->json(['success' => true, 'message' => 'Data presensi berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Admin Presensi Destroy Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function applyContext(Request $request): array
    {
        $semester = Semester::where('is_active', true)->first();
        $tanggal = $request->query('tanggal', date('Y-m-d'));
        return ['semester' => $semester, 'tanggal' => $tanggal, 'hari' => Carbon::parse($tanggal)->locale('id')->dayName];
    }

    private function applyPaginationResponse($collection, Request $request, array $context): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $currentPage = (int) $request->query('page', 1);
        $paginator = new LengthAwarePaginator(
            $collection->forPage($currentPage, $perPage)->values(),
            $collection->count(), $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return response()->json([
            'success' => true,
            'info' => [
                'tanggal' => Carbon::parse($context['tanggal'])->format('Y-m-d'), 
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
                'last' => $paginator->url($paginator->lastPage()), 
                'prev' => $paginator->previousPageUrl(), 
                'next' => $paginator->nextPageUrl()
            ],
        ], Response::HTTP_OK);
    }
}