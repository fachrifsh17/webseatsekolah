<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, PresensiDetail, Siswa, Kelas, Semester, KalenderAkademik, KelasWaliKelas};
use App\Http\Requests\{StorePresensiRequest, UpdatePresensiRequest};
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

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

        try {
            $semesterId = $request->semester_id ?? Semester::where('is_active', true)->first()?->id;

            if (!$semesterId) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $query = Presensi::with(['kelas', 'semester', 'guru'])
                ->where('semester_id', $semesterId)
                ->where('guru_id', $guruId);

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
            Log::error('Walikelas Presensi Index Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);
        $guruId = $this->getGuruId();

        try {
            if (!$request->filled('kelas_id') || !$request->filled('bulan')) {
                return response()->json(['success' => false, 'message' => 'Kelas dan Bulan wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $semesterId = $request->semester_id ?? Semester::where('is_active', true)->first()?->id;

            if (!$semesterId) {
                return response()->json(['success' => false, 'message' => 'Semester aktif tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $isWali = DB::table('kelas_wali_kelas')
                ->where('kelas_id', (string) $request->kelas_id)
                ->where('guru_staf_id', $guruId)
                ->where('semester_id', $semesterId)
                ->where('is_active', 1)
                ->exists();

            if (!$isWali) {
                return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki riwayat sebagai Wali Kelas di kelas ini.'], 403);
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
                    "walikelas", 
                    $semester
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Walikelas Export Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh file: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function listSiswaPresensi(Request $request, $kelas_id): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);
        $guruId = $this->getGuruId();

        try {
            $tanggal = $request->query('tanggal', date('Y-m-d'));
            $semesterId = $request->query('semester_id');
            $semester = $semesterId ? Semester::find($semesterId) : Semester::where('is_active', true)->first();

            if (!$semester) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            if (!$this->checkIsWali($kelas_id, $guruId, $semester->id)) {
                return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda tidak memiliki riwayat sebagai Wali Kelas di kelas ini.'], 403);
            }

            $kelas = Kelas::findOrFail((string) $kelas_id);
            $carbonDate = Carbon::parse($tanggal);
            
            $header = Presensi::whereDate('tanggal', $tanggal)
                ->where('kelas_id', (string) $kelas_id)
                ->where('semester_id', $semester->id)
                ->first();

            $errorLibur = $this->validateHariLibur($header, $carbonDate, $tanggal, $semester->id);
            if ($errorLibur) {
                return $errorLibur;
            }

            $siswa = $this->getSiswaPresensi($header, $kelas_id, $semester);
            $collection = $this->formatListSiswa($siswa, $header);

            return response()->json([
                'success' => true, 
                'info'    => [
                    'kelas'           => $kelas->nama_kelas,
                    'tanggal'         => $carbonDate->format('Y-m-d'),
                    'hari'            => $carbonDate->locale('id')->dayName,
                    'semester'        => $semester->nama,
                    'mode'            => $header ? 'Histori Jurnal' : 'Presensi Harian',
                    'sudah_isi_absen' => $header ? true : false,
                ],
                'data'    => $collection
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Walikelas List Siswa Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal memuat siswa.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function checkIsWali($kelas_id, $guruId, $semesterId)
    {
        return DB::table('kelas_wali_kelas')
            ->where('kelas_id', (string) $kelas_id)
            ->where('guru_staf_id', $guruId)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    private function validateHariLibur($header, $carbonDate, $tanggal, $semesterId)
    {
        if (!$header) {
            if ($carbonDate->isWeekend()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat memuat siswa: Hari ' . $carbonDate->locale('id')->dayName . ' adalah hari libur.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $liburKalender = KalenderAkademik::where('semester_id', $semesterId)
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
        return null;
    }

    private function getSiswaPresensi($header, $kelas_id, $semester)
    {
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

        return $querySiswa->with(['presensiDetail' => function($q) use ($header) {
            if ($header) {
                $q->where('presensi_id', $header->id);
            } else {
                $q->whereRaw('1 = 0'); 
            }
        }])
        ->orderBy('nama_lengkap', 'asc')
        ->get();
    }

    private function formatListSiswa($siswa, $header)
    {
        return $siswa->map(fn($item) => [
            'siswa_id'   => $item->id,
            'nama'       => $item->nama_lengkap,
            'nisn'       => $item->nisn,
            'status'     => $item->presensiDetail->first()?->status ?? null,
            'keterangan' => $item->presensiDetail->first()?->keterangan ?? null,
            'is_active'  => $item->is_active
        ]);
    }
    public function store(StorePresensiRequest $request): JsonResponse
    {
        $this->authorize('create', Presensi::class);
        $guruId = $this->getGuruId();

        try {
            $currentTime = Carbon::now();
            $startTime = Carbon::createFromTimeString('06:30:00');
            $endTime = Carbon::createFromTimeString('10:00:00');

            if (!$currentTime->between($startTime, $endTime)) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses Ditolak: Presensi hanya dapat diisi antara pukul 06:30 sampai 10:00 WIB.'
                ], Response::HTTP_FORBIDDEN);
            }

            $semesterActive = Semester::where('is_active', true)->firstOrFail();
            $tanggalInput = $request->query('tanggal', date('Y-m-d'));
            $requestKelasId = (string) $request->input('kelas_id');
            $carbonDate = Carbon::parse($tanggalInput);

            if ($carbonDate->isFuture()) {
                return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda tidak dapat mengisi presensi untuk tanggal di masa depan.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($carbonDate->isWeekend()) {
                return response()->json(['success' => false, 'message' => 'Tidak dapat melakukan presensi pada hari libur.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $isLiburKalender = KalenderAkademik::where('semester_id', $semesterActive->id)
                ->where('kategori', 'Libur')
                ->whereDate('tanggal_mulai', '<=', $tanggalInput)
                ->whereDate('tanggal_selesai', '>=', $tanggalInput)
                ->exists();

            if ($isLiburKalender) {
                return response()->json(['success' => false, 'message' => 'Tanggal tersebut adalah libur di kalender akademik.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $waliKelasRecord = KelasWaliKelas::where('kelas_id', $requestKelasId)
                ->where('semester_id', $semesterActive->id)
                ->where('guru_staf_id', $guruId)
                ->where('is_active', 1)
                ->first();

            if (!$waliKelasRecord) {
                return response()->json(['success' => false, 'message' => 'Akses Ditolak: Anda bukan Wali Kelas aktif di kelas ini pada semester berjalan.'], 403);
            }

            $dataInput = $request->has('data_presensi') ? $request->input('data_presensi') : [$request->all()];
            $inputSiswaIds = collect($dataInput)->pluck('siswa_id')->toArray();

            $siswaAktifIds = Siswa::whereHas('riwayatKelas', function($q) use ($requestKelasId, $semesterActive) {
                    $q->where('kelas_id', $requestKelasId)
                      ->where('semester_id', $semesterActive->id)
                      ->where('is_active', 1);
                })
                ->where('is_active', 1)
                ->pluck('id')
                ->toArray();

            foreach ($dataInput as $item) {
                $siswa = Siswa::find($item['siswa_id']);
                if (!$siswa) {
                    return response()->json(['success' => false, 'message' => "Gagal: Siswa dengan ID {$item['siswa_id']} tidak ditemukan."], 422);
                }
                if ($siswa->is_active != 1) {
                    return response()->json(['success' => false, 'message' => "Gagal: Siswa {$siswa->nama_lengkap} sudah tidak aktif, jangan tembak data."], 422);
                }
                $isMemberKelas = in_array($siswa->id, $siswaAktifIds);
                if (!$isMemberKelas) {
                    return response()->json(['success' => false, 'message' => "Gagal: Siswa {$siswa->nama_lengkap} tidak terdaftar di kelas ini."], 422);
                }
            }

            $missingSiswaIds = array_diff($siswaAktifIds, $inputSiswaIds);
            if (!empty($missingSiswaIds)) {
                $names = Siswa::whereIn('id', $missingSiswaIds)->pluck('nama_lengkap')->implode(', ');
                return response()->json(['success' => false, 'message' => "Gagal: Semua siswa aktif wajib diisi presensinya. Siswa yang belum: {$names}"], 422);
            }
            
            DB::transaction(function () use ($dataInput, $tanggalInput, $semesterActive, $requestKelasId, $request, $guruId, $waliKelasRecord) {
                $presensiHeader = Presensi::updateOrCreate(
                    [
                        'tanggal' => $tanggalInput, 
                        'kelas_id' => $requestKelasId, 
                        'semester_id' => $semesterActive->id
                    ],
                    [
                        'kelas_wali_id' => $waliKelasRecord->id,
                        'guru_id'     => $guruId,
                        'kegiatan'    => $request->kegiatan,
                        'materi'      => $request->materi,
                        'jam_mulai'   => $request->jam_mulai,
                        'jam_selesai' => $request->jam_selesai,
                    ]
                );

                foreach ($dataInput as $item) {
                    PresensiDetail::updateOrCreate(
                        ['presensi_id' => $presensiHeader->id, 'siswa_id' => $item['siswa_id']],
                        ['status' => $item['status'], 'keterangan' => $item['keterangan'] ?? 'Diinput Wali Kelas: ' . Auth::user()->username]
                    );
                }
            });

            return response()->json(['success' => true, 'message' => 'Presensi berhasil disimpan.'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Walikelas Store Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, $presensiId): JsonResponse
    {
        $guruId = $this->getGuruId();
        $presensiHeader = Presensi::with('semester')
            ->where('id', $presensiId)
            ->where('guru_id', $guruId)
            ->first();

        if (!$presensiHeader) {
            return response()->json(['success' => false, 'message' => 'Data presensi tidak ditemukan atau Anda tidak memiliki akses.'], Response::HTTP_NOT_FOUND);
        }

        if (!$presensiHeader->semester || $presensiHeader->semester->is_active != 1) {
            return response()->json(['success' => false, 'message' => 'Akses Ditolak: Data presensi pada semester tidak aktif tidak dapat diubah.'], Response::HTTP_FORBIDDEN);
        }

        $this->authorize('update', $presensiHeader);

        try {
            DB::beginTransaction();

            $presensiHeader->update($request->only(['kegiatan', 'materi', 'jam_mulai', 'jam_selesai']));

            if ($request->has('data_presensi')) {
                foreach ($request->data_presensi as $item) {
                    $siswa = Siswa::find($item['siswa_id']);
                    if (!$siswa || $siswa->is_active != 1) {
                        return response()->json(['success' => false, 'message' => "Gagal: Siswa " . ($siswa->nama_lengkap ?? $item['siswa_id']) . " tidak aktif atau tidak ditemukan."], 422);
                    }

                    $detail = PresensiDetail::where('presensi_id', $presensiHeader->id)
                        ->where('siswa_id', $item['siswa_id'])
                        ->first();

                    if ($detail) {
                        $detail->update([
                            'status' => $item['status'],
                            'keterangan' => $item['keterangan'] ?? 'Diupdate Wali Kelas: ' . Auth::user()->username
                        ]);
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data presensi berhasil diperbarui.'], Response::HTTP_OK);
            
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Walikelas Update Presensi Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}