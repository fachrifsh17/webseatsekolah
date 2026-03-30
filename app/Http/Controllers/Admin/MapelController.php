<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, Jurusan, ProfilSekolah, DataKontak, Semester};
use App\Http\Resources\MapelResource;
use App\Http\Requests\{StoreMapelRequest, UpdateMapelRequest};
use App\Exports\MapelExport;
use App\Imports\MapelImport;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Http\{JsonResponse, Request};
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'destroyBulk', 'import']);
        
        $this->authorizeResource(MataPelajaran::class, 'mapel');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = MataPelajaran::with('jurusan');

            if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('jurusan_id')) {
                $query->where('jurusan_id', $request->jurusan_id);
            }

            if ($request->filled('tipe_mapel')) {
                $query->where('tipe_mapel', $request->tipe_mapel);
            }

            if ($request->filled('kategori_mapel')) {
                $query->where('kategori_mapel', $request->kategori_mapel);
            }

            if ($request->filled('search')) {
                $query->where('nama_mapel', 'like', "%{$request->search}%");
            }

            $perPage = $request->query('per_page', 12);
            $data = $query->latest()->paginate($perPage);
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => MapelResource::collection($data),
                'meta'    => [
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
            Log::error('Failed to fetch mata pelajaran', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function importPreview(Request $request): JsonResponse
    {
        $this->authorize('create', MataPelajaran::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $rows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function headingRow(): int { return 1; }
            }, $request->file('file'))[0];

            $previewData = [];
            $processedInThisFile = [];

            foreach ($rows as $index => $row) {
                $namaMapel = isset($row['nama_mata_pelajaran']) ? trim((string)$row['nama_mata_pelajaran']) : null;
                $jurusanNama = isset($row['jurusan']) ? trim((string)$row['jurusan']) : null;
                
                $jurusanId = null;
                $jurusanFound = true;

                if (!empty($jurusanNama) && strtoupper($jurusanNama) !== 'UMUM') {
                    $jurusan = Jurusan::where(function($q) use ($jurusanNama) {
                        $q->where('nama_jurusan', 'LIKE', '%' . $jurusanNama . '%')
                          ->orWhere('id', $jurusanNama);
                    })
                    ->where('is_active', 1)
                    ->first();

                    if ($jurusan) {
                        $jurusanId = $jurusan->id;
                    } else {
                        $jurusanFound = false;
                    }
                }

                $identifier = strtolower($namaMapel ?? '') . '|' . ($jurusanId ?? 'umum');
                
                $isDuplicateInternal = false;
                if (isset($processedInThisFile[$identifier])) {
                    $isDuplicateInternal = true;
                } else {
                    $processedInThisFile[$identifier] = $index + 2;
                }

                $isDuplicateDatabase = false;
                if ($namaMapel && $jurusanFound) {
                    $isDuplicateDatabase = MataPelajaran::where('nama_mapel', $namaMapel)
                        ->where(function($q) use ($jurusanId) {
                            return $jurusanId ? $q->where('jurusan_id', $jurusanId) : $q->whereNull('jurusan_id');
                        })->exists();
                }

                $row['jurusan_found'] = $jurusanFound;
                $row['is_duplicate_internal'] = $isDuplicateInternal;
                $row['is_duplicate_database'] = $isDuplicateDatabase;
                $row['is_valid'] = !empty($namaMapel) && $jurusanFound && !$isDuplicateInternal && !$isDuplicateDatabase;
                
                $previewData[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => $previewData
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Preview Import Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses preview file.',
                'errors' => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', MataPelajaran::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv,txt|max:2048']);

        try {
            $access = ['isFullAccess' => true, 'guruStaf' => null, 'namaJabatan' => 'Admin'];
            $import = new MapelImport($access);
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => empty($conflicts) ? 'Data mata pelajaran berhasil diimpor.' : 'Import selesai dengan catatan.',
                'conflicts' => $conflicts
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data mata pelajaran.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', MataPelajaran::class);

        try {
            $filters = $request->only(['search', 'jurusan_id', 'tipe_mapel', 'kategori_mapel']);
            
            if ($request->has('is_active') && $request->query('is_active') !== null && $request->query('is_active') !== '') {
                $filters['is_active'] = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 
            $activeSemester = Semester::with('tahunAjaran')->where('is_active', 1)->first();

            $filenameParts = ['DATA_MAPEL'];
            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
            } else {
                $filenameParts[] = 'SEMUA_JURUSAN';
            }

            if ($activeSemester) {
                $namaTa = strtoupper(str_replace([' ', '-', '/'], '_', $activeSemester->tahunAjaran->nama));
                $namaSemester = strtoupper(str_replace(' ', '_', $activeSemester->nama));
                $filenameParts[] = $namaTa;
                $filenameParts[] = $namaSemester;
            }

            if (isset($filters['is_active'])) {
                $filenameParts[] = ($filters['is_active'] == 1) ? 'AKTIF' : 'NON_AKTIF';
            } else {
                $filenameParts[] = 'SEMUA_STATUS';
            }

            $fileName = implode('_', $filenameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();
            return Excel::download(new MapelExport($filters, $profil, $kontak, $activeSemester), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (!empty($validated['jurusan_id'])) {
            if (!Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Jurusan tidak aktif.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])->where('jurusan_id', $validated['jurusan_id'] ?? null)->exists()) {
            return response()->json([
                'success' => false, 
                'message' => 'Nama mata pelajaran sudah terdaftar.'
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create(array_merge($validated, ['is_active' => 1])));
            return response()->json([
                'success' => true, 
                'data' => new MapelResource($item->load('jurusan'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal simpan data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(MataPelajaran $mapel): JsonResponse
    {
        return response()->json([
            'success' => true, 
            'data' => new MapelResource($mapel->load('jurusan'))
        ], Response::HTTP_OK);
    }

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $validated = $request->validated();
        if (!empty($validated['nama_mapel'])) {
            $exists = MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('jurusan_id', $validated['jurusan_id'] ?? $mapel->jurusan_id)
                ->where('id', '!=', $mapel->id)->exists();
            if ($exists) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Nama mapel sudah ada.'
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));
            return response()->json([
                'success' => true, 
                'data' => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal perbarui data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        try {
            if ($mapel->guruMapel()->exists() || $mapel->presensiGuruMapel()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menghapus. Data mata pelajaran ini sudah digunakan di modul Guru Mapel atau Presensi.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(fn() => $mapel->delete());
            return response()->json([
                'success' => true, 
                'message' => 'Data berhasil dihapus permanen.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal hapus data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroyBulk(Request $request): JsonResponse
    {
        $this->authorize('delete', MataPelajaran::class);
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:mata_pelajaran,id']);

        try {
            $ids = $request->ids;
            $cannotDelete = [];
            $canDelete = [];

            foreach ($ids as $id) {
                $mapel = MataPelajaran::find($id);
                if ($mapel->guruMapel()->exists() || $mapel->presensiGuruMapel()->exists()) {
                    $cannotDelete[] = $mapel->nama_mapel;
                } else {
                    $canDelete[] = $id;
                }
            }

            if (empty($canDelete)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua data yang dipilih tidak dapat dihapus karena sudah memiliki relasi.',
                    'details' => $cannotDelete
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(fn() => MataPelajaran::whereIn('id', $canDelete)->delete());

            return response()->json([
                'success' => true,
                'message' => count($canDelete) . ' data berhasil dihapus.' . (count($cannotDelete) > 0 ? ' Beberapa data gagal dihapus karena memiliki relasi.' : ''),
                'failed_items' => $cannotDelete
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus masal.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}