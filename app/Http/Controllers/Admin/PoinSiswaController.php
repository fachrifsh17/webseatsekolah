<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{PoinSiswa, Semester, Siswa, Kelas};
use App\Http\Requests\{StorePoinSiswaRequest, UpdatePoinSiswaRequest};
use App\Http\Resources\PoinSiswaResource;
use App\Exports\PoinSiswaExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Auth, Log};
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PoinSiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(PoinSiswa::class, 'poin_siswa');
    }

    private function getPoinWithKumulatif($id)
    {
        return PoinSiswa::with([
                'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                'guruStaf', 
                'semester'
            ])
            ->select('poin_siswa.*')
            ->addSelect([
                'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_positif)'),
                'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                    ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                    ->selectRaw('SUM(poin_negatif)')
            ])
            ->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $semesterActive = Semester::where('is_active', true)->first();
            
            $query = PoinSiswa::with([
                    'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                    'guruStaf', 
                    'semester'
                ])
                ->select('poin_siswa.*')
                ->addSelect([
                    'total_kumulatif_positif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_positif)'),
                    'total_kumulatif_negatif' => DB::table('poin_siswa as ps')
                        ->whereColumn('ps.siswa_id', 'poin_siswa.siswa_id')
                        ->selectRaw('SUM(poin_negatif)')
                ]);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }

            if ($request->filled('semester_id')) {
                $query->where('semester_id', $request->semester_id);
            } else {
                if ($semesterActive) {
                    $query->where('semester_id', $semesterActive->id);
                }
            }

            if ($request->filled('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }
            
            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $query->whereMonth('tanggal', date('m', $time))
                      ->whereYear('tanggal', date('Y', $time));
            } elseif ($semesterActive && !$request->filled('semester_id')) {
                $isGanjil = stripos($semesterActive->nama, 'Ganjil') !== false;
                if ($isGanjil) {
                    $query->whereMonth('tanggal', '>=', 7)
                          ->whereMonth('tanggal', '<=', 12);
                } else {
                    $query->where(function($q) {
                        $q->whereMonth('tanggal', '>=', 1)
                          ->whereMonth('tanggal', '<=', 6);
                    });
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', function($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%");
                });
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderByDesc('total_kumulatif_negatif')
                          ->orderByDesc('tanggal')
                          ->paginate($perPage);

            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data' => PoinSiswaResource::collection($data),
                'meta' => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                    'from'          => $data->firstItem(),
                    'to'            => $data->lastItem(),
                    'next_page_url' => $data->nextPageUrl(),
                    'prev_page_url' => $data->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Fetch Poin Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengambil data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StorePoinSiswaRequest $request): JsonResponse
    {
        try {
            $semester = Semester::where('is_active', true)->firstOrFail();
            $user = Auth::user()->load('guruStaf');

            $siswa = Siswa::where('id', $request->siswa_id)
                ->where('is_active', true)
                ->whereHas('riwayatKelas', function($q) use ($semester) {
                    $q->where('semester_id', $semester->id)
                      ->where('is_active', true);
                })
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan atau tidak aktif di semester berjalan.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $poin = DB::transaction(function () use ($request, $semester, $user, $siswa) {
                $guruStafId = $request->guru_staf_id ?? ($user->guruStaf ? $user->guruStaf->id : null);
                $riwayatAktif = $siswa->riwayatKelas()->where('semester_id', $semester->id)->where('is_active', true)->first();
                $kelasId = $riwayatAktif ? $riwayatAktif->kelas_id : null;

                return PoinSiswa::create(array_merge($request->validated(), [
                    'tanggal' => now()->toDateString(),
                    'semester_id' => $semester->id,
                    'guru_staf_id' => $guruStafId,
                    'kelas_id' => $kelasId
                ]));
            });

            return response()->json([
                'success' => true,
                'message' => 'Poin berhasil dicatat.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poin->id))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mencatat poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(PoinSiswa $poinSiswa): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePoinSiswaRequest $request, PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->update($request->validated()));
            
            return response()->json([
                'success' => true,
                'message' => 'Data poin berhasil diperbarui.',
                'data' => new PoinSiswaResource($this->getPoinWithKumulatif($poinSiswa->id))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(PoinSiswa $poinSiswa): JsonResponse
    {
        try {
            DB::transaction(fn() => $poinSiswa->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Data poin berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Poin Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus data poin.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', PoinSiswa::class);

            if (!$request->filled('semester_id')) {
                return response()->json(['success' => false, 'message' => 'Semester wajib dipilih.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $semesterObj = Semester::with('tahunAjaran')->findOrFail($request->semester_id);
            
            $namaSemester = strtoupper($semesterObj->nama);
            $namaTA = $semesterObj->tahunAjaran->nama ?? "-";

            $namaKelasLaporan = 'SELURUH SISWA';
            if ($request->filled('kelas_id')) {
                $kelasObj = Kelas::find($request->kelas_id);
                $namaKelasLaporan = $kelasObj ? $kelasObj->nama_kelas : $request->kelas_id;
            }

            $labelWaktu = "KUMULATIF";
            $bulanStr = "";
            $inputMonth = null;
            $inputYear = null;
            
            if ($request->filled('bulan')) {
                $time = strtotime($request->bulan);
                $inputMonth = date('m', $time);
                $inputYear = date('Y', $time);
                $labelWaktu = date('F Y', $time);
                $bulanStr = "_BULAN_" . $inputMonth;
            }

            $isGanjil = stripos($namaSemester, 'Ganjil') !== false;
            
            if ($inputMonth) {
                if ($isGanjil && !in_array((int)$inputMonth, [7, 8, 9, 10, 11, 12])) {
                    return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Ganjil (Juli - Desember).'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if (!$isGanjil && !in_array((int)$inputMonth, [1, 2, 3, 4, 5, 6])) {
                    return response()->json(['success' => false, 'message' => 'Bulan yang dipilih tidak masuk dalam periode Semester Genap (Januari - Juni).'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $semSlug = strtoupper(str_replace([' ', '/', '\\'], '_', $namaSemester));
            $taSlug = strtoupper(str_replace([' ', '/', '\\'], '_', $namaTA));
            $kelasSlug = strtoupper(str_replace([' ', '/', '\\'], '_', $namaKelasLaporan));
            $fileName = "REKAP_POIN{$bulanStr}_{$kelasSlug}_{$taSlug}_{$semSlug}.XLSX";

            $query = PoinSiswa::with([
                'siswa.riwayatKelas' => fn($q) => $q->with('kelas'),
                'guruStaf', 
                'semester'
            ]);

            if ($request->filled('kelas_id')) {
                $query->where('kelas_id', $request->kelas_id);
            }

            $query->where('semester_id', $semesterObj->id);

            if ($inputMonth && $inputYear) {
                $query->whereMonth('tanggal', $inputMonth)
                      ->whereYear('tanggal', $inputYear);
            } else {
                if ($isGanjil) {
                    $query->whereMonth('tanggal', '>=', 7)
                          ->whereMonth('tanggal', '<=', 12);
                } else {
                    $query->whereMonth('tanggal', '>=', 1)
                          ->whereMonth('tanggal', '<=', 6);
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->whereHas('siswa', function($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%");
                });
            }

            return Excel::download(
                new PoinSiswaExport(
                    $query->orderBy('tanggal', 'asc'), 
                    $namaKelasLaporan, 
                    $labelWaktu, 
                    $profil, 
                    $kontak, 
                    $namaTA,
                    $namaSemester
                ),
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Poin Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh laporan.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}