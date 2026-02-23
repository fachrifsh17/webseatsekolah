<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, Jurusan, ProfilSekolah, DataKontak};
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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);
        
        $this->authorizeResource(MataPelajaran::class, 'mapel');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = MataPelajaran::with('jurusan');

            $isActive = $request->get('is_active', 1);
            $query->where('is_active', $isActive);

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

            $perPage = $request->get('per_page', 12);
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

    public function export(Request $request)
    {
        $this->authorize('viewAny', MataPelajaran::class);

        try {
            $filters = $request->only(['search', 'jurusan_id', 'tipe_mapel', 'kategori_mapel']);
            $filters['is_active'] = $request->get('is_active', 1);
            
            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $filenameParts = ['DATA_MATA_PELAJARAN'];

            if ($filters['is_active'] == 0) {
                $filenameParts[] = 'NON_AKTIF';
            }

            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) {
                    $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
                }
            }

            if ($request->filled('tipe_mapel')) {
                $filenameParts[] = strtoupper($request->tipe_mapel);
            }

            $fileName = implode('_', $filenameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new MapelExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
{
    $this->authorize('create', MataPelajaran::class);

    $request->validate(['file' => 'required|mimes:xlsx,xls,csv,txt|max:2048']);

    try {
        $access = [
            'isFullAccess' => true,
            'guruStaf' => null,
            'namaJabatan' => 'Admin'
        ];

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
    public function store(StoreMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menambahkan mata pelajaran.',
                    'errors'  => ['jurusan_id' => ['Jurusan yang dipilih tidak aktif atau tidak ditemukan.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
            ->where('jurusan_id', $validated['jurusan_id'] ?? null)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar di jurusan ini.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create(array_merge($validated, ['is_active' => 1])));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil ditambahkan.',
                'data'    => new MapelResource($item->load('jurusan'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create mata pelajaran', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(MataPelajaran $mapel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new MapelResource($mapel->load('jurusan'))
        ], Response::HTTP_OK);
    }

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui mata pelajaran.',
                    'errors'  => ['jurusan_id' => ['Jurusan yang dipilih tidak aktif atau tidak ditemukan.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!empty($validated['nama_mapel'])) {
            $exists = MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('jurusan_id', $validated['jurusan_id'] ?? $mapel->jurusan_id)
                ->where('id', '!=', $mapel->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama mata pelajaran sudah terdaftar di jurusan ini.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diperbarui.',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update mata pelajaran', ['mapel_id' => $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        try {
            DB::transaction(fn() => $mapel->update(['is_active' => 0]));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil dinonaktifkan.',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to disable mata pelajaran', ['mapel_id' => $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menonaktifkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}