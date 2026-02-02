<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Http\Resources\MapelResource;
use App\Http\Requests\StoreMapelRequest;
use App\Http\Requests\UpdateMapelRequest;
use App\Exports\MapelExport;
use App\Imports\MapelImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy', 'import']);
    }

    private function getUserAccess()
    {
        /** @var User $user */
        $user = User::with(['guruStaf.strukturJabatan.jabatan'])->find(Auth::id());
        $guruStaf = $user?->guruStaf;
        $namaJabatan = $guruStaf?->strukturJabatan?->jabatan?->nama_jabatan;

        $isFullAccess = $user->hasRole('Admin') || in_array($namaJabatan, ['Waka Kurikulum', 'Kurikulum']);

        return [
            'isFullAccess' => $isFullAccess,
            'guruStaf' => $guruStaf,
            'namaJabatan' => $namaJabatan
        ];
    }

    public function export(Request $request)
    {
        try {
            $access = $this->getUserAccess();
            
            // Siapkan array filter dari request
            $filters = [
                'jurusan_id'     => $request->query('jurusan_id'),
                'search'         => $request->query('search'),
                'tipe_mapel'     => $request->query('tipe_mapel'),
                'kategori_mapel' => $request->query('kategori_mapel'),
            ];

            // Proteksi: Jika bukan Admin/Kurikulum, paksa jurusan_id ke jurusan milik user
            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                    $filters['jurusan_id'] = $access['guruStaf']->jurusan_id;
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            // Pastikan Class MapelExport Anda sudah diupdate untuk menerima array $filters
            return Excel::download(new MapelExport($filters), 'data_mata_pelajaran.xlsx');
        } catch (Throwable $e) {
            Log::error('Export Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $access = $this->getUserAccess();
            $query = MataPelajaran::with('jurusan');

            // 1. Filter Berdasarkan Hak Akses (Role/Jabatan)
            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                    $query->where('jurusan_id', $access['guruStaf']->jurusan_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                // Admin dapat memfilter berdasarkan jurusan secara manual
                if ($request->filled('jurusan_id')) {
                    $query->where('jurusan_id', $request->jurusan_id);
                }
            }

            // 2. Filter Berdasarkan Tipe Mapel
            if ($request->filled('tipe_mapel')) {
                $query->where('tipe_mapel', $request->tipe_mapel);
            }

            // 3. Filter Berdasarkan Kategori Mapel
            if ($request->filled('kategori_mapel')) {
                $query->where('kategori_mapel', $request->kategori_mapel);
            }

            // 4. Filter Berdasarkan Pencarian Nama
            if ($request->filled('search')) {
                $query->where('nama_mapel', 'like', "%{$request->search}%");
            }

            $data = $query->latest()->paginate(12);

            return response()->json([
                'success' => true,
                'data'    => MapelResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
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

    // ... (Method show, store, update, destroy, dan import tetap sama dengan logika sebelumnya)
    
    public function show(MataPelajaran $mapel): JsonResponse
    {
        try {
            $access = $this->getUserAccess();

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan') {
                    if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                        return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                    }
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch mata pelajaran detail', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $access = $this->getUserAccess();
        $validated = $request->validated();

        if (!$access['isFullAccess']) {
            if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                $validated['jurusan_id'] = $access['guruStaf']->jurusan_id;
                $validated['kategori_mapel'] = 'produktif';
                $validated['tipe_mapel'] = 'khusus';
            } else {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }
        }

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
            ->where('jurusan_id', $validated['jurusan_id'] ?? null)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar di jurusan ini.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create($validated));

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

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $access = $this->getUserAccess();
        $validated = $request->validated();

        if (!$access['isFullAccess']) {
            if ($access['namaJabatan'] === 'Ketua Jurusan') {
                if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
                $validated['jurusan_id'] = $access['guruStaf']->jurusan_id;
                $validated['kategori_mapel'] = 'produktif';
                $validated['tipe_mapel'] = 'khusus';
            } else {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }
        }

        if (!empty($validated['nama_mapel']) &&
            MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('jurusan_id', $validated['jurusan_id'] ?? $mapel->jurusan_id)
                ->where('id', '<>', $mapel->id)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));
            $mapel->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diperbarui.',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update mata pelajaran', ['mapel_id' => (string) $mapel->id, 'payload' => $validated, 'error' => $e->getMessage()]);
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
            $access = $this->getUserAccess();

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan') {
                    if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                        return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                    }
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            DB::transaction(fn() => $mapel->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Data mata pelajaran berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete mata pelajaran', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls']);

        try {
            $access = $this->getUserAccess();

            if (!$access['isFullAccess'] && $access['namaJabatan'] !== 'Ketua Jurusan') {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }

            Excel::import(new MapelImport($access), $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diimport'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data. Pastikan format kolom sesuai.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}