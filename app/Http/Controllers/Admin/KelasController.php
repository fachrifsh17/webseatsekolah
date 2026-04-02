<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Kelas, Jurusan, Semester, ProfilSekolah, DataKontak, Tingkatan};
use App\Http\Requests\{StoreKelasRequest, UpdateKelasRequest};
use App\Http\Resources\KelasResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use App\Imports\KelasImport;

class KelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import', 'bulkDelete']);
        
        $this->authorizeResource(Kelas::class, 'kelas');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $semesterAktif = Semester::where('is_active', true)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            $query = Kelas::with([
                'jurusan', 
                'tingkatan',
                'waliKelas' => function($q) use ($semesterId) {
                    if ($semesterId) {
                        $q->wherePivot('semester_id', $semesterId);
                    }
                }
            ])
            ->withCount(['siswa as siswa_count' => function($q) use ($semesterId) {
                $q->where('siswa_kelas.is_active', true);
                if ($semesterId) {
                    $q->where('siswa_kelas.semester_id', $semesterId);
                }
            }]);

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            $filters = ['jurusan_id', 'tingkatan_id'];
            foreach ($filters as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->{$filter});
                }
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            } else {
                $query->where('is_active', true);
            }

            $perPage = $request->query('per_page', 10);
            $kelas = $query->orderBy('nama_kelas', 'asc')->paginate($perPage);
            $paginationData = $kelas->toArray();

            return response()->json([
                'success' => true,
                'data'    => KelasResource::collection($kelas),
                'meta'    => [
                    'current_page'  => $kelas->currentPage(),
                    'last_page'     => $kelas->lastPage(),
                    'per_page'      => $kelas->perPage(),
                    'total'         => $kelas->total(),
                    'from'          => $kelas->firstItem(),
                    'to'            => $kelas->lastItem(),
                    'next_page_url' => $kelas->nextPageUrl(),
                    'prev_page_url' => $kelas->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kelas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Kelas::class);

        try {
            $filters = $request->only(['search', 'jurusan_id', 'tingkatan_id', 'semester_id']);
            
            $isActive = true;
            if ($request->has('is_active')) {
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }
            $filters['is_active'] = $isActive;
            $filters['identitas_laporan'] = 'SEMUA JURUSAN';

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $fileNameParts = ['DATA_KELAS'];

            if ($request->filled('search')) {
                $fileNameParts[] = strtoupper(str_replace(' ', '_', $request->search));
            }

            if ($request->filled('tingkatan_id')) {
                $tingkatan = Tingkatan::find($request->tingkatan_id);
                if ($tingkatan) {
                    $fileNameParts[] = strtoupper(str_replace(' ', '_', $tingkatan->nama_tingkatan));
                }
            }

            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) {
                    $filters['identitas_laporan'] = 'JURUSAN ' . strtoupper($jurusan->nama_jurusan);
                    $fileNameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
                }
            }

            $semester = $request->filled('semester_id') 
                ? Semester::with('tahunAjaran')->find($request->semester_id) 
                : Semester::with('tahunAjaran')->where('is_active', true)->first();

            if ($semester) {
                $filters['semester_id'] = $semester->id;
                $taName = str_replace(['/', ' '], '_', $semester->tahunAjaran->nama);
                $semName = strtoupper($semester->nama);
                $fileNameParts[] = "{$taName}_{$semName}";
            }

            $fileNameParts[] = $isActive ? 'AKTIF' : 'NON_AKTIF';
            $fileName = implode('_', $fileNameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new KelasExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Kelas Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function importPreview(Request $request): JsonResponse
    {
        $this->authorize('create', Kelas::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $rows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function headingRow(): int { return 1; }
            }, $request->file('file'))[0];

            $previewData = [];

            foreach ($rows as $row) {
                $namaKelas = isset($row['nama_kelas']) ? trim((string)$row['nama_kelas']) : null;
                $inputJurusan = isset($row['jurusan']) ? trim((string)$row['jurusan']) : null;
                $inputTingkatan = isset($row['tingkatan']) ? trim((string)$row['tingkatan']) : null;

                $isDuplicate = false;
                if ($namaKelas) {
                    $isDuplicate = Kelas::where('nama_kelas', $namaKelas)->exists();
                }

                $jurusanFound = false;
                if ($inputJurusan) {
                    $jurusanFound = Jurusan::where(function($q) use ($inputJurusan) {
                        $q->where('id', $inputJurusan)
                          ->orWhere('nama_jurusan', 'LIKE', '%' . $inputJurusan . '%');
                    })
                    ->where('is_active', 1)
                    ->exists();
                }

                $tingkatanFound = false;
                if ($inputTingkatan) {
                    $tingkatanFound = Tingkatan::where('nama_tingkatan', $inputTingkatan)->exists();
                }

                $row['is_duplicate'] = $isDuplicate;
                $row['jurusan_found'] = $jurusanFound;
                $row['tingkatan_found'] = $tingkatanFound;
                $row['is_valid'] = !empty($namaKelas) && $jurusanFound && $tingkatanFound;
                
                $previewData[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => $previewData
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Preview Import Kelas Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses preview file.',
                'errors' => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Kelas::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $import = new KelasImport;
            Excel::import($import, $request->file('file'));
            $skippedMessages = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => 'Proses impor selesai.',
                'info'    => $skippedMessages, 
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Kelas Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json(['success' => false, 'message' => 'Gagal: Jurusan tidak aktif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (Kelas::where('nama_kelas', $validated['nama_kelas'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah terdaftar.'], Response::HTTP_CONFLICT);
        }

        $validated['is_active'] = true;

        try {
            $kelas = DB::transaction(fn() => Kelas::create($validated));
            
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tingkatan', 'waliKelas'])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create kelas', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        $semesterAktif = Semester::where('is_active', true)->first();

        return response()->json([
            'success' => true,
            'data'    => new KelasResource($kelas->load([
                'jurusan', 
                'tingkatan', 
                'waliKelas' => fn($q) => $q->wherePivot('semester_id', $semesterAktif?->id)
            ])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
        ], Response::HTTP_OK);
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json(['success' => false, 'message' => 'Gagal: Jurusan tidak aktif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!empty($validated['nama_kelas'])) {
            if (Kelas::where('nama_kelas', $validated['nama_kelas'])->where('id', '!=', $kelas->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah digunakan.'], Response::HTTP_CONFLICT);
            }
        }

        if ($request->filled('is_active')) {
            $validated['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        try {
            DB::transaction(fn() => $kelas->update($validated));
            
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tingkatan', 'waliKelas'])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        try {
            if ($this->checkDependencies($kelas)) {
                return response()->json(['success' => false, 'message' => 'Gagal: Kelas memiliki ketergantungan data.'], Response::HTTP_CONFLICT);
            }

            DB::transaction(fn() => $kelas->delete());
            return response()->json(['success' => true, 'message' => 'Data kelas berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('delete', Kelas::class);
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'string'])['ids'];

        try {
            $deletedCount = 0;
            $skippedCount = 0;

            DB::transaction(function () use ($ids, &$deletedCount, &$skippedCount) {
                foreach ($ids as $id) {
                    $kelas = Kelas::find($id);
                    if ($kelas && !$this->checkDependencies($kelas)) {
                        $kelas->delete();
                        $deletedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus $deletedCount data. $skippedCount data dilewati karena memiliki relasi.",
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus massal data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function checkDependencies(Kelas $kelas): bool
    {
        return $kelas->siswa()->exists() || 
               $kelas->presensiGuruMapel()->exists() || 
               $kelas->guruMapel()->exists() || 
               $kelas->riwayatKelas()->exists() ||
               $kelas->presensi()->exists() ||
               $kelas->waliKelas()->exists();
    }
}