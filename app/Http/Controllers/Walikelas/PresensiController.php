<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, PresensiDetail, Siswa, Kelas, Semester};
use App\Http\Requests\{StorePresensiRequest, UpdatePresensiRequest};
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

        if (!$request->filled('semester_id') || !$request->filled('bulan')) {
            return response()->json([
                'success' => false,
                'message' => 'Semester dan Bulan wajib dipilih.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $semesterId = $request->semester_id;
            $bulan = (int) $request->bulan;
            $semesterData = Semester::findOrFail($semesterId);

            $isSemesterGanjil = strtolower($semesterData->type) === 'ganjil';
            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($isSemesterGanjil && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (!$isSemesterGanjil && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tahunFilter = $semesterData->tahun;

            $query = Kelas::query();
            $query->where('wali_kelas_id', $guruId);

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', "%{$request->search}%");
            }

            $kelasPaginated = $query->orderBy('nama_kelas', 'asc')->paginate(50);

            $dataCollection = $kelasPaginated->getCollection()->map(function ($kelas) use ($bulan, $tahunFilter, $semesterId) {

                $jumlahSiswa = DB::table('siswa_kelas')
                    ->where('kelas_id', $kelas->id)
                    ->where('semester_id', $semesterId)
                    ->count();

                $sudahAbsen = Presensi::where('kelas_id', $kelas->id)
                    ->where('semester_id', $semesterId)
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
            if (!$request->filled('kelas_id') || !$request->filled('bulan') || !$request->filled('semester_id')) {
                return response()->json(['success' => false, 'message' => 'Kelas, Bulan, dan Semester wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $kelas = Kelas::where('id', trim($request->kelas_id))
                ->where('wali_kelas_id', $guruId)
                ->firstOrFail();

            $semesterData = Semester::findOrFail($request->semester_id);
            $bulan = (int) $request->bulan;

            $tahunInput = $semesterData->tahun;
            $semesterType = strtolower($semesterData->type);

            $ganjilMonths = [7, 8, 9, 10, 11, 12];
            $genapMonths = [1, 2, 3, 4, 5, 6];

            if ($semesterType === 'ganjil' && !in_array($bulan, $ganjilMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($semesterType === 'genap' && !in_array($bulan, $genapMonths)) {
                return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();

            $namaKelasClean = str_replace([' ', '/'], '_', strtoupper($kelas->nama_kelas));
            $semesterClean = strtoupper($semesterData->name);

            $fileName = "REKAP_PRESENSI_{$namaKelasClean}_BULAN_{$bulan}_{$semesterClean}.xlsx";

            return Excel::download(
                new PresensiExport(
                    $semesterData->id,
                    $kelas->nama_kelas,
                    "Bulan-{$bulan}-Tahun-{$tahunInput}",
                    $profil,
                    $kontak,
                    $kelas,
                    "walikelas",
                    $semesterData
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
        $this->authorize('viewAny', Siswa::class);
        $guruId = $this->getGuruId();
        $cleanKelasId = trim($kelas_id);

        try {
            $kelas = Kelas::where('id', $cleanKelasId)
                ->where('wali_kelas_id', $guruId)
                ->where('is_active', 1)
                ->firstOrFail();

            $tanggal = $request->get('tanggal', date('Y-m-d'));

            $semesterId = $request->get('semester_id');
            $semester = $semesterId ? Semester::find($semesterId) : Semester::where('is_active', true)->first();

            if (!$semester) {
                return response()->json(['success' => false, 'message' => 'Semester tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswa = Siswa::whereHas('riwayatKelas', function ($q) use ($cleanKelasId, $semester) {
                $q->where('kelas_id', $cleanKelasId)
                    ->where('semester_id', $semester->id);
            })
                ->with(['presensiDetail' => function ($q) use ($tanggal, $semester) {
                    $q->whereHas('presensi', function ($query) use ($tanggal, $semester) {
                        $query->whereDate('tanggal', $tanggal)
                            ->where('semester_id', $semester->id);
                    });
                }])
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            if ($siswa->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data siswa tidak ditemukan pada kelas dan semester ini.',
                    'data' => []
                ], Response::HTTP_NOT_FOUND);
            }

            $collection = $siswa->map(fn($item) => [
                'siswa_id' => $item->id,
                'nama'    => $item->nama_lengkap,
                'nisn'    => $item->nisn,
                'status'  => $item->presensiDetail->first()?->status ?? null,
                'catatan'  => $item->presensiDetail->first()?->keterangan ?? null
            ]);

            return response()->json([
                'success' => true,
                'info'    => [
                    'kelas'          => $kelas->nama_kelas,
                    'tanggal'        => $tanggal,
                    'semester'        => $semester->name,
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
            $endAllowed = Carbon::createFromTime(16, 0, 0, 'Asia/Jakarta');

            if ($now->lt($startAllowed) || $now->gt($endAllowed)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Presensi hanya dapat diinput pada jam 06:30 hingga 16:00 WIB.'
                ], Response::HTTP_FORBIDDEN);
            }

            $semesterActive = Semester::where('is_active', true)->firstOrFail();
            $tanggalInput = $request->get('tanggal', date('Y-m-d'));
            
            $inputDate = Carbon::parse($tanggalInput);
            if ($inputDate->year != $semesterActive->tahun) {
                 return response()->json([
                    'success' => false,
                    'message' => "Tahun pada tanggal yang diinput ({$inputDate->year}) tidak sesuai dengan tahun ajaran semester berjalan ({$semesterActive->tahun})."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $requestKelasId = trim($request->input('kelas_id'));

            $kelas = Kelas::where('id', $requestKelasId)
                ->where('wali_kelas_id', $guruId)
                ->firstOrFail();

            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $requestKelasId)
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

            $siswaSalah = array_diff($siswaIdInput, $siswaIdWajib);

            if (count($siswaSalah) > 0) {
                $namaSiswaSalah = Siswa::whereIn('id', $siswaSalah)->pluck('nama_lengkap')->implode(', ');
                return response()->json([
                    'success' => false,
                    'message' => "Siswa berikut tidak terdaftar atau tidak aktif di kelas ini: [{$namaSiswaSalah}]."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($dataInput, $tanggalInput, $semesterActive, $requestKelasId, $guruId) {

                $presensiHeader = Presensi::updateOrCreate(
                    [
                        'tanggal' => $tanggalInput,
                        'kelas_id' => $requestKelasId,
                        'semester_id' => $semesterActive->id
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
            
            $semester = Semester::find($presensiHeader->semester_id);
            $inputDate = Carbon::parse($presensiHeader->tanggal);
            
            if ($inputDate->year != $semester->tahun) {
                 return response()->json([
                    'success' => false,
                    'message' => "Data tidak dapat diubah karena tahun presensi ({$inputDate->year}) tidak sesuai dengan tahun ajaran semester ({$semester->tahun})."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $siswaIdWajib = DB::table('siswa_kelas')
                ->where('kelas_id', $presensiHeader->kelas_id)
                ->where('semester_id', $presensiHeader->semester_id)
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
                    'message' => 'Beberapa siswa tidak terdaftar di kelas/semester ini.',
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
}